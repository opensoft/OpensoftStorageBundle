<?php

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
