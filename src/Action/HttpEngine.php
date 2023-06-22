<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2023 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Fusio\Adapter\Http\Action;

use Fusio\Engine\Action\RuntimeInterface;
use Fusio\Engine\ActionInterface;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\Request\HttpRequestContext;
use Fusio\Engine\RequestInterface;
use Fusio\Engine\Response\FactoryInterface;
use GuzzleHttp\Client;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\MediaType;
use PSX\Record\Transformer;

/**
 * HttpEngine
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
class HttpEngine implements ActionInterface
{
    public const TYPE_JSON = 'application/json';
    public const TYPE_FORM = 'application/x-www-form-urlencoded';

    public const HTTP_1_0 = '1.0';
    public const HTTP_1_1 = '1.1';
    public const HTTP_2_0 = '2.0';

    protected const CONTENT_TYPE = [
        self::TYPE_JSON => self::TYPE_JSON,
        self::TYPE_FORM => self::TYPE_FORM,
    ];

    protected const VERSION = [
        self::HTTP_1_0 => self::HTTP_1_0,
        self::HTTP_1_1 => self::HTTP_1_1,
        self::HTTP_2_0 => self::HTTP_2_0,
    ];

    private ?string $url = null;
    private ?string $type = null;
    private ?string $version = null;
    private ?string $authorization = null;
    private ?Client $client = null;

    protected FactoryInterface $response;

    public function __construct(RuntimeInterface $runtime)
    {
        $this->response = $runtime->getResponse();
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }

    public function setAuthorization(?string $authorization): void
    {
        $this->authorization = $authorization;
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $requestContext = $request->getContext();
        if ($requestContext instanceof HttpRequestContext) {
            $httpRequest = $requestContext->getRequest();
            $exclude = ['accept', 'accept-charset', 'accept-encoding', 'accept-language', 'authorization', 'connection', 'content-type', 'host', 'user-agent'];
            $headers = $httpRequest->getHeaders();
            $headers = array_diff_key($headers, array_combine($exclude, array_fill(0, count($exclude), null)));

            $method = $httpRequest->getMethod();
            $uriFragments = $requestContext->getParameters();
            $query = $httpRequest->getUri()->getParameters();
            $host = $httpRequest->getHeader('Host');
            $auth = $httpRequest->getHeader('Proxy-Authorization');
        } else {
            $method = 'POST';
            $uriFragments = [];
            $query = [];
            $headers = [];
            $host = null;
            $auth = null;
        }

        $headers['x-fusio-route-id'] = '' . $context->getRouteId();
        $headers['x-fusio-user-anonymous'] = $context->getUser()->isAnonymous() ? '1' : '0';
        $headers['x-fusio-user-id'] = '' . $context->getUser()->getId();
        $headers['x-fusio-user-name'] = $context->getUser()->getName();
        $headers['x-fusio-app-id'] = '' . $context->getApp()->getId();
        $headers['x-fusio-app-key'] = $context->getApp()->getAppKey();
        $headers['x-fusio-remote-ip'] = $clientIp;
        $headers['x-forwarded-for'] = $clientIp;
        $headers['accept'] = 'application/json, application/x-www-form-urlencoded;q=0.9, */*;q=0.8';

        if (!empty($host)) {
            $headers['x-forwarded-host'] = $host;
        }

        if (!empty($this->authorization)) {
            $headers['authorization'] = $this->authorization;
        } elseif (!empty($auth)) {
            $headers['authorization'] = $auth;
        }

        $options = [
            'headers' => $headers,
            'query' => $query,
            'http_errors' => false,
        ];

        if (!empty($this->version)) {
            $options['version'] = $this->version;
        }

        if ($this->type == self::TYPE_FORM) {
            $options['form_params'] = Transformer::toArray($request->getPayload());
        } else {
            $options['json'] = $request->getPayload();
        }

        $url = $this->url ?? throw new ConfigurationException('Provided no url');
        if (!empty($uriFragments)) {
            foreach ($uriFragments as $name => $value) {
                $url = str_replace(':' . $name, $value, $url);
            }
        }

        $client      = $this->client ?? new Client();
        $response    = $client->request($method, $url, $options);
        $contentType = $response->getHeaderLine('Content-Type');
        $response    = $response->withoutHeader('Content-Type');
        $response    = $response->withoutHeader('Content-Length');
        $body        = (string) $response->getBody();

        if ($this->isJson($contentType)) {
            $data = json_decode($body);
        } elseif (str_contains($contentType, self::TYPE_FORM)) {
            $data = [];
            parse_str($body, $data);
        } else {
            if (!empty($contentType)) {
                $response = $response->withHeader('Content-Type', $contentType);
            }

            $data = $body;
        }

        return $this->response->build(
            $response->getStatusCode(),
            $response->getHeaders(),
            $data
        );
    }

    private function isJson(?string $contentType): bool
    {
        if (!empty($contentType)) {
            try {
                return MediaType\Json::isMediaType(MediaType::parse($contentType));
            } catch (\InvalidArgumentException $e) {
            }
        }

        return false;
    }
}
