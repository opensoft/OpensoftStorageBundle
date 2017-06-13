<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) 2015 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Finds configured storage adapters and registers them with the adapter resolver
 *
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StorageAdapterRegistrationCompilerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('opensoft_onp_core.storage.gaufrette_adapter_resolver')) {
            return;
        }
        $definition = $container->getDefinition('opensoft_onp_core.storage.gaufrette_adapter_resolver');
        foreach ($container->findTaggedServiceIds('onp.storage_adapter') as $id => $attributes) {
            $definition->addMethodCall('addConfiguration', array(new Reference($id)));
        }
    }
}
