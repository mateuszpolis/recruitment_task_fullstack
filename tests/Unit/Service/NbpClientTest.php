<?php

declare(strict_types=1);

namespace Unit\Service;

use App\Service\NbpClient;
use App\Service\NbpClientException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class NbpClientTest extends TestCase
{
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
    }

    // getCurrentTableA Tests

    public function testGetCurrentTableASuccess(): void
    {
        $responseBody = json_encode([
            [
                'table' => 'A',
                'no' => '123/A/NBP/2025',
                'effectiveDate' => '2025-09-16',
                'rates' => [
                    ['currency' => 'euro', 'code' => 'EUR', 'mid' => 4.3245],
                    ['currency' => 'dolar amerykański', 'code' => 'USD', 'mid' => 3.9876],
                    ['currency' => 'korona czeska', 'code' => 'CZK', 'mid' => 0.1789],
                    ['currency' => 'rupia indonezyjska', 'code' => 'IDR', 'mid' => 0.000275],
                    ['currency' => 'real', 'code' => 'BRL', 'mid' => 0.8534],
                ]
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $responseBody)
        ]);

        $client = $this->createNbpClient($mock);
        $result = $client->getCurrentTableA();

        // Verify the structure and key fields
        $this->assertArrayHasKey('table', $result);
        $this->assertArrayHasKey('no', $result);
        $this->assertArrayHasKey('effectiveDate', $result);
        $this->assertArrayHasKey('asOf', $result);
        $this->assertArrayHasKey('etag', $result);
        $this->assertArrayHasKey('rates', $result);

        // Verify specific values
        $this->assertSame('A', $result['table']);
        $this->assertSame('123/A/NBP/2025', $result['no']);
        $this->assertSame('2025-09-16', $result['effectiveDate']);

        // asOf should be an ISO 8601 timestamp (current time)
        $this->assertRegExp('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $result['asOf']);

        // etag should be a SHA-1 hash
        $this->assertRegExp('/^[a-f0-9]{40}$/', $result['etag']);

        // Verify rates
        $expectedRates = [
            'EUR' => '4.3245',
            'USD' => '3.9876',
            'CZK' => '0.1789',
            'IDR' => '0.000275',
            'BRL' => '0.8534',
        ];
        $this->assertSame($expectedRates, $result['rates']);
    }

    public function testGetCurrentTableACacheHit(): void
    {
        $responseBody = json_encode([
            [
                'table' => 'A',
                'rates' => [
                    ['currency' => 'euro', 'code' => 'EUR', 'mid' => 4.3245],
                ]
            ]
        ]);

        // First request should hit the API
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $responseBody)
        ]);

        $client = $this->createNbpClient($mock);

        // First call - should make HTTP request
        $result1 = $client->getCurrentTableA();

        // Second call with same client (same cache) - should use cache
        $result2 = $client->getCurrentTableA();

        // Results should be identical
        $this->assertSame($result1, $result2);

        // Verify structure and values for cached result
        $this->assertSame('A', $result1['table']);
        $this->assertSame('', $result1['no']); // Empty string when no data
        $this->assertSame('', $result1['effectiveDate']); // Empty string when no data

        // asOf should be an ISO 8601 timestamp (current time)
        $this->assertRegExp('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $result1['asOf']);

        // etag should be a SHA-1 hash
        $this->assertRegExp('/^[a-f0-9]{40}$/', $result1['etag']);

        // Verify rates
        $this->assertSame(['EUR' => '4.3245'], $result1['rates']);

        // Mock should have been called only once
        $this->assertCount(0, $mock); // All responses consumed
    }

    public function testGetCurrentTableA404NotFound(): void
    {
        $mock = new MockHandler([
            new Response(404, [], 'Not Found')
        ]);

        $client = $this->createNbpClient($mock);

        $this->expectException(NbpClientException::class);
        $this->expectExceptionMessage('Table A data not available (404)');

        $client->getCurrentTableA();
    }

    public function testGetCurrentTableAConnectionTimeout(): void
    {
        // Need multiple responses for retry logic (1 initial + 2 retries = 3 total)
        $mock = new MockHandler([
            new ConnectException('Connection timeout', new Request('GET', 'test')),
            new ConnectException('Connection timeout', new Request('GET', 'test')),
            new ConnectException('Connection timeout', new Request('GET', 'test'))
        ]);

        $client = $this->createNbpClient($mock);

        $this->expectException(NbpClientException::class);
        $this->expectExceptionMessage('NBP API connection timeout or network error');

        $client->getCurrentTableA();
    }

    public function testGetCurrentTableAInvalidJson(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], 'invalid json')
        ]);

        $client = $this->createNbpClient($mock);

        $this->expectException(NbpClientException::class);
        $this->expectExceptionMessage('Invalid JSON response from NBP API');

        $client->getCurrentTableA();
    }

    public function testGetCurrentTableAInvalidResponseFormat(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"invalid": "format"}')
        ]);

        $client = $this->createNbpClient($mock);

        $this->expectException(NbpClientException::class);
        $this->expectExceptionMessage('Invalid API response format for Table A');

        $client->getCurrentTableA();
    }

    // getHistory Tests

    public function testGetHistorySuccess(): void
    {
        $responseBody = json_encode([
            'table' => 'A',
            'currency' => 'euro',
            'code' => 'EUR',
            'rates' => [
                ['no' => '177/A/NBP/2025', 'effectiveDate' => '2025-09-13', 'mid' => 4.3245],
                ['no' => '178/A/NBP/2025', 'effectiveDate' => '2025-09-16', 'mid' => 4.3156],
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $responseBody)
        ]);

        $client = $this->createNbpClient($mock);
        $result = $client->getHistory('EUR', '2025-09-13', '2025-09-16');

        $expectedResult = [
            ['date' => '2025-09-13', 'mid' => '4.3245'],
            ['date' => '2025-09-16', 'mid' => '4.3156'],
        ];

        $this->assertSame($expectedResult, $result);
    }

    public function testGetHistoryCacheHit(): void
    {
        $responseBody = json_encode([
            'table' => 'A',
            'currency' => 'euro',
            'code' => 'EUR',
            'rates' => [
                ['no' => '177/A/NBP/2025', 'effectiveDate' => '2025-09-13', 'mid' => 4.3245],
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $responseBody)
        ]);

        $client = $this->createNbpClient($mock);

        // First call - should make HTTP request
        $result1 = $client->getHistory('EUR', '2025-09-13', '2025-09-13');

        // Second call with same parameters - should use cache
        $result2 = $client->getHistory('EUR', '2025-09-13', '2025-09-13');

        // Results should be identical
        $this->assertSame($result1, $result2);
        $this->assertSame([['date' => '2025-09-13', 'mid' => '4.3245']], $result1);

        // Mock should have been called only once
        $this->assertCount(0, $mock); // All responses consumed
    }

    public function testGetHistoryCaseInsensitive(): void
    {
        $responseBody = json_encode([
            'table' => 'A',
            'currency' => 'euro',
            'code' => 'EUR',
            'rates' => [
                ['no' => '177/A/NBP/2025', 'effectiveDate' => '2025-09-13', 'mid' => 4.3245],
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $responseBody)
        ]);

        $client = $this->createNbpClient($mock);
        $result = $client->getHistory('eur', '2025-09-13', '2025-09-13');

        $this->assertSame([['date' => '2025-09-13', 'mid' => '4.3245']], $result);
    }

    public function testGetHistory404NotFound(): void
    {
        $mock = new MockHandler([
            new Response(404, [], 'Not Found')
        ]);

        $client = $this->createNbpClient($mock);

        $this->expectException(NbpClientException::class);
        $this->expectExceptionMessage('No data available for EUR in period 2025-09-13 to 2025-09-16 (404)');

        $client->getHistory('EUR', '2025-09-13', '2025-09-16');
    }

    public function testGetHistoryInvalidResponseFormat(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"invalid": "format"}')
        ]);

        $client = $this->createNbpClient($mock);

        $this->expectException(NbpClientException::class);
        $this->expectExceptionMessage('Invalid API response format for EUR history');

        $client->getHistory('EUR', '2025-09-13', '2025-09-16');
    }

    // Date validation tests

    public function testGetHistoryInvalidDateFormat(): void
    {
        $client = $this->createNbpClient(new MockHandler());

        $this->expectException(NbpClientException::class);
        $this->expectExceptionMessage('Date must be in YYYY-MM-DD format');

        $client->getHistory('EUR', '2025/09/13', '2025-09-16');
    }

    public function testGetHistoryInvalidDate(): void
    {
        $client = $this->createNbpClient(new MockHandler());

        $this->expectException(NbpClientException::class);
        $this->expectExceptionMessage('Invalid date format');

        $client->getHistory('EUR', '2025-13-45', '2025-09-16');
    }

    public function testGetHistoryStartDateAfterEndDate(): void
    {
        $client = $this->createNbpClient(new MockHandler());

        $this->expectException(NbpClientException::class);
        $this->expectExceptionMessage('Start date must be before or equal to end date');

        $client->getHistory('EUR', '2025-09-16', '2025-09-13');
    }

    public function testGetHistoryDateRangeExceeds93Days(): void
    {
        $client = $this->createNbpClient(new MockHandler());

        $this->expectException(NbpClientException::class);
        $this->expectExceptionMessage('Date range cannot exceed 93 days (requested: 94 days)');

        $client->getHistory('EUR', '2025-01-01', '2025-04-05'); // 94 days
    }

    public function testGetHistoryDateRangeExactly93Days(): void
    {
        $responseBody = json_encode([
            'table' => 'A',
            'currency' => 'euro',
            'code' => 'EUR',
            'rates' => [
                ['no' => '177/A/NBP/2025', 'effectiveDate' => '2025-01-01', 'mid' => 4.3245],
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $responseBody)
        ]);

        $client = $this->createNbpClient($mock);

        // 93 days should be allowed
        $result = $client->getHistory('EUR', '2025-01-01', '2025-04-04'); // Exactly 93 days

        $this->assertNotEmpty($result);
    }

    // Integration and boundary tests

    public function testGetHistoryEmptyRatesArray(): void
    {
        $responseBody = json_encode([
            'table' => 'A',
            'currency' => 'euro',
            'code' => 'EUR',
            'rates' => []
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $responseBody)
        ]);

        $client = $this->createNbpClient($mock);
        $result = $client->getHistory('EUR', '2025-09-13', '2025-09-16');

        $this->assertSame([], $result);
    }

    public function testHttpRequestContainsCorrectHeaders(): void
    {
        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '[]')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        $httpClient = new Client(['handler' => $handlerStack]);
        $client = new NbpClient($httpClient, $this->cache);

        try {
            $client->getCurrentTableA();
        } catch (NbpClientException $e) {
            // Expected due to invalid response format
        }

        $this->assertCount(1, $container);
        $transaction = $container[0];

        /** @var Request $request */
        $request = $transaction['request'];

        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertRegExp('#/exchangerates/tables/A/#', (string) $request->getUri());
    }

    public function testCachingUsesCorrectKeys(): void
    {
        // Test that different requests use different cache keys
        $cache = new ArrayAdapter();

        $tableAResponse = json_encode([
            [
                'table' => 'A',
                'rates' => [
                    ['currency' => 'euro', 'code' => 'EUR', 'mid' => 4.3245],
                ]
            ]
        ]);

        $historyResponse = json_encode([
            'table' => 'A',
            'currency' => 'euro',
            'code' => 'EUR',
            'rates' => [
                ['no' => '177/A/NBP/2025', 'effectiveDate' => '2025-09-13', 'mid' => 4.3245],
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $tableAResponse),
            new Response(200, ['Content-Type' => 'application/json'], $historyResponse),
        ]);

        $client = new NbpClient(new Client(['handler' => HandlerStack::create($mock)]), $cache);

        // Make both types of requests
        $tableResult = $client->getCurrentTableA();
        $historyResult = $client->getHistory('EUR', '2025-09-13', '2025-09-13');

        // Verify both are cached with different keys
        $this->assertTrue($cache->hasItem('nbp_tableA'));
        $this->assertTrue($cache->hasItem('nbp_history_EUR_2025-09-13_2025-09-13'));

        // Verify different results
        $this->assertNotSame($tableResult, $historyResult);
    }

    private function createNbpClient(MockHandler $mock): NbpClient
    {
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        return new NbpClient($httpClient, $this->cache);
    }
}
