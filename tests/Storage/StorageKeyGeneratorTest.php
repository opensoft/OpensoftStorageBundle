<?php

namespace Opensoft\StorageBundle\Tests\Storage;

use Opensoft\StorageBundle\Storage\StorageKeyGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
final class StorageKeyGeneratorTest extends TestCase
{
    /**
     * @param string $base
     * @dataProvider getGenerateCases
     */
    public function testGenerate(string $base): void
    {
        $keyGenerator = new StorageKeyGenerator();

        $key = $keyGenerator->generate($base);

        $this->assertContains($base, $key);

        // ensure our key/directory structure is 3 levels deep
        $this->assertEquals(3, substr_count($key, '/'));
    }

    /**
     * @return array
     */
    public function getGenerateCases(): array
    {
        return [
            ['thing1.tif'],
            ['some_longer_key.pdf'],
        ];
    }
}
