<?php

namespace LinkORB\Bundle\WikiBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class LinkORBWikiBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
            ['LinkORB\Bundle\WikiBundle\Entity'],
            [__DIR__.DIRECTORY_SEPARATOR.'Entity']
        ));
    }

    /**
     * @param array<string,mixed> $config
     * @param ContainerConfigurator $container
     * @param ContainerBuilder $builder
     * @return void
     */
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $container->import('../config/services.yaml');
    }
}
