<?php

declare(strict_types=1);

namespace Unit\Http;

use App\Http\ErrorResponse;
use PHPUnit\Framework\TestCase;

class ErrorResponseTest extends TestCase
{
    public function testFromCreatesCorrectStructure(): void
    {
        $code = 'VALIDATION_ERROR';
        $message = 'Invalid input provided';
        $details = ['field' => 'email', 'reason' => 'invalid format'];

        $result = ErrorResponse::from($code, $message, $details);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);

        $error = $result['error'];
        $this->assertIsArray($error);
        $this->assertArrayHasKey('code', $error);
        $this->assertArrayHasKey('message', $error);
        $this->assertArrayHasKey('details', $error);

        $this->assertSame($code, $error['code']);
        $this->assertSame($message, $error['message']);
        $this->assertSame($details, $error['details']);
    }

    public function testFromWithEmptyDetails(): void
    {
        $code = 'NOT_FOUND';
        $message = 'Resource not found';

        $result = ErrorResponse::from($code, $message);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);

        $error = $result['error'];
        $this->assertArrayHasKey('details', $error);
        $this->assertSame([], $error['details']);
    }

    public function testFromWithExplicitEmptyDetails(): void
    {
        $code = 'SERVER_ERROR';
        $message = 'Internal server error';
        $details = [];

        $result = ErrorResponse::from($code, $message, $details);

        $this->assertIsArray($result);
        $error = $result['error'];
        $this->assertSame([], $error['details']);
    }
}
