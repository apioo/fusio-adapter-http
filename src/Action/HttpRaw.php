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
            $context,
            $this->getClient($configuration)
        );
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory): void
    {
        $builder->add($elementFactory->newConnection('connection', 'Connection', 'The HTTP connection which should be used, this is optional in case you provide an absolute url'));
        $builder->add($elementFactory->newSelect('method', 'Method', self::METHODS, 'The HTTP method'));
        $builder->add($elementFactory->newInput('url', 'URL', 'text', 'A url to the HTTP endpoint, this gets resolved against the connection base url so relativ urls like <code>/foo/bar</code> are possible'));
        $builder->add($elementFactory->newInput('query', 'Query', 'text', 'Optional query parameters i.e. <code>foo=bar&bar=foo</code>'));
        $builder->add($elementFactory->newMap('headers', 'Headers', 'text', 'Optional HTTP headers'));
        $builder->add($elementFactory->newTextArea('body', 'Body', 'text', 'The HTTP body'));
    }

    protected function getRequestValues(RequestConfig $config, RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): array
    {
        /** @var array<string, mixed> $headers */
        $headers = $configuration->get('headers');
        if (!is_array($headers)) {
            $headers = [];
        }

        $templateContext = [
            'payload' => $request->getPayload(),
            'arguments' => $request->getArguments(),
            'context' => $context,
        ];

        $queryParameters = $config->getQuery();

        $requestContext = $request->getContext();
        if ($requestContext instanceof HttpRequestContext) {
            $queryParameters = array_merge($requestContext->getRequest()->getUri()->getParameters(), $queryParameters);

            $templateContext['uriFragments'] = $requestContext->getParameters();
            $templateContext['query'] = $queryParameters;
        }

        $body = $configuration->get('body');
        if (!empty($body)) {
            $twig = new Environment(new ArrayLoader(['body' => $body]), []);
            $payload = $twig->render('body', $templateContext);
        } else {
            $payload = null;
        }

        return [
            $configuration->get('method'),
            $requestContext instanceof HttpRequestContext ? $requestContext->getParameters() : [],
            $queryParameters,
            $headers,
            $payload
        ];
    }
}
