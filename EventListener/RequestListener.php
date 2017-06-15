<?php

namespace Opensoft\StorageBundle\EventListener;

use Doctrine\Common\Persistence\ManagerRegistry;
use Opensoft\StorageBundle\Storage\RequestMatcher\RequestMatcherInterface;
use Opensoft\StorageBundle\Storage\StorageFileTypeProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;


/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class RequestListener implements EventSubscriberInterface
{
    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * @var StorageFileTypeProviderInterface
     */
    private $typeProvider;

    /**
     * @var RequestMatcherInterface
     */
    private $requestMatcher;

    public function __construct(
        ManagerRegistry $doctrine,
        StorageFileTypeProviderInterface $typeProvider,
        RequestMatcherInterface $requestMatcher
    ) {
        $this->doctrine = $doctrine;
        $this->typeProvider = $typeProvider;
        $this->requestMatcher = $requestMatcher;
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onEarlyKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        $request = $event->getRequest();

        // if pathInfo starts with some configured prefix, or request comes from a specific VHOST
        if (!$this->requestMatcher->matches($request)) {
            return;
        }

        $storageKey = $this->requestMatcher->retrieveStorageKey($request);

        $sql = '
            SELECT sf.mime_type,
                   sf.type,
                   sf.size_in_bytes,
                   s.adapter_options
            FROM storage_file sf
            INNER JOIN storage s ON sf.storage_id = s.id
            WHERE sf.storage_key = ?
        ';

        $storageInfo = $this->doctrine->getConnection()->fetchAssoc($sql, [$storageKey]);

        if (empty($storageInfo['adapter_options'])) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $adapter = unserialize($storageInfo['adapter_options']);
        /** @var AdapterConfigurationInterface|string $adapterClass */
        $adapterClass = $adapter['class'];

        if (!class_exists($adapterClass)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $response = $adapterClass::createPermanentUrlResponse($request, 'https', $storageKey, $adapter, $storageInfo);

        if (!$response->isNotFound()) {
            $this->typeProvider->addResponseHeaders($response, $storageInfo['type']);
        }

        $event->setResponse($response);
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onEarlyKernelRequest', 254]
        ];
    }
}
