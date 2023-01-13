<?php

namespace Pontedilana\PhpWeasyPrint;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * @deprecated 1.0.0 This class is used only by Image class which is deprecated
 */
class Version
{
    private ?string $binary;

    public function __construct(?string $binary = '/usr/local/bin/weasyprint')
    {
        $this->binary = $binary;
    }

    public function getVersion(): array
    {
        $output = $this->runCommand();

        return $this->parseOutput($output);
    }

    public function getMajorVersion(): string
    {
        $output = $this->runCommand();

        return $this->parseOutput($output)['major'];
    }

    public function parseOutput(string $output): array
    {
        $re = '/^WeasyPrint version (?P<fullversion>((?P<major>\d+)\.?(?P<minor>.*)))$/';
        preg_match($re, $output, $matches);

        return [
            'fullversion' => $matches['fullversion'],
            'major' => $matches['major'],
            'minor' => '' != $matches['minor'] ? $matches['minor'] : '0',
        ];
    }

    private function runCommand(): string
    {
        $process = new Process([$this->binary, '--version']);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}
