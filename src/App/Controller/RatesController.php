<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\HistoryPoint;
use App\DTO\RateQuote;
use App\Http\ErrorResponse;
use App\Service\NbpClientInterface;
use App\Service\NbpClientException;
use App\Service\RateCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RatesController extends AbstractController
{
    private const SUPPORTED_CURRENCIES = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];

    public function __construct(
        private readonly NbpClientInterface $nbpClient,
        private readonly RateCalculator $rateCalculator
    ) {
    }

    /**
     * GET /api/rates/current?codes=EUR,USD,CZK,IDR,BRL
     */
    public function getCurrentRates(Request $request): JsonResponse
    {
        try {
            // Parse and validate currency codes
            $codesParam = $request->query->get('codes', '');
            $requestedCodes = $this->parseAndValidateCurrencyCodes($codesParam);

            // Fetch current NBP data
            $tableData = $this->nbpClient->getCurrentTableA();
            $available = array_keys($tableData['rates'] ?? []);
            $missing = array_values(array_diff($requestedCodes, $available));
            
            if ($missing) {
                return $this->json(
                    ErrorResponse::from(
                        'CURRENCY_NOT_AVAILABLE',
                        sprintf('Currencies not available in current NBP data: %s', implode(', ', $missing))
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Build response with calculated buy/sell rates
            $rates = [];
            foreach ($requestedCodes as $code) {
                
                $mid = $tableData['rates'][$code];
                $buySell = $this->rateCalculator->midToBuySell($code, $mid);

                $rates[] = new RateQuote(
                    code: $code,
                    mid: (string) $mid,
                    buy: $buySell['buy'] !== null ? (string) $buySell['buy'] : null,
                    sell: $buySell['sell'] !== null ? (string) $buySell['sell'] : null,
                    effectiveDate: $tableData['effectiveDate']
                );
            }

            return $this->json([
                'effectiveDate' => $tableData['effectiveDate'],
                'fetchedAt' => $tableData['asOf'],
                'rates' => array_map(fn (RateQuote $rate) => $rate->toArray(), $rates),
            ]);

        } catch (NbpClientException $e) {
            return $this->json(
                ErrorResponse::from('NBP_API_ERROR', $e->getMessage()),
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ErrorResponse::from('VALIDATION_ERROR', $e->getMessage()),
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Throwable $e) {
            return $this->json(
                ErrorResponse::from('INTERNAL_ERROR', 'An unexpected error occurred'),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * GET /api/rates/{code}/history?date=YYYY-MM-DD&days=14
     */
    public function getHistoryRates(Request $request, string $code): JsonResponse
    {
        try {
            // Validate currency code
            $code = strtoupper(trim($code));
            if (!in_array($code, self::SUPPORTED_CURRENCIES, true)) {
                return $this->json(
                    ErrorResponse::from(
                        'UNSUPPORTED_CURRENCY',
                        sprintf('Currency %s is not supported', $code)
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Parse and validate date
            $dateParam = $request->query->get('date');
            $daysParam = $request->query->get('days', '14');

            if ($dateParam === null) {
                return $this->json(
                    ErrorResponse::from('VALIDATION_ERROR', 'Date parameter is required'),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $endDate = $this->validateAndParseDate($dateParam);
            $days = $this->validateDays($daysParam);

            // Calculate date range (14 days before the specified date)
            $startDate = $endDate->modify(sprintf('-%d days', $days - 1));

            // Fetch historical data
            $historyData = $this->nbpClient->getHistory(
                $code,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            // Build response with buy/sell rates for EUR/USD only
            $history = [];
            foreach ($historyData as $point) {
                $buySell = $this->rateCalculator->midToBuySell($code, $point['mid']);

                $history[] = new HistoryPoint(
                    date: $point['date'],
                    mid: (string) $point['mid'],
                    buy: $buySell['buy'] !== null ? (string) $buySell['buy'] : null,
                    sell: $buySell['sell'] !== null ? (string) $buySell['sell'] : null
                );
            }

            return $this->json([
                'code' => $code,
                'dateRange' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'days' => $days,
                ],
                'history' => array_map(fn (HistoryPoint $point) => $point->toArray(), $history),
            ]);

        } catch (NbpClientException $e) {
            return $this->json(
                ErrorResponse::from('NBP_API_ERROR', $e->getMessage()),
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ErrorResponse::from('VALIDATION_ERROR', $e->getMessage()),
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Throwable $e) {
            return $this->json(
                ErrorResponse::from('INTERNAL_ERROR', 'An unexpected error occurred'),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * POST /api/quote
     * Body: { "code": "EUR", "side": "buy"|"sell", "amount": "100.50" }
     */
    public function getQuote(Request $request): JsonResponse
    {
        try {
            // Parse JSON body
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                return $this->json(
                    ErrorResponse::from('VALIDATION_ERROR', 'Request body must be a JSON object'),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Validate required fields
            $requiredFields = ['code', 'side', 'amount'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                    return $this->json(
                        ErrorResponse::from(
                            'VALIDATION_ERROR',
                            sprintf('Field "%s" is required and must be a non-empty string', $field)
                        ),
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            $code = strtoupper(trim($data['code']));
            $side = strtolower(trim($data['side']));
            $amountStr = trim($data['amount']);

            // Validate currency code
            if (!in_array($code, self::SUPPORTED_CURRENCIES, true)) {
                return $this->json(
                    ErrorResponse::from(
                        'UNSUPPORTED_CURRENCY',
                        sprintf('Currency %s is not supported', $code)
                    ),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Fetch current rates
            $tableData = $this->nbpClient->getCurrentTableA();

            if (!isset($tableData['rates'][$code])) {
                return $this->json(
                    ErrorResponse::from(
                        'CURRENCY_NOT_AVAILABLE',
                        sprintf('Currency %s not available in current NBP data', $code)
                    ),
                    Response::HTTP_NOT_FOUND
                );
            }

            // Calculate quote using RateCalculator
            $mid = $tableData['rates'][$code];
            $quote = $this->rateCalculator->calculateQuote($code, $mid, $side, $amountStr);

            return $this->json([
                'code' => $code,
                'side' => $side,
                'amount' => $amountStr,
                'unitRate' => $quote['unitRate'],
                'total' => $quote['total'],
                'effectiveDate' => $tableData['effectiveDate'],
                'quotedAt' => gmdate('c'),
            ]);

        } catch (\JsonException $e) {
            return $this->json(
                ErrorResponse::from('VALIDATION_ERROR', 'Invalid JSON in request body'),
                Response::HTTP_BAD_REQUEST
            );
        } catch (NbpClientException $e) {
            return $this->json(
                ErrorResponse::from('NBP_API_ERROR', $e->getMessage()),
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ErrorResponse::from('VALIDATION_ERROR', $e->getMessage()),
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Throwable $e) {
            return $this->json(
                ErrorResponse::from('INTERNAL_ERROR', 'An unexpected error occurred'),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Parse and validate currency codes from comma-separated string.
     *
     * @return string[] Array of validated currency codes
     */
    private function parseAndValidateCurrencyCodes(string $codesParam): array
    {
        if (trim($codesParam) === '') {
            // Default to all supported currencies if none specified
            return self::SUPPORTED_CURRENCIES;
        }

        $codes = array_map('trim', explode(',', $codesParam));
        $codes = array_map('strtoupper', $codes);
        $codes = array_filter($codes, fn (string $code) => $code !== '');
        $codes = array_unique($codes);

        if (count($codes) > 20) { throw new \InvalidArgumentException('Too many currency codes (max 20)'); }

        if (empty($codes)) {
            throw new \InvalidArgumentException('At least one valid currency code must be provided');
        }

        foreach ($codes as $code) {
            if (!in_array($code, self::SUPPORTED_CURRENCIES, true)) {
                throw new \InvalidArgumentException(sprintf('Unsupported currency code: %s', $code));
            }
        }

        return array_values($codes);
    }

    /**
     * Validate and parse date string.
     */
    private function validateAndParseDate(string $dateStr): \DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            throw new \InvalidArgumentException('Date must be in YYYY-MM-DD format');
        }

        try {
            return new \DateTimeImmutable($dateStr, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date: ' . $dateStr, 0, $e);
        }
    }

    /**
     * Validate days parameter.
     */
    private function validateDays(string $daysStr): int
    {
        if (!ctype_digit($daysStr)) {
            throw new \InvalidArgumentException('Days parameter must be a positive integer');
        }

        $days = (int) $daysStr;
        if ($days < 1 || $days > 93) {
            throw new \InvalidArgumentException('Days must be between 1 and 93');
        }

        return $days;
    }
}
