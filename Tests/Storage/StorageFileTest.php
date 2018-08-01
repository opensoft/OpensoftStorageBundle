<?php

namespace Opensoft\StorageBundle\Tests\Storage;


use Gaufrette\Filesystem;
use function GuzzleHttp\Psr7\stream_for;
use GuzzleHttp\Psr7\StreamWrapper;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Storage\Gaufrette\Adapter\Local;

class StorageFileTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var StorageFile
     */
    private $file;

    /** @var string */
    private $directory;
    /** @var string */
    private $key;

    protected function setUp()
    {
        $this->directory = sys_get_temp_dir() . '/test';
        $this->key = 'test.txt';

        $filesystem = new Filesystem(new Local($this->directory, true));

        $this->file = new StorageFile($this->key, $filesystem, new Storage());
    }

    public function testContentCanBeSetAsString()
    {
        $expected = 'here is my content';
        $this->file->setContent($expected);

        $this->assertFileContent($expected);
    }

    public function testContentCanBeSetAsStream()
    {
        $expected = 'here is my content';
        $this->file->setContent(stream_for($expected));

        $this->assertFileContent($expected);
    }

    public function testContentCanBeSetAsResource()
    {
        $expected = 'here is my content';
        $this->file->setContent(StreamWrapper::getResource(stream_for($expected)));

        $this->assertFileContent($expected);
    }

    private function assertFileContent($expected)
    {
        $this->assertEquals($expected, (string)$this->file->getContent());
        $this->assertEquals(md5($expected), $this->file->getContentHash());

        $this->assertEquals($expected, file_get_contents($this->directory . '/' . $this->key));
    }
}