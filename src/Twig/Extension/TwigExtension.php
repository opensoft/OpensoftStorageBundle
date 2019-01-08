<?php

namespace Opensoft\StorageBundle\Twig\Extension;

use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Storage\StorageManagerInterface;
use Opensoft\StorageBundle\Storage\StorageUrlResolverInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class TwigExtension extends \Twig_Extension
{
    /**
     * @var StorageManagerInterface
     */
    private $storageManager;

    /**
     * @param StorageManagerInterface $storageManager
     */
    public function __construct(StorageManagerInterface $storageManager)
    {
        $this->storageManager = $storageManager;
    }

    public function getFunctions(): array
    {
        return [
            new \Twig_SimpleFunction('storage_url', array($this, 'getStorageUrl')),
            new \Twig_SimpleFunction('storage_exists', array($this, 'getStorageExists')),
            new \Twig_SimpleFunction('storage_metadata', array($this, 'getStorageMetadata')),
            new \Twig_SimpleFunction('storage_dump', array($this, 'getStorageDump'), ['is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array
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
    public function getStorageUrl(StorageFile $file, string $referenceType = StorageUrlResolverInterface::ABSOLUTE_URL): string
    {
        return $this->storageManager->url($file, $referenceType);
    }

    /**
     * @param StorageFile $file
     * @return bool
     */
    public function getStorageExists(StorageFile $file): bool
    {
        return $this->storageManager->exists($file);
    }

    /**
     * @param StorageFile $file
     * @return array
     */
    public function getStorageMetadata(StorageFile $file): array
    {
        return $this->storageManager->metadata($file);
    }

    /**
     * @param mixed $var
     * @return string
     */
    public function getStorageDump($var): string
    {
        $dumper = new HtmlDumper();
        $cloner = new VarCloner();

        $output = fopen('php://memory', 'r+b');

        $dumper->dump($cloner->cloneVar($var), $output);
        rewind($output);

        return stream_get_contents($output);
    }

    /**
     * @param $number
     * @param bool $base2conversion
     * @return string
     */
    public function formatBytes($number, bool $base2conversion = true): string
    {
        if (!is_numeric($number)) {
            return '';
        }

        $unit = $base2conversion ? 1024 : 1000;
        if ($number < $unit) {
            return $number.' B';
        }
        $exp = (int)(log($number) / log($unit));
        $pre = ($base2conversion ? 'kMGTPE' : 'KMGTPE');
        $pre = $pre[$exp - 1] . ($base2conversion ? '' : 'i');

        return sprintf('%.1f %sB', $number / ($unit ** $exp), $pre);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName(): string
    {
        return 'storage_engine';
    }
}
