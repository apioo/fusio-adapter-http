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

namespace Fusio\Adapter\Http\Tests\Action;

use Fusio\Adapter\Http\Action\HttpComposition;
use Fusio\Adapter\Http\Action\HttpSenderAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Form\Builder;
use Fusio\Engine\Form\Container;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use Fusio\Engine\Test\EngineTestCaseTrait;

/**
 * HttpCompositeTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class HttpCompositeTest extends HttpActionTestCase
{
    protected function getActionClass(): string
    {
        return HttpComposition::class;
    }

    protected function getConfiguration(string $url, ?string $type = null, ?string $authorization = null, ?bool $cache = false): array
    {
        return [
            'url' => [$url],
            'type' => $type,
            'authorization' => $authorization,
            'cache' => $cache ? 1 : 0,
        ];
    }

    protected function getExpectedJson(string $url): string
    {
        return \json_encode([
            $url => [
                'foo' => 'bar',
                'bar' => 'foo'
            ]
        ]);
    }

    protected function getExpectedXml(string $url): string|array
    {
        return [
            $url => '<foo>response</foo>'
        ];
    }
}
