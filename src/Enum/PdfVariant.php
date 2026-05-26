<?php

namespace Pontedilana\PhpWeasyPrint\Enum;

/**
 * Allowed values for the WeasyPrint `--pdf-variant` option (argparse "choices").
 *
 * Pass a case directly to setOption(), e.g.:
 *   $pdf->setOption('pdf-variant', PdfVariant::PdfA3b);
 */
enum PdfVariant: string
{
    case PdfA1b = 'pdf/a-1b';
    case PdfA2b = 'pdf/a-2b';
    case PdfA3b = 'pdf/a-3b';
    case PdfA2u = 'pdf/a-2u';
    case PdfA3u = 'pdf/a-3u';
    case PdfA4u = 'pdf/a-4u';
    case PdfA1a = 'pdf/a-1a';
    case PdfA2a = 'pdf/a-2a';
    case PdfA3a = 'pdf/a-3a';
    case PdfA4e = 'pdf/a-4e';
    case PdfA4f = 'pdf/a-4f';
    case PdfUa1 = 'pdf/ua-1';
    case PdfUa2 = 'pdf/ua-2';
    case PdfX1a = 'pdf/x-1a';
    case PdfX3 = 'pdf/x-3';
    case PdfX4 = 'pdf/x-4';
    case PdfX5g = 'pdf/x-5g';
    case Debug = 'debug';
}
