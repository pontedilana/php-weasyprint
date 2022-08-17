<?php

require __DIR__ . '/vendor/autoload.php';

use xmarcos\PhpWeasyPrint\Pdf as WeasyPdf;
use Symfony\Component\Process\Process;

$weasyprint_bin = '/usr/bin/weasyprint';

try {

  $version_cmd = sprintf("%s --version 2>/dev/null", $weasyprint_bin);
  $version = new Process($version_cmd);
  $version->run();

  echo sprintf("\nRunning Integration Test with '%s'\n", trim($version->getOutput()));

  $url = 'https://twitter.com/robots.txt';
  $pdf_file = 'robots.pdf';
  $pdf_generator = new WeasyPdf($weasyprint_bin);
  $pdf_generator->generate($url, $pdf_file);

  $command = sprintf("pdfgrep -ic 'yahoo!' %s", $pdf_file);
  $pdfgrep = new Process($command);
  $pdfgrep->run();

  echo sprintf("
    PDF Generated: %s
    PDF Size: %s
    PDF Content: %s\n",
    file_exists($pdf_file) ? 'OK' : 'ERROR',
    filesize($pdf_file),
    $pdfgrep->getOutput() == 1 ? 'OK' : 'ERROR'
  );
} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage();
}
