<?php
/**
 * This file is part of ONP.
 *
 * Copywrite (c) 2015 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */
namespace Opensoft\StorageBundle\Tests\Storage;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Opensoft\StorageBundle\Entity\Repository\StoragePolicyRepository;
use Opensoft\StorageBundle\Entity\Repository\StorageRepository;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Entity\StoragePolicy;
use Opensoft\StorageBundle\Storage\Adapter\LocalAdapterConfiguration;
use Opensoft\StorageBundle\Storage\GaufretteAdapterResolver;
use Opensoft\StorageBundle\Storage\StorageKeyGenerator;
use Opensoft\StorageBundle\Storage\StorageManager;
use Opensoft\StorageBundle\Storage\StorageUrlResolverInterface;
use Tests\TestCase;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\RouterInterface;

/**
 * Ensures the storage manager functions work properly
 *
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StorageManagerTest extends TestCase
{
    /**
     * @var StorageManager
     */
    private $storageManager;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ManagerRegistry
     */
    private $doctrine;

    protected function setUp()
    {
        $adapterResolver = new GaufretteAdapterResolver();
        $adapterResolver->addConfiguration(new LocalAdapterConfiguration(
            $this->createMock(RouterInterface::class),
            new Packages(new Package(new StaticVersionStrategy('v1')), [
                'unversioned' => new UrlPackage(['//onp.dev'], new EmptyVersionStrategy())
            ]),
            "/testserver.dev/app_file.php"
        ));

        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $this->storageManager = new StorageManager(
            $this->doctrine,
            $adapterResolver,
            $this->createMock(StorageUrlResolverInterface::class),
            new StorageKeyGenerator()
        );

        $this->setUpSaveExpectations();
    }

    public function testMoveStorageFile()
    {
        $storage = $this->generateTmpStorage(sys_get_temp_dir() . '/test1src');

        $file = new StorageFile('test.txt', $this->storageManager->getFilesystemForStorage($storage), $storage);
        $file->setContent('here is my content');

        $newStorage = $this->generateTmpStorage(sys_get_temp_dir() . '/test1dest');

        $this->storageManager->moveStorageFile($file, $newStorage);

        $this->assertFileExists(sys_get_temp_dir() . '/test1dest/test.txt');
        $this->assertEquals('here is my content', file_get_contents(sys_get_temp_dir() . '/test1dest/test.txt'));
        $this->assertFileNotExists(sys_get_temp_dir() . '/test1src/test.txt');

        unlink(sys_get_temp_dir() . '/test1dest/test.txt');
    }

    public function testStoreFileFromLocalPath()
    {
        $tempFileLocation = sys_get_temp_dir() . '/phpunit-test1.txt';
        file_put_contents($tempFileLocation, 'this is the content');

        $storedFile = $this->storageManager->storeFileFromLocalPath(StorageFile::TYPE_HI_RES, $tempFileLocation);

        $this->assertEquals('this is the content', $storedFile->getContent());
        $this->assertEquals(StorageFile::TYPE_HI_RES, $storedFile->getType());
        $this->assertEquals(md5_file($tempFileLocation), $storedFile->getContentHash());
        $this->assertEquals(19, $storedFile->getSize());
        $this->assertTrue($storedFile->isLocal());
        $this->assertFileExists($tempFileLocation);
        unlink($tempFileLocation);

        $this->assertTrue($storedFile->delete());
    }

    public function testStoreFileFromLocalPathAndUnlink()
    {
        $tempFileLocation = sys_get_temp_dir() . '/phpunit-test2.bak.txt';
        file_put_contents($tempFileLocation, 'this is the content');
        $computedHash = md5('this is the content');

        $storedFile = $this->storageManager->storeFileFromLocalPath(StorageFile::TYPE_HI_RES, $tempFileLocation, null, true);

        $this->assertEquals('this is the content', $storedFile->getContent());
        $this->assertEquals(StorageFile::TYPE_HI_RES, $storedFile->getType());
        $this->assertEquals($computedHash, $storedFile->getContentHash());
        $this->assertEquals(19, $storedFile->getSize());
        $this->assertTrue($storedFile->isLocal());
        $this->assertFileNotExists($tempFileLocation);
        $this->assertTrue($storedFile->delete());
    }

    public function testCopyStoredFileToScratch()
    {
        $storage = $this->generateTmpStorage(sys_get_temp_dir() . '/testcopysrc');
        $file = new StorageFile('test_phpunit_copy.txt', $this->storageManager->getFilesystemForStorage($storage), $storage);
        $file->setContent('here is my content');

        $newLocalFile = $this->storageManager->copyStorageFileToScratch($file);

        $this->assertFileExists($newLocalFile);

        if (file_exists($newLocalFile)) {
            unlink($newLocalFile);
        }
    }

    public function testStoreUploadedFile()
    {
        $tempFileLocation = sys_get_temp_dir() . '/phpunit-test3.txt';
        file_put_contents($tempFileLocation, 'this is the content');
        $uploadFile = new UploadedFile($tempFileLocation, 'phpunit-test3.txt');

        $storedFile = $this->storageManager->storeUploadedFile(StorageFile::TYPE_TAX_EXEMPT, $uploadFile, null, false);

        $this->assertEquals('this is the content', $storedFile->getContent());
        $this->assertEquals(StorageFile::TYPE_TAX_EXEMPT, $storedFile->getType());
        $this->assertEquals(19, $storedFile->getSize());
        $this->assertTrue($storedFile->isLocal());
        $this->assertFileExists($tempFileLocation);
        unlink($tempFileLocation);

        $this->assertTrue($storedFile->delete());
    }

    /**
     * @param string $directory
     * @return Storage
     */
    private function generateTmpStorage($directory)
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
            'mode' => '0777'
        ]);
        $storage->setName('test');
        $this->setPropertyOnObject($storage, 'slug', uniqid('tmp'));
        $this->setPropertyOnObject($storage, 'id', $id++);

        return $storage;
    }

    private function setUpSaveExpectations()
    {
        $storageRepository = $this->getMockBuilder(StorageRepository::class)->disableOriginalConstructor()->getMock();
        $storageRepository->expects($this->any())->method('findOneByActive')->willReturn($this->generateTmpStorage(sys_get_temp_dir() . '/tmpstorage'));

        $storagePolicyRepository = $this->getMockBuilder(StoragePolicyRepository::class)->disableOriginalConstructor()->getMock();
        $storagePolicyRepository->expects($this->any())->method('findOneByType')->willReturn(null);

        $this->doctrine->expects($this->any())->method('getRepository')->withConsecutive(
            [StoragePolicy::class],
            [Storage::class]
        )->willReturnOnConsecutiveCalls(
            $storagePolicyRepository,
            $storageRepository
        );

        $em = $this->getMockBuilder(EntityManager::class)->disableOriginalConstructor()->getMock();
        $this->doctrine->expects($this->any())->method('getManager')->willReturn($em);
    }
}
