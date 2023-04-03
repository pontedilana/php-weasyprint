<?php

namespace Pontedilana\PhpWeasyPrint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pontedilana\PhpWeasyPrint\Pdf;
use Pontedilana\PhpWeasyPrint\Tests\PdfSpy;

/**
 * @covers \Pontedilana\PhpWeasyPrint\Pdf
 */
class PdfTest extends TestCase
{
    public const SHELL_ARG_QUOTE_REGEX = '(?:"|\')'; // escapeshellarg produces double quotes on Windows, single quotes otherwise

    protected function setUp(): void
    {
        set_error_handler(
            static function ($errno, $errstr) {
                throw new \Exception($errstr, $errno);
            },
            \E_USER_ERROR
        );
    }

    protected function tearDown(): void
    {
        $directory = __DIR__ . '/i-dont-exist';

        if (\file_exists($directory)) {
            $iterator = new \RecursiveDirectoryIterator(
                $directory,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS
            );

            foreach ($iterator as $item) {
                \unlink((string)$item);
            }

            \rmdir($directory);
        }

        $htmlFiles = new \CallbackFilterIterator(
            new \DirectoryIterator(__DIR__),
            function ($filename) {
                return 1 === \preg_match('/\.html$/', $filename);
            }
        );

        foreach ($htmlFiles as $file) {
            \unlink($file->getPathname());
        }

        restore_error_handler();
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\Pdf::__construct
     */
    public function testCreateInstance(): void
    {
        $testObject = new Pdf();
        $this->assertInstanceOf(Pdf::class, $testObject);
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\Pdf::setTemporaryFolder
     */
    public function testThatSomethingUsingTmpFolder(): void
    {
        $q = self::SHELL_ARG_QUOTE_REGEX;
        $testObject = new PdfSpy();
        $testObject->setTemporaryFolder(__DIR__);

        $testObject->getOutputFromHtml('<html></html>', ['stylesheet' => 'html {font-size: 16px;}']);
        $this->assertMatchesRegularExpression('/emptyBinary --stylesheet ' . $q . '.*' . $q . ' ' . $q . '.*' . $q . ' ' . $q . '.*' . $q . '/', $testObject->getLastCommand());
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\Pdf::setTemporaryFolder
     */
    public function testThatSomethingUsingNonexistentTmpFolder(): void
    {
        $temporaryFolder = \sys_get_temp_dir() . '/i-dont-exist';

        $testObject = new PdfSpy();
        $testObject->setTemporaryFolder($temporaryFolder);

        $testObject->getOutputFromHtml('<html></html>', ['stylesheet' => 'html {font-size: 16px;}']);

        $this->assertDirectoryExists($temporaryFolder);
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\Pdf::createTemporaryFile
     */
    public function testRemovesLocalFilesOnError(): void
    {
        $pdf = new PdfSpy();
        $method = new \ReflectionMethod($pdf, 'createTemporaryFile');
        $method->setAccessible(true);
        $method->invoke($pdf, 'test', $pdf->getDefaultExtension());
        $this->assertCount(1, $pdf->temporaryFiles);
        $this->expectException(\RuntimeException::class);
        throw new \RuntimeException('Throw exception to cleanup files');
        $this->assertFileDoesNotExist(\reset($pdf->temporaryFiles));
    }

    /**
     * @dataProvider dataOptions
     */
    public function testOptions(array $options, string $expectedRegex): void
    {
        $testObject = new PdfSpy();
        $testObject->getOutputFromHtml('<html></html>', $options);
        // fwrite(\STDERR, print_r($testObject->getLastCommand() . "\n\n", true));
        $this->assertMatchesRegularExpression($expectedRegex, $testObject->getLastCommand());
    }

    public function dataOptions(): array
    {
        $q = self::SHELL_ARG_QUOTE_REGEX;

        return [
            '0 - no options' => [
                [],
                '/emptyBinary ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '1 - pass a single stylesheet URL' => [
                ['stylesheet' => 'https://google.com'],
                '/emptyBinary --stylesheet ' . $q . 'https:\/\/google\.com' . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '2 - pass a single stylesheet file' => [
                ['stylesheet' => __DIR__ . '/../Fixture/style1.css'],
                '/emptyBinary --stylesheet ' . $q . \preg_quote(__DIR__ . '/../Fixture/style1.css', '/') . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '3 - pass two stylesheet files' => [
                ['stylesheet' => [__DIR__ . '/../Fixture/style1.css', __DIR__ . '/../Fixture/style2.css']],
                '/emptyBinary --stylesheet ' . $q . \preg_quote(__DIR__ . '/../Fixture/style1.css', '/') . $q . ' '
                . '--stylesheet ' . $q . \preg_quote(__DIR__ . '/../Fixture/style2.css', '/') . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '4 - pass one stylesheet file and one inline css' => [
                ['stylesheet' => [__DIR__ . '/../Fixture/style1.css', 'html {font-size: 24px;}']],
                '/emptyBinary --stylesheet ' . $q . \preg_quote(__DIR__ . '/../Fixture/style1.css', '/') . $q . ' '
                . '--stylesheet ' . $q . '.*php_weasyprint.*\.css' . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '5 - save the given stylesheet CSS string into a temporary file and pass that filename' => [
                ['stylesheet' => 'html {font-size: 16px;}'],
                '/emptyBinary --stylesheet ' . $q . '.*\.css' . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '6 - save the content of the given attachment URL to a file and pass that filename' => [
                ['attachment' => 'https://www.google.com/favicon.ico'],
                '/emptyBinary --attachment ' . $q . '.*php_weasyprint.*\.temp' . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '7 - save the content of multiple attachments URL to files and pass those filenames' => [
                ['attachment' => ['https://www.google.com/favicon.ico', 'https://github.githubassets.com/favicons/favicon.svg']],
                '/emptyBinary --attachment ' . $q . '.*php_weasyprint.*\.temp' . $q . ' --attachment ' . $q . '.*php_weasyprint.*\.temp' . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],
        ];
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\Pdf::createTemporaryFile
     * @covers \Pontedilana\PhpWeasyPrint\Pdf::__destruct
     */
    public function testRemovesLocalFilesOnDestruct(): void
    {
        $pdf = new PdfSpy();
        $method = new \ReflectionMethod($pdf, 'createTemporaryFile');
        $method->setAccessible(true);
        $method->invoke($pdf, 'test', $pdf->getDefaultExtension());
        $this->assertCount(1, $pdf->temporaryFiles);
        $file = \reset($pdf->temporaryFiles);
        $this->assertIsNotBool($file);
        $this->assertFileExists($file);
        $pdf->__destruct();
        $this->assertFileDoesNotExist($file);
    }
}
