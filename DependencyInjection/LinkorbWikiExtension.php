<?php

namespace LinkORB\Bundle\WikiBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class LinkorbWikiExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        // $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        // $loader->load('services.xml');

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $this->addAnnotatedClassesToCompile([
            '**\\Controller\\',

            // ... but glob patterns are also supported:
            'App\\WikiBundle\\Controller\\WikiController',

            // ...
        ]);
    }
}
