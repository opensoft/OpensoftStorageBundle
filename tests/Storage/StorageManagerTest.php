<?php
declare(strict_types=1);

namespace Opensoft\StorageBundle\Tests\Storage;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Opensoft\StorageBundle\Entity\Repository\StoragePolicyRepository;
use Opensoft\StorageBundle\Entity\Repository\StorageRepository;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Entity\StoragePolicy;
use Opensoft\StorageBundle\Storage\Adapter\LocalAdapterConfiguration;
use Opensoft\StorageBundle\Storage\AdapterResolver;
use Opensoft\StorageBundle\Storage\StorageFileTypeProviderInterface;
use Opensoft\StorageBundle\Storage\StorageKeyGenerator;
use Opensoft\StorageBundle\Storage\StorageManager;
use Opensoft\StorageBundle\Storage\StorageUrlResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\RouterInterface;

/**
 * Ensures the storage manager functions work properly
 *
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
final class StorageManagerTest extends TestCase
{
    /**
     * @var StorageManager
     */
    private $storageManager;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ManagerRegistry
     */
    private $doctrine;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|StorageFileTypeProviderInterface
     */
    private $typeProvider;

    protected function setUp()
    {
        // Make sure we don't go crazy on memory usage
        $this->iniSet('memory_limit', '15M');

        $adapterResolver = new AdapterResolver();
        $adapterResolver->addConfiguration(new LocalAdapterConfiguration(
            $this->createMock(RouterInterface::class),
            new Packages(new Package(new StaticVersionStrategy('v1')), [
                'unversioned' => new UrlPackage(['//onp.dev'], new EmptyVersionStrategy())
            ]),
            '/testserver.dev'
        ));

        $this->doctrine = $this->createMock(ManagerRegistry::class);
        $this->typeProvider = $this->createMock(StorageFileTypeProviderInterface::class);
        $this->typeProvider->method('getTypes')->willReturn([
            1 => 'type 1',
            2 => 'type 2',
            3 => 'type 3'
        ]);
        $this->typeProvider->method('generateBaseFilename')->willReturnMap([
            [1, uniqid('fhi_v2_', true)],
            [2, uniqid('type2_', true)]
        ]);


        $storageRepository = $this->createMock(StorageRepository::class);
        $storageRepository->method('findOneByActive')->willReturn($this->generateTmpStorage(self::storageDir()));

        $storagePolicyRepository = $this->createMock(StoragePolicyRepository::class);
        $storagePolicyRepository->method('findOneByType')->willReturn(null);

        $this->doctrine->method('getRepository')->withConsecutive(
            [StoragePolicy::class],
            [Storage::class]
        )->willReturnOnConsecutiveCalls(
            $storagePolicyRepository,
            $storageRepository
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrine->method('getManager')->willReturn($em);


        $this->storageManager = new StorageManager(
            $this->doctrine,
            $adapterResolver,
            $this->createMock(StorageUrlResolverInterface::class),
            new StorageKeyGenerator(),
            $this->typeProvider
        );
    }

    public function testStoreOnString(): void
    {
        $file = $this->storageManager->store(1, 'this is the content', ['string_content' => true]);

        $this->assertStorageFileEquals($file);
        $this->assertEquals('text/plain', $file->getMimeType());
    }

    public function testStoreOnStringNotAFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->storageManager->store(1, 'this is the content');
    }

    public function testStoreOnFilePath(): void
    {
        $tempFileLocation = sys_get_temp_dir() . '/phpunit-test1.txt';
        file_put_contents($tempFileLocation, 'this is the content');

        $file = $this->storageManager->store(1, $tempFileLocation);

        $this->assertStorageFileEquals($file);
        $this->assertEquals('text/plain', $file->getMimeType());

        unlink($tempFileLocation);
    }

    public function testStoreBigFileOnResource(): void
    {
//        $tempFileLocation = sys_get_temp_dir() . '/phpunit-test1.txt';
//        file_put_contents($tempFileLocation, 'this is the content');

        $file = $this->storageManager->store(1, __DIR__.'/../fixtures/krampuscard_a.pdf');

        $tempFileLocation = self::storageDir() . '/' . $file->getKey();

        $this->assertEquals(1, $file->getType());
        $this->assertFileExists($tempFileLocation);
//        $this->assertEquals('this is the content', file_get_contents($tempFileLocation));
        $this->assertEquals(md5_file($tempFileLocation), $file->getContentHash());
        $this->assertEquals(25412016, $file->getSize());
        $this->assertEquals('application/pdf', $file->getMimeType());
//        $this->assertEquals('', $file->getKey());
    }

    public function testStoreResource(): void
    {
        $tempFileLocation = sys_get_temp_dir() . '/phpunit-test1.txt';
        file_put_contents($tempFileLocation, 'this is the content');

        $handle = fopen($tempFileLocation, 'rb');

        $file = $this->storageManager->store(2, $handle);

        $this->assertStorageFileEquals($file);
        $this->assertEquals('text/plain', $file->getMimeType());

        unlink($tempFileLocation);
    }


    public function testStoreFile(): void
    {
        $tempFileLocation = sys_get_temp_dir() . '/phpunit-test1.txt';
        file_put_contents($tempFileLocation, 'this is the content');

        $handle = new File($tempFileLocation);

        $file = $this->storageManager->store(3, $handle);

        $this->assertStorageFileEquals($file);
        $this->assertEquals('text/plain', $file->getMimeType());

        unlink($tempFileLocation);
    }

    public function testCopy(): void
    {
        $file = $this->storageManager->store(1, 'this is the content', ['string_content' => true]);

        $dst = $this->storageManager->copy($file);

        $this->assertFileExists($dst);
        $this->assertEquals('this is the content', file_get_contents($dst));

        unlink($dst);

        // copy a 2nd time, just to make sure
        $dst = $this->storageManager->copy($file);

        $this->assertFileExists($dst);
        $this->assertEquals('this is the content', file_get_contents($dst));

        unlink($dst);
    }
















    private function assertStorageFileEquals(StorageFile $file): void
    {

        $tempFileLocation = self::storageDir() . '/' . $file->getKey();

//        $this->assertEquals(1, $file->getType());
        $this->assertFileExists($tempFileLocation);
        $this->assertEquals('this is the content', file_get_contents($tempFileLocation));
        $this->assertEquals(md5_file($tempFileLocation), $file->getContentHash());
        $this->assertEquals(19, $file->getSize());
    }

