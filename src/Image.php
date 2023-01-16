<?php

namespace Pontedilana\PhpWeasyPrint;

use Pontedilana\PhpWeasyPrint\Exception\UnsupportedWeasyPrintVersionException;

/**
 * Use this class to create a snapshot / thumbnail from an HTML page.
 *
 * @author  Manuel Dalla Lana <manuel@pontedilana.it>
 *
 * @deprecated 1.0.0 WeasyPrint version 53 has deprecated image generation
 */
class Image extends AbstractGenerator
{
    private Version $binaryVersion;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $binary = null, array $options = [], array $env = null)
    {
        parent::__construct($binary, $options, $env);

        $this->setDefaultExtension('png');
        $this->binaryVersion = new Version($this->getBinary());
    }

    protected function configure(): void
    {
        $this->addOptions([
            // Global options
            'format' => 'png', // forced to 'png', should not be overridden
            'encoding' => null,
            'stylesheet' => [], // repeatable
            'media-type' => null,
            'resolution' => null, // png only
            'base-url' => null,
            'attachment' => [], // repeatable
            'presentational-hints' => null,
            'optimize-images' => null, // deprecated in 53.0b2
            'optimize-size' => null, // from 53.0b2
        ]);
    }

    public function generate(string $input, string $output, array $options = [], bool $overwrite = false): void
    {
        $this->assertVersionIsCompatible();
        parent::generate($input, $output, $options, $overwrite);
    }

    public function generateFromHtml(string $html, string $output, array $options = [], bool $overwrite = false): void
    {
        $this->assertVersionIsCompatible();
        parent::generateFromHtml($html, $output, $options, $overwrite);
    }

    public function getOutput(string $input, array $options = []): string
    {
        $this->assertVersionIsCompatible();

        return parent::getOutput($input, $options);
    }

    public function getOutputFromHtml(string $html, array $options = []): string
    {
        $this->assertVersionIsCompatible();

        return parent::getOutputFromHtml($html, $options);
    }

    private function assertVersionIsCompatible(): void
    {
        if ($this->binaryVersion->getMajorVersion() >= '53') {
            throw new UnsupportedWeasyPrintVersionException('Image generation is unsupported in WeasyPrint >= 53');
        }
    }
}
