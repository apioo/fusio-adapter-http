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
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\Request\HttpRequestContext;
use Fusio\Engine\RequestInterface;

/**
 * HttpProxyAbstract
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
abstract class HttpProxyAbstract extends HttpSenderAbstract
{
    protected function getRequestValues(RequestConfig $config, RequestInterface $request, ParametersInterface $configuration): array
    {
        $requestContext = $request->getContext();
        if ($requestContext instanceof HttpRequestContext) {
            $httpRequest = $requestContext->getRequest();
            $exclude = array_merge(['accept', 'accept-charset', 'accept-encoding', 'accept-language', 'authorization', 'content-type', 'host', 'user-agent'], self::HOP_BY_HOP_HEADERS);
            $headers = $httpRequest->getHeaders();
            $headers = array_diff_key($headers, array_combine($exclude, array_fill(0, count($exclude), null)));

            $authorization = $config->getAuthorization();
            $proxyAuthorization = $httpRequest->getHeader('Proxy-Authorization');
            if (!empty($authorization)) {
                $headers['authorization'] = $authorization;
            } elseif (!empty($proxyAuthorization)) {
                $headers['authorization'] = $proxyAuthorization;
            }

            $host = $httpRequest->getHeader('Host');
            if (!empty($host)) {
                $headers['x-forwarded-host'] = $host;
            }

            return [
                $httpRequest->getMethod(),
                $requestContext->getParameters(),
                $httpRequest->getUri()->getParameters(),
                $headers,
                $request->getPayload()
            ];
        } else {
            return [
                'POST',
                [],
                [],
                [],
                $request->getPayload(),
            ];
        }
    }
}
