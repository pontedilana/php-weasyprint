<?php

namespace Pontedilana\PhpWeasyPrint\Enum;

/**
 * Common values for the WeasyPrint `--pdf-version` option.
 *
 * Unlike `--pdf-variant`, WeasyPrint does not restrict `--pdf-version` to a closed
 * set of "choices": this enum is a convenience for the values commonly produced by
 * WeasyPrint (default is 1.7) and is NOT enforced by WeasyPrintOptionValues.
 *
 * Pass a case directly to setOption(), e.g.:
 *   $pdf->setOption('pdf-version', PdfVersion::Pdf17);
 */
enum PdfVersion: string
{
    case Pdf14 = '1.4';
    case Pdf15 = '1.5';
    case Pdf16 = '1.6';
    case Pdf17 = '1.7';
    case Pdf20 = '2.0';
}
