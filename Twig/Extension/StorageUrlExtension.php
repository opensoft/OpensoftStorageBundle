<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) 2014 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Twig\Extension;

use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Storage\StorageUrlResolverInterface;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StorageUrlExtension extends \Twig_Extension
{

    /**
     * @var StorageUrlResolverInterface
     */
    private $storageUrlResolver;

    /**
     * @param StorageUrlResolverInterface $storageUrlResolver
     */
    public function __construct(StorageUrlResolverInterface $storageUrlResolver)
    {
        $this->storageUrlResolver = $storageUrlResolver;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('storage_url', array($this, 'getStorageUrl')),
        ];
    }

    /**
     * @param StorageFile $file
     * @param string $referenceType
     * @return string
     */
    public function getStorageUrl(StorageFile $file, $referenceType = StorageUrlResolverInterface::ABSOLUTE_URL)
    {
        return $this->storageUrlResolver->getUrl($file, $referenceType);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'storage_url';
    }
}
