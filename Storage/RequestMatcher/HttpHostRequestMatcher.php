<?php

namespace Opensoft\StorageBundle\Storage\RequestMatcher;

use Symfony\Component\HttpFoundation\Request;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class HttpHostRequestMatcher implements RequestMatcherInterface
{
    /**
     * @var string
     */
    private $serverName;

    /**
     * @param string $serverName
     */
    public function __construct($serverName)
    {
        $this->serverName = $serverName;
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function matches(Request $request)
    {
        return $request->getHttpHost() === $this->serverName;
    }

    /**
     * @param Request $request
     * @return string
     */
    public function retrieveStorageKey(Request $request)
    {
        return substr($request->getPathInfo(), 1);
    }
}
