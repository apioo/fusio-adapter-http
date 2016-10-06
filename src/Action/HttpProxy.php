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

namespace Fusio\Adapter\Http\Action;

use Doctrine\Common\Annotations\SimpleAnnotationReader;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;
use PSX\Data\Configuration;
use PSX\Data\Payload;
use PSX\Data\Processor;
use PSX\Schema\SchemaManager;

/**
 * HttpProxy
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class HttpProxy extends HttpRequest
{
    public function getName()
    {
        return 'HTTP-Proxy';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $response = $this->executeRequest($request, $configuration, $context);

        $body = (string) $response->getBody();
        $data = $this->getProcessor()->parse(Payload::create($body, $response->getHeader('Content-Type')));

        return $this->response->build($response->getStatusCode(), [], $data);
    }

    protected function getProcessor()
    {
        $reader = new SimpleAnnotationReader();
        $config = Configuration::createDefault(
            $reader,
            new SchemaManager($reader),
            'http://phpsx.org/2014/data'
        );

        return new Processor($config);
    }
}
