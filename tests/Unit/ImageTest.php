<?php

namespace Tests\Unit\Pontedilana\PhpWeasyPrint;

use PHPUnit\Framework\TestCase;
use Pontedilana\PhpWeasyPrint\Image;

/**
 * @covers \Pontedilana\PhpWeasyPrint\Image
 *
 * @deprecated 1.0.0 Image class is deprecated and will be removed
 */
class ImageTest extends TestCase
{
    public function testCreateInstance(): void
    {
        $testObject = new Image();
        $this->assertInstanceOf(Image::class, $testObject);
    }
}
