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
use Fusio\Engine\ContextInterface;
use Fusio\Engine\Exception\ConfigurationException;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\Request\HttpRequestContext;
use Fusio\Engine\RequestInterface;
use PSX\Http\Environment\HttpResponseInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * HttpRaw
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class HttpRaw extends HttpSenderAbstract
{
    private const METHODS = [
        'GET' => 'GET',
        'POST' => 'POST',
        'PUT' => 'PUT',
        'PATCH' => 'PATCH',
        'DELETE' => 'DELETE',
    ];

    public function getName(): string
    {
        return 'HTTP-Raw';
    }


    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): HttpResponseInterface
    {
        $url = $configuration->get('url');
        if (empty($url)) {
            throw new ConfigurationException('No url configured');
        }

        return $this->send(
            RequestConfig::forRaw($url, $configuration),
            $request,
            $configuration,
            $context
        );
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newSelect('method', 'Method', self::METHODS, 'The HTTP method'));
        $builder->add($elementFactory->newInput('url', 'URL', 'text', 'An url to the HTTP endpoint'));
        $builder->add($elementFactory->newMap('headers', 'Headers', 'text', 'The HTTP headers'));
        $builder->add($elementFactory->newSelect('type', 'Content-Type', self::CONTENT_TYPE, 'The content type which you want to send to the endpoint.'));
        $builder->add($elementFactory->newSelect('version', 'HTTP Version', self::VERSION, 'Optional HTTP protocol which you want to send to the endpoint.'));
        $builder->add($elementFactory->newInput('query', 'Query', 'text', 'Optional fix query parameters which are attached to the url.'));
        $builder->add($elementFactory->newSelect('cache', 'Cache', self::CACHE, 'Optional consider HTTP cache headers.'));
        $builder->add($elementFactory->newTextArea('body', 'Body', 'text', 'The HTTP body'));
    }

    protected function getRequestValues(RequestConfig $config, RequestInterface $request, ParametersInterface $configuration): array
    {
        $headers = $configuration->get('headers');
        if (!is_array($headers)) {
            $headers = [];
        }

        $templateContext = [
            'payload' => $request->getPayload(),
            'arguments' => $request->getArguments(),
        ];

        $requestContext = $request->getContext();
        if ($requestContext instanceof HttpRequestContext) {
            $templateContext['uriFragments'] = $requestContext->getParameters();
            $templateContext['query'] = $requestContext->getRequest()->getUri()->getParameters();
        }

        $loader = new ArrayLoader(['body' => $configuration->get('body')]);
        $twig = new Environment($loader, []);

        return [
            $configuration->get('method'),
            $requestContext instanceof HttpRequestContext ? $requestContext->getParameters() : [],
            $config->getQuery(),
            $headers,
            $twig->render('body', $templateContext)
        ];
    }
}
