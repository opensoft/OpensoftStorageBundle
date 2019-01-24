<?php

namespace Opensoft\StorageBundle\Storage;

use Opensoft\StorageBundle\Entity\StorageFile;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
interface StorageUrlResolverInterface
{
    /**
     * Generates an absolute URL, e.g. "https://example.com/dir/file".
     */
    public const ABSOLUTE_URL = 'absolute_url';

    /**
     * Generates a url to be handled by the RequestListener permanent url strategy
     */
    public const PERMANENT_URL = 'permanent_url';

    /**
     * Retrieve a URL for a specific file that can be given to the browser
     *
     * @param StorageFile $file
     * @param string $referenceType
     * @return string
     */
    public function getUrl(StorageFile $file, string $referenceType = self::ABSOLUTE_URL): string;

    /**
     * Retrieve stream context, if required
     *
     * @param StorageFile $file
     * @return resource
     */
    public function getContext(StorageFile $file);
}
