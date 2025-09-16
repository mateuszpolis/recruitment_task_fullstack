<?php

declare(strict_types=1);

namespace App\DTO;

final class HistoryPoint
{
    public function __construct(
        public readonly string $date,
        public readonly string $mid,
        public readonly ?string $buy,
        public readonly ?string $sell
    ) {
    }

    /**
     * @return array{date: string, mid: string, buy: string|null, sell: string|null}
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
