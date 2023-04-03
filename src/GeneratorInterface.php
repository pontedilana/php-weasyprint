<?php

namespace Pontedilana\PhpWeasyPrint;

/**
 * Interface for the media generators.
 *
 * @author  Matthieu Bontemps <matthieu.bontemps@knplabs.com>
 * @author  Antoine Hérault <antoine.herault@knplabs.com>
 * @author  Manuel Dalla Lana <manuel@pontedilana.it>
 */
interface GeneratorInterface
{
    /**
     * Generates the output media file from the specified input HTML file.
     *
     * @param string                                $input     The input HTML filename or URL
     * @param string                                $output    The output media filename
     * @param array<string, bool|string|array|null> $options   An array of options for this generation only
     * @param bool                                  $overwrite Overwrite the file if it exists. If not, throw a FileAlreadyExistsException
     */
    public function generate(string $input, string $output, array $options = [], bool $overwrite = false): void;

    /**
     * Generates the output media file from the given HTML.
     *
     * @param string                                $html      The HTML to be converted
     * @param string                                $output    The output media filename
     * @param array<string, bool|string|array|null> $options   An array of options for this generation only
     * @param bool                                  $overwrite Overwrite the file if it exists. If not, throw a FileAlreadyExistsException
     */
    public function generateFromHtml(string $html, string $output, array $options = [], bool $overwrite = false): void;

    /**
     * Returns the output of the media generated from the specified input HTML
     * file.
     *
     * @param string                                $input   The input HTML filename or URL
     * @param array<string, bool|string|array|null> $options An array of options for this output only
     */
    public function getOutput(string $input, array $options = []): string;

    /**
     * Returns the output of the media generated from the given HTML.
     *
     * @param string                                $html    The HTML to be converted
     * @param array<string, bool|string|array|null> $options An array of options for this output only
     */
    public function getOutputFromHtml(string $html, array $options = []): string;
}
