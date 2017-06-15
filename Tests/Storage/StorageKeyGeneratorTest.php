<?php

namespace Opensoft\StorageBundle\Tests\Storage;

use Opensoft\StorageBundle\Storage\StorageKeyGenerator;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StorageKeyGeneratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getGenerateCases
     */
    public function testGenerate($base)
    {
        $keyGenerator = new StorageKeyGenerator();

        $key = $keyGenerator->generate($base);

        $this->assertContains($base, $key);

        // ensure our key/directory structure is 3 levels deep
        $this->assertTrue(3 == substr_count($key, '/'));
    }

    /**
     * @return array
     */
    public function getGenerateCases()
    {
        return [
            ['thing1.tif'],
            ['some_longer_key.pdf'],
        ];
    }
}
