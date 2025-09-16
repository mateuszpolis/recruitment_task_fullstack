<?php

declare(strict_types=1);

namespace Unit\Service;

use App\Service\RateCalculator;
use PHPUnit\Framework\TestCase;

class RateCalculatorTest extends TestCase
{
    private RateCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new RateCalculator();
    }

    // parseDecimalToScaled Tests

    public function testParseDecimalToScaledBasicCases(): void
    {
        $this->assertSame(42345, $this->calculator->parseDecimalToScaled('4.2345'));
        $this->assertSame(10000, $this->calculator->parseDecimalToScaled('1'));
        $this->assertSame(5000, $this->calculator->parseDecimalToScaled('0.5'));
        $this->assertSame(0, $this->calculator->parseDecimalToScaled('0'));
        $this->assertSame(123, $this->calculator->parseDecimalToScaled('0.0123'));
    }

    public function testParseDecimalToScaledNegativeNumbers(): void
    {
        $this->assertSame(-42345, $this->calculator->parseDecimalToScaled('-4.2345'));
        $this->assertSame(-10000, $this->calculator->parseDecimalToScaled('-1'));
        $this->assertSame(-5000, $this->calculator->parseDecimalToScaled('-0.5'));
    }

    public function testParseDecimalToScaledBoundaryPrecision(): void
    {
        // Test precise boundaries for rounding scenarios
        $this->assertSame(14995, $this->calculator->parseDecimalToScaled('1.4995'));
        $this->assertSame(15005, $this->calculator->parseDecimalToScaled('1.5005'));
        $this->assertSame(1500, $this->calculator->parseDecimalToScaled('0.15'));
        $this->assertSame(1499, $this->calculator->parseDecimalToScaled('0.1499'));
        $this->assertSame(1501, $this->calculator->parseDecimalToScaled('0.1501'));
    }

    public function testParseDecimalToScaledCustomScale(): void
    {
        $this->assertSame(4234, $this->calculator->parseDecimalToScaled('4.234', 1000));
        $this->assertSame(423, $this->calculator->parseDecimalToScaled('4.23', 100));
        $this->assertSame(42, $this->calculator->parseDecimalToScaled('4.2', 10));
    }

    public function testParseDecimalToScaledWithWhitespace(): void
    {
        $this->assertSame(42345, $this->calculator->parseDecimalToScaled(' 4.2345 '));
        $this->assertSame(10000, $this->calculator->parseDecimalToScaled("\t1\n"));
    }

    public function testParseDecimalToScaledInvalidFormats(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid decimal format');
        $this->calculator->parseDecimalToScaled('4.23.45');
    }

    public function testParseDecimalToScaledEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Decimal string cannot be empty');
        $this->calculator->parseDecimalToScaled('');
    }

    public function testParseDecimalToScaledNonNumeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculator->parseDecimalToScaled('abc');
    }

    // formatPln Tests

    public function testFormatPlnBasicCases(): void
    {
        $this->assertSame('4.23', $this->calculator->formatPln(42345));
        $this->assertSame('1.00', $this->calculator->formatPln(10000));
        $this->assertSame('0.50', $this->calculator->formatPln(5000));
        $this->assertSame('0.00', $this->calculator->formatPln(0));
        $this->assertSame('0.01', $this->calculator->formatPln(123));
    }

    public function testFormatPlnNegativeNumbers(): void
    {
        $this->assertSame('-4.23', $this->calculator->formatPln(-42345));
        $this->assertSame('-1.00', $this->calculator->formatPln(-10000));
        $this->assertSame('-0.50', $this->calculator->formatPln(-5000));
    }

    public function testFormatPlnRoundingBoundaries(): void
    {
        // Test precise rounding to 2 decimal places
        $this->assertSame('1.50', $this->calculator->formatPln(14995)); // 1.4995 -> rounds to 1.50
        $this->assertSame('1.50', $this->calculator->formatPln(15005)); // 1.5005 -> rounds to 1.50
        $this->assertSame('0.15', $this->calculator->formatPln(1500));  // 0.15 exactly
        $this->assertSame('0.15', $this->calculator->formatPln(1499));  // 0.1499 -> rounds to 0.15
        $this->assertSame('0.15', $this->calculator->formatPln(1501));  // 0.1501 -> rounds to 0.15
    }

    public function testFormatPlnEdgeCasesRounding(): void
    {
        // Test edge cases for rounding
        $this->assertSame('0.00', $this->calculator->formatPln(4));     // 0.0004 -> 0.00
        $this->assertSame('0.00', $this->calculator->formatPln(49));    // 0.0049 -> 0.00
        $this->assertSame('0.01', $this->calculator->formatPln(50));    // 0.005 -> 0.01 (rounds up)
        $this->assertSame('0.01', $this->calculator->formatPln(99));    // 0.0099 -> 0.01
        $this->assertSame('0.01', $this->calculator->formatPln(100));   // 0.01 exactly
    }

    public function testFormatPlnCustomScale(): void
    {
        $this->assertSame('4.23', $this->calculator->formatPln(4234, 1000));
        $this->assertSame('42.30', $this->calculator->formatPln(423, 10));
        $this->assertSame('4.20', $this->calculator->formatPln(42, 10));
    }

    // midToBuySell Tests

    public function testMidToBuySellEUR(): void
    {
        $result = $this->calculator->midToBuySell('EUR', '4.50');
        
        $this->assertArrayHasKey('buy', $result);
        $this->assertArrayHasKey('sell', $result);
        $this->assertSame('4.35', $result['buy']);  // 4.50 - 0.15
        $this->assertSame('4.61', $result['sell']); // 4.50 + 0.11
    }

    public function testMidToBuySellUSD(): void
    {
        $result = $this->calculator->midToBuySell('USD', '4.25');
        
        $this->assertSame('4.10', $result['buy']);  // 4.25 - 0.15
        $this->assertSame('4.36', $result['sell']); // 4.25 + 0.11
    }

    public function testMidToBuySellCZK(): void
    {
        $result = $this->calculator->midToBuySell('CZK', '0.18');
        
        $this->assertNull($result['buy']);           // No buy rate for CZK
        $this->assertSame('0.38', $result['sell']); // 0.18 + 0.20
    }

    public function testMidToBuySellIDR(): void
    {
        $result = $this->calculator->midToBuySell('IDR', '0.000275');
        
        $this->assertNull($result['buy']);              // No buy rate for IDR
        $this->assertSame('0.20', $result['sell']);    // 0.000275 + 0.20
    }

    public function testMidToBuySellBRL(): void
    {
        $result = $this->calculator->midToBuySell('BRL', '0.85');
        
        $this->assertNull($result['buy']);           // No buy rate for BRL
        $this->assertSame('1.05', $result['sell']); // 0.85 + 0.20
    }

    public function testMidToBuySellCaseInsensitive(): void
    {
        $result1 = $this->calculator->midToBuySell('eur', '4.50');
        $result2 = $this->calculator->midToBuySell('EUR', '4.50');
        $result3 = $this->calculator->midToBuySell('Eur', '4.50');
        
        $this->assertSame($result2, $result1);
        $this->assertSame($result2, $result3);
    }

    public function testMidToBuySellWithWhitespace(): void
    {
        $result = $this->calculator->midToBuySell(' EUR ', '4.50');
        
        $this->assertSame('4.35', $result['buy']);
        $this->assertSame('4.61', $result['sell']);
    }

    public function testMidToBuySellBoundaryOffsets(): void
    {
        // Test with mid rate that creates boundary cases after offset
        $result = $this->calculator->midToBuySell('EUR', '0.14995');
        
        // 0.14995 - 0.15 = -0.00005, 0.14995 + 0.11 = 0.25995
        $this->assertSame('0.00', $result['buy']);   // Very small negative rounds to 0.00
        $this->assertSame('0.26', $result['sell']);
    }

    public function testMidToBuySellPrecisionBoundaries(): void
    {
        // Test precise calculations with boundary values
        $result = $this->calculator->midToBuySell('EUR', '1.5005');
        
        // 1.5005 - 0.15 = 1.3505, 1.5005 + 0.11 = 1.6105
        $this->assertSame('1.35', $result['buy']);   // 1.3505 -> rounds to 1.35
        $this->assertSame('1.61', $result['sell']);  // 1.6105 -> rounds to 1.61
    }

    public function testMidToBuySellUnsupportedCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported currency code: GBP');
        $this->calculator->midToBuySell('GBP', '5.00');
    }

    public function testMidToBuySellInvalidMidRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculator->midToBuySell('EUR', 'invalid');
    }

    // Integration Tests

    public function testRoundTripConversion(): void
    {
        // Test that parseDecimalToScaled -> formatPln preserves precision
        $original = '4.23';
        $scaled = $this->calculator->parseDecimalToScaled($original);
        $formatted = $this->calculator->formatPln($scaled);
        
        $this->assertSame($original, $formatted);
    }

    public function testFullWorkflowWithRealRates(): void
    {
        // Test complete workflow with realistic NBP rates
        $eurMid = '4.3245';
        $usdMid = '3.9876';
        $czkMid = '0.1789';
        
        $eurRates = $this->calculator->midToBuySell('EUR', $eurMid);
        $usdRates = $this->calculator->midToBuySell('USD', $usdMid);
        $czkRates = $this->calculator->midToBuySell('CZK', $czkMid);
        
        // Verify EUR rates
        $this->assertSame('4.17', $eurRates['buy']);  // 4.3245 - 0.15
        $this->assertSame('4.43', $eurRates['sell']); // 4.3245 + 0.11
        
        // Verify USD rates
        $this->assertSame('3.84', $usdRates['buy']);  // 3.9876 - 0.15
        $this->assertSame('4.10', $usdRates['sell']); // 3.9876 + 0.11
        
        // Verify CZK rates
        $this->assertNull($czkRates['buy']);
        $this->assertSame('0.38', $czkRates['sell']); // 0.1789 + 0.20
    }
}
