<?php

declare(strict_types=1);

namespace App\DTO;

final class HistoryPoint
{
    public function __construct(
        public readonly string $date,
        public readonly float $mid,
        public readonly ?float $buy,
        public readonly ?float $sell
    ) {
    }

    /**
     * @return array{date: string, mid: float, buy: float|null, sell: float|null}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'mid' => $this->mid,
            'buy' => $this->buy,
            'sell' => $this->sell,
        ];
    }
}
