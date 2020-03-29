<?php

namespace Rector\CodeQuality\Tests\Rector\FuncCall\ArrayKeysAndInArrayToIssetRector\Fixture;

use Rector\CodeQuality\Tests\Rector\FuncCall\ArrayKeysAndInArrayToArrayKeyExistsRector\AbstractRunnable;

class SkipOver
{
    public function test($values)
    {
        $keys = array_keys($values);
    }

    public function again($packageName, $values, $keys)
    {
        return in_array($packageName, $keys, true);
    }
}
