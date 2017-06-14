<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Storage\RequestMatcher;

use Symfony\Component\HttpFoundation\Request;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
interface RequestMatcherInterface
{
    /**
     * @param Request $request
     * @return bool
     */
    public function matches(Request $request);

    /**
     * @param Request $request
     * @return string
     */
    public function retrieveStorageKey(Request $request);
}
