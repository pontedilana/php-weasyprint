<?php

namespace Pontedilana\PhpWeasyPrint\Tests;

use Pontedilana\PhpWeasyPrint\Pdf;

class PdfSpy extends Pdf
{
    private string $lastCommand;

    /** @var list<string> */
    private array $attachmentContents = [];

    public function __construct()
    {
        parent::__construct('emptyBinary');
    }

    public function getLastCommand(): string
    {
        return $this->lastCommand;
    }

    /**
     * @return list<string>
     */
    public function getAttachmentContents(): array
    {
        return $this->attachmentContents;
    }

    protected function checkBinary(string $binary): void
    {
        // 'emptyBinary' is a stub, not a real file: skip the executable check in the spy.
    }

    /**
     * @param list<string> $command
     */
    protected function executeCommand(array $command): array
    {
        $this->lastCommand = \implode(' ', $command);
        $this->attachmentContents = [];
        foreach ($command as $index => $argument) {
            if ('--attachment' === $argument) {
                $this->attachmentContents[] = $this->getFileContents($command[$index + 1]);
            }
        }
        \file_put_contents($command[\count($command) - 1], 'output');

        return [0, 'output', 'errorOutput'];
    }

    protected function getFileContents(string $filename): string
    {
        $fixtures = [
            'https://example.test/attachment-one.txt' => __DIR__ . '/Fixture/attachment-one.txt',
            'https://example.test/attachment-two.txt' => __DIR__ . '/Fixture/attachment-two.txt',
        ];

        return parent::getFileContents($fixtures[$filename] ?? $filename);
    }

    protected function checkOutput(string $output, string $command): void
    {
        // let's say everything went right
    }
}
