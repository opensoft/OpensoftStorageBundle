<?php
/**
 * This file is part of ONP.
 *
 * Copywrite (c) 2014 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Storage\Adapter;

use Gaufrette\Adapter;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Ensures validation of adapter options prior to creating the adapters
 *
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
abstract class AbstractAdapterConfiguration implements AdapterConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function createAdapter(array $options)
    {
        $options = $this->getOptionsResolver()->resolve($options);

        return $this->doCreateAdapter($options);
    }

    /**
     * @return array
     */
    public function getProtectedOptions()
    {
        return [];
    }

    /**
     * Options are pre-validated, create and return the adapter object
     *
     * @param array $options
     * @return Adapter
     */
    abstract protected function doCreateAdapter(array $options);

    /**
     * Retrieve option configuration for this adapter
     *
     * @return OptionsResolver
     */
    abstract protected function getOptionsResolver();
}
