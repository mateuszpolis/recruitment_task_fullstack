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

  
    // midToBuySell Tests

    public function testMidToBuySellEUR(): void
    {
        $result = $this->calculator->midToBuySell('EUR', '4.50');

        $this->assertArrayHasKey('buy', $result);
        $this->assertArrayHasKey('sell', $result);
        $this->assertSame('4.3500', $result['buy']);  // 4.50 - 0.15
        $this->assertSame('4.6100', $result['sell']); // 4.50 + 0.11
    }

    public function testMidToBuySellUSD(): void
    {
        $result = $this->calculator->midToBuySell('USD', '4.25');

        $this->assertSame('4.1000', $result['buy']);  // 4.25 - 0.15
        $this->assertSame('4.3600', $result['sell']); // 4.25 + 0.11
    }

    public function testMidToBuySellCZK(): void
    {
        $result = $this->calculator->midToBuySell('CZK', '0.18');

        $this->assertNull($result['buy']);           // No buy rate for CZK
        $this->assertSame('0.3800', $result['sell']); // 0.18 + 0.20
    }

    public function testMidToBuySellIDR(): void
    {
        $result = $this->calculator->midToBuySell('IDR', '0.000275');

        $this->assertNull($result['buy']);              // No buy rate for IDR
        $this->assertSame('0.2003', $result['sell']);    // 0.000275 + 0.20
    }

    public function testMidToBuySellBRL(): void
    {
        $result = $this->calculator->midToBuySell('BRL', '0.85');

        $this->assertNull($result['buy']);           // No buy rate for BRL
        $this->assertSame('1.0500', $result['sell']); // 0.85 + 0.20
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

        $this->assertSame('4.3500', $result['buy']);
        $this->assertSame('4.6100', $result['sell']);
    }

    public function testMidToBuySellBoundaryOffsets(): void
    {
        // Test with mid rate that creates boundary cases after offset
        $result = $this->calculator->midToBuySell('EUR', '0.14995');

        // 0.14995 - 0.15 = -0.00005, 0.14995 + 0.11 = 0.25995
        $this->assertSame('0.0000', $result['buy']);   // Very small negative rounds to 0.00
        $this->assertSame('0.2600', $result['sell']);
    }

    public function testMidToBuySellPrecisionBoundaries(): void
    {
        // Test precise calculations with boundary values
        $result = $this->calculator->midToBuySell('EUR', '1.5005');

        // 1.5005 - 0.15 = 1.3505, 1.5005 + 0.11 = 1.6105
        $this->assertSame('1.3505', $result['buy']);   // 1.3505 -> rounds to 1.35
        $this->assertSame('1.6105', $result['sell']);  // 1.6105 -> rounds to 1.61
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
        $this->assertSame('4.1745', $eurRates['buy']);  // 4.3245 - 0.15
        $this->assertSame('4.4345', $eurRates['sell']); // 4.3245 + 0.11

        // Verify USD rates
        $this->assertSame('3.8376', $usdRates['buy']);  // 3.9876 - 0.15
        $this->assertSame('4.0976', $usdRates['sell']); // 3.9876 + 0.11

        // Verify CZK rates
        $this->assertNull($czkRates['buy']);
        $this->assertSame('0.3789', $czkRates['sell']); // 0.1789 + 0.20
    }

    public function testCalculateQuoteEurBuy(): void
    {
        $result = $this->calculator->calculateQuote('EUR', '4.2500', 'buy', '100.00');

        $this->assertSame('4.1000', $result['unitRate']); // 4.25 - 0.15
        $this->assertSame('410.00', $result['total']); // 100 * 4.10
    }

    public function testCalculateQuoteEurSell(): void
    {
        $result = $this->calculator->calculateQuote('EUR', '4.2500', 'sell', '50.25');

        $this->assertSame('4.3600', $result['unitRate']); // 4.25 + 0.11
        $this->assertSame('219.09', $result['total']); // 50.25 * 4.36
    }

    public function testCalculateQuoteUsdBuy(): void
    {
        $result = $this->calculator->calculateQuote('USD', '3.9800', 'buy', '200.00');

        $this->assertSame('3.8300', $result['unitRate']);
        $this->assertSame('766.00', $result['total']);
    }

    public function testCalculateQuoteCzkSell(): void
    {
        $result = $this->calculator->calculateQuote('CZK', '0.1750', 'sell', '75.50');

        $this->assertSame('0.3750', $result['unitRate']);
        $this->assertSame('28.31', $result['total']);
    }

    public function testCalculateQuoteCzkBuyNotSupported(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Buy operations are not supported for CZK');

        $this->calculator->calculateQuote('CZK', '0.1750', 'buy', '100.00');
    }

    public function testCalculateQuoteInvalidSide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Side must be either "buy" or "sell"');

        $this->calculator->calculateQuote('EUR', '4.2500', 'invalid', '100.00');
    }

    public function testCalculateQuoteInvalidAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be a positive number with at most 2 decimal places');

        $this->calculator->calculateQuote('EUR', '4.2500', 'buy', 'invalid');
    }

    public function testCalculateQuoteZeroAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculateQuote('EUR', '4.2500', 'buy', '0.00');
    }

    public function testCalculateQuoteAmountPrecision(): void
    {
        // Test with amount having exactly 2 decimal places
        $result = $this->calculator->calculateQuote('EUR', '4.2500', 'buy', '123.45');
        $this->assertSame('4.1000', $result['unitRate']);
        $this->assertSame('506.15', $result['total']);

        // Test with amount having 1 decimal place
        $result = $this->calculator->calculateQuote('EUR', '4.2500', 'buy', '123.5');
        $this->assertSame('4.1000', $result['unitRate']);
        $this->assertSame('506.35', $result['total']);

        // Test with amount having no decimal places
        $result = $this->calculator->calculateQuote('EUR', '4.2500', 'buy', '123');
        $this->assertSame('4.1000', $result['unitRate']);
        $this->assertSame('504.30', $result['total']);
    }

    public function testCalculateQuoteAmountTooManyDecimals(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be a positive number with at most 2 decimal places');

        $this->calculator->calculateQuote('EUR', '4.2500', 'buy', '100.123');
    }

    public function testCalculateQuoteCaseInsensitive(): void
    {
        $result1 = $this->calculator->calculateQuote('eur', '4.2500', 'BUY', '100.00');
        $result2 = $this->calculator->calculateQuote('EUR', '4.2500', 'buy', '100.00');

        $this->assertSame($result2, $result1);
    }

    public function testCalculateQuoteWithWhitespace(): void
    {
        $result = $this->calculator->calculateQuote(' EUR ', '4.2500', ' buy ', ' 100.00 ');

        $this->assertSame('4.1000', $result['unitRate']);
        $this->assertSame('410.00', $result['total']);
    }

    public function testCalculateQuotePrecision(): void
    {
        // Test a case where floating point precision might be an issue
        $result = $this->calculator->calculateQuote('EUR', '4.1111', 'buy', '33.33');

        $this->assertSame('3.9611', $result['unitRate']);
        $this->assertSame('132.02', $result['total']);
    }
}
