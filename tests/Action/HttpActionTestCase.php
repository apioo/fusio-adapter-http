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

namespace Fusio\Adapter\Http\Tests\Action;

use Composer\InstalledVersions;
use Fusio\Adapter\Http\Action\HttpSenderAbstract;
use Fusio\Adapter\Http\Tests\HttpTestCase;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Record\Record;

/**
 * HttpEngineTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
abstract class HttpActionTestCase extends HttpTestCase
{
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

        $action = $this->getActionFactory()->factory($this->getActionClass());
        if ($action instanceof HttpSenderAbstract) {
            $action->setClient($client);
        }

        // handle request
        $url = 'http://127.0.0.1';

        $response = $this->handle(
            $action,
            $this->getRequest(
                'GET',
                ['foo' => 'bar'],
                ['foo' => 'bar'],
                ['Content-Type' => 'application/json'],
                Record::fromArray(['foo' => 'bar'])
            ),
            $this->getParameters($this->getConfiguration($url)),
            $this->getContext()
        );

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = $this->getExpectedJson($url);

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['x-foo' => ['Foo']], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);

        $this->assertEquals(1, count($transactions));
        $transaction = reset($transactions);

        $headers = [
            'x-fusio-operation-id' => ['34'],
            'x-fusio-user-anonymous' => ['0'],
            'x-fusio-user-id' => ['2'],
            'x-fusio-user-name' => ['Consumer'],
            'x-fusio-app-id' => ['3'],
            'x-fusio-app-key' => ['5347307d-d801-4075-9aaa-a21a29a448c5'],
            'x-fusio-remote-ip' => ['127.0.0.1'],
            'x-forwarded-for' => ['127.0.0.1'],
            'accept' => ['application/json, application/x-www-form-urlencoded;q=0.9, */*;q=0.8'],
            'user-agent' => ['Fusio Adapter-HTTP v' . InstalledVersions::getVersion('fusio/adapter-http')],
        ];

        $this->assertEquals('GET', $transaction['request']->getMethod());
        $this->assertEquals('http://127.0.0.1?foo=bar', $transaction['request']->getUri()->__toString());
        $this->assertEquals($headers, $this->getXHeaders($transaction['request']->getHeaders()));
        $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', $transaction['request']->getBody()->__toString());
    }

    public function testHandleForm()
    {
        $transactions = [];
        $history = Middleware::history($transactions);

        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Foo', 'Content-Type' => 'application/x-www-form-urlencoded'], http_build_query(['foo' => 'bar', 'bar' => 'foo'], '', '&')),
        ]);

        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $client = new Client(['handler' => $handler]);

        $action = $this->getActionFactory()->factory($this->getActionClass());
        if ($action instanceof HttpSenderAbstract) {
            $action->setClient($client);
        }

        // handle request
        $url = 'http://127.0.0.1';

        $response = $this->handle(
            $action,
            $this->getRequest(
                'GET',
                ['foo' => 'bar'],
                ['foo' => 'bar'],
                ['Content-Type' => 'application/json'],
                Record::fromArray(['foo' => 'bar', 'x' => 'bar'])
            ),
            $this->getParameters($this->getConfiguration($url, HttpSenderAbstract::TYPE_FORM)),
            $this->getContext()
        );

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = $this->getExpectedJson($url);

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['x-foo' => ['Foo']], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);

        $this->assertEquals(1, count($transactions));
        $transaction = reset($transactions);

        $headers = [
            'x-fusio-operation-id' => ['34'],
            'x-fusio-user-anonymous' => ['0'],
            'x-fusio-user-id' => ['2'],
            'x-fusio-user-name' => ['Consumer'],
            'x-fusio-app-id' => ['3'],
            'x-fusio-app-key' => ['5347307d-d801-4075-9aaa-a21a29a448c5'],
            'x-fusio-remote-ip' => ['127.0.0.1'],
            'x-forwarded-for' => ['127.0.0.1'],
            'accept' => ['application/json, application/x-www-form-urlencoded;q=0.9, */*;q=0.8'],
            'user-agent' => ['Fusio Adapter-HTTP v' . InstalledVersions::getVersion('fusio/adapter-http')],
        ];

        $this->assertEquals('GET', $transaction['request']->getMethod());
        $this->assertEquals('http://127.0.0.1?foo=bar', $transaction['request']->getUri()->__toString());
        $this->assertEquals($headers, $this->getXHeaders($transaction['request']->getHeaders()));
        $this->assertEquals('foo=bar&x=bar', $transaction['request']->getBody()->__toString());
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

        $action = $this->getActionFactory()->factory($this->getActionClass());
        if ($action instanceof HttpSenderAbstract) {
            $action->setClient($client);
        }

        // handle request
        $url = 'http://127.0.0.1';

        $response = $this->handle(
            $action,
            $this->getRequest(
                'GET',
                ['foo' => 'bar'],
                ['foo' => 'bar'],
                ['Content-Type' => 'application/json'],
                Record::fromArray(['foo' => 'bar'])
            ),
            $this->getParameters($this->getConfiguration($url)),
            $this->getContext()
        );

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['x-foo' => ['Foo'], 'content-type' => ['application/xml']], $response->getHeaders());
        $this->assertEquals($this->getExpectedXml($url), $response->getBody());

        $this->assertEquals(1, count($transactions));
        $transaction = reset($transactions);

        $headers = [
            'x-fusio-operation-id' => ['34'],
            'x-fusio-user-anonymous' => ['0'],
            'x-fusio-user-id' => ['2'],
            'x-fusio-user-name' => ['Consumer'],
            'x-fusio-app-id' => ['3'],
            'x-fusio-app-key' => ['5347307d-d801-4075-9aaa-a21a29a448c5'],
            'x-fusio-remote-ip' => ['127.0.0.1'],
            'x-forwarded-for' => ['127.0.0.1'],
            'accept' => ['application/json, application/x-www-form-urlencoded;q=0.9, */*;q=0.8'],
            'user-agent' => ['Fusio Adapter-HTTP v' . InstalledVersions::getVersion('fusio/adapter-http')],
        ];

        $this->assertEquals('GET', $transaction['request']->getMethod());
        $this->assertEquals('http://127.0.0.1?foo=bar', $transaction['request']->getUri()->__toString());
        $this->assertEquals($headers, $this->getXHeaders($transaction['request']->getHeaders()));
        $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', $transaction['request']->getBody()->__toString());
    }

    public function testHandleVariablePathFragment()
    {
        $transactions = [];
        $history = Middleware::history($transactions);

        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Foo', 'Content-Type' => 'application/json'], json_encode(['foo' => 'bar', 'bar' => 'foo'])),
        ]);

        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $client = new Client(['handler' => $handler]);

        $action = $this->getActionFactory()->factory($this->getActionClass());
        if ($action instanceof HttpSenderAbstract) {
            $action->setClient($client);
        }

        // handle request
        $url = 'http://127.0.0.1/foo/:foo';

        $response = $this->handle(
            $action,
            $this->getRequest(
                'GET',
                ['foo' => 'bar'],
                ['foo' => 'bar'],
                ['Content-Type' => 'application/json'],
                Record::fromArray(['foo' => 'bar'])
            ),
            $this->getParameters($this->getConfiguration($url)),
            $this->getContext()
        );

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = $this->getExpectedJson($url);

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['x-foo' => ['Foo']], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);

        $this->assertEquals(1, count($transactions));
        $transaction = reset($transactions);

        $headers = [
            'x-fusio-operation-id' => ['34'],
            'x-fusio-user-anonymous' => ['0'],
            'x-fusio-user-id' => ['2'],
            'x-fusio-user-name' => ['Consumer'],
            'x-fusio-app-id' => ['3'],
            'x-fusio-app-key' => ['5347307d-d801-4075-9aaa-a21a29a448c5'],
            'x-fusio-remote-ip' => ['127.0.0.1'],
            'x-forwarded-for' => ['127.0.0.1'],
            'accept' => ['application/json, application/x-www-form-urlencoded;q=0.9, */*;q=0.8'],
            'user-agent' => ['Fusio Adapter-HTTP v' . InstalledVersions::getVersion('fusio/adapter-http')],
        ];

        $this->assertEquals('GET', $transaction['request']->getMethod());
        $this->assertEquals('http://127.0.0.1/foo/bar?foo=bar', $transaction['request']->getUri()->__toString());
        $this->assertEquals($headers, $this->getXHeaders($transaction['request']->getHeaders()));
        $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', $transaction['request']->getBody()->__toString());
    }

    public function testHandleAuthorization()
    {
        $transactions = [];
        $history = Middleware::history($transactions);

        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Foo', 'Content-Type' => 'application/json'], json_encode(['foo' => 'bar', 'bar' => 'foo'])),
        ]);

        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $client = new Client(['handler' => $handler]);

        $action = $this->getActionFactory()->factory($this->getActionClass());
        if ($action instanceof HttpSenderAbstract) {
            $action->setClient($client);
        }

        // handle request
        $url = 'http://127.0.0.1';

        $response = $this->handle(
            $action,
            $this->getRequest(
                'GET',
                ['foo' => 'bar'],
                ['foo' => 'bar'],
                ['Content-Type' => 'application/json'],
                Record::fromArray(['foo' => 'bar'])
            ),
            $this->getParameters($this->getConfiguration($url, authorization: 'Bearer my_token')),
            $this->getContext()
        );

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = $this->getExpectedJson($url);

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['x-foo' => ['Foo']], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);

        $this->assertEquals(1, count($transactions));
        $transaction = reset($transactions);

        $headers = [
            'x-fusio-operation-id' => ['34'],
            'x-fusio-user-anonymous' => ['0'],
            'x-fusio-user-id' => ['2'],
            'x-fusio-user-name' => ['Consumer'],
            'x-fusio-app-id' => ['3'],
            'x-fusio-app-key' => ['5347307d-d801-4075-9aaa-a21a29a448c5'],
            'x-fusio-remote-ip' => ['127.0.0.1'],
            'x-forwarded-for' => ['127.0.0.1'],
            'accept' => ['application/json, application/x-www-form-urlencoded;q=0.9, */*;q=0.8'],
            'user-agent' => ['Fusio Adapter-HTTP v' . InstalledVersions::getVersion('fusio/adapter-http')],
            'authorization' => ['Bearer my_token']
        ];

        $this->assertEquals('GET', $transaction['request']->getMethod());
        $this->assertEquals('http://127.0.0.1?foo=bar', $transaction['request']->getUri()->__toString());
        $this->assertEquals($headers, $this->getXHeaders($transaction['request']->getHeaders()));
        $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', $transaction['request']->getBody()->__toString());
    }

    public function testHandleCache()
    {
        $transactions = [];
        $history = Middleware::history($transactions);

        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Foo', 'Content-Type' => 'application/json'], json_encode(['foo' => 'bar', 'bar' => 'foo'])),
        ]);

        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $client = new Client(['handler' => $handler]);

        $action = $this->getActionFactory()->factory($this->getActionClass());
        if ($action instanceof HttpSenderAbstract) {
            $action->setClient($client);
        }

        // handle request
        $url = 'http://127.0.0.1';

        $response = $this->handle(
            $action,
            $this->getRequest(
                'GET',
                ['foo' => 'bar'],
                ['foo' => 'bar'],
                ['Content-Type' => 'application/json'],
                Record::fromArray(['foo' => 'bar'])
            ),
            $this->getParameters($this->getConfiguration($url, cache: true)),
            $this->getContext()
        );

        $actual = json_encode($response->getBody(), JSON_PRETTY_PRINT);
        $expect = $this->getExpectedJson($url);

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['x-foo' => ['Foo']], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);

        $this->assertEquals(1, count($transactions));
        $transaction = reset($transactions);

        $headers = [
            'x-fusio-operation-id' => ['34'],
            'x-fusio-user-anonymous' => ['0'],
            'x-fusio-user-id' => ['2'],
            'x-fusio-user-name' => ['Consumer'],
            'x-fusio-app-id' => ['3'],
            'x-fusio-app-key' => ['5347307d-d801-4075-9aaa-a21a29a448c5'],
            'x-fusio-remote-ip' => ['127.0.0.1'],
            'x-forwarded-for' => ['127.0.0.1'],
            'accept' => ['application/json, application/x-www-form-urlencoded;q=0.9, */*;q=0.8'],
            'user-agent' => ['Fusio Adapter-HTTP v' . InstalledVersions::getVersion('fusio/adapter-http')],
        ];

        $this->assertEquals('GET', $transaction['request']->getMethod());
        $this->assertEquals('http://127.0.0.1?foo=bar', $transaction['request']->getUri()->__toString());
        $this->assertEquals($headers, $this->getXHeaders($transaction['request']->getHeaders()));
        $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', $transaction['request']->getBody()->__toString());
    }

    abstract protected function getActionClass();

    protected function handle(HttpSenderAbstract $action, RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        return $action->handle($request, $configuration, $context);
    }

    protected function getConfiguration(string $url, ?string $type = null, ?string $authorization = null, ?bool $cache = false): array
    {
        return [
            'url' => $url,
            'type' => $type,
            'authorization' => $authorization,
            'cache' => $cache ? 1 : 0,
        ];
    }

    protected function getExpectedJson(string $url): string
    {
        return \json_encode(['foo' => 'bar', 'bar' => 'foo']);
    }

    protected function getExpectedXml(string $url): string|array
    {
        return '<foo>response</foo>';
    }

    private function getXHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $header) {
            if (!in_array($name, ['Content-Length', 'User-Agent', 'Content-Type', 'Host'])) {
                $result[$name] = $header;
            }
        }

        return $result;
    }
}
