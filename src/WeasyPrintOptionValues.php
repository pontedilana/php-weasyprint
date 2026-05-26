<?php

namespace Pontedilana\PhpWeasyPrint;

use Pontedilana\PhpWeasyPrint\Enum\PdfVariant;

/**
 * Single source of truth for WeasyPrint CLI options whose value is constrained
 * to a fixed set (argparse "choices"). Validating option values against this
 * allow-list prevents invalid values from reaching the command line and acts as
 * defense-in-depth against argument/command injection through those options.
 *
 * The allowed values are derived from the backed enums in the Enum namespace
 * (e.g. PdfVariant), which are the canonical definition. Only options that
 * WeasyPrint itself restricts to a closed set are listed here; free-string and
 * numeric options are not.
 *
 * @author  Manuel Dalla Lana <manuel@pontedilana.it>
 */
final class WeasyPrintOptionValues
{
    /**
     * Whether the given option has a constrained set of allowed values.
     */
    public static function isConstrained(string $option): bool
    {
        return [] !== self::getAllowedValues($option);
    }

    /**
     * @return list<string>
     */
    public static function getAllowedValues(string $option): array
    {
        switch ($option) {
            case 'pdf-variant':
                return \array_map(static fn(PdfVariant $case): string => $case->value, PdfVariant::cases());
            default:
                return [];
        }
    }

    /**
     * Returns true if the value is allowed for the option or if the option is
     * not constrained at all.
     *
     * @param mixed $value
     */
    public static function isAllowed(string $option, $value): bool
    {
        $allowedValues = self::getAllowedValues($option);

        if ([] === $allowedValues) {
            return true;
        }

        return \in_array((string)$value, $allowedValues, true);
    }
}
