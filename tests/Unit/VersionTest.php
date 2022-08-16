<?php

namespace Tests\Unit\xmarcos\PhpWeasyPrint;

use PHPUnit\Framework\TestCase;
use xmarcos\PhpWeasyPrint\Version;

class VersionTest extends TestCase
{
    /**
     * @dataProvider dataVersions
     * @covers \xmarcos\PhpWeasyPrint\Version::parseOutput
     * @covers \xmarcos\PhpWeasyPrint\Version::__construct
     */
    public function testGetVersion($versionString, $expected)
    {
        $versionParser = new Version();
        $output = $versionParser->parseOutput($versionString);
        $this->assertTrue(is_array($output));
        $this->assertEquals($expected, $output);
    }

    public function dataVersions()
    {
        return [
            '53.0b2' => [
                'WeasyPrint version 53.0b2',
                [
                    'fullversion' => '53.0b2',
                    'major' => '53',
                    'minor' => '0b2',
                ],
            ],
            '52' => [
                'WeasyPrint version 52',
                [
                    'fullversion' => '52',
                    'major' => '52',
                    'minor' => '0',
                ],
            ],
            '52.5' => [
                'WeasyPrint version 52.5',
                [
                    'fullversion' => '52.5',
                    'major' => '52',
                    'minor' => '5',
                ],
            ],
            '49' => [
                'WeasyPrint version 49',
                [
                    'fullversion' => '49',
                    'major' => '49',
                    'minor' => '0',
                ],
            ],
        ];
    }
}
