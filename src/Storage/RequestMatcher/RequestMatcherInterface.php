<?php

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
    public function matches(Request $request): bool;

    /**
     * @param Request $request
     * @return string
     */
    public function retrieveStorageKey(Request $request): string;
}
