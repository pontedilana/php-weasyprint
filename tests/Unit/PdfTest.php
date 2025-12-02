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
            function($filename) {
                return 1 === \preg_match('/\.html$/', $filename);
            }
        );

        foreach ($htmlFiles as $file) {
            \unlink($file->getPathname());
        }
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\Pdf::__construct
     */
    public function testCreateInstance(): void
    {
        $testObject = new Pdf();
        /** @phpstan-ignore-next-line */
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
        $this->assertMatchesRegularExpression('/emptyBinary --stylesheet ' . $q . '.*' . $q . ' --timeout \d* ' . $q . '.*' . $q . ' ' . $q . '.*' . $q . '/', $testObject->getLastCommand());
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
        (\PHP_VERSION_ID < 80100) && $method->setAccessible(true);
        $method->invoke($pdf, 'test', $pdf->getDefaultExtension());
        $this->assertCount(1, $pdf->temporaryFiles);
        $this->expectException(\RuntimeException::class);
        throw new \RuntimeException('Throw exception to cleanup files');
        /** @phpstan-ignore-next-line */
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
                '/emptyBinary --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '1 - pass a single stylesheet URL' => [
                ['stylesheet' => 'https://google.com'],
                '/emptyBinary --stylesheet ' . $q . 'https:\/\/google\.com' . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '2 - pass a single stylesheet file' => [
                ['stylesheet' => __DIR__ . '/../Fixture/style1.css'],
                '/emptyBinary --stylesheet ' . $q . \preg_quote(__DIR__ . '/../Fixture/style1.css', '/') . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '3 - pass two stylesheet files' => [
                ['stylesheet' => [__DIR__ . '/../Fixture/style1.css', __DIR__ . '/../Fixture/style2.css']],
                '/emptyBinary --stylesheet ' . $q . \preg_quote(__DIR__ . '/../Fixture/style1.css', '/') . $q . ' '
                . '--stylesheet ' . $q . \preg_quote(__DIR__ . '/../Fixture/style2.css', '/') . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '4 - pass one stylesheet file and one inline css' => [
                ['stylesheet' => [__DIR__ . '/../Fixture/style1.css', 'html {font-size: 24px;}']],
                '/emptyBinary --stylesheet ' . $q . \preg_quote(__DIR__ . '/../Fixture/style1.css', '/') . $q . ' '
                . '--stylesheet ' . $q . '.*php_weasyprint.*\.css' . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '5 - save the given stylesheet CSS string into a temporary file and pass that filename' => [
                ['stylesheet' => 'html {font-size: 16px;}'],
                '/emptyBinary --stylesheet ' . $q . '.*\.css' . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '6 - save the content of the given attachment URL to a file and pass that filename' => [
                ['attachment' => 'https://www.google.com/favicon.ico'],
                '/emptyBinary --attachment ' . $q . '.*php_weasyprint.*\.temp' . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '7 - save the content of multiple attachments URL to files and pass those filenames' => [
                ['attachment' => ['https://www.google.com/favicon.ico', 'https://github.githubassets.com/favicons/favicon.svg']],
                '/emptyBinary --attachment ' . $q . '.*php_weasyprint.*\.temp' . $q . ' --attachment ' . $q . '.*php_weasyprint.*\.temp' . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '8 - set integer, string, and boolean options' => [
                ['pdf-variant' => 'pdf/ua-1', 'dpi' => 300, 'timeout' => 60, 'srgb' => true, 'resolution' => 100],
                "/emptyBinary --pdf-variant 'pdf\/ua-1' --dpi 300 --timeout 60 --srgb --resolution 100 " . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],
        ];
    }

    public function testSetTimeoutConfiguresBothProcessAndWeasyPrintTimeout(): void
    {
        $testObject = new PdfSpy();
        $testObject->setTimeout(30);
        $testObject->getOutputFromHtml('<html></html>');

        // Verify that --timeout 30 is in the command
        $this->assertMatchesRegularExpression('/--timeout 30/', $testObject->getLastCommand());
    }

    public function testDisableTimeout(): void
    {
        $testObject = new PdfSpy();
        $testObject->disableTimeout();
        $testObject->getOutputFromHtml('<html></html>');

        $q = self::SHELL_ARG_QUOTE_REGEX;
        $expectedRegex = '/emptyBinary ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/';

        $this->assertMatchesRegularExpression($expectedRegex, $testObject->getLastCommand());

        $testObject2 = new PdfSpy();
        $testObject2->setOption('timeout', null);
        $testObject2->getOutputFromHtml('<html></html>');

        $this->assertMatchesRegularExpression($expectedRegex, $testObject2->getLastCommand());
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\Pdf::createTemporaryFile
     * @covers \Pontedilana\PhpWeasyPrint\Pdf::__destruct
     */
    public function testRemovesLocalFilesOnDestruct(): void
    {
        $pdf = new PdfSpy();
        $method = new \ReflectionMethod($pdf, 'createTemporaryFile');
        (\PHP_VERSION_ID < 80100) && $method->setAccessible(true);
        $method->invoke($pdf, 'test', $pdf->getDefaultExtension());
        $this->assertCount(1, $pdf->temporaryFiles);
        $file = \reset($pdf->temporaryFiles);
        $this->assertIsNotBool($file);
        $this->assertFileExists($file);
        $pdf->__destruct();
        $this->assertFileDoesNotExist($file);
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\Pdf::buildCommand
     */
    public function testBuildCommandHandlesIntegerOptions(): void
    {
        $pdf = new PdfSpy();
        $method = new \ReflectionMethod($pdf, 'buildCommand');
        (\PHP_VERSION_ID < 80100) && $method->setAccessible(true);

        $command = $method->invoke($pdf, 'weasyprint', 'input.html', 'output.pdf', [
            'dpi' => 300,
            'jpeg-quality' => 85,
            'timeout' => 60,
            'resolution' => 100,
        ]);

        $this->assertStringContainsString('--dpi 300', $command);
        $this->assertStringContainsString('--jpeg-quality 85', $command);
        $this->assertStringContainsString('--timeout 60', $command);
        $this->assertStringContainsString('--resolution 100', $command);
    }

    /**
     * @covers \Pontedilana\PhpWeasyPrint\Pdf::buildCommand
     */
    public function testBuildCommandHandlesDeprecatedFormatOption(): void
    {
        $pdf = new PdfSpy();
        $method = new \ReflectionMethod($pdf, 'buildCommand');
        (\PHP_VERSION_ID < 80100) && $method->setAccessible(true);

        $command = $method->invoke($pdf, 'weasyprint', 'input.html', 'output.pdf', [
            'format' => 'pdf',
        ]);

        // The format option should be passed without escaping (deprecated option)
        $this->assertStringContainsString('--format pdf', $command);
    }
}
