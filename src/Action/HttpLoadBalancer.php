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

/**
 * HttpLoadBalancer
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class HttpLoadBalancer extends HttpProxyAbstract implements ConfigurableInterface
{
    public function getName(): string
    {
        return 'HTTP-Load-Balancer';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $urls = $configuration->get('url');
        if (!is_array($urls) || empty($urls)) {
            throw new ConfigurationException('No fitting urls configured');
        }

        $url = $urls[array_rand($urls)] ?? null;
        if (empty($url)) {
            throw new ConfigurationException('No fitting url configured');
        }

        return $this->send(
            RequestConfig::forProxy($url, $configuration),
            $request,
            $configuration,
            $context
        );
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newCollection('url', 'URL', 'Multiple urls which are called randomly for load balancing'));
        $builder->add($elementFactory->newSelect('type', 'Content-Type', self::CONTENT_TYPE, 'The content type which you want to send to the endpoint.'));
        $builder->add($elementFactory->newSelect('version', 'HTTP Version', self::VERSION, 'Optional http protocol which you want to send to the endpoint.'));
        $builder->add($elementFactory->newInput('authorization', 'Authorization', 'text', 'Optional a HTTP authorization header which gets passed to the endpoint.'));
        $builder->add($elementFactory->newInput('query', 'Query', 'text', 'Optional fix query parameters which are attached to the url.'));
        $builder->add($elementFactory->newSelect('cache', 'Cache', self::CACHE, 'Optional consider HTTP cache headers.'));
    }
}
