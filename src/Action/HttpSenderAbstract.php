<?php
/*
 * Fusio is an open source API management platform which helps to create innovative API solutions.
 * For the current version and information visit <https://www.fusio-project.org/>
 *
 * Copyright 2015-2023 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Fusio\Adapter\Http\Action;

use Fusio\Engine\ActionAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Request\HttpRequestContext;
use Fusio\Engine\RequestInterface;
use GuzzleHttp\Client;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\MediaType;
use PSX\Record\Transformer;

/**
 * HttpSenderAbstract
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
abstract class HttpSenderAbstract extends ActionAbstract
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

    private ?Client $client = null;

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    public function send(string $url, ?string $type, ?string $version, ?string $authorization, RequestInterface $request, ContextInterface $context): HttpResponseInterface
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
            $proxyAuthorization = $httpRequest->getHeader('Proxy-Authorization');
        } else {
            $method = 'POST';
            $uriFragments = [];
            $query = [];
            $headers = [];
            $host = null;
            $proxyAuthorization = null;
        }

        $headers['x-fusio-operation-id'] = '' . $context->getOperationId();
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

        if (!empty($authorization)) {
            $headers['authorization'] = $authorization;
        } elseif (!empty($proxyAuthorization)) {
            $headers['authorization'] = $proxyAuthorization;
        }

        $options = [
            'headers' => $headers,
            'query' => $query,
            'http_errors' => false,
        ];

        if (!empty($version)) {
            $options['version'] = $version;
        }

        if ($type == self::TYPE_FORM) {
            $options['form_params'] = Transformer::toArray($request->getPayload());
        } else {
            $options['json'] = $request->getPayload();
        }

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
