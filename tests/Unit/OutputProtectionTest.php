<?php

namespace Pontedilana\PhpWeasyPrint\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pontedilana\PhpWeasyPrint\AbstractGenerator;
use Pontedilana\PhpWeasyPrint\Exception\FileAlreadyExistsException;
use Pontedilana\PhpWeasyPrint\Pdf;

#[CoversClass(AbstractGenerator::class)]
#[CoversClass(Pdf::class)]
class OutputProtectionTest extends TestCase
{
    private string $directory;
    private string $output;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/weasyprint output ' . \bin2hex(\random_bytes(8));
        \mkdir($this->directory);
        $this->output = $this->directory . '/document.pdf';
        \file_put_contents($this->output, 'original');
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->directory . '/*') ?: [] as $path) {
            \unlink($path);
        }
        \rmdir($this->directory);
    }

    #[DataProvider('failedGenerationModes')]
    public function testFailedGenerationPreservesExistingOutput(string $mode): void
    {
        $pdf = $this->createGenerator($mode);
        if ('invalid binary' === $mode) {
            $pdf->setBinary($this->directory . '/missing-binary');
        }

        $failure = null;
        try {
            $pdf->generate('input.html', $this->output, [], true);
        } catch (\RuntimeException $exception) {
            $failure = $exception;
        }
        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertFileExists($this->output);
        $this->assertSame('original', \file_get_contents($this->output));
        $this->assertSame([$this->output], \glob($this->directory . '/*'));
    }

    public static function failedGenerationModes(): array
    {
        return [
            'invalid binary' => ['invalid binary'],
            'failed process with partial output' => ['partial'],
            'empty output' => ['empty'],
            'thrown process exception' => ['exception'],
        ];
    }

    #[DataProvider('outputPathKinds')]
    public function testSuccessfulGenerationReplacesExistingOutput(bool $fileUrl): void
    {
        $pdf = $this->createGenerator('success');
        $output = $fileUrl ? 'file://' . $this->output : $this->output;
        $pdf->generate('input.html', $output, [], true);

        $this->assertSame('replacement', \file_get_contents($this->output));
        $this->assertSame([$this->output], \glob($this->directory . '/*'));
    }

    public static function outputPathKinds(): array
    {
        return ['local path' => [false], 'file URL' => [true]];
    }

    public function testOverwriteMustBeExplicit(): void
    {
        $pdf = $this->createGenerator('success');
        try {
            $pdf->generate('input.html', $this->output);
            $this->fail('Overwriting must be explicitly enabled.');
        } catch (FileAlreadyExistsException $exception) {
            $this->assertSame('original', \file_get_contents($this->output));
        }
    }

    private function createGenerator(string $mode): Pdf
    {
        return new class($mode) extends Pdf {
            private string $mode;

            public function __construct(string $mode)
            {
                parent::__construct(\PHP_BINARY);
                $this->mode = $mode;
            }

            protected function executeCommand(array $command): array
            {
                if ('exception' === $this->mode) {
                    throw new \RuntimeException('Process failed to start.');
                }
                \file_put_contents($command[\count($command) - 1], 'empty' === $this->mode ? '' : 'replacement');

                return ['partial' === $this->mode ? 1 : 0, '', ''];
            }
        };
    }
}
