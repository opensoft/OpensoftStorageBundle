<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */
namespace Opensoft\StorageBundle\Storage\Adapter;

use Opensoft\StorageBundle\Entity\Storage;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
interface UsageAwareInterface
{
    /**
     * Reports on usage for block level filesystems where this object is stored
     *
     * @param Storage $storage
     * @return array
     */
    public function usage(Storage $storage);
}
