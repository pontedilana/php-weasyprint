<?php

namespace xmarcos\PhpWeasyPrint;

use xmarcos\PhpWeasyPrint\Exception\UnsupportedWeasyPrintVersionException;

/**
 * Use this class to create a snapshot / thumbnail from an HTML page.
 *
 * @author  Manuel Dalla Lana <manuel@pontedilana.it>
 */
class Image extends AbstractGenerator
{
    private $binaryVersion;

    /**
     * {@inheritdoc}
     */
    public function __construct($binary = null, $options = [], $env = null)
    {
        parent::__construct($binary, $options, $env);

        $this->setDefaultExtension('png');
        $this->binaryVersion = new Version($this->getBinary());
    }

    protected function configure()
    {
        $this->addOptions([
            // Global options
            'format' => 'png', // forced to 'png', should not be overridden
            'encoding' => null,
            'stylesheet' => [], //repeatable
            'media-type' => null,
            'resolution' => null, //png only
            'base-url' => null,
            'attachment' => [], //repeatable
            'presentational-hints' => null,
            'optimize-images' => null, // deprecated in 53.0b2
            'optimize-size' => null, //from 53.0b2
        ]);
    }

    public function generate($input, $output, $options = [], $overwrite = false)
    {
        $this->assertVersionIsCompatible();
        parent::generate($input, $output, $options, $overwrite);
    }

    public function generateFromHtml($html, $output, $options = [], $overwrite = false)
    {
        $this->assertVersionIsCompatible();
        parent::generateFromHtml($html, $output, $options, $overwrite);
    }

    public function getOutput($input, $options = [])
    {
        $this->assertVersionIsCompatible();

        return parent::getOutput($input, $options);
    }

    public function getOutputFromHtml($html, $options = [])
    {
        $this->assertVersionIsCompatible();

        return parent::getOutputFromHtml($html, $options);
    }

    private function assertVersionIsCompatible()
    {
        if ($this->binaryVersion->getMajorVersion() >= '53') {
            throw new UnsupportedWeasyPrintVersionException('Image generation is unsupported in WeasyPrint >= 53');
        }
    }
}
