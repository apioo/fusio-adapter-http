<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2017 Christoph Kappestein <christoph.kappestein@gmail.com>
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

/**
 * HttpEngine
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class HttpEngine extends ActionAbstract
{
    /**
     * @var string
     */
    protected $url;

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

    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $headers = $request->getHeaders();
        $headers['X-Fusio-Route-Id'] = '' . $context->getRouteId();
        $headers['X-Fusio-User-Anonymous'] = $context->getUser()->isAnonymous() ? '1' : '0';
        $headers['X-Fusio-User-Id'] = '' . $context->getUser()->getId();
        $headers['X-Fusio-App-Id'] = '' . $context->getApp()->getId();
        $headers['X-Fusio-App-Key'] = $context->getApp()->getAppKey();
        $headers['X-Fusio-Remote-Ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

        $response = $this->client->request($request->getMethod(), $this->url, [
            'headers' => $headers,
            'query' => $request->getParameters()->toArray(),
            'json' => $request->getBody(),
            'http_errors' => false,
        ]);

        if ($this->isJson($response->getHeaderLine('Content-Type'))) {
            $body = json_decode($response->getBody());
        } else {
            $body = $response->getBody()->__toString();
        }

        return $this->response->build(
            $response->getStatusCode(),
            $response->getHeaders(),
            $body
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
