<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Rate calculator using scaled-integer arithmetic to avoid floating-point precision issues.
 * All internal calculations use integers with a scale factor of 10,000.
 */
final class RateCalculator
{
    private const DEFAULT_SCALE = 10000;

    // Business rule constants (scaled integers)
    private const EUR_USD_BUY_OFFSET = 1500;  // -0.15 PLN * 10000
    private const EUR_USD_SELL_OFFSET = 1100; // +0.11 PLN * 10000
    private const OTHER_SELL_OFFSET = 2000;   // +0.20 PLN * 10000

    // Supported currencies with buy/sell rules
    private const CURRENCY_RULES = [
        'EUR' => ['has_buy' => true, 'has_sell' => true],
        'USD' => ['has_buy' => true, 'has_sell' => true],
        'CZK' => ['has_buy' => false, 'has_sell' => true],
        'IDR' => ['has_buy' => false, 'has_sell' => true],
        'BRL' => ['has_buy' => false, 'has_sell' => true],
    ];

    /**
     * Converts a decimal string to scaled integer.
     *
     * @param string $decimal Decimal number as string (e.g., "4.2345")
     * @param int    $scale   Scale factor (default: 10000)
     *
     * @return int Scaled integer representation
     *
     * @throws \InvalidArgumentException if the decimal string is empty or invalid
     */
    public function parseDecimalToScaled(string $decimal, int $scale = self::DEFAULT_SCALE): int
    {
        // Handle edge cases
        if (trim($decimal) === '') {
            throw new \InvalidArgumentException('Decimal string cannot be empty');
        }

        // Validate decimal format
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', trim($decimal))) {
            throw new \InvalidArgumentException('Invalid decimal format: ' . $decimal);
        }

        // Use bcmul for precise multiplication to avoid float precision issues
        $result = bcmul(trim($decimal), (string) $scale, 0);

        // Convert to integer (bcmul returns string)
        return (int) $result;
    }

    /**
     * Formats a scaled integer as PLN string with 2 decimal places.
     *
     * @param int $scaled Scaled integer value
     * @param int $scale  Scale factor (default: 10000)
     *
     * @return string Formatted PLN string (e.g., "4.23")
     */
    public function formatPln(int $scaled, int $scale = self::DEFAULT_SCALE): string
    {
        // Use bcdiv for precise division
        $result = bcdiv((string) $scaled, (string) $scale, 4);

        // Properly round to 2 decimal places using bcadd with rounding
        // Add 0.005 for positive numbers, subtract 0.005 for negative numbers to round correctly
        if (bccomp($result, '0', 4) >= 0) {
            $rounded = bcadd($result, '0.005', 4);
        } else {
            $rounded = bcsub($result, '0.005', 4);
        }

        // Truncate to 2 decimal places after rounding
        $truncated = bcadd($rounded, '0', 2);

        return number_format((float) $truncated, 2, '.', '');
    }

    /**
     * Calculates buy and sell rates from NBP mid rate according to business rules.
     *
     * @param string $code Currency code (EUR, USD, CZK, IDR, BRL)
     * @param string $mid  NBP mid rate as decimal string
     *
     * @return array Array with 'buy' and 'sell' keys (null if not available)
     *
     * @throws \InvalidArgumentException If currency code is not supported
     */
    public function midToBuySell(string $code, string $mid): array
    {
        $code = strtoupper(trim($code));

        if (!isset(self::CURRENCY_RULES[$code])) {
            throw new \InvalidArgumentException('Unsupported currency code: ' . $code);
        }

        $rules = self::CURRENCY_RULES[$code];
        $midScaled = $this->parseDecimalToScaled($mid);

        $result = ['buy' => null, 'sell' => null];

        // Calculate buy rate (EUR/USD only)
        if ($rules['has_buy']) {
            $buyScaled = $midScaled - self::EUR_USD_BUY_OFFSET;
            $result['buy'] = $this->formatPln($buyScaled);
        }

        // Calculate sell rate (all currencies)
        if ($rules['has_sell']) {
            if ($code === 'EUR' || $code === 'USD') {
                $sellScaled = $midScaled + self::EUR_USD_SELL_OFFSET;
            } else {
                $sellScaled = $midScaled + self::OTHER_SELL_OFFSET;
            }
            $result['sell'] = $this->formatPln($sellScaled);
        }

        return $result;
    }
}
