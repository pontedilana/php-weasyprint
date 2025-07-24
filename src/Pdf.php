<?php

namespace Pontedilana\PhpWeasyPrint;

/**
 * Use this class to transform a html/an url to a pdf.
 *
 * @author  Manuel Dalla Lana <manuel@pontedilana.it>
 */
class Pdf extends AbstractGenerator
{
    /**
     * @var array<string, string>
     */
    protected array $optionsWithContentCheck = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(?string $binary = null, array $options = [], ?array $env = null)
    {
        $this->setDefaultExtension('pdf');
        $this->setOptionsWithContentCheck();
        parent::__construct($binary, $options, $env);
    }

    /**
     * {@inheritdoc}
     */
    public function generate(string $input, string $output, array $options = [], bool $overwrite = false): void
    {
        $options = $this->handleOptions($this->mergeOptions($options));

        parent::generate($input, $output, $options, $overwrite);
    }

    public function setTimeout(?int $timeout): self
    {
        parent::setTimeout($timeout);
        $this->setOption('timeout', $timeout);

        return $this;
    }

    public function disableTimeout(): self
    {
        parent::disableTimeout();
        $this->setOption('timeout', null);

        return $this;
    }

    /**
     * @param array<string, bool|int|string|array|null> $options
     *
     * @return array<string, bool|int|string|array|null>
     */
    protected function handleOptions(array $options = []): array
    {
        foreach ($options as $option => $value) {
            if (null === $value) {
                unset($options[$option]);

                continue;
            }

            if ('attachment' === $option || 'stylesheet' === $option) {
                $handledOption = $this->handleArrayOptions($option, $value);
                if (\count($handledOption) > 0) {
                    $options[$option] = $handledOption;
                }
            }
        }

        return $options;
    }

    /**
     * @param mixed $value
     *
     * @return list<string>
     */
    private function handleArrayOptions(string $option, $value): array
    {
        if (!\is_array($value)) {
            $value = [$value];
        }

        $returnOptions = [];
        foreach ($value as $item) {
            $saveToTempFile = !$this->isFile($item) && !$this->isOptionUrl($item);
            $fetchUrlContent = 'attachment' === $option && $this->isOptionUrl($item);
            if ($saveToTempFile || $fetchUrlContent) {
                $fileContent = $fetchUrlContent ? \file_get_contents($item) : $item;
                $returnOptions[] = $this->createTemporaryFile(
                    $fileContent,
                    $this->optionsWithContentCheck[$option] ?? 'temp'
                );
            } else {
                $returnOptions[] = $item;
            }
        }

        return $returnOptions;
    }

    /**
     * Convert option content or url to file if it is needed.
     *
     * @param mixed $option
     */
    protected function isOptionUrl($option): bool
    {
        return false !== \filter_var($option, \FILTER_VALIDATE_URL);
    }

    protected function configure(): void
    {
        $this->addOptions([
            // Global options
            'encoding' => null,
            'stylesheet' => [], // repeatable
            'media-type' => null,
            'base-url' => null,
            'attachment' => [], // repeatable
            'presentational-hints' => null,
            'pdf-identifier' => null, // added in WeasyPrint 56.0b1
            'pdf-variant' => null, // added in WeasyPrint 56.0b1
            'pdf-version' => null, // added in WeasyPrint 56.0b1
            'pdf-forms' => null, // added in WeasyPrint 58.0b1
            'pdf-tags' => null, // added in WeasyPrint 66.0
            'custom-metadata' => null, // added in WeasyPrint 56.0b1
            'uncompressed-pdf' => null, // added in WeasyPrint 59.0b1
            'full-fonts' => null, // added in WeasyPrint 59.0b1
            'hinting' => null, // added in WeasyPrint 59.0b1
            'dpi' => null, // added in WeasyPrint 59.0b1
            'jpeg-quality' => null, // added in WeasyPrint 59.0b1
            'optimize-images' => null, // no longer deprecated in WeasyPrint 59.0b1
            'cache-folder' => null, // added in WeasyPrint 59.0b1
            'timeout' => null, // added in WeasyPrint 60.0
            'srgb' => null, // added in WeasyPrint 63.0
            // Deprecated
            'format' => null, // deprecated in WeasyPrint 53.0b2
            'resolution' => null, // deprecated - png only
            'optimize-size' => null, // added in WeasyPrint 53.0b2, deprecated in 59.0b1
        ]);
    }

    /**
     * Builds the command string.
     *
     * @param string                                    $binary  The binary path/name
     * @param string                                    $input   Url or file location of the page to process
     * @param string                                    $output  File location to the pdf-or-image-to-be
     * @param array<string, bool|int|string|array|null> $options An array of options
     */
    protected function buildCommand(string $binary, string $input, string $output, array $options = []): string
    {
        $escapedBinary = \escapeshellarg($binary);
        $command = \is_executable($escapedBinary) ? $escapedBinary : $binary;

        foreach ($options as $key => $option) {
            if (null === $option || false === $option) {
                continue;
            }

            if (true === $option) {
                $command .= ' --' . $key;
                continue;
            }

            if (\is_array($option)) {
                foreach ($option as $v) {
                    $command .= ' --' . $key . ' ' . \escapeshellarg($v);
                }
            } else {
                switch ($key) {
                    case 'format':
                        $command .= ' --' . $key . ' ' . $option;
                        break;
                    case 'dpi':
                    case 'jpeg-quality':
                    case 'resolution':
                    case 'timeout':
                        $command .= ' --' . $key . ' ' . (int)$option;
                        break;
                    default:
                        $command .= ' --' . $key . ' ' . \escapeshellarg((string)$option);
                        break;
                }
            }
        }

        return $command . (' ' . \escapeshellarg($input) . ' ' . \escapeshellarg($output));
    }

    private function setOptionsWithContentCheck(): void
    {
        $this->optionsWithContentCheck = [
            'stylesheet' => 'css',
        ];
    }
}
