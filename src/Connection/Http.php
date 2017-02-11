<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2016 Christoph Kappestein <christoph.kappestein@gmail.com>
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

namespace Fusio\Adapter\Http\Connection;

use Fusio\Engine\Connection\PingableInterface;
use Fusio\Engine\ConnectionInterface;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use GuzzleHttp;

/**
 * Http
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class Http implements ConnectionInterface, PingableInterface
{
    public function getName()
    {
        return 'HTTP';
    }

    /**
     * @param \Fusio\Engine\ParametersInterface $config
     * @return \GuzzleHttp\Client
     */
    public function getConnection(ParametersInterface $config)
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

        $options['http_errors'] = false;

        return new GuzzleHttp\Client($options);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory)
    {
        $builder->add($elementFactory->newInput('url', 'Url', 'text', 'HTTP base url'));
        $builder->add($elementFactory->newInput('username', 'Username', 'text', 'Optional username for authentication'));
        $builder->add($elementFactory->newInput('password', 'Password', 'text', 'Optional password for authentication'));
        $builder->add($elementFactory->newInput('proxy', 'Proxy', 'text', 'Optional HTTP proxy'));
    }

    public function ping($connection)
    {
        if ($connection instanceof GuzzleHttp\Client) {
            $response = $connection->head('/', [
                'http_errors' => false
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } else {
            return false;
        }
    }
}
