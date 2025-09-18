<?php

declare(strict_types=1);

namespace Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RatesControllerTest extends WebTestCase
{
    public function testRatesCurrentEndpointExists(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/current');

        // Since NBP API isn't mocked, this might fail with 503 (NBP unavailable)
        // but should not give 404 (route not found)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Route should exist');
        $this->assertContains($statusCode, [200, 503], 'Should be OK or Service Unavailable');
    }

    public function testRatesHistoryEndpointExists(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/EUR/history?date=2024-06-15');

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Route should exist');
        $this->assertContains($statusCode, [200, 400, 503], 'Should be OK, Bad Request, or Service Unavailable');
    }

    public function testQuoteEndpointExists(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/quote', [], [], [], json_encode([
            'code' => 'EUR',
            'side' => 'buy',
            'amount' => '100.00'
        ]));

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, 'Route should exist');
        $this->assertContains($statusCode, [200, 400, 503], 'Should be OK, Bad Request, or Service Unavailable');
    }

    public function testCurrentRatesInvalidCurrency(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/current?codes=XXX');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('VALIDATION_ERROR', $data['error']['code']);
    }

    public function testHistoryInvalidCurrency(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/XXX/history?date=2024-06-15');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('UNSUPPORTED_CURRENCY', $data['error']['code']);
    }

    public function testHistoryMissingDate(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/EUR/history');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('VALIDATION_ERROR', $data['error']['code']);
        $this->assertStringContainsString('Date parameter is required', $data['error']['message']);
    }

    public function testQuoteInvalidJson(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/quote', [], [], [], 'invalid-json');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('VALIDATION_ERROR', $data['error']['code']);
        $this->assertStringContainsString('Invalid JSON', $data['error']['message']);
    }

    public function testQuoteUnsupportedCurrency(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/quote', [], [], [], json_encode([
            'code' => 'XXX',
            'side' => 'buy',
            'amount' => '100.00'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('UNSUPPORTED_CURRENCY', $data['error']['code']);
    }

    public function testQuoteBuyNotSupportedForCzk(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/quote', [], [], [], json_encode([
            'code' => 'CZK',
            'side' => 'buy',
            'amount' => '100.00'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = $client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('VALIDATION_ERROR', $data['error']['code']);
        $this->assertStringContainsString('CZK', $data['error']['message']);
    }

}
