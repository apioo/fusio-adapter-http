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
                $context
            );

            foreach ($response->getHeaders() as $key => $value) {
                $headers[$key] = $value;
            }

            $data[$url] = $response->getBody();
        }

        return $this->response->build(
            200,
            $headers,
            $data
        );
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newCollection('url', 'URL', 'Calls multiple defined urls and returns a composite result of every call'));
        $builder->add($elementFactory->newSelect('type', 'Content-Type', self::CONTENT_TYPE, 'The content type which you want to send to the endpoint.'));
        $builder->add($elementFactory->newSelect('version', 'HTTP Version', self::VERSION, 'Optional http protocol which you want to send to the endpoint.'));
        $builder->add($elementFactory->newInput('authorization', 'Authorization', 'text', 'Optional a HTTP authorization header which gets passed to the endpoint.'));
        $builder->add($elementFactory->newInput('query', 'Query', 'text', 'Optional fix query parameters which are attached to the url.'));
        $builder->add($elementFactory->newSelect('cache', 'Cache', self::CACHE, 'Optional consider HTTP cache headers.'));
    }
}
