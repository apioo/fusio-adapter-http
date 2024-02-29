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

namespace Fusio\Adapter\Http\Connection;

use Composer\InstalledVersions;
use Fusio\Engine\ConnectionAbstract;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use GuzzleHttp;

/**
 * Http
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class Http extends ConnectionAbstract
{
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
            $options['auth'] = [$username, $password];
        }

        $proxy = $config->get('proxy');
        if (!empty($proxy)) {
            $options['proxy'] = $proxy;
        }

        $headers = [];
        $headers['user-agent'] = 'Fusio Adapter-HTTP v' . InstalledVersions::getVersion('fusio/adapter-http');

        $authorization = $config->get('authorization');
        if (!empty($authorization)) {
            $headers['authorization'] = $authorization;
        }

        $options['headers'] = $headers;
        $options['http_errors'] = false;

        return new GuzzleHttp\Client($options);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newInput('url', 'Url', 'text', 'HTTP base url'));
        $builder->add($elementFactory->newInput('username', 'Username', 'text', 'Optional username for authentication'));
        $builder->add($elementFactory->newInput('password', 'Password', 'text', 'Optional password for authentication'));
        $builder->add($elementFactory->newInput('authorization', 'Authorization', 'text', 'Optional an HTTP authorization header which gets passed to the endpoint i.e. <code>Bearer my_token</code>.'));
        $builder->add($elementFactory->newInput('proxy', 'Proxy', 'text', 'Optional HTTP proxy i.e. <code>http://localhost:8125</code>'));
    }
}
