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

namespace Fusio\Adapter\Http\Action;

use Fusio\Adapter\Http\RequestConfig;
use Fusio\Engine\ConfigurableInterface;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\Response as HttpResponse;
use PSX\Http\Writer\Stream;
use PSX\Json\Parser;

/**
 * HttpComposition
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class HttpComposition extends HttpProxyAbstract implements ConfigurableInterface
{
    public function getName(): string
    {
        return 'HTTP-Composition';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $urls = $configuration->get('url');
        if (!is_array($urls) || empty($urls)) {
            throw new ConfigurationException('No fitting urls configured');
        }

        $headers = [];
        $data = [];

        foreach ($urls as $url) {
            $response = $this->send(
                RequestConfig::forProxy($url, $configuration),
                $request,
                $configuration,
                $context,
                $this->getClient($configuration)
            );

            $data[$url] = $this->getStreamBodyString($response);
        }

        return $this->response->build(
            200,
            $headers,
            $data
        );
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The HTTP connection which should be used, this is optional in case you provide an absolute url'));
        $builder->add($elementFactory->newCollection('url', 'URL', 'text', 'Calls multiple defined urls and returns a composite result of every call, these urls are resolved against the connection base url so relativ urls like <code>/foo/bar</code> are possible'));
        $builder->add($elementFactory->newSelect('type', 'Content-Type', self::CONTENT_TYPE, 'The content type which you want to send to the endpoint'));
        $builder->add($elementFactory->newInput('authorization', 'Authorization', 'text', 'Optional a HTTP authorization header which gets passed to the endpoint'));
        $builder->add($elementFactory->newInput('query', 'Query', 'text', 'Optional query parameters i.e. <code>foo=bar&bar=foo</code>'));
    }

    private function getStreamBodyString(HttpResponseInterface $return): mixed
    {
        $contentType = $return->getHeader('Content-Type');

        $body = $return->getBody();
        if ($body instanceof Stream) {
            $response = new HttpResponse();
            $body->writeTo($response);

            $return = (string) $response->getBody();
        } else {
            $return = $body;
        }

        if (is_string($return) && str_starts_with($contentType, 'application/json')) {
            $return = Parser::decode($return);
        }

        return $return;
    }
}
