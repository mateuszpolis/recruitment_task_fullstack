<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Rate calculator using scaled-integer arithmetic to avoid floating-point precision issues.
 * All internal calculations use integers with a scale factor of 10,000.
 */
final class RateCalculator
{
    // Scaled integers for unit rates and PLN totals
    private const RATE_SCALE = 10000;     // 4 d.p. for unit rates
    private const TOTAL_SCALE = 100;      // 2 d.p. for PLN totals (grosz)

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
    public function parseDecimalToScaled(string $decimal, int $scale = self::RATE_SCALE): int
    {
        // Handle edge cases
        if (trim($decimal) === '') {
            throw new \InvalidArgumentException('Decimal string cannot be empty');
        }

        // Validate decimal format
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', trim($decimal))) {
            throw new \InvalidArgumentException('Invalid decimal format: ' . $decimal);
        }

        // Fallback to regular multiplication since BC Math is not available
        $result = (float) trim($decimal) * $scale;

        // Convert to integer
        return (int) round($result);
    }


    /**
     * Converts a NBP mid rate to buy/sell rates.
     *
     * @param string $code Currency code (EUR, USD, CZK, IDR, BRL)
     * @param string $mid  NBP mid rate as decimal string
     *
     * @return array Array with 'buy' and 'sell' as scaled integers
     */
    public function midToBuySellInt(string $code, string $mid): array
    {
        $code = strtoupper(trim($code));
        if (!isset(self::CURRENCY_RULES[$code])) {
            throw new \InvalidArgumentException('Unsupported currency code: ' . $code);
        }
        $rules = self::CURRENCY_RULES[$code];
        $midScaled = $this->toScaledInt($mid, self::RATE_SCALE);

        $buy = null; $sell = null;
        if ($rules['has_buy']) {
            $buy = $midScaled - self::EUR_USD_BUY_OFFSET;    // offsets already in RATE_SCALE
        }
        if ($rules['has_sell']) {
            $sell = $midScaled + (($code === 'EUR' || $code === 'USD') ? self::EUR_USD_SELL_OFFSET : self::OTHER_SELL_OFFSET);
        }
        return ['buy' => $buy, 'sell' => $sell]; // scaled ints
    }

    /**
     * Converts a NBP mid rate to buy/sell rates.
     *
     * @param string $code Currency code (EUR, USD, CZK, IDR, BRL)
     * @param string $mid  NBP mid rate as decimal string
     *
     * @return array Array with 'buy' and 'sell' as formatted strings
     */
    public function midToBuySell(string $code, string $mid): array
    {
        $r = $this->midToBuySellInt($code, $mid);
        return [
            'buy'  => $r['buy']  === null ? null : $this->formatScaled($r['buy'],  self::RATE_SCALE, 4),
            'sell' => $r['sell'] === null ? null : $this->formatScaled($r['sell'], self::RATE_SCALE, 4),
        ];
    }

    /**
     * Calculates a quote for currency exchange.
     *
     * @param string $code   Currency code (EUR, USD, CZK, IDR, BRL)
     * @param string $mid    NBP mid rate as decimal string
     * @param string $side   Either 'buy' or 'sell'
     * @param string $amount Amount to exchange as decimal string
     *
     * @return array Array with 'unitRate' and 'total' as formatted strings
     *
     * @throws \InvalidArgumentException If currency code is not supported, side is invalid, or operation not supported
     */
    public function calculateQuote(string $code, string $mid, string $side, string $amount): array
    {
        $code = strtoupper(trim($code));
        $side = strtolower(trim($side));

        if (!in_array($side, ['buy', 'sell'], true)) {
            throw new \InvalidArgumentException('Side must be either "buy" or "sell"');
        }
        if (!preg_match('/^(?!0+(?:[.,]0{1,2})?$)\d+(?:[.,]\d{1,2})?$/', trim($amount))) {
            throw new \InvalidArgumentException('Amount must be a positive number with at most 2 decimal places');
        }
        $amountScaled = $this->toScaledInt($amount, 100); // amount with 2 d.p.

        $rates = $this->midToBuySellInt($code, $mid);
        if ($rates[$side] === null) {
            throw new \InvalidArgumentException(sprintf('%s operations are not supported for %s', ucfirst($side), $code));
        }
        $unitRateScaled = $rates[$side]; // PLN * 10000 per 1 unit

        // total_grosz = round_half_up( amount_scaled * unit_rate_scaled / 10000 )
        $product = $amountScaled * $unitRateScaled;            // scale = 100 * 10000
        $totalGrosz = intdiv($product + 5000, 10000);          // +5000 for half-up

        $unitRateStr = $this->formatScaled($unitRateScaled, self::RATE_SCALE, 4); // 4 d.p. rate
        $totalStr    = $this->formatScaled($totalGrosz, self::TOTAL_SCALE, 2);    // PLN total 2 d.p.

        return [
            'unitRate' => $unitRateStr,
            'total'    => $totalStr,
        ];
    }

    /**
     * Converts a decimal string to scaled integer.
     *
     * @param string $decimal Decimal number as string (e.g., "4.2345")
     * @param int    $scale   Scale factor (default: 10000)
     *
     * @return int Scaled integer representation
     */
    private function toScaledInt(string $decimal, int $scale): int
    {
        $s = trim($decimal);
        if ($s === '') throw new \InvalidArgumentException('Decimal string cannot be empty');
        if (!preg_match('/^[+-]?\d+(?:[.,]\d+)?$/', $s)) {
            throw new \InvalidArgumentException('Invalid decimal format: ' . $decimal);
        }

        // normalize comma to dot
        $s = str_replace(',', '.', $s);

        $neg = $s[0] === '-';
        if ($s[0] === '+' || $s[0] === '-') $s = substr($s, 1);

        [$int, $frac] = array_pad(explode('.', $s, 2), 2, '');    
        $intPart = ltrim($int, '0'); if ($intPart === '') $intPart = '0';

        $scaleDigits = (int)log10($scale); // e.g., 4 for 10000
        $frac = substr($frac . str_repeat('0', $scaleDigits), 0, $scaleDigits);

        // half-up rounding beyond scale
        $nextDigit = strlen($decimal) && preg_match('/\.(\d{'.($scaleDigits+1).'})/', str_replace(',', '.', trim($decimal)), $m)
            ? (int)$m[1][$scaleDigits] : 0;

        $scaled = (int)$intPart * $scale + (int)$frac;
        if ($nextDigit >= 5) $scaled += 1;

        return $neg ? -$scaled : $scaled;
    }

    /**
     * Formats a scaled integer as a decimal string.
     *
     * @param int $scaled     Scaled integer value
     * @param int $scale      Scale factor
     * @param int $decimals   Number of decimal places
     *
     * @return string Formatted decimal string
     */
    private function formatScaled(int $scaled, int $scale, int $decimals): string
    {
        $neg = $scaled < 0 ? '-' : '';
        $scaled = abs($scaled);
        $scaleDigits = (int)log10($scale);
        $intPart = intdiv($scaled, $scale);
        $fracPart = $scaled % $scale;

        $fracStr = str_pad((string)$fracPart, $scaleDigits, '0', STR_PAD_LEFT);
        if ($decimals < $scaleDigits) {
            // round half up to desired decimals
            $roundDigit = (int)$fracStr[$decimals] ?? 0;
            $fracStr = substr($fracStr, 0, $decimals);
            if ($roundDigit >= 5) {
                $carry = 1;
                for ($i = $decimals - 1; $i >= 0 && $carry; $i--) {
                    $d = (int)$fracStr[$i] + 1;
                    $fracStr[$i] = (string)($d % 10);
                    $carry = $d >= 10 ? 1 : 0;
                }
                if ($carry) $intPart += 1;
            }
        } else {
            $fracStr = str_pad($fracStr, $decimals, '0', STR_PAD_RIGHT);
        }

        return $decimals === 0 ? $neg.$intPart : $neg.$intPart.'.'.$fracStr;
    }
}
