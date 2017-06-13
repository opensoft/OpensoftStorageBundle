<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

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
