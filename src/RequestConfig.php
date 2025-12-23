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

namespace Fusio\Adapter\Http;

use Fusio\Adapter\Http\Action\HttpSenderAbstract;
use Fusio\Engine\ParametersInterface;

/**
 * RequestConfig
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class RequestConfig
{
    private string $url;
    private ?string $type;
    private ?string $version;
    private ?string $authorization;
    /**
     * @var array<string, mixed>|null
     */
    private ?array $query;
    private mixed $payload;
    private bool $cache;

    /**
     * @param array<string, mixed>|null $query
     */
    public function __construct(string $url, ?string $type = null, ?string $version = null, ?string $authorization = null, ?array $query = null, mixed $payload = null, bool $cache = false)
    {
        $this->url = $url;
        $this->type = $type;
        $this->version = $version;
        $this->authorization = $authorization;
        $this->query = $query;
        $this->payload = $payload;
        $this->cache = $cache;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getAuthorization(): ?string
    {
        return $this->authorization;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getQuery(): ?array
    {
        return $this->query;
    }

    public function getPayload(): mixed
    {
        return $this->payload;
    }

    public function shouldCache(): bool
    {
        return $this->cache;
    }

    public static function forProxy(string $url, ParametersInterface $configuration): self
    {
        $type = $configuration->get('type');
        $version = $configuration->get('version');
        $authorization = $configuration->get('authorization');
        $cache = $configuration->get('cache');

        $rawQuery = $configuration->get('query');
        $query = null;
        if (!empty($rawQuery)) {
            $query = [];
            parse_str($rawQuery, $query);
        }

        /** @phpstan-ignore argument.type */
        return new self($url, $type, $version, $authorization, $query, null, !empty($cache));
    }

    public static function forRaw(string $url, ParametersInterface $configuration): self
    {
        $type = HttpSenderAbstract::TYPE_TEXT;
        $version = $configuration->get('version');
        $body = $configuration->get('body');
        $cache = $configuration->get('cache');

        $rawQuery = $configuration->get('query');
        $query = null;
        if (!empty($rawQuery)) {
            $query = [];
            parse_str($rawQuery, $query);
        }

        /** @phpstan-ignore argument.type */
        return new self($url, $type, $version, null, $query, $body, !empty($cache));
    }
}
