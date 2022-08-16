<?php

namespace xmarcos\PhpWeasyPrint;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Version
{
    private $binary;

    public function __construct($binary = '/usr/local/bin/weasyprint')
    {
        $this->binary = $binary;
    }

    public function getVersion()
    {
        $output = $this->runCommand();

        return $this->parseOutput($output);
    }

    public function getMajorVersion()
    {
        $output = $this->runCommand();

        return $this->parseOutput($output)['major'];
    }

    public function parseOutput($output)
    {
        $re = '/^WeasyPrint version (?P<fullversion>((?P<major>\d+)\.?(?P<minor>.*)))$/';
        preg_match($re, $output, $matches);

        return [
            'fullversion' => $matches['fullversion'],
            'major' => $matches['major'],
            'minor' => '' != $matches['minor'] ? $matches['minor'] : '0',
        ];
    }

    private function runCommand()
    {
        $process = new Process([$this->binary, '--version']);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}
