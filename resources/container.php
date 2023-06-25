<?php

use Fusio\Adapter\Http\Action\HttpComposition;
use Fusio\Adapter\Http\Action\HttpSenderAbstract;
use Fusio\Adapter\Http\Action\HttpLoadBalancer;
use Fusio\Adapter\Http\Action\HttpProcessor;
use Fusio\Adapter\Http\Connection\Http;
use Fusio\Engine\Adapter\ServiceBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    $services = ServiceBuilder::build($container);
    $services->set(Http::class);
    $services->set(HttpComposition::class);
    $services->set(HttpLoadBalancer::class);
    $services->set(HttpProcessor::class);
};
