<?php

declare(strict_types=1);

namespace App\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * NBP API client designed around NBP's official business day publication schedule.
 * One table publication per business day (~noon Warsaw time), not live rates.
 */
final class NbpClient implements NbpClientInterface
{
    private const BASE_URL = 'https://api.nbp.pl/api';
    private const MAX_HISTORY_DAYS = 93; // NBP API limit

    // TTL constants (in seconds)
    private const WEEKEND_TTL = 14400;     // 4 hours for weekends/holidays
    private const PRE_PUBLISH_TTL = 3600;  // 1 hour before publish window
    private const PUBLISH_WINDOW_TTL = 300; // 5 minutes during publish window
    private const POST_PUBLISH_TTL = 7200; // 2 hours after publish window
    private const TTL_JITTER_PERCENT = 20; // ±20% jitter

    // Stale-if-error settings
    private const STALE_IF_ERROR_WINDOW = 600; // 10 minutes past TTL

    private ClientInterface $httpClient;
    private CacheInterface $cache;

    public function __construct(ClientInterface $httpClient, CacheInterface $cache)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
    }

    /**
     * Retrieves current Table A exchange rates.
     * Returns the latest published table (once per business day).
     *
     * @return array Table A structure: {table, no, effectiveDate, asOf, etag, rates}
     *
     * @throws NbpClientException When API request fails or data is unavailable
     */
    public function getCurrentTableA(): array
    {
        $mainKey = 'nbp_tableA';
        $lastGoodKey = 'nbp_tableA_lastGood';

        try {
            return $this->cache->get($mainKey, function (ItemInterface $item) use ($lastGoodKey): array {
                // Set publish-aware TTL
                $ttlWithJitter = $this->computePublishAwareTtl($this->nowWaw());
                $item->expiresAfter($ttlWithJitter);

                // Fetch fresh data
                $data = $this->fetchTableAFromApi();

                // Update lastGood cache on success (for potential future use)
                $this->updateLastGood($lastGoodKey, $data, $ttlWithJitter);

                return $data;
            }, 1.5); // Beta factor for singleflight/anti-stampede
        } catch (\Throwable $e) {
            $originalException = $e instanceof NbpClientException ? $e : new NbpClientException('Fetch failed', 0, $e);
            return $this->lastGoodOrThrow($lastGoodKey, $originalException);
        }
    }

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
    public function getHistory(string $code, string $startDate, string $endDate): array
    {
        $this->validateDateRange($startDate, $endDate);

        $code = strtoupper(trim($code));
        $cacheKey = sprintf('nbp_history_%s_%s_%s', $code, $startDate, $endDate);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($code, $startDate, $endDate): array {
            // Historical data TTL: longer for older data, publish-aware for recent data
            $ttl = $this->calculateHistoryTtl($endDate);
            $item->expiresAfter($ttl);

            return $this->fetchHistoryFromApi($code, $startDate, $endDate);
        }, 1.5);
    }

    /**
     * Returns current time in Europe/Warsaw timezone.
     */
    private function nowWaw(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Europe/Warsaw'));
    }

    private function computePublishAwareTtl(\DateTimeImmutable $nowWaw): int
    {
        [$baseTtl, $nextChangeAt] = $this->phaseBaseTtlAndNextChange($nowWaw);

        // Optional: small guard so we expire *before* the boundary
        $GUARD = 60; // seconds

        if ($nextChangeAt !== null) {
            $secondsToBoundary = max(1, $nextChangeAt->getTimestamp() - $nowWaw->getTimestamp() - $GUARD);
            $cap = $secondsToBoundary;
            $ttl = min($baseTtl, $cap);
            return $this->addJitterCapped($ttl, $cap);
        }

        // No boundary to care about (e.g., post-publish / weekend)
        return $this->addJitter($baseTtl);
    }

    /**
     * Returns [baseTtl, nextChangeAt] where nextChangeAt is the next phase boundary,
     * or null when no cap is needed (post-publish/weekend).
     */
    private function phaseBaseTtlAndNextChange(\DateTimeImmutable $nowWaw): array
    {
        $dow = (int) $nowWaw->format('w'); // 0 Sun ... 6 Sat
        $h = (int) $nowWaw->format('H');
        $m = (int) $nowWaw->format('i');
        $minutes = $h * 60 + $m;

        // Weekend: use weekend TTL, no boundary cap
        if ($dow === 0 || $dow === 6) {
            return [self::WEEKEND_TTL, null];
        }

        // Build today's 11:30 and 12:30 boundaries
        $today = $nowWaw->setTime(0, 0);
        $at1130 = $today->setTime(11, 30);
        $at1230 = $today->setTime(12, 30);

        if ($minutes < 11 * 60 + 30) {
            // Pre-publish → boundary is 11:30 today
            return [self::PRE_PUBLISH_TTL, $at1130];
        }

        if ($minutes < 12 * 60 + 30) {
            // Publish window → boundary is 12:30 today
            return [self::PUBLISH_WINDOW_TTL, $at1230];
        }

        // Post-publish: generous TTL; no strict boundary
        return [self::POST_PUBLISH_TTL, null];
    }

    /** Apply jitter but never exceed the cap. */
    private function addJitterCapped(int $ttl, int $cap): int
    {
        // If we’re close to a boundary, prefer non-positive jitter so we don’t overshoot
        $jittered = $this->addJitter($ttl);
        return max(1, min($jittered, $cap));
    }

    /**
     * Adds random jitter to TTL to prevent cache stampedes.
     *
     * @param int $baseTtl Base TTL value in seconds
     * @return int TTL with ±20% jitter applied (minimum 1 second)
     */
    private function addJitter(int $baseTtl): int
    {
        $jitterRange = (int) ($baseTtl * self::TTL_JITTER_PERCENT / 100);
        $jitter = random_int(-$jitterRange, $jitterRange);
        return max(1, $baseTtl + $jitter);
    }

    /**
     * Calculates TTL for historical data.
     * Recent data (≤7 days) uses publish-aware TTL, older data uses longer TTL.
     */
    private function calculateHistoryTtl(string $endDate): int
    {
        $endDateTime = new \DateTime($endDate);
        $now = $this->nowWaw();
        $daysDiff = $now->diff($endDateTime)->days;

        // Recent data: use publish-aware TTL
        if ($daysDiff <= 7) {
            return $this->computePublishAwareTtl($this->nowWaw());
        }

        // Older data: longer TTL (historical rates rarely change)
        return $this->addJitter(3600); // 1 hour with jitter
    }

    /**
     * Updates lastGood cache with successful data for fallback purposes.
     */
    private function updateLastGood(string $key, array $payload, int $ttl): void
    {
        $lastGoodData = [
            'data' => $payload,
            'ts' => time(),
            'ttl' => $ttl,
        ];

        // Store with 24-hour TTL and always overwrite (beta=INF)
        $this->cache->get(
            $key,
            function (ItemInterface $item) use ($lastGoodData): array {
                $item->expiresAfter(86400); // 24 hours
                return $lastGoodData;
            },
            \INF // Always recompute/overwrite on success
        );
    }

    /**
     * Fetches Table A data from NBP API with retry logic.
     */
    private function fetchTableAFromApi(): array
    {
        $url = self::BASE_URL . '/exchangerates/tables/A/';

        $maxRetries = 2;
        $lastException = null;

        for ($retry = 0; $retry <= $maxRetries; $retry++) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'headers' => ['Accept' => 'application/json'],
                        'timeout' => 3.0,
                        'connect_timeout' => 0.3,
                        'http_errors' => false,
                    ]);

                $statusCode = $response->getStatusCode();

                // Handle specific HTTP status codes
                if ($statusCode === 404) {
                    throw new NbpClientException('Table A data not available (404)');
                }

                // Retry on server errors and 429
                if (in_array($statusCode, [429, 502, 503, 504]) && $retry < $maxRetries) {
                    $this->backoffDelay($retry);
                    continue;
                }

                if ($statusCode >= 400) {
                    throw new NbpClientException(sprintf('NBP API HTTP error: %d', $statusCode));
                }

                // Parse JSON response
                $responseContent = (string) $response->getBody();
                try {
                    $data = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $je) {
                    throw new NbpClientException('Invalid JSON response from NBP API', 0, $je);
                }

                // Validate response structure
                if (!is_array($data) || empty($data) || !isset($data[0]['rates'])) {
                    throw new NbpClientException('Invalid API response format for Table A');
                }

                $tableData = $data[0];

                // Validate required fields (table is mandatory, others optional for backward compatibility)
                if (!isset($tableData['table'])) {
                    throw new NbpClientException('Missing required table field in Table A response');
                }

                // Build standardized response
                $result = [
                    'table' => (string) $tableData['table'],
                    'no' => (string) ($tableData['no'] ?? ''),
                    'effectiveDate' => (string) ($tableData['effectiveDate'] ?? ''),
                    'asOf' => gmdate('c'), // Server fetch time in UTC
                    'etag' => sha1($tableData['table'] . ':' . ($tableData['no'] ?? '') . ':' . ($tableData['effectiveDate'] ?? '')),
                    'rates' => [],
                ];

                // Process rates
                foreach ($tableData['rates'] as $rate) {
                    if (isset($rate['code'], $rate['mid'])) {
                        $result['rates'][$rate['code']] = (string) $rate['mid'];
                    }
                }

                return $result;

            } catch (ConnectException $e) {
                $lastException = $e;
                if ($retry < $maxRetries) {
                    $this->backoffDelay($retry);
                    continue;
                }
            } catch (RequestException $e) {
                $lastException = $e;
                if ($retry < $maxRetries) {
                    $this->backoffDelay($retry);
                    continue;
                }
            } catch (NbpClientException $e) {
                // Don't retry on application-level errors
                throw $e;
            }
        }

        // All retries failed
        if ($lastException instanceof ConnectException) {
            throw new NbpClientException('NBP API connection timeout or network error', 0, $lastException);
        } elseif ($lastException instanceof RequestException) {
            throw new NbpClientException('NBP API request failed: ' . $lastException->getMessage(), 0, $lastException);
        } else {
            throw new NbpClientException('NBP API request failed after retries');
        }
    }

    /**
     * Fetches historical data from NBP API with retry logic.
     */
    private function fetchHistoryFromApi(string $code, string $startDate, string $endDate): array
    {
        $url = sprintf(
            '%s/exchangerates/rates/A/%s/%s/%s/',
            self::BASE_URL,
            $code,
            $startDate,
            $endDate
        );

        $maxRetries = 2;
        $lastException = null;

        for ($retry = 0; $retry <= $maxRetries; $retry++) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'headers' => ['Accept' => 'application/json'],
                        'timeout' => 3.0,
                        'connect_timeout' => 0.3,
                        'http_errors' => false,
                    ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode === 404) {
                    throw new NbpClientException(
                        sprintf('No data available for %s in period %s to %s (404)', $code, $startDate, $endDate)
                    );
                }

                // Retry on server errors and 429
                if (in_array($statusCode, [429, 502, 503, 504]) && $retry < $maxRetries) {
                    $this->backoffDelay($retry);
                    continue;
                }

                if ($statusCode >= 400) {
                    throw new NbpClientException(sprintf('NBP API HTTP error: %d', $statusCode));
                }

                // Parse JSON response
                $responseContent = (string) $response->getBody();
                try {
                    $data = json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $je) {
                    throw new NbpClientException('Invalid JSON response from NBP API', 0, $je);
                }

                if (!is_array($data) || !isset($data['rates']) || !is_array($data['rates'])) {
                    throw new NbpClientException(sprintf('Invalid API response format for %s history', $code));
                }

                $result = [];
                foreach ($data['rates'] as $rate) {
                    if (isset($rate['effectiveDate'], $rate['mid'])) {
                        $result[] = [
                            'date' => $rate['effectiveDate'],
                            'mid' => (string) $rate['mid'],
                        ];
                    }
                }

                return $result;

            } catch (ConnectException $e) {
                $lastException = $e;
                if ($retry < $maxRetries) {
                    $this->backoffDelay($retry);
                    continue;
                }
            } catch (RequestException $e) {
                $lastException = $e;
                if ($retry < $maxRetries) {
                    $this->backoffDelay($retry);
                    continue;
                }
            } catch (NbpClientException $e) {
                // Don't retry on application-level errors
                throw $e;
            }
        }

        // All retries failed
        if ($lastException instanceof ConnectException) {
            throw new NbpClientException('NBP API connection timeout or network error', 0, $lastException);
        } elseif ($lastException instanceof RequestException) {
            throw new NbpClientException('NBP API request failed: ' . $lastException->getMessage(), 0, $lastException);
        } else {
            throw new NbpClientException('NBP API request failed after retries');
        }
    }

    /**
     * Implements exponential backoff delay for retries.
     */
    private function backoffDelay(int $retryNumber): void
    {
        $delay = min(1000000 * (2 ** $retryNumber), 3000000); // 1s, 2s, max 3s in microseconds
        usleep($delay);
    }

    /**
     * Validates date range according to NBP API limitations.
     *
     * @throws NbpClientException When date format is invalid or range exceeds 93 days
     */
    private function validateDateRange(string $startDate, string $endDate): void
    {
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            throw new NbpClientException('Date must be in YYYY-MM-DD format');
        }

        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
        } catch (\Exception $e) {
            throw new NbpClientException('Invalid date format', 0, $e);
        }

        if ($start > $end) {
            throw new NbpClientException('Start date must be before or equal to end date');
        }

        $daysDiff = $start->diff($end)->days;
        if ($daysDiff > self::MAX_HISTORY_DAYS) {
            throw new NbpClientException(
                sprintf(
                    'Date range cannot exceed %d days (requested: %d days)',
                    self::MAX_HISTORY_DAYS,
                    $daysDiff
                )
            );
        }
    }

    /**
     * Returns lastGood data or throws the original exception if no data is available.
     *
     * @throws NbpClientException When no data is available or data is too old
     */
    private function lastGoodOrThrow(string $key, \Throwable $originalException): array
    {
        $lastGood = $this->cache->get($key, function (ItemInterface $item): ?array {
            $item->expiresAfter(1); // don't poison cache with null
            return null;
        });

        if (!is_array($lastGood) || !isset($lastGood['data'], $lastGood['ts'], $lastGood['ttl'])) {
            // No lastGood data available, throw the original exception
            if ($originalException instanceof NbpClientException) {
                throw $originalException;
            }
            throw new NbpClientException('No lastGood data available', 0, $originalException);
        }

        $age = time() - $lastGood['ts'];
        $maxAge = $lastGood['ttl'] + self::STALE_IF_ERROR_WINDOW;
        if ($age <= $maxAge) {
            return $lastGood['data'];
        }
        
        // LastGood data is too old, throw the original exception
        if ($originalException instanceof NbpClientException) {
            throw $originalException;
        }
        throw new NbpClientException('LastGood data too old', 0, $originalException);
    }
}

/**
 * Exception thrown by NbpClient when API operations fail.
 */
class NbpClientException extends \Exception
{
}
