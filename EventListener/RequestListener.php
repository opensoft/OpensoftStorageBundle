<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;


/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class RequestListener implements EventSubscriberInterface
{
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

        try {
            $pathInfo = $request->getPathInfo();
            $scheme = 'https';
        } catch (\Exception $e) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }


        $storageKey = substr($pathInfo, 1);

        $sql = '
            SELECT sf.mime_type,
                   sf.type,
                   sf.size_in_bytes,
                   s.adapter_options
            FROM storage_file sf
            INNER JOIN storage s ON sf.storage_id = s.id
            WHERE sf.storage_key = ?
        ';

        $storageInfo = $this->connection->fetchAssoc($sql, [$storageKey]);

        if (empty($storageInfo['adapter_options'])) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $adapter = unserialize($storageInfo['adapter_options']);
        /** @var AdapterConfigurationInterface|string $adapterClass */
        $adapterClass = $adapter['class'];

        if (!class_exists($adapterClass)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }


        $response = $adapterClass::createPermanentUrlResponse($request, $scheme, $storageKey, $adapter, $storageInfo);

        if (!$response->isNotFound()) {
            $this->addHeadersByType($storageInfo['type'], $response);
        }

        return $response;
    }
}
