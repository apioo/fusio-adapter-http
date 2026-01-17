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

use Composer\InstalledVersions;
use Fusio\Adapter\Http\RequestConfig;
use Fusio\Engine\ActionAbstract;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Utils;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr16CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Psr\Http\Message\StreamInterface;
use PSX\Data\Body;
use PSX\Data\Multipart\File;
use PSX\Http\Environment\HttpResponseInterface;
use PSX\Http\Exception\BadRequestException;
use PSX\Http\MediaType;
use PSX\Record\Transformer;

/**
 * HttpSenderAbstract
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
abstract class HttpSenderAbstract extends ActionAbstract
{
    public const TYPE_BINARY = 'application/octet-stream';
    public const TYPE_FORM = 'application/x-www-form-urlencoded';
    public const TYPE_JSON = 'application/json';
    public const TYPE_MULTIPART = 'multipart/form-data';
    public const TYPE_TEXT = 'text/plain';
    public const TYPE_XML = 'application/xml';

    protected const CONTENT_TYPE = [
        self::TYPE_BINARY => self::TYPE_BINARY,
        self::TYPE_FORM => self::TYPE_FORM,
        self::TYPE_JSON => self::TYPE_JSON,
        self::TYPE_MULTIPART => self::TYPE_MULTIPART,
        self::TYPE_TEXT => self::TYPE_TEXT,
        self::TYPE_XML => self::TYPE_XML,
    ];

    private ?Client $client = null;

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    public function send(RequestConfig $config, RequestInterface $request, ParametersInterface $configuration, ContextInterface $context, ?Client $client = null): HttpResponseInterface
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        [$method, $uriFragments, $query, $headers, $payload] = $this->getRequestValues($config, $request, $configuration, $context);

        $headers['x-fusio-operation-id'] = '' . $context->getOperationId();
        $headers['x-fusio-user-anonymous'] = $context->getUser()->isAnonymous() ? '1' : '0';

        if (!$context->getUser()->isAnonymous()) {
            $headers['x-fusio-user-id'] = '' . $context->getUser()->getId();
            $headers['x-fusio-user-name'] = $context->getUser()->getName();
        }

        if (!$context->getApp()->isAnonymous()) {
            $headers['x-fusio-app-id'] = '' . $context->getApp()->getId();
            $headers['x-fusio-app-key'] = $context->getApp()->getAppKey();
        }

        $headers['x-fusio-remote-ip'] = $clientIp;
        $headers['x-forwarded-for'] = $clientIp;
        $headers['accept'] = 'application/json, application/x-www-form-urlencoded;q=0.9, */*;q=0.8';
        $headers['user-agent'] = 'Fusio Adapter-HTTP v' . InstalledVersions::getVersion('fusio/adapter-http');

        $url = $config->getUrl();
        if (!empty($uriFragments)) {
            foreach ($uriFragments as $name => $value) {
                $url = str_replace(':' . $name, $value, $url);
            }
        }

        $options = $this->getRequestOptions($config, $headers, $query, $payload);

        if (!$client instanceof Client) {
            $guzzleOptions = [];
            if ($config->shouldCache()) {
                $stack = HandlerStack::create();
                $stack->push(new CacheMiddleware(new PrivateCacheStrategy(new Psr16CacheStorage($this->cache))), 'cache');
                $guzzleOptions['handler'] = $stack;
            }

            $client = $this->client ?? new Client($guzzleOptions);
        }

        $response = $client->request($method, $url, $options);

        return $this->response->proxy($response);
    }

    /**
     * @return array{string, array<string, mixed>|null, array<string, mixed>|null, array<string, mixed>, mixed}
     */
    abstract protected function getRequestValues(RequestConfig $config, RequestInterface $request, ParametersInterface $configuration, ContextInterface $context): array;

    protected function getClient(ParametersInterface $configuration): ?Client
    {
        $connectionName = $configuration->get('connection');
        if (empty($connectionName)) {
            return null;
        }

        $connection = $this->connector->getConnection($connectionName);
        if (!$connection instanceof Client) {
            return null;
        }

        return $connection;
    }

    /**
     * @param array<string, mixed> $headers
     * @param array<string, mixed>|null $query
     * @return array<string, mixed>
     */
    private function getRequestOptions(RequestConfig $config, array $headers, ?array $query, mixed $payload): array
    {
        $configuredQuery = $config->getQuery();
        if (!empty($configuredQuery)) {
            $query = array_merge($query ?? [], $configuredQuery);
        }

        $contentType = null;
        $options = [];
        if ($payload !== null) {
            if ($config->getType() === self::TYPE_BINARY) {
                $contentType = 'application/octet-stream';
                if ($payload instanceof StreamInterface) {
                    $options['body'] = $payload;
                } elseif (is_string($payload)) {
                    $options['body'] = $payload;
                } else {
                    throw new BadRequestException('Provided an invalid request payload');
                }
            } elseif ($config->getType() === self::TYPE_FORM) {
                if ($payload instanceof \JsonSerializable) {
                    $options['form_params'] = Transformer::toArray($payload);
                } elseif (is_array($payload)) {
                    $options['form_params'] = $payload;
                } else {
                    throw new BadRequestException('Provided an invalid request payload');
                }
            } elseif ($config->getType() === self::TYPE_MULTIPART) {
                if ($payload instanceof Body\Multipart) {
                    $parts = [];
                    foreach ($payload->getAll() as $name => $part) {
                        if ($part instanceof File) {
                            $tmpName = $part->getTmpName();
                            if ($tmpName === null || !is_file($tmpName)) {
                                continue;
                            }

                            $parts[] = [
                                'name' => $name,
                                'contents' => Utils::tryFopen($tmpName, 'r'),
                                'filename' => $part->getName(),
                            ];
                        } elseif (is_string($part)) {
                            $parts[] = [
                                'name' => $name,
                                'contents' => $part,
                            ];
                        }
                    }

                    $options['multipart'] = $parts;
                } else {
                    throw new BadRequestException('Provided an invalid request payload');
                }
            } elseif ($config->getType() === self::TYPE_TEXT) {
                if (is_string($payload)) {
                    $options['body'] = $payload;
                } else {
                    throw new BadRequestException('Provided an invalid request payload');
                }
            } elseif ($config->getType() === self::TYPE_XML) {
                $contentType = 'application/xml';
                if ($payload instanceof \DOMDocument) {
                    $options['body'] = $payload->saveXML();
                } elseif (is_string($payload)) {
                    $options['body'] = $payload;
                } else {
                    throw new BadRequestException('Provided an invalid request payload');
                }
            } else {
                $options['json'] = $payload;
            }
        }

        if (!isset($headers['content-type']) && $contentType !== null) {
            $headers['content-type'] = $contentType;
        }

        $options['headers'] = $headers;
        $options['query'] = $query;
        $options['http_errors'] = false;

        $version = $config->getVersion();
        if (!empty($version)) {
            $options['version'] = $version;
        }

        return $options;
    }

    private function isJson(string $contentType): bool
    {
        try {
            return MediaType\Json::isMediaType(MediaType::parse($contentType));
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
