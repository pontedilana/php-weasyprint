<?php

namespace Pontedilana\PhpWeasyPrint;

/**
 * Single source of truth for WeasyPrint CLI options whose value is constrained
 * to a fixed set (argparse "choices"). Validating option values against this
 * allowlist prevents invalid values from reaching the command line and acts as
 * defense-in-depth against argument/command injection through those options.
 *
 * Only options that WeasyPrint itself restricts to a closed set are listed here;
 * free-string and numeric options are not.
 *
 * @author  Manuel Dalla Lana <manuel@pontedilana.it>
 */
final class WeasyPrintOptionValues
{
    /**
     * Option name => list of values WeasyPrint accepts as CLI "choices".
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_VALUES = [
        // --pdf-variant choices (WeasyPrint 68.1)
        'pdf-variant' => [
            'pdf/a-1b', 'pdf/a-2b', 'pdf/a-3b', 'pdf/a-2u', 'pdf/a-3u', 'pdf/a-4u',
            'pdf/a-1a', 'pdf/a-2a', 'pdf/a-3a', 'pdf/a-4e', 'pdf/a-4f',
            'pdf/ua-1', 'pdf/ua-2', 'pdf/x-1a', 'pdf/x-3', 'pdf/x-4', 'pdf/x-5g',
            'debug',
        ],
        // --format choices (removed after WeasyPrint 53.0b2, but still accepted for backward compatibility)
        'format' => ['pdf', 'png'],
    ];

    /**
     * Whether the given option has a constrained set of allowed values.
     */
    public static function isConstrained(string $option): bool
    {
        return isset(self::ALLOWED_VALUES[$option]);
    }

    /**
     * @return list<string>
     */
    public static function getAllowedValues(string $option): array
    {
        return self::ALLOWED_VALUES[$option] ?? [];
    }

    /**
     * Returns true if the value is allowed for the option or if the option is
     * not constrained at all.
     *
     * @param mixed $value
     */
    public static function isAllowed(string $option, $value): bool
    {
        if (!isset(self::ALLOWED_VALUES[$option])) {
            return true;
        }

        return \in_array((string)$value, self::ALLOWED_VALUES[$option], true);
    }
}
