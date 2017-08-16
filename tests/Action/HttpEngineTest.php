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

namespace Fusio\Adapter\Http\Tests\Action;

use Fusio\Adapter\Http\Action\HttpEngine;
use Fusio\Engine\Form\Builder;
use Fusio\Engine\Form\Container;
use Fusio\Engine\ResponseInterface;
use Fusio\Engine\Test\EngineTestCaseTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PSX\Record\Record;

/**
 * HttpEngineTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class HttpEngineTest extends \PHPUnit_Framework_TestCase
{
    use EngineTestCaseTrait;

    protected function setUp()
    {
        parent::setUp();
    }

    public function testHandle()
    {
        $transactions = [];
        $history = Middleware::history($transactions);
        
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Foo', 'Content-Type' => 'application/json'], json_encode(['foo' => 'bar', 'bar' => 'foo'])),
        ]);

        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $client = new Client(['handler' => $handler]);

        $action = $this->getActionFactory()->factory(HttpEngine::class);
        $action->setUrl('http://127.0.0.1');
        $action->setClient($client);

        // handle request
        $response = $action->handle(
            $this->getRequest(
                'GET',
                ['foo' => 'bar'],
                ['foo' => 'bar'],
                ['Content-Type' => 'application/json'],
                Record::fromArray(['foo' => 'bar'])
            ),
            $this->getParameters(),
            $this->getContext()
        );

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = <<<JSON
{"foo":"bar","bar":"foo"}
JSON;

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['x-foo' => ['Foo'], 'content-type' => ['application/json']], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);

        $this->assertEquals(1, count($transactions));
        $transaction = reset($transactions);

        $headers = [
            'X-Fusio-Route-Id' => ['34'],
            'X-Fusio-User-Anonymous' => ['0'],
            'X-Fusio-User-Id' => ['2'],
            'X-Fusio-App-Id' => ['3'],
            'X-Fusio-App-Key' => ['5347307d-d801-4075-9aaa-a21a29a448c5'],
            'X-Fusio-Remote-Ip' => ['127.0.0.1'],
        ];

        $this->assertEquals('GET', $transaction['request']->getMethod());
        $this->assertEquals('http://127.0.0.1?foo=bar', $transaction['request']->getUri()->__toString());
        $this->assertEquals($headers, $this->getXHeaders($transaction['request']->getHeaders()));
        $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', $transaction['request']->getBody()->__toString());
    }

    public function testHandleXml()
    {
        $transactions = [];
        $history = Middleware::history($transactions);

        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Foo', 'Content-Type' => 'application/xml'], '<foo>response</foo>'),
        ]);

        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $client = new Client(['handler' => $handler]);

        $action = $this->getActionFactory()->factory(HttpEngine::class);
        $action->setUrl('http://127.0.0.1');
        $action->setClient($client);

        // handle request
        $response = $action->handle(
            $this->getRequest(
                'GET',
                ['foo' => 'bar'],
                ['foo' => 'bar'],
                ['Content-Type' => 'application/json'],
                Record::fromArray(['foo' => 'bar'])
            ),
            $this->getParameters(),
            $this->getContext()
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['x-foo' => ['Foo'], 'content-type' => ['application/xml']], $response->getHeaders());
        $this->assertEquals('<foo>response</foo>', $response->getBody());

        $this->assertEquals(1, count($transactions));
        $transaction = reset($transactions);

        $headers = [
            'X-Fusio-Route-Id' => ['34'],
            'X-Fusio-User-Anonymous' => ['0'],
            'X-Fusio-User-Id' => ['2'],
            'X-Fusio-App-Id' => ['3'],
            'X-Fusio-App-Key' => ['5347307d-d801-4075-9aaa-a21a29a448c5'],
            'X-Fusio-Remote-Ip' => ['127.0.0.1'],
        ];

        $this->assertEquals('GET', $transaction['request']->getMethod());
        $this->assertEquals('http://127.0.0.1?foo=bar', $transaction['request']->getUri()->__toString());
        $this->assertEquals($headers, $this->getXHeaders($transaction['request']->getHeaders()));
        $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', $transaction['request']->getBody()->__toString());
    }

    public function testGetForm()
    {
        $action  = $this->getActionFactory()->factory(HttpEngine::class);
        $builder = new Builder();
        $factory = $this->getFormElementFactory();

        $action->configure($builder, $factory);

        $this->assertInstanceOf(Container::class, $builder->getForm());
    }

    private function getXHeaders(array $headers)
    {
        $result = [];
        foreach ($headers as $name => $header) {
            if (substr($name, 0, 2) == 'X-') {
                $result[$name] = $header;
            }
        }

        return $result;
    }
}
