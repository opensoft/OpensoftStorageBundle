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
    const ABSOLUTE_URL = 'absolute_url';

    /**
     * Generates a url to be used through the app_file.php proxy service.
     */
    const PERMANENT_URL = 'permanent_url';

    /**
     * Retrieve a URL for a specific file that can be given to the browser
     *
     * @param StorageFile $file
     * @param string $referenceType
     * @return string
     */
    public function getUrl(StorageFile $file, $referenceType = self::ABSOLUTE_URL);

    /**
     * Retrieve stream context, if required
     *
     * @param StorageFile $file
     * @return mixed
     */
    public function getContext(StorageFile $file);
}
