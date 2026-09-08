<?php

namespace Pontedilana\PhpWeasyPrint;

/**
 * Converts an HTML document to a PDF.
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
     * URL schemes the library is allowed to fetch server-side for options that
     * accept URLs (e.g. `attachment`). Restricting these prevents SSRF and local
     * file disclosure (file://, php://, ftp://, ...) through an attacker-controlled
     * option value: a URL with a non-allowed scheme is treated as inline content
     * instead of being fetched.
     *
     * @var list<string>
     */
    private array $allowedSchemes = ['http', 'https'];

    /**
     * {@inheritdoc}
     *
     * @param list<string>|null $allowedSchemes URL schemes allowed for options that accept URLs (e.g. 'http', 'https', 'ftp', 'file'). If null, defaults to ['http', 'https'].
     */
    public function __construct(?string $binary = null, array $options = [], ?array $env = null, ?array $allowedSchemes = null)
    {
        $this->setDefaultExtension('pdf');
        $this->setOptionsWithContentCheck();

        if (null !== $allowedSchemes) {
            $this->allowedSchemes = $allowedSchemes;
        }

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
     * Checks whether the option value is a URL with an allowed scheme.
     *
     * @param mixed $option
     */
    protected function isOptionUrl($option): bool
    {
        $url = \parse_url((string)$option);

        return false !== $url
            && isset($url['scheme'])
            && \in_array(\strtolower($url['scheme']), $this->allowedSchemes, true);
    }

    /**
     * Validates an option value against the WeasyPrint allow-list of constrained
     * values. Null and booleans are passed through (unset / flags). Arrays are
     * validated element by element, so a repeatable constrained option cannot
     * smuggle a disallowed value.
     *
     * @param bool|int|string|array|null $value
     *
     * @throws \InvalidArgumentException if the value is not allowed for the option
     */
    protected function validateOptionValue(string $name, $value): void
    {
        if (null === $value || \is_bool($value)) {
            return;
        }

        $values = \is_array($value) ? $value : [$value];

        foreach ($values as $item) {
            if (!WeasyPrintOptionValues::isAllowed($name, $item)) {
                throw new \InvalidArgumentException(\sprintf("The value '%s' is not allowed for option '%s'. Allowed values: '%s'.", (string)$item, $name, \implode("', '", WeasyPrintOptionValues::getAllowedValues($name))));
            }
        }
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
            'srgb' => null, // added in WeasyPrint 63.0, replaced by output-intent in 69.0
            'output-intent' => null, // added in WeasyPrint 69.0
            'allowed-protocols' => null, // added in WeasyPrint 67.0
            'attachment-relationship' => null, // added in WeasyPrint 68.0
            'xmp-metadata' => null, // added in WeasyPrint 68.0
            'optimize-size' => null, // deprecated in WeasyPrint 59, removed in 61; repeatable
            'info' => null,
            'quiet' => null,
            'verbose' => null,
            'debug' => null,
            'version' => null,
            'no-http-redirects' => null,
            'fail-on-http-errors' => null,
        ]);
    }

    /**
     * Builds the command string.
     *
     * @param string                                    $binary  The binary path/name
     * @param string                                    $input   URL or path of the input document
     * @param string                                    $output  Path to the output file
     * @param array<string, bool|int|string|array|null> $options An array of options
     */
    protected function buildCommand(string $binary, string $input, string $output, array $options = []): string
    {
        $command = $this->getEscapedBinary($binary);

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
                    case 'dpi':
                    case 'jpeg-quality':
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
