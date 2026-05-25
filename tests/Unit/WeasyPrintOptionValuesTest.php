<?php

namespace Pontedilana\PhpWeasyPrint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pontedilana\PhpWeasyPrint\WeasyPrintOptionValues;

/**
 * @covers \Pontedilana\PhpWeasyPrint\WeasyPrintOptionValues
 */
class WeasyPrintOptionValuesTest extends TestCase
{
    public function testIsAllowedAcceptsWhitelistedValue(): void
    {
        $this->assertTrue(WeasyPrintOptionValues::isAllowed('format', 'pdf'));
        $this->assertTrue(WeasyPrintOptionValues::isAllowed('format', 'png'));
        $this->assertTrue(WeasyPrintOptionValues::isAllowed('pdf-variant', 'pdf/a-3b'));
    }

    public function testIsAllowedRejectsValueOutsideWhitelist(): void
    {
        $this->assertFalse(WeasyPrintOptionValues::isAllowed('format', 'pdf; touch /tmp/x'));
        $this->assertFalse(WeasyPrintOptionValues::isAllowed('pdf-variant', 'pdf/bogus'));
    }

    public function testIsAllowedIsCaseSensitive(): void
    {
        // WeasyPrint argparse "choices" are case-sensitive: must not be normalized.
        $this->assertFalse(WeasyPrintOptionValues::isAllowed('format', 'PDF'));
        $this->assertFalse(WeasyPrintOptionValues::isAllowed('pdf-variant', 'PDF/A-3B'));
    }

    public function testIsAllowedDoesNotCoerceToFalsePositive(): void
    {
        $this->assertFalse(WeasyPrintOptionValues::isAllowed('format', 0));
        $this->assertFalse(WeasyPrintOptionValues::isAllowed('format', 123));
    }

    public function testUnconstrainedOptionAllowsAnyValue(): void
    {
        $this->assertTrue(WeasyPrintOptionValues::isAllowed('encoding', 'utf-8'));
        $this->assertTrue(WeasyPrintOptionValues::isAllowed('base-url', 'whatever'));
    }

    public function testIsConstrained(): void
    {
        $this->assertTrue(WeasyPrintOptionValues::isConstrained('format'));
        $this->assertTrue(WeasyPrintOptionValues::isConstrained('pdf-variant'));
        $this->assertFalse(WeasyPrintOptionValues::isConstrained('encoding'));
        $this->assertFalse(WeasyPrintOptionValues::isConstrained('does-not-exist'));
    }

    public function testGetAllowedValues(): void
    {
        $this->assertSame(['pdf', 'png'], WeasyPrintOptionValues::getAllowedValues('format'));
        $this->assertContains('pdf/a-3b', WeasyPrintOptionValues::getAllowedValues('pdf-variant'));
        $this->assertSame([], WeasyPrintOptionValues::getAllowedValues('encoding'));
    }
}
