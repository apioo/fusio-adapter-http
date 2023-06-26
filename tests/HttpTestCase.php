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

namespace Fusio\Adapter\Http\Tests;

use Fusio\Adapter\Http\Action\HttpComposition;
use Fusio\Adapter\Http\Action\HttpSenderAbstract;
use Fusio\Adapter\Http\Action\HttpLoadBalancer;
use Fusio\Adapter\Http\Action\HttpProcessor;
use Fusio\Adapter\Http\Connection\Http;
use Fusio\Engine\Action\Runtime;
use Fusio\Engine\Test\EngineTestCaseTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * HttpTestCase
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    https://www.fusio-project.org/
 */
abstract class HttpTestCase extends TestCase
{
    use EngineTestCaseTrait;

    protected function configure(Runtime $runtime, Container $container): void
    {
        $container->set(Http::class, new Http());
        $container->set(HttpComposition::class, new HttpComposition($runtime));
        $container->set(HttpLoadBalancer::class, new HttpLoadBalancer($runtime));
        $container->set(HttpProcessor::class, new HttpProcessor($runtime));
    }
}
