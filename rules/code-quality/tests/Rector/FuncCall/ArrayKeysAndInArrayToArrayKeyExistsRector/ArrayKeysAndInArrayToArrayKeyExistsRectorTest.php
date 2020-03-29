<?php

declare(strict_types=1);

namespace Rector\CodeQuality\Tests\Rector\FuncCall\ArrayKeysAndInArrayToArrayKeyExistsRector;

use Iterator;
use Rector\CodeQuality\Rector\FuncCall\ArrayKeysAndInArrayToArrayKeyExistsRector;

final class ArrayKeysAndInArrayToArrayKeyExistsRectorTest extends AbstractRunnableTestCase
{
    /**
     * @dataProvider provideData()
     */
    public function test(string $file): void
    {
        $this->doTestFile($file);
        $this->assertOriginalAndFixedFileResultEquals($file);
    }

    public function provideData(): Iterator
    {
        return $this->yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    protected function getRectorClass(): string
    {
        return ArrayKeysAndInArrayToArrayKeyExistsRector::class;
    }
}
