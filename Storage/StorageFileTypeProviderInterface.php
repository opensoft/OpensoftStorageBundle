<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Storage;

use Symfony\Component\HttpFoundation\Response;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
interface StorageFileTypeProviderInterface
{
    /**
     * @return array
     */
    public function getTypes();

    /**
     * @param Response $response
     * @param $type
     */
    public function addResponseHeaders(Response $response, $type);
}
