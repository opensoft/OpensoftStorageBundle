<?php

namespace Opensoft\StorageBundle\Twig\Extension;

use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Storage\StorageUrlResolverInterface;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class TwigExtension extends \Twig_Extension
{

    /**
     * @var StorageUrlResolverInterface
     */
    private $storageUrlResolver;

    /**
     * @param StorageUrlResolverInterface $storageUrlResolver
     */
    public function __construct(StorageUrlResolverInterface $storageUrlResolver)
    {
        $this->storageUrlResolver = $storageUrlResolver;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('storage_url', array($this, 'getStorageUrl')),
        ];
    }

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('format_bytes',  array($this, 'formatBytes')),
        ];
    }

    /**
     * @param StorageFile $file
     * @param string $referenceType
     * @return string
     */
    public function getStorageUrl(StorageFile $file, $referenceType = StorageUrlResolverInterface::ABSOLUTE_URL)
    {
        return $this->storageUrlResolver->getUrl($file, $referenceType);
    }

    /**
     * @param $number
     * @param bool $base2conversion
     * @return string
     */
    public function formatBytes($number, $base2conversion = true)
    {
        if (!is_numeric($number)) {
            return '';
        }

        $unit = $base2conversion ? 1024 : 1000;
        if ($number < $unit) {
            return $number.' B';
        }
        $exp = intval((log($number) / log($unit)));
        $pre = ($base2conversion ? 'kMGTPE' : 'KMGTPE');
        $pre = $pre[$exp - 1] . ($base2conversion ? '' : 'i');

        return sprintf('%.1f %sB', $number / pow($unit, $exp), $pre);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'storage_engine';
    }
}
