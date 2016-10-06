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

use Fusio\Adapter\Http\Action\HttpProxy;
use Fusio\Engine\Form\Builder;
use Fusio\Engine\Form\Container;
use Fusio\Engine\ResponseInterface;
use Fusio\Engine\Test\EngineTestCaseTrait;
use PSX\Http\Client;
use PSX\Http\RequestInterface;
use PSX\Http\Response;
use PSX\Http\Stream\StringStream;
use PSX\Record\Record;

/**
 * HttpProxyTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class HttpProxyTest extends \PHPUnit_Framework_TestCase
{
    use EngineTestCaseTrait;

    public function testHandle()
    {
        $httpClient = $this->getMock(Client::class, array('request'));
        $httpClient->expects($this->once())
            ->method('request')
            ->with($this->callback(function ($request) {
                /** @var \PSX\Http\RequestInterface $request */
                $this->assertInstanceOf(RequestInterface::class, $request);
                $this->assertEquals('application/json', $request->getHeader('Content-Type'));
                $this->assertEquals('foo', $request->getHeader('X-Api-Key'));
                $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', (string) $request->getBody());

                return true;
            }))
            ->will($this->returnValue(new Response(200, [], new StringStream(json_encode(['bar' => 'foo'])))));

        $parameters = $this->getParameters([
            'url'     => 'http://127.0.0.1/bar',
            'headers' => 'Content-Type=application/json&X-Api-Key=foo',
            'body'    => '{{ request.body|json }}',
        ]);

        $body = Record::fromArray([
            'foo' => 'bar'
        ]);

        $action   = $this->getActionFactory()->factory(HttpProxy::class);
        $response = $action->handle($this->getRequest('POST', [], [], [], $body), $parameters, $this->getContext());

        $body = (object) ['bar' => 'foo'];

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals($body, $response->getBody());
    }

    public function testGetForm()
    {
        $action  = $this->getActionFactory()->factory(HttpProxy::class);
        $builder = new Builder();
        $factory = $this->getFormElementFactory();

        $action->configure($builder, $factory);

        $this->assertInstanceOf(Container::class, $builder->getForm());
    }
}
