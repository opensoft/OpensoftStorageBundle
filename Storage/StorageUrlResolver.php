<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) 2014 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Storage;

use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Storage\Adapter\AdapterConfigurationInterface;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StorageUrlResolver implements StorageUrlResolverInterface
{
    /**
     * @var GaufretteAdapterResolver
     */
    private $adapterResolver;

    /**
     * @param GaufretteAdapterResolver $adapterResolver
     */
    public function __construct(GaufretteAdapterResolver $adapterResolver)
    {
        $this->adapterResolver = $adapterResolver;
    }

    /**
     * Retrieve a URL for a specific file that can be given to the browser
     *
     * @param StorageFile $file
     * @param string $referenceType
     * @return string
     */
    public function getUrl(StorageFile $file, $referenceType = StorageUrlResolverInterface::ABSOLUTE_URL)
    {
        return $this->getConfiguration($file)->getUrl($file, $referenceType);
    }

    /**
     * @param StorageFile $file
     * @return resource
     */
    public function getContext(StorageFile $file)
    {
        return $this->getConfiguration($file)->getContext($file);
    }

    /**
     * @param StorageFile $file
     * @return AdapterConfigurationInterface
     */
    private function getConfiguration(StorageFile $file)
    {
        $adapterOptions = $file->getStorage()->getAdapterOptions();
        $configuration = $this->adapterResolver->getConfigurationByClass($adapterOptions['class']);

        return $configuration;
    }
}
