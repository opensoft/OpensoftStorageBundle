<?php
/**
 * This file is part of ONP.
 *
 * Copywrite (c) 2014 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Storage;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
interface StorageKeyGeneratorInterface
{
    /**
     * @param string $baseFilename
     * @return string
     */
    public function generate($baseFilename);
}
