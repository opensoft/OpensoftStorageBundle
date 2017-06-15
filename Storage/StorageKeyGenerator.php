<?php

namespace Opensoft\StorageBundle\Storage;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StorageKeyGenerator implements StorageKeyGeneratorInterface
{
    /**
     * Generates a storage key for use in the storage engine based on a filename and extension
     *
     * @param string $baseFilename
     * @return string
     */
    public function generate($baseFilename)
    {
        $hash = hash('sha256', $baseFilename);
        $path = substr($hash, 0, 2) . DIRECTORY_SEPARATOR . substr($hash, 2, 2) . DIRECTORY_SEPARATOR . substr($hash, 4, 2);
        $id = preg_replace('@[\\\/:"*?<>|]+@', '', $baseFilename);

        return $path . DIRECTORY_SEPARATOR . $id;
    }
}
