<?php
/*
 * Fusio - Self-Hosted API Management for Builders.
 * For the current version and information visit <https://www.fusio-project.org/>
 *
 * Copyright (c) Christoph Kappestein <christoph.kappestein@gmail.com>
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

namespace Fusio\Adapter\Http\Connection;

use Composer\InstalledVersions;
use Fusio\Engine\ConnectionAbstract;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use GuzzleHttp;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr16CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Psr\SimpleCache\CacheInterface;

/**
 * Http
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class Http extends ConnectionAbstract
{
    private const HTTP_1_0 = '1.0';
    private const HTTP_1_1 = '1.1';
    private const HTTP_2_0 = '2.0';
    private const HTTP_3_0 = '3.0';

    private const VERSION = [
        self::HTTP_1_0 => self::HTTP_1_0,
        self::HTTP_1_1 => self::HTTP_1_1,
        self::HTTP_2_0 => self::HTTP_2_0,
        self::HTTP_3_0 => self::HTTP_3_0,
    ];

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getName(): string
    {
        return 'HTTP';
    }

    public function getConnection(ParametersInterface $config): GuzzleHttp\Client
    {
        $options = [];

        $baseUri = $config->get('url');
        if (!empty($baseUri)) {
            $options['base_uri'] = $config->get('url');
        }

        $username = $config->get('username');
        $password = $config->get('password');
        if (!empty($username) && !empty($password)) {
            $options[GuzzleHttp\RequestOptions::AUTH] = [$username, $password];
        }

        $proxy = $config->get('proxy');
        if (!empty($proxy)) {
            $options[GuzzleHttp\RequestOptions::PROXY] = $proxy;
        }

        $verify = $config->get('verify');
        if ($verify === false) {
            $options[GuzzleHttp\RequestOptions::VERIFY] = false;
        }

        $version = $config->get('version');
        if (!empty($version)) {
            $options[GuzzleHttp\RequestOptions::VERSION] = $version;
        }

        $timeout = $config->get('timeout');
        if (!empty($timeout)) {
            $options[GuzzleHttp\RequestOptions::TIMEOUT] = (float) $timeout;
        }

        $headers = [];

        $additionalHeaders = $this->getHeaders($config);
        if (!empty($additionalHeaders)) {
            $headers = array_merge($headers, array_change_key_case($additionalHeaders));
        }

        $headers['user-agent'] = 'Fusio Adapter-HTTP v' . InstalledVersions::getVersion('fusio/adapter-http');

        $authorization = $config->get('authorization');
        if (!empty($authorization)) {
            $headers['authorization'] = $authorization;
        }

        $options[GuzzleHttp\RequestOptions::HEADERS] = $headers;
        $options[GuzzleHttp\RequestOptions::HTTP_ERRORS] = false;

        if ($config->get('cache')) {
            $stack = HandlerStack::create();
            $stack->push(new CacheMiddleware(new PrivateCacheStrategy(new Psr16CacheStorage($this->cache))), 'cache');
            $options['handler'] = $stack;
        }

        return new GuzzleHttp\Client($options);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newInput('url', 'URL', 'url', 'HTTP base url'));
        $builder->add($elementFactory->newInput('username', 'Username', 'text', 'Optional username for authentication'));
        $builder->add($elementFactory->newInput('password', 'Password', 'text', 'Optional password for authentication'));
        $builder->add($elementFactory->newInput('authorization', 'Authorization', 'text', 'Optional an HTTP authorization header which gets passed to the endpoint i.e. <code>Bearer my_token</code>.'));
        $builder->add($elementFactory->newInput('proxy', 'Proxy', 'text', 'Optional HTTP proxy i.e. <code>http://localhost:8125</code>'));
        $builder->add($elementFactory->newCheckbox('cache', 'Cache', 'Optional whether the connection should handle caching headers'));
        $builder->add($elementFactory->newCheckbox('verify', 'Verify', 'Optional whether to disable SSL verification'));
        $builder->add($elementFactory->newSelect('version', 'HTTP Version', self::VERSION, 'Optional HTTP protocol version'));
        $builder->add($elementFactory->newInput('timeout', 'Timeout', 'number', 'Optional a timeout in seconds i.e. <code>3.14</code>'));
        $builder->add($elementFactory->newMap('headers', 'Headers', 'text', 'Optional fix headers which are always provided'));
    }

    /**
     * @return array<string, string>|null
     */
    private function getHeaders(ParametersInterface $configuration): ?array
    {
        $headers = $configuration->get('headers');
        if (empty($headers)) {
            return null;
        }

        if (is_array($headers)) {
            return $headers;
        } elseif ($headers instanceof \stdClass) {
            return (array) $headers;
        }

        return null;
    }
}
