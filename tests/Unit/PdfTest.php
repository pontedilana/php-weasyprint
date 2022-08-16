<?php

namespace Tests\Unit\xmarcos\PhpWeasyPrint;

use CallbackFilterIterator;
use DirectoryIterator;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use ReflectionMethod;
use xmarcos\PhpWeasyPrint\Pdf;

/**
 * @covers \xmarcos\PhpWeasyPrint\Pdf
 */
class PdfTest extends TestCase
{
    const SHELL_ARG_QUOTE_REGEX = '(?:"|\')'; // escapeshellarg produces double quotes on Windows, single quotes otherwise

    protected function tearDown()
    {
        $directory = __DIR__ . '/i-dont-exist';

        if (\file_exists($directory)) {
            $iterator = new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
            );

            foreach ($iterator as $item) {
                \unlink((string)$item);
            }

            \rmdir($directory);
        }

        $htmlFiles = new CallbackFilterIterator(
            new DirectoryIterator(__DIR__),
            function ($filename) {
                return 1 === \preg_match('/\.html$/', $filename);
            }
        );

        foreach ($htmlFiles as $file) {
            \unlink($file->getPathname());
        }
    }

    /**
     * @covers \xmarcos\PhpWeasyPrint\Pdf::__construct
     */
    public function testCreateInstance()
    {
        $testObject = new Pdf();
        $this->assertInstanceOf(Pdf::class, $testObject);
    }

    /**
     * @covers \xmarcos\PhpWeasyPrint\Pdf::setTemporaryFolder
     */
    public function testThatSomethingUsingTmpFolder()
    {
        $q = self::SHELL_ARG_QUOTE_REGEX;
        $testObject = new PdfSpy();
        $testObject->setTemporaryFolder(__DIR__);

        $testObject->getOutputFromHtml('<html></html>', ['stylesheet' => 'html {font-size: 16px;}']);
        $this->assertRegExp('/emptyBinary --stylesheet ' . $q . '.*' . $q . ' ' . $q . '.*' . $q . ' ' . $q . '.*' . $q . '/', $testObject->getLastCommand());
    }

    /**
     * @covers \xmarcos\PhpWeasyPrint\Pdf::setTemporaryFolder
     */
    public function testThatSomethingUsingNonexistentTmpFolder()
    {
        $temporaryFolder = \sys_get_temp_dir() . '/i-dont-exist';

        $testObject = new PdfSpy();
        $testObject->setTemporaryFolder($temporaryFolder);

        $testObject->getOutputFromHtml('<html></html>', ['stylesheet' => 'html {font-size: 16px;}']);

        $this->assertFileExists($temporaryFolder);
    }

    /**
     * @covers \xmarcos\PhpWeasyPrint\Pdf::createTemporaryFile
     */
    public function testRemovesLocalFilesOnError()
    {
        $pdf = new PdfSpy();
        $method = new ReflectionMethod($pdf, 'createTemporaryFile');
        $method->setAccessible(true);
        $method->invoke($pdf, 'test', $pdf->getDefaultExtension());
        $this->assertCount(1, $pdf->temporaryFiles);
        $this->expectException('PHPUnit_Framework_Error');
        \trigger_error('test error', \E_USER_ERROR);
        $this->assertFileNotExists(\reset($pdf->temporaryFiles));
    }

    /**
     * @dataProvider dataOptions
     */
    public function testOptions($options, $expectedRegex)
    {
        $testObject = new PdfSpy();
        $testObject->getOutputFromHtml('<html></html>', $options);
        $this->assertRegExp($expectedRegex, $testObject->getLastCommand());
    }

    public function dataOptions()
    {
        $q = self::SHELL_ARG_QUOTE_REGEX;

        return [
            // no options
            'no options' => [
                [],
                '/emptyBinary ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],
            // just pass a single stylesheet URL
            'just pass a single stylesheet URL' => [
                ['stylesheet' => 'https://google.com'],
                '/emptyBinary --stylesheet ' . $q . 'https:\/\/google\.com' . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],
            // just pass the given footer file
            'just pass a single stylesheet file' => [
                ['stylesheet' => __FILE__],
                '/emptyBinary --stylesheet ' . $q . \preg_quote(__FILE__, '/') . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],
            // save the given stylesheet CSS string into a temporary file and pass that filename
            'save the given stylesheet CSS string into a temporary file and pass that filename' => [
                ['stylesheet' => 'html {font-size: 16px;}'],
                '/emptyBinary --stylesheet ' . $q . '.*\.css' . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],
            // save the content of the given attachment URL to a file and pass that filename
            'save the content of the given attachment URL to a file and pass that filename' => [
                ['attachment' => 'https://www.google.com/favicon.ico'],
                '/emptyBinary --attachment ' . $q . '.*' . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],
        ];
    }

    /**
     * @covers \xmarcos\PhpWeasyPrint\Pdf::createTemporaryFile
     * @covers \xmarcos\PhpWeasyPrint\Pdf::__destruct
     */
    public function testRemovesLocalFilesOnDestruct()
    {
        $pdf = new PdfSpy();
        $method = new ReflectionMethod($pdf, 'createTemporaryFile');
        $method->setAccessible(true);
        $method->invoke($pdf, 'test', $pdf->getDefaultExtension());
        $this->assertCount(1, $pdf->temporaryFiles);
        $this->assertFileExists(\reset($pdf->temporaryFiles));
        $pdf->__destruct();
        $this->assertFileNotExists(\reset($pdf->temporaryFiles));
    }
}

class PdfSpy extends Pdf
{
    /**
     * @var string
     */
    private $lastCommand;

    public function __construct()
    {
        parent::__construct('emptyBinary');
    }

    public function getLastCommand()
    {
        return $this->lastCommand;
    }

    public function getOutput($input, $options = [])
    {
        $filename = $this->createTemporaryFile(null, $this->getDefaultExtension());
        $this->generate($input, $filename, $options, true);

        return 'output';
    }

    protected function executeCommand($command)
    {
        $this->lastCommand = $command;

        return [0, 'output', 'errorOutput'];
    }

    protected function checkOutput($output, $command)
    {
        //let's say everything went right
    }
}
