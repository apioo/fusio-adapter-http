<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2018 Christoph Kappestein <christoph.kappestein@gmail.com>
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

use Fusio\Engine\ContextInterface;
use Fusio\Engine\Form\BuilderInterface;
use Fusio\Engine\Form\ElementFactoryInterface;
use Fusio\Engine\ParametersInterface;
use Fusio\Engine\RequestInterface;

/**
 * HttpProcessor
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class HttpProcessor extends HttpEngine
{
    public function getName()
    {
        return 'HTTP-Processor';
    }

    public function handle(RequestInterface $request, ParametersInterface $configuration, ContextInterface $context)
    {
        $this->setUrl($configuration->get('url'));
        $this->setType($configuration->get('type'));

        if (!empty($configuration->get('version'))) {
            $this->setVersion($configuration->get('version'));
        }

        return parent::handle($request, $configuration, $context);
    }

    public function configure(BuilderInterface $builder, ElementFactoryInterface $elementFactory)
    {
        $options = [
            self::TYPE_JSON => self::TYPE_JSON,
            self::TYPE_FORM => self::TYPE_FORM,
        ];

        $httpOptions = [
            self::HTTP_1_0 => self::HTTP_1_0,
            self::HTTP_1_1 => self::HTTP_1_1,
            self::HTTP_2_0 => self::HTTP_2_0,
        ];

        $builder->add($elementFactory->newInput('url', 'URL', 'text', 'Click <a ng-click="help.showDialog(\'help/action/http.md\')">here</a> for more information.'));
        $builder->add($elementFactory->newSelect('type', 'Content-Type', $options, 'The content type which you want to send to the endpoint.'));
        $builder->add($elementFactory->newSelect('version', 'HTTP Version', $httpOptions, 'Optional http protocol which you want to send to the endpoint.'));

    }
}
