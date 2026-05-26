<?php

namespace Pontedilana\PhpWeasyPrint\Enum;

/**
 * Common values for the WeasyPrint `--media-type` option (used for CSS @media).
 *
 * Pass a case directly to setOption(), e.g.:
 *   $pdf->setOption('media-type', MediaType::Screen);
 */
enum MediaType: string
{
    case Print = 'print';
    case Screen = 'screen';
}
