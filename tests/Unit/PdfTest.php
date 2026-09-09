<?php

namespace Pontedilana\PhpWeasyPrint\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pontedilana\PhpWeasyPrint\Enum\MediaType;
use Pontedilana\PhpWeasyPrint\Enum\PdfVariant;
use Pontedilana\PhpWeasyPrint\Enum\PdfVersion;
use Pontedilana\PhpWeasyPrint\Pdf;
use Pontedilana\PhpWeasyPrint\Tests\PdfSpy;
use Pontedilana\PhpWeasyPrint\WeasyPrintOptionValues;

#[CoversClass(Pdf::class)]
#[CoversMethod(\Pontedilana\PhpWeasyPrint\AbstractGenerator::class, 'buildCommandArray')]
#[CoversMethod(\Pontedilana\PhpWeasyPrint\AbstractGenerator::class, 'setOption')]
#[CoversClass(WeasyPrintOptionValues::class)]
#[CoversMethod(\Pontedilana\PhpWeasyPrint\AbstractGenerator::class, 'mergeOptions')]
#[CoversMethod(\Pontedilana\PhpWeasyPrint\AbstractGenerator::class, 'normalizeOptionValue')]
class PdfTest extends TestCase
{
    // The executed command is now an argument array joined with spaces (no shell escaping),
    // so quotes are optional: this keeps the regexes valid whether or not a value is quoted.
    public const SHELL_ARG_QUOTE_REGEX = '(?:"|\')?';

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
            static function($filename) {
                return 1 === \preg_match('/\.html$/', $filename);
            }
        );

        foreach ($htmlFiles as $file) {
            \unlink($file->getPathname());
        }
    }

    public function testCreateInstance(): void
    {
        $testObject = new Pdf();
        /** @phpstan-ignore-next-line */
        $this->assertInstanceOf(Pdf::class, $testObject);
    }

    public function testThatSomethingUsingTmpFolder(): void
    {
        $q = self::SHELL_ARG_QUOTE_REGEX;
        $testObject = new PdfSpy();
        $testObject->setTemporaryFolder(__DIR__);

        $testObject->getOutputFromHtml('<html></html>', ['stylesheet' => 'html {font-size: 16px;}']);
        $this->assertMatchesRegularExpression('/' . $q . 'emptyBinary' . $q . ' --stylesheet ' . $q . '.*' . $q . ' --timeout \d* ' . $q . '.*' . $q . ' ' . $q . '.*' . $q . '/', $testObject->getLastCommand());
    }

    public function testThatSomethingUsingNonexistentTmpFolder(): void
    {
        $temporaryFolder = \sys_get_temp_dir() . '/i-dont-exist';

        $testObject = new PdfSpy();
        $testObject->setTemporaryFolder($temporaryFolder);

        $testObject->getOutputFromHtml('<html></html>', ['stylesheet' => 'html {font-size: 16px;}']);

        $this->assertDirectoryExists($temporaryFolder);
    }

    public function testRemovesLocalFilesOnError(): void
    {
        $pdf = new PdfSpy();
        $method = new \ReflectionMethod($pdf, 'createTemporaryFile');
        $method->invoke($pdf, 'test', $pdf->getDefaultExtension());
        $this->assertCount(1, $pdf->temporaryFiles);
        $this->expectException(\RuntimeException::class);
        throw new \RuntimeException('Throw exception to cleanup files');
        /** @phpstan-ignore-next-line */
        $this->assertFileDoesNotExist(\reset($pdf->temporaryFiles));
    }

    #[DataProvider('dataOptions')]
    public function testOptions(array $options, string $expectedRegex): void
    {
        $testObject = new PdfSpy();
        $testObject->getOutputFromHtml('<html></html>', $options);
        // fwrite(\STDERR, print_r($testObject->getLastCommand() . "\n\n", true));
        $this->assertMatchesRegularExpression($expectedRegex, $testObject->getLastCommand());
    }

    public function testAttachmentUrlsPreserveDownloadedContents(): void
    {
        $pdf = new PdfSpy();
        $pdf->getOutputFromHtml('<html></html>', ['attachment' => [
            'https://example.test/attachment-one.txt',
            'https://example.test/attachment-two.txt',
        ]]);

        $attachments = \array_values(\array_filter(
            $pdf->temporaryFiles,
            static fn(string $path): bool => 'temp' === \pathinfo($path, \PATHINFO_EXTENSION)
        ));
        $this->assertCount(2, $attachments);
        $this->assertSame(\file_get_contents(__DIR__ . '/../Fixture/attachment-one.txt'), \file_get_contents($attachments[0]));
        $this->assertSame(\file_get_contents(__DIR__ . '/../Fixture/attachment-two.txt'), \file_get_contents($attachments[1]));
    }

    #[DataProvider('disabledOptionValues')]
    public function testPerCallOptionsCanDisableInstanceOptions(bool|array|null $disabled): void
    {
        $pdf = new PdfSpy();
        $pdf->setOptions([
            'timeout' => 30,
            'stylesheet' => 'h1 { color: navy; }',
            'attachment' => 'Attachment content',
            'presentational-hints' => true,
        ]);
        $instanceOptions = $pdf->getOptions();
        $pdf->getOutputFromHtml('<h1>Test</h1>', [
            'timeout' => $disabled,
            'stylesheet' => $disabled,
            'attachment' => $disabled,
            'presentational-hints' => $disabled,
        ]);

        foreach (['timeout', 'stylesheet', 'attachment', 'presentational-hints'] as $option) {
            $this->assertStringNotContainsString('--' . $option, $pdf->getLastCommand());
        }
        $this->assertSame($instanceOptions, $pdf->getOptions());

        $pdf->getOutputFromHtml('<h1>Next call</h1>');
        foreach (['timeout', 'stylesheet', 'attachment', 'presentational-hints'] as $option) {
            $this->assertStringContainsString('--' . $option, $pdf->getLastCommand());
        }
    }

    public static function disabledOptionValues(): array
    {
        return ['null' => [null], 'false' => [false], 'empty array' => [[]]];
    }

    public static function dataOptions(): array
    {
        $q = self::SHELL_ARG_QUOTE_REGEX;

        return [
            '0 - no options' => [
                [],
                '/' . $q . 'emptyBinary' . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '1 - pass a single stylesheet URL' => [
                ['stylesheet' => 'https://google.com'],
                '/' . $q . 'emptyBinary' . $q . ' --stylesheet ' . $q . 'https:\/\/google\.com' . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '2 - pass a single stylesheet file' => [
                ['stylesheet' => __DIR__ . '/../Fixture/style1.css'],
                '/' . $q . 'emptyBinary' . $q . ' --stylesheet ' . $q . \preg_quote(__DIR__ . '/../Fixture/style1.css', '/') . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '3 - pass two stylesheet files' => [
                ['stylesheet' => [__DIR__ . '/../Fixture/style1.css', __DIR__ . '/../Fixture/style2.css']],
                '/' . $q . 'emptyBinary' . $q . ' --stylesheet ' . $q . \preg_quote(__DIR__ . '/../Fixture/style1.css', '/') . $q . ' '
                . '--stylesheet ' . $q . \preg_quote(__DIR__ . '/../Fixture/style2.css', '/') . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '4 - pass one stylesheet file and one inline css' => [
                ['stylesheet' => [__DIR__ . '/../Fixture/style1.css', 'html {font-size: 24px;}']],
                '/' . $q . 'emptyBinary' . $q . ' --stylesheet ' . $q . \preg_quote(__DIR__ . '/../Fixture/style1.css', '/') . $q . ' '
                . '--stylesheet ' . $q . '.*php_weasyprint.*\.css' . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '5 - save the given stylesheet CSS string into a temporary file and pass that filename' => [
                ['stylesheet' => 'html {font-size: 16px;}'],
                '/' . $q . 'emptyBinary' . $q . ' --stylesheet ' . $q . '.*\.css' . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '6 - save the content of the given attachment URL to a file and pass that filename' => [
                ['attachment' => 'https://example.test/attachment-one.txt'],
                '/' . $q . 'emptyBinary' . $q . ' --attachment ' . $q . '.*php_weasyprint.*\.temp' . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '7 - save the content of multiple attachment URLs to files and pass those filenames' => [
                ['attachment' => ['https://example.test/attachment-one.txt', 'https://example.test/attachment-two.txt']],
                '/' . $q . 'emptyBinary' . $q . ' --attachment ' . $q . '.*php_weasyprint.*\.temp' . $q . ' --attachment ' . $q . '.*php_weasyprint.*\.temp' . $q . ' --timeout \d* ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],

            '8 - set integer, string, and boolean options' => [
                ['pdf-variant' => 'pdf/ua-1', 'dpi' => 300, 'timeout' => 60, 'srgb' => true],
                '/' . $q . 'emptyBinary' . $q . ' --pdf-variant ' . $q . 'pdf\/ua-1' . $q . ' --dpi 300 --timeout 60 --srgb ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],
            '9 - new boolean options' => [
                ['no-http-redirects' => true, 'fail-on-http-errors' => true, 'verbose' => true, 'debug' => true, 'info' => true, 'version' => true],
                '/' . $q . 'emptyBinary' . $q . ' --timeout \d* --info --verbose --debug --version --no-http-redirects --fail-on-http-errors ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
            ],
            '10 - output intent option' => [
                ['output-intent' => 'device-cmyk'],
                '/' . $q . 'emptyBinary' . $q . ' --timeout \d* --output-intent ' . $q . 'device-cmyk' . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/',
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
        $expectedRegex = '/' . $q . 'emptyBinary' . $q . ' ' . $q . '.*\.html' . $q . ' ' . $q . '.*\.pdf' . $q . '/';

        $this->assertMatchesRegularExpression($expectedRegex, $testObject->getLastCommand());

        $testObject2 = new PdfSpy();
        $testObject2->setOption('timeout', null);
        $testObject2->getOutputFromHtml('<html></html>');

        $this->assertMatchesRegularExpression($expectedRegex, $testObject2->getLastCommand());
    }

    public function testRemovesLocalFilesOnDestruct(): void
    {
        $pdf = new PdfSpy();
        $method = new \ReflectionMethod($pdf, 'createTemporaryFile');
        $method->invoke($pdf, 'test', $pdf->getDefaultExtension());
        $this->assertCount(1, $pdf->temporaryFiles);
        $file = \reset($pdf->temporaryFiles);
        $this->assertIsNotBool($file);
        $this->assertFileExists($file);
        $pdf->__destruct();
        $this->assertFileDoesNotExist($file);
    }

    public function testBuildCommandHandlesIntegerOptions(): void
    {
        $pdf = new PdfSpy();
        $method = new \ReflectionMethod($pdf, 'buildCommand');

        $command = $method->invoke($pdf, \PHP_BINARY, 'input.html', 'output.pdf', [
            'dpi' => 300,
            'jpeg-quality' => 85,
            'timeout' => 60,
        ]);

        $this->assertStringContainsString('--dpi 300', $command);
        $this->assertStringContainsString('--jpeg-quality 85', $command);
        $this->assertStringContainsString('--timeout 60', $command);
    }

    public function testNumericOptionsMatchTheDisplayedCommand(): void
    {
        $pdf = new PdfSpy();
        $pdf->setOptions(['dpi' => '300.0', 'jpeg-quality' => '85.9', 'timeout' => '60.0']);
        $method = new \ReflectionMethod($pdf, 'buildCommandArray');

        $this->assertSame(
            ['emptyBinary', '--dpi', '300', '--jpeg-quality', '85', '--timeout', '60', 'input.html', 'output.pdf'],
            $method->invoke($pdf, 'emptyBinary', 'input.html', 'output.pdf', $pdf->getOptions())
        );
        $this->assertSame(
            "'emptyBinary' --dpi 300 --jpeg-quality 85 --timeout 60 'input.html' 'output.pdf'",
            $pdf->getCommand('input.html', 'output.pdf')
        );
    }

    public function testSetOptionRejectsValueOutsideWhitelist(): void
    {
        $pdf = new PdfSpy();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The value 'pdf/a-3b; touch /tmp/pwn' is not allowed for option 'pdf-variant'.");

        $pdf->setOption('pdf-variant', 'pdf/a-3b; touch /tmp/pwn');
    }

    public function testPerCallOptionRejectsValueOutsideWhitelist(): void
    {
        $pdf = new PdfSpy();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The value 'pdf/foo' is not allowed for option 'pdf-variant'.");

        $pdf->getOutputFromHtml('<html></html>', ['pdf-variant' => 'pdf/foo']);
    }

    public function testWhitelistedValuesAreAccepted(): void
    {
        $pdf = new PdfSpy();
        $pdf->setOption('pdf-variant', 'pdf/a-3b');
        $pdf->getOutputFromHtml('<html></html>');

        $q = self::SHELL_ARG_QUOTE_REGEX;
        $this->assertMatchesRegularExpression('/--pdf-variant ' . $q . 'pdf\/a-3b' . $q . '/', $pdf->getLastCommand());
    }

    public function testSetOptionAcceptsBackedEnumValues(): void
    {
        $pdf = new PdfSpy();
        $pdf->setOption('pdf-variant', PdfVariant::PdfA3b);
        $pdf->setOption('media-type', MediaType::Screen);
        $pdf->setOption('pdf-version', PdfVersion::Pdf17);
        $pdf->getOutputFromHtml('<html></html>');

        $q = self::SHELL_ARG_QUOTE_REGEX;
        $this->assertMatchesRegularExpression('/--pdf-variant ' . $q . 'pdf\/a-3b' . $q . '/', $pdf->getLastCommand());
        $this->assertMatchesRegularExpression('/--media-type ' . $q . 'screen' . $q . '/', $pdf->getLastCommand());
        $this->assertMatchesRegularExpression('/--pdf-version ' . $q . '1\.7' . $q . '/', $pdf->getLastCommand());
        $this->assertSame('pdf/a-3b', $pdf->getOptions()['pdf-variant']);
        $this->assertSame('1.7', $pdf->getOptions()['pdf-version']);
    }

    public function testPerCallOptionAcceptsBackedEnum(): void
    {
        $pdf = new PdfSpy();
        $pdf->getOutputFromHtml('<html></html>', ['pdf-variant' => PdfVariant::PdfUa1]);

        $q = self::SHELL_ARG_QUOTE_REGEX;
        $this->assertMatchesRegularExpression('/--pdf-variant ' . $q . 'pdf\/ua-1' . $q . '/', $pdf->getLastCommand());
    }

    public function testConstrainedOptionValidatesEachArrayElement(): void
    {
        $pdf = new PdfSpy();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The value 'pdf/bogus' is not allowed for option 'pdf-variant'.");

        $pdf->setOption('pdf-variant', ['pdf/a-3b', 'pdf/bogus']);
    }

    public function testUnconstrainedOptionsAcceptAnyValue(): void
    {
        $this->assertTrue(WeasyPrintOptionValues::isAllowed('encoding', 'anything-goes'));
        $this->assertFalse(WeasyPrintOptionValues::isConstrained('encoding'));
        $this->assertTrue(WeasyPrintOptionValues::isConstrained('pdf-variant'));
    }

    public function testBuildCommandThrowsOnNonExecutableBinary(): void
    {
        $maliciousBinary = 'weasyprint; touch /tmp/pwn; #';
        $pdf = new Pdf($maliciousBinary);
        $method = new \ReflectionMethod($pdf, 'buildCommand');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\sprintf("The binary '%s' is not executable.", $maliciousBinary));

        $method->invoke($pdf, $maliciousBinary, 'input.html', 'output.pdf', []);
    }

    public function testIsOptionUrlOnlyAllowsConfiguredSchemes(): void
    {
        $pdf = new PdfSpy();
        $method = new \ReflectionMethod($pdf, 'isOptionUrl');

        $this->assertTrue($method->invoke($pdf, 'https://example.com/style.css'));
        $this->assertTrue($method->invoke($pdf, 'http://example.com/style.css'));
        $this->assertFalse($method->invoke($pdf, 'file:///etc/passwd'));
        $this->assertFalse($method->invoke($pdf, 'php://filter/convert.base64-encode/resource=/etc/passwd'));
        $this->assertFalse($method->invoke($pdf, 'ftp://example.com/secret'));
        $this->assertFalse($method->invoke($pdf, '/plain/local/path'));
    }

    public function testAllowedSchemesCanBeConfiguredViaConstructor(): void
    {
        $pdf = new Pdf('weasyprint', [], null, ['http', 'https', 'file']);
        $method = new \ReflectionMethod($pdf, 'isOptionUrl');

        $this->assertTrue($method->invoke($pdf, 'file:///etc/passwd'));
        $this->assertFalse($method->invoke($pdf, 'php://filter/resource=/etc/passwd'));
    }

    public function testAttachmentWithDisallowedSchemeIsTreatedAsContentNotFetched(): void
    {
        $pdf = new PdfSpy();
        $method = new \ReflectionMethod($pdf, 'handleArrayOptions');

        $payload = 'php://filter/convert.base64-encode/resource=/etc/passwd';

        /** @var list<string> $result */
        $result = $method->invoke($pdf, 'attachment', $payload);

        // The php:// wrapper is never fetched: the value is written verbatim to a temp
        // file. If file_get_contents() had run, the file would hold base64 of /etc/passwd.
        $this->assertCount(1, $result);
        $this->assertStringEqualsFile($result[0], $payload);
    }
}
