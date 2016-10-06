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

namespace Fusio\Adapter\Http\Tests\Action;

use Fusio\Adapter\Http\Action\HttpRequest;
use Fusio\Engine\Form\Builder;
use Fusio\Engine\Form\Container;
use Fusio\Engine\Http\ClientInterface;
use Fusio\Engine\ResponseInterface;
use Fusio\Engine\Test\EngineTestCaseTrait;
use PSX\Http\Response;
use PSX\Record\Record;

/**
 * HttpRequestTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class HttpRequestTest extends \PHPUnit_Framework_TestCase
{
    use EngineTestCaseTrait;

    public function testHandle()
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)
            ->setMethods(['request'])
            ->getMock();

        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('http://127.0.0.1/bar'),
                $this->equalTo('POST'),
                $this->equalTo(['User-Agent' => 'Fusio-Http-Adapter']),
                $this->callback(function ($body) {
                    $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', $body);
                    return true;
                })
            )
            ->will($this->returnValue(new Response(200)));

        $parameters = $this->getParameters([
            'url'  => 'http://127.0.0.1/bar',
            'body' => '{{ request.body|json }}',
        ]);

        $body = Record::fromArray([
            'foo' => 'bar'
        ]);

        $action = $this->getActionFactory()->factory(HttpRequest::class);
        $action->setHttpClient($httpClient);

        $response = $action->handle($this->getRequest('POST', [], [], [], $body), $parameters, $this->getContext());

        $body = [
            'success' => true,
            'message' => 'Request successful'
        ];

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($body, $response->getBody());
    }

    public function testHandleVariableUrl()
    {
        $httpClient = $this->getMockBuilder(ClientInterface::class)
            ->setMethods(['request'])
            ->getMock();

        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('http://127.0.0.1/bar/1'),
                $this->equalTo('POST'),
                $this->equalTo(['User-Agent' => 'Fusio-Http-Adapter']),
                $this->callback(function ($body) {
                    $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', $body);
                    return true;
                })
            )
            ->will($this->returnValue(new Response(200)));

        $parameters = $this->getParameters([
            'url'  => 'http://127.0.0.1/bar/:id',
            'body' => '{{ request.body|json }}',
        ]);

        $body = Record::fromArray([
            'foo' => 'bar'
        ]);

        $action = $this->getActionFactory()->factory(HttpRequest::class);
        $action->setHttpClient($httpClient);

        $response = $action->handle($this->getRequest('POST', ['id' => 1], [], [], $body), $parameters, $this->getContext());

        $body = [
            'success' => true,
            'message' => 'Request successful'
        ];

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($body, $response->getBody());
    }

    public function testGetForm()
    {
        $action  = $this->getActionFactory()->factory(HttpRequest::class);
        $builder = new Builder();
        $factory = $this->getFormElementFactory();

        $action->configure($builder, $factory);

        $this->assertInstanceOf(Container::class, $builder->getForm());
    }
}
