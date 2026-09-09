<?php

namespace Pontedilana\PhpWeasyPrint\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pontedilana\PhpWeasyPrint\AbstractGenerator;
use Pontedilana\PhpWeasyPrint\Pdf;
use Pontedilana\PhpWeasyPrint\Tests\PdfSpy;

#[CoversClass(AbstractGenerator::class)]
#[CoversClass(Pdf::class)]
class TemporaryFilesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/weasyprint-lifecycle-' . \bin2hex(\random_bytes(8));
        \mkdir($this->directory);
        \mkdir($this->directory . '/first');
        \mkdir($this->directory . '/second');
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir() && !$file->isLink()) {
                \rmdir($file->getPathname());
            } else {
                \unlink($file->getPathname());
            }
        }
        \rmdir($this->directory);
    }

    public function testDestructorReleasesTheInstanceAndItsFiles(): void
    {
        $pdf = new PdfSpy();
        $pdf->setTemporaryFolder($this->directory . '/first');
        $file = $this->createFile($pdf, 'content');
        $reference = \WeakReference::create($pdf);
        unset($pdf);

        $this->assertNull($reference->get());
        $this->assertFileDoesNotExist($file);
    }

    public function testCleanupHandlesFilesCreatedInDifferentDirectories(): void
    {
        $pdf = new PdfSpy();
        $pdf->setTemporaryFolder($this->directory . '/first');
        $first = $this->createFile($pdf, 'first');
        $pdf->setTemporaryFolder($this->directory . '/second');
        $second = $this->createFile($pdf, 'second');
        $pdf->removeTemporaryFiles();
        $pdf->removeTemporaryFiles();

        $this->assertFileDoesNotExist($first);
        $this->assertFileDoesNotExist($second);
        $this->assertSame([], $pdf->getTemporaryFiles());
    }

    public function testTheFileListIsASnapshotAndCannotRegisterUnownedFiles(): void
    {
        $pdf = new PdfSpy();
        $pdf->setTemporaryFolder($this->directory . '/first');
        $owned = $this->createFile($pdf, 'owned');
        $unowned = $this->directory . '/first/user-file.txt';
        \file_put_contents($unowned, 'user content');
        $files = $pdf->getTemporaryFiles();
        $files[] = $unowned;
        $this->assertNotSame($files, $pdf->getTemporaryFiles());
        $pdf->removeTemporaryFiles();

        $this->assertFileDoesNotExist($owned);
        $this->assertSame('user content', \file_get_contents($unowned));
    }

    public function testRepeatedOutputCallsDoNotAccumulateTemporaryFiles(): void
    {
        $pdf = new PdfSpy();
        $pdf->setTemporaryFolder($this->directory . '/first');
        for ($iteration = 0; $iteration < 3; ++$iteration) {
            $this->assertSame('output', $pdf->getOutputFromHtml('<h1>Test</h1>', [
                'stylesheet' => 'h1 { color: navy; }',
                'attachment' => 'Attachment content',
            ]));
            $this->assertSame([], $pdf->getTemporaryFiles());
            $this->assertSame([], \glob($this->directory . '/first/*'));
        }
    }

    public function testProcessErrorsCleanUpInputOutputAndOptionFiles(): void
    {
        $pdf = new class extends PdfSpy {
            protected function executeCommand(array $command): array
            {
                parent::executeCommand($command);
                throw new \RuntimeException('Process failed.');
            }
        };
        $pdf->setTemporaryFolder($this->directory . '/first');
        $failure = null;
        try {
            $pdf->getOutputFromHtml('<h1>Test</h1>', ['stylesheet' => 'h1 {}', 'attachment' => 'content']);
        } catch (\RuntimeException $exception) {
            $failure = $exception;
        }
        $this->assertInstanceOf(\RuntimeException::class, $failure);
        $this->assertSame([], $pdf->getTemporaryFiles());
        $this->assertSame([], \glob($this->directory . '/first/*'));
    }

    public function testOptionErrorsCleanUpFilesCreatedBeforeTheError(): void
    {
        $pdf = new PdfSpy();
        $pdf->setTemporaryFolder($this->directory . '/first');
        $failure = null;
        try {
            $pdf->getOutputFromHtml('<h1>Test</h1>', ['attachment' => ['content', ['invalid nested value']]]);
        } catch (\TypeError $exception) {
            $failure = $exception;
        }
        $this->assertInstanceOf(\TypeError::class, $failure);
        $this->assertSame([], $pdf->getTemporaryFiles());
        $this->assertSame([], \glob($this->directory . '/first/*'));
    }

    public function testGenerationPreservesCallerOwnedInputAndOutputFiles(): void
    {
        $pdf = new PdfSpy();
        $pdf->setTemporaryFolder($this->directory . '/first');
        $input = $this->directory . '/first/input.html';
        $output = $this->directory . '/first/output.pdf';
        \file_put_contents($input, '<h1>Original input</h1>');
        $pdf->generate($input, $output, ['stylesheet' => 'h1 {}']);
        $pdf->removeTemporaryFiles();

        $this->assertSame('<h1>Original input</h1>', \file_get_contents($input));
        $this->assertSame('output', \file_get_contents($output));
    }

    public function testNullContentReservesAFileAndCleanupRemovesIt(): void
    {
        $pdf = new PdfSpy();
        $pdf->setTemporaryFolder($this->directory . '/first');
        $file = $this->createFile($pdf, null);
        $this->assertFileExists($file);
        $pdf->removeTemporaryFiles();
        $this->assertFileDoesNotExist($file);
        $this->assertSame([], $pdf->getTemporaryFiles());
    }

    public function testCleanupRetriesFailedDeletions(): void
    {
        $pdf = new class extends Pdf {
            public bool $allowDelete = false;

            protected function unlink(string $filename): bool
            {
                return $this->allowDelete && parent::unlink($filename);
            }
        };
        $pdf->setTemporaryFolder($this->directory . '/first');
        $file = $this->createFile($pdf, 'retry');
        $pdf->removeTemporaryFiles();
        $this->assertSame([$file], $pdf->getTemporaryFiles());
        $this->assertFileExists($file);

        $pdf->allowDelete = true;
        $pdf->removeTemporaryFiles();
        $this->assertSame([], $pdf->getTemporaryFiles());
        $this->assertFileDoesNotExist($file);
    }

    public function testCallCleanupPreservesFilesOwnedBeforeTheCall(): void
    {
        $pdf = new PdfSpy();
        $pdf->setTemporaryFolder($this->directory . '/first');
        $file = $this->createFile($pdf, 'outer call');
        $pdf->getOutputFromHtml('<h1>Nested call</h1>', ['stylesheet' => 'h1 {}']);

        $this->assertSame('outer call', \file_get_contents($file));
        $this->assertSame([$file], $pdf->getTemporaryFiles());
    }

    public function testTemporaryFileExtensionsCannotEscapeTheirDirectory(): void
    {
        $pdf = new PdfSpy();
        $pdf->setTemporaryFolder($this->directory . '/first');
        $this->expectException(\InvalidArgumentException::class);
        (new \ReflectionMethod($pdf, 'createTemporaryFile'))->invoke($pdf, 'content', '../../escape');
    }

    public function testCleanupDoesNotFollowAReplacedParentDirectory(): void
    {
        $pdf = new PdfSpy();
        $pdf->setTemporaryFolder($this->directory . '/first');
        $file = $this->createFile($pdf, 'owned');
        $unowned = $this->directory . '/second/' . \basename($file);
        \file_put_contents($unowned, 'user content');
        \rename($this->directory . '/first', $this->directory . '/original');
        \symlink($this->directory . '/second', $this->directory . '/first');
        $pdf->removeTemporaryFiles();

        $this->assertSame('user content', \file_get_contents($unowned));
        $this->assertSame([$file], $pdf->getTemporaryFiles());

        \unlink($this->directory . '/first');
        \rename($this->directory . '/original', $this->directory . '/first');
        $pdf->removeTemporaryFiles();
        $this->assertFileDoesNotExist($file);
        $this->assertSame([], $pdf->getTemporaryFiles());
    }

    public function testCleanupRemovesDanglingLinksAtOwnedPaths(): void
    {
        $pdf = new PdfSpy();
        $pdf->setTemporaryFolder($this->directory . '/first');
        $file = $this->createFile($pdf, 'owned');
        \unlink($file);
        \symlink($this->directory . '/missing', $file);
        $pdf->removeTemporaryFiles();

        $this->assertFalse(\is_link($file));
        $this->assertSame([], $pdf->getTemporaryFiles());
    }

    private function createFile(Pdf $pdf, ?string $content): string
    {
        return (new \ReflectionMethod($pdf, 'createTemporaryFile'))->invoke($pdf, $content, 'html');
    }
}
