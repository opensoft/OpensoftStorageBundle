<?php

namespace Opensoft\StorageBundle;

use Opensoft\StorageBundle\DependencyInjection\CompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class OpensoftStorageBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new CompilerPass\StorageAdapterRegistrationCompilerPass());
    }
}