//    public function testMoveStorageFile()
//    {
//        $storage = $this->generateTmpStorage(sys_get_temp_dir() . '/test1src');
//
//        $file = new StorageFile('test.txt', $storage);
//        $file->setContent('here is my content');
//
//        $newStorage = $this->generateTmpStorage(sys_get_temp_dir() . '/test1dest');
//
//        $this->storageManager->moveStorageFile($file, $newStorage);
//
//        $this->assertFileExists(sys_get_temp_dir() . '/test1dest/test.txt');
//        $this->assertEquals('here is my content', file_get_contents(sys_get_temp_dir() . '/test1dest/test.txt'));
//        $this->assertFileNotExists(sys_get_temp_dir() . '/test1src/test.txt');
//
//        unlink(sys_get_temp_dir() . '/test1dest/test.txt');
//    }

//    public function testStoreFileFromLocalPath()
//    {
//        $tempFileLocation = sys_get_temp_dir() . '/phpunit-test1.txt';
//        file_put_contents($tempFileLocation, 'this is the content');
//
//        $storedFile = $this->storageManager->store(2, $tempFileLocation);
//
//        $this->assertEquals('this is the content', $storedFile->getContent());
//        $this->assertEquals(2, $storedFile->getType());
//        $this->assertEquals(md5_file($tempFileLocation), $storedFile->getContentHash());
//        $this->assertEquals(19, $storedFile->getSize());
//        $this->assertTrue($storedFile->isLocal());
//        $this->assertFileExists($tempFileLocation);
//        unlink($tempFileLocation);
//
//        $this->assertTrue($storedFile->delete());
//    }
//
//    public function testStoreFileFromLocalPathAndUnlink()
//    {
//        $tempFileLocation = sys_get_temp_dir() . '/phpunit-test2.bak.txt';
//        file_put_contents($tempFileLocation, 'this is the content');
//        $computedHash = md5('this is the content');
//
//        $storedFile = $this->storageManager->storeFileFromLocalPath(3, $tempFileLocation, null, true);
//
//        $this->assertEquals('this is the content', $storedFile->getContent());
//        $this->assertEquals(3, $storedFile->getType());
//        $this->assertEquals($computedHash, $storedFile->getContentHash());
//        $this->assertEquals(19, $storedFile->getSize());
//        $this->assertTrue($storedFile->isLocal());
//        $this->assertFileNotExists($tempFileLocation);
//        $this->assertTrue($storedFile->delete());
//    }
//
//    public function testCopyStoredFileToScratch()
//    {
//        $storage = $this->generateTmpStorage(sys_get_temp_dir() . '/testcopysrc');
//        $file = new StorageFile('test_phpunit_copy.txt', $this->storageManager->getFilesystemForStorage($storage), $storage);
//        $file->setContent('here is my content');
//
//        $newLocalFile = $this->storageManager->copyStorageFileToScratch($file);
//
//        $this->assertFileExists($newLocalFile);
//
//        if (file_exists($newLocalFile)) {
//            unlink($newLocalFile);
//        }
//    }
//
//    public function testStoreUploadedFile()
//    {
//        $tempFileLocation = sys_get_temp_dir() . '/phpunit-test3.txt';
//        file_put_contents($tempFileLocation, 'this is the content');
//        $uploadFile = new UploadedFile($tempFileLocation, 'phpunit-test3.txt');
//
//        $storedFile = $this->storageManager->storeUploadedFile(1, $uploadFile, null, false);
//
//        $this->assertEquals('this is the content', $storedFile->getContent());
//        $this->assertEquals(1, $storedFile->getType());
//        $this->assertEquals(19, $storedFile->getSize());
//        $this->assertTrue($storedFile->isLocal());
//        $this->assertFileExists($tempFileLocation);
//        unlink($tempFileLocation);
//
//        $this->assertTrue($storedFile->delete());
//    }
//
//    public function testStoreStream()
//    {
//        $filename = 'phpunit-test4.txt';
//        $tempFileLocation = sys_get_temp_dir() . '/' . $filename;
//        file_put_contents($tempFileLocation, 'this is the content');
//        $stream = stream_for(fopen($tempFileLocation, 'r'));
//
//        $storedFile = $this->storageManager->store(1, $stream, [
//            'originalFilename' => $filename,
//            'metadata' => [
//                'ContentLength' => filesize($tempFileLocation),
//                'ContentType' => 'text/plain'
//            ]
//        ]);
//
//        $this->assertEquals('this is the content', (string)$storedFile->getContent());
//        $this->assertEquals(1, $storedFile->getType());
//        $this->assertEquals(19, $storedFile->getSize());
//        $this->assertEquals(md5_file($tempFileLocation), $storedFile->getContentHash());
//        $this->assertTrue($storedFile->isLocal());
//        $this->assertFileExists($tempFileLocation);
//        unlink($tempFileLocation);
//
//        $this->assertTrue($storedFile->delete());
//    }
//
    /**
     * @param string $directory
     * @return Storage
     */
    private function generateTmpStorage($directory): Storage
    {
        static $id;

        if (!$id) {
            $id = 1;
        }

        $storage = new Storage();
        $storage->setAdapterOptions([
            'class' => LocalAdapterConfiguration::class,
            'directory' => $directory,
            'create' => true,
            'mode' => '0777',
            'http_host' => 'onp.dev'
        ]);
        $storage->setName('test');
        $this->setPropertyOnObject($storage, 'id', $id++);

        return $storage;
    }

    /**
     * Sets the given property to given value on Object in Test
     *
     * @param Storage $object Subject under test
     * @param string $name   Property name
     * @param mixed $value  Value
     * @throws \ReflectionException
     */
    protected function setPropertyOnObject($object, $name, $value): void
    {
        $property = new \ReflectionProperty($object, $name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * @return string
     */
    private static function storageDir(): string
    {
        static $dir;

        if ($dir === null) {
            $dir = sys_get_temp_dir() . '/' . uniqid('storage_');
        }

        return $dir;
    }

    protected function tearDown()
    {
        (new Filesystem())->remove(self::storageDir());
    }
}
