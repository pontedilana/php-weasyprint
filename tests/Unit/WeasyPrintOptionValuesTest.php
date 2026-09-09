<?php

namespace Pontedilana\PhpWeasyPrint\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pontedilana\PhpWeasyPrint\WeasyPrintOptionValues;

#[CoversClass(WeasyPrintOptionValues::class)]
class WeasyPrintOptionValuesTest extends TestCase
{
    public function testIsAllowedAcceptsWhitelistedValue(): void
    {
        $this->assertTrue(WeasyPrintOptionValues::isAllowed('pdf-variant', 'pdf/a-3b'));
        $this->assertTrue(WeasyPrintOptionValues::isAllowed('pdf-variant', 'pdf/ua-1'));
    }

    public function testIsAllowedRejectsValueOutsideWhitelist(): void
    {
        $this->assertFalse(WeasyPrintOptionValues::isAllowed('pdf-variant', 'pdf/bogus'));
        $this->assertFalse(WeasyPrintOptionValues::isAllowed('pdf-variant', 'pdf/a-3b; touch /tmp/x'));
    }

    public function testIsAllowedIsCaseSensitive(): void
    {
        // WeasyPrint argparse "choices" are case-sensitive: must not be normalized.
        $this->assertFalse(WeasyPrintOptionValues::isAllowed('pdf-variant', 'PDF/A-3B'));
    }

    public function testIsAllowedDoesNotCoerceToFalsePositive(): void
    {
        $this->assertFalse(WeasyPrintOptionValues::isAllowed('pdf-variant', 0));
        $this->assertFalse(WeasyPrintOptionValues::isAllowed('pdf-variant', 123));
    }

    public function testUnconstrainedOptionAllowsAnyValue(): void
    {
        $this->assertTrue(WeasyPrintOptionValues::isAllowed('encoding', 'utf-8'));
        $this->assertTrue(WeasyPrintOptionValues::isAllowed('base-url', 'whatever'));
    }

    public function testIsConstrained(): void
    {
        $this->assertTrue(WeasyPrintOptionValues::isConstrained('pdf-variant'));
        $this->assertFalse(WeasyPrintOptionValues::isConstrained('encoding'));
        $this->assertFalse(WeasyPrintOptionValues::isConstrained('does-not-exist'));
    }

    public function testGetAllowedValues(): void
    {
        $this->assertContains('pdf/a-3b', WeasyPrintOptionValues::getAllowedValues('pdf-variant'));
        $this->assertSame([], WeasyPrintOptionValues::getAllowedValues('encoding'));
    }
}
