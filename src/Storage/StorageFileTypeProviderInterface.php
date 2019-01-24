<?php

namespace Opensoft\StorageBundle\Storage;

use Symfony\Component\HttpFoundation\Response;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
interface StorageFileTypeProviderInterface
{
    /**
     * Returns an array of types available within the application.  They keys of this array are expected to be unique
     * integers, while the values should be human readable descriptions of each type.
     *
     * @return array<int, string>
     */
    public function getTypes(): array;

    /**
     * Generate the filename to be used in the storage engine based on the time
     *
     * @param int $type
     * @return string|null
     */
    public function generateBaseFilename(int $type): ?string;

    /**
     * Add headers to a permanent URL request based on the type.  This is often used to add CORS headers to some specific
     * file types.
     *
     * @param Response $response
     * @param int $type
     */
    public function addResponseHeaders(Response $response, int $type);
}
