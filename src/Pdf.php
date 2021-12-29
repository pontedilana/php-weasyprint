<?php

namespace Pontedilana\PhpWeasyPrint;

/**
 * Use this class to transform a html/a url to a pdf.
 *
 * @author  Manuel Dalla Lana <manuel@pontedilana.it>
 */
class Pdf extends AbstractGenerator
{
    protected array $optionsWithContentCheck = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(string $binary = null, array $options = [], array $env = null)
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

    protected function handleOptions(array $options = []): array
    {
        foreach ($options as $option => $value) {
            if (null === $value) {
                unset($options[$option]);

                continue;
            }

            if (!empty($value) && \array_key_exists($option, $this->optionsWithContentCheck)) {
                $saveToTempFile = !$this->isFile($value) && !$this->isOptionUrl($value);
                $fetchUrlContent = 'attachment' === $option && $this->isOptionUrl($value);

                if ($saveToTempFile || $fetchUrlContent) {
                    $fileContent = $fetchUrlContent ? \file_get_contents($value) : $value;
                    $options[$option] = $this->createTemporaryFile($fileContent,
                        $this->optionsWithContentCheck[$option]);
                }
            }
        }

        return $options;
    }

    /**
     * Convert option content or url to file if it is needed.
     *
     * @param mixed $option
     */
    protected function isOptionUrl($option): bool
    {
        return (bool)\filter_var($option, \FILTER_VALIDATE_URL);
    }

    protected function configure(): void
    {
        $this->addOptions([
            // Global options
            'encoding' => null,
            'stylesheet' => [], //repeatable
            'media-type' => null,
            'base-url' => null,
            'attachment' => [], //repeatable
            'presentational-hints' => null,
            'optimize-size' => null, // added in 53.0b2
            // Deprecated
            'format' => null, // deprecated in 53.0b2
            'optimize-images' => null, // deprecated in 53.0b2
            'resolution' => null, // deprecated - png only
        ]);
    }

    private function setOptionsWithContentCheck(): void
    {
        $this->optionsWithContentCheck = [
            'stylesheet' => 'css',
        ];
    }
}
