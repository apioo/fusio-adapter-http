<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2018 Christoph Kappestein <christoph.kappestein@gmail.com>
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

use Fusio\Engine\ActionAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use GuzzleHttp\Client;
use PSX\Http\MediaType;
use PSX\Record\Transformer;

/**
 * HttpEngine
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class HttpEngine extends ActionAbstract
{
    const TYPE_JSON = 'application/json';
    const TYPE_FORM = 'application/x-www-form-urlencoded';

    const HTTP_1_0 = '1.0';
    const HTTP_1_1 = '1.1';
    const HTTP_2_0 = '2.0';

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    public function __construct($url = null, Client $client = null)
    {
        $this->url    = $url;
        $this->client = $client ?: new Client();
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function setVersion($version)
    {
        $this->version = $version;
    }

    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

        $exclude = ['accept', 'accept-charset', 'accept-encoding', 'accept-language', 'authorization', 'connection', 'content-type', 'host', 'user-agent'];
        $headers = $request->getHeaders();
        $headers = array_diff_key($headers, array_combine($exclude, array_fill(0, count($exclude), null)));

        $headers['x-fusio-route-id'] = '' . $context->getRouteId();
        $headers['x-fusio-user-anonymous'] = $context->getUser()->isAnonymous() ? '1' : '0';
        $headers['x-fusio-user-id'] = '' . $context->getUser()->getId();
        $headers['x-fusio-app-id'] = '' . $context->getApp()->getId();
        $headers['x-fusio-app-key'] = $context->getApp()->getAppKey();
        $headers['x-fusio-remote-ip'] = $clientIp;
        $headers['x-forwarded-for'] = $clientIp;
        $headers['accept'] = 'application/json, application/x-www-form-urlencoded;q=0.9, */*;q=0.8';

        $host = $request->getHeader('Host');
        if (!empty($host)) {
            $headers['x-forwarded-host'] = $host;
        }

        $auth = $request->getHeader('Proxy-Authorization');
        if (!empty($auth)) {
            $headers['authorization'] = $auth;
        }

        $options = [
            'headers' => $headers,
            'query' => $request->getParameters(),
            'http_errors' => false,
        ];

        if(!empty($this->version)) {
            $options['version'] = $this->version;
        }

        if ($this->type == self::TYPE_FORM) {
            $options['form_params'] = Transformer::toArray($request->getBody());
        } else {
            $options['json'] = $request->getBody();
        }

        $url = $this->url;
        $uriFragments = $request->getUriFragments();
        if (!empty($uriFragments)) {
            foreach ($uriFragments as $name => $value) {
                $url = str_replace(':' . $name, $value, $url);
            }
        }

        $response    = $this->client->request($request->getMethod(), $url, $options);
        $contentType = $response->getHeaderLine('Content-Type');
        $response    = $response->withoutHeader('Content-Type');
        $body        = (string) $response->getBody();

        if ($this->isJson($contentType)) {
            $data = json_decode($body);
        } elseif (strpos($contentType, self::TYPE_FORM) !== false) {
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

    private function isJson($contentType)
    {
        if (!empty($contentType)) {
            try {
                return MediaType\Json::isMediaType(new MediaType($contentType));
            } catch (\InvalidArgumentException $e) {
            }
        }

        return false;
    }
}
