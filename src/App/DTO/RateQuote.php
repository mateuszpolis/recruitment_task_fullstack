<?php

declare(strict_types=1);

namespace App\DTO;

final class RateQuote
{
    public function __construct(
        public readonly string $code,
        public readonly float $mid,
        public readonly ?float $buy,
        public readonly ?float $sell,
        public readonly string $effectiveDate
    ) {
    }

    /**
     * @return array{code: string, mid: float, buy: float|null, sell: float|null, effectiveDate: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'mid' => $this->mid,
            'buy' => $this->buy,
            'sell' => $this->sell,
            'effectiveDate' => $this->effectiveDate,
        ];
    }
}
