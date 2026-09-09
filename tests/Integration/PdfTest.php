<?php

namespace Pontedilana\PhpWeasyPrint\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pontedilana\PhpWeasyPrint\Enum\PdfVariant;
use Pontedilana\PhpWeasyPrint\Pdf;
use Symfony\Component\Process\Process;

#[CoversClass(\Pontedilana\PhpWeasyPrint\AbstractGenerator::class)]
#[CoversClass(Pdf::class)]
class PdfTest extends TestCase
{
    private Pdf $pdf;
    private string $version;

    protected function setUp(): void
    {
        $binary = \getenv('WEASYPRINT_BINARY');
        $this->assertNotFalse($binary, 'Set WEASYPRINT_BINARY to the executable path.');
        $process = new Process([$binary, '--version']);
        $process->mustRun();
        $this->assertSame(1, \preg_match('/WeasyPrint version (\d+\.\d+)/', $process->getOutput(), $matches));
        $this->version = $matches[1];
        $this->pdf = new Pdf($binary);
        $this->pdf->setTimeout(30);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdf)) {
            $this->pdf->removeTemporaryFiles();
        }
    }

    public function testGenerateWithEnumsNumericOptionsAndAttachments(): void
    {
        $output = $this->pdf->getOutputFromHtml('<!doctype html><h1>Qualità PDF</h1>', [
            'pdf-variant' => PdfVariant::PdfA3b,
            'dpi' => '300.0',
            'jpeg-quality' => '85.9',
            'timeout' => '30.0',
            'stylesheet' => 'h1 { color: navy; }',
            'attachment' => __DIR__ . '/../Fixture/attachment-one.txt',
        ]);

        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertStringContainsString('%%EOF', $output);
    }

    public function testGenerateWithVersionSpecificOptions(): void
    {
        $options = \version_compare($this->version, '69.0', '>=')
            ? ['output-intent' => 'srgb']
            : ['optimize-size' => ['fonts', 'images']];
        $output = $this->pdf->getOutputFromHtml('<!doctype html><p>Version-specific options</p>', $options);

        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertStringContainsString('%%EOF', $output);
    }
}
