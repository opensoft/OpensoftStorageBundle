<?php

namespace Opensoft\StorageBundle\DependencyInjection;

use Opensoft\StorageBundle\Storage\RequestMatcher\HttpHostRequestMatcher;
use Opensoft\StorageBundle\Storage\StorageFileTypeProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class OpensoftStorageExtension extends Extension
{
    /**
     * @param array $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $container->setParameter('opensoft_storage.parameter.permanent_base_url', $config['permanent_url']['base_url']);

        // Set the default request matcher based on the strategy selected
        if ($config['permanent_url']['strategy'] == 'http_host') {
            $container->setAlias('opensoft_storage.request_matcher.default_request_matcher', HttpHostRequestMatcher::class);
            // Set parameters for the opensoft_storage.request_matcher.http_host_request_matcher service
            $container->setParameter('opensoft_storage.parameter.permanent.http_host', $config['permanent_url']['http_host']);
        }

        $container->setAlias(StorageFileTypeProviderInterface::class, $config['storage_type_provider_service']);
    }
}
