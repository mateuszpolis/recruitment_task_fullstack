<?php

declare(strict_types=1);

namespace App\Service;

interface NbpClientInterface
{
    /**
     * Retrieves current Table A exchange rates.
     *
     * @return array Table A structure: {table, no, effectiveDate, asOf, etag, rates}
     *
     * @throws NbpClientException When API request fails or data is unavailable
     */
    public function getCurrentTableA(): array;

    /**
     * Retrieves historical exchange rates.
     *
     * @param string $code      3-letter currency code (e.g., EUR, USD)
     * @param string $startDate Start date in YYYY-MM-DD format
     * @param string $endDate   End date in YYYY-MM-DD format
     *
     * @return array List of {date: string, mid: string} objects
     *
     * @throws NbpClientException When API request fails, date range is invalid, or data is unavailable
     */
    public function getHistory(string $code, string $startDate, string $endDate): array;
}
