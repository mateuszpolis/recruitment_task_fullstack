<?php

declare(strict_types=1);

namespace App\DTO;

final class RateQuote
{
    public function __construct(
        public readonly string $code,
        public readonly string $mid,
        public readonly ?string $buy,
        public readonly ?string $sell,
        public readonly string $effectiveDate
    ) {
    }

    /**
     * @return array{code: string, mid: string, buy: string|null, sell: string|null, effectiveDate: string}
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
