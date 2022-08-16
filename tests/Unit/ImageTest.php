<?php

namespace Tests\Unit\xmarcos\PhpWeasyPrint;

use PHPUnit\Framework\TestCase;
use xmarcos\PhpWeasyPrint\Image;

/**
 * @covers \xmarcos\PhpWeasyPrint\Image
 */
class ImageTest extends TestCase
{
    public function testCreateInstance()
    {
        $testObject = new Image();
        $this->assertInstanceOf(Image::class, $testObject);
    }
}
