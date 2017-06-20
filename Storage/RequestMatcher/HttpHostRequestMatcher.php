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
    private $httpHost;

    /**
     * @param string $httpHost
     */
    public function __construct($httpHost)
    {
        $this->httpHost = $httpHost;
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function matches(Request $request)
    {
        return $request->getHttpHost() === $this->httpHost;
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
