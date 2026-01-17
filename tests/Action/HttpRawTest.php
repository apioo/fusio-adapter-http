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

use Composer\InstalledVersions;
use Fusio\Adapter\Http\Action\HttpRaw;
use Fusio\Adapter\Http\Tests\HttpTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\Stream\StringStream;
use PSX\Http\Response as HttpResponse;
use PSX\Json\Parser;
use PSX\Record\Record;
use PSX\Http\Writer\Stream;

/**
 * HttpRawTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class HttpRawTest extends HttpTestCase
{
    public function testHandle(): void
    {
        $transactions = [];
        $history = Middleware::history($transactions);

        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Foo', 'Content-Type' => 'application/json'], Parser::encode(['foo' => 'bar', 'bar' => 'foo'])),
        ]);

        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $client = new Client(['handler' => $handler]);

        $action = $this->getActionFactory()->factory(HttpRaw::class);
        if ($action instanceof HttpRaw) {
            $action->setClient($client);
        }

        // handle request
        $url = 'http://127.0.0.1';

        $response = $action->handle(
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

        $actual = $this->getStreamBodyString($response);
        $expect = $this->getExpectedJson();

        $this->assertInstanceOf(HttpResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['x-foo' => 'Foo', 'content-type' => 'application/json'], $response->getHeaders());
        $this->assertJsonStringEqualsJsonString($expect, $actual, $actual);

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
            'X-Foo' => ['Bar'],
        ];

        $this->assertEquals('POST', $transaction['request']->getMethod());
        $this->assertEquals('http://127.0.0.1?foo=bar', $transaction['request']->getUri()->__toString());
        $this->assertEquals($headers, $this->getXHeaders($transaction['request']->getHeaders()));
        $this->assertEquals('foobar: bar, payload: bar', $transaction['request']->getBody()->__toString());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConfiguration(string $url): array
    {
        return [
            'method' => 'POST',
            'url' => $url,
            'query' => 'foo=bar',
            'headers' => [
                'X-Foo' => 'Bar',
            ],
            'body' => 'foobar: {{query.foo}}, payload: {{payload.foo}}',
        ];
    }

    private function getExpectedJson(): string
    {
        return Parser::encode(['foo' => 'bar', 'bar' => 'foo']);
    }

    private function getStreamBodyString(HttpResponseInterface $return): string
    {
        $response = new HttpResponse();
        $body = $return->getBody();
        $this->assertInstanceOf(Stream::class, $body);
        $body->writeTo($response);

        return (string) $response->getBody();
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
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
