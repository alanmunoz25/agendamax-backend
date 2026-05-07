<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Logging\ContextProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

class LoggingChannelTest extends TestCase
{
    use RefreshDatabase;

    // ── ContextProcessor ────────────────────────────────────────────────────

    public function test_context_processor_injects_request_id(): void
    {
        app()->instance('request_id', 'test-request-id-abc');

        $logger = new Logger('test');
        $handler = new TestHandler;
        $logger->pushProcessor(new ContextProcessor);
        $logger->pushHandler($handler);

        $logger->info('Test message');

        $this->assertTrue($handler->hasInfoRecords());
        $record = $handler->getRecords()[0];
        $this->assertSame('test-request-id-abc', $record['extra']['request_id']);
    }

    public function test_context_processor_injects_ip_and_route(): void
    {
        $logger = new Logger('test');
        $handler = new TestHandler;
        $logger->pushProcessor(new ContextProcessor);
        $logger->pushHandler($handler);

        $logger->info('Test message');

        $record = $handler->getRecords()[0];
        $this->assertArrayHasKey('ip', $record['extra']);
        $this->assertArrayHasKey('route', $record['extra']);
    }

    public function test_context_processor_injects_null_user_when_unauthenticated(): void
    {
        $logger = new Logger('test');
        $handler = new TestHandler;
        $logger->pushProcessor(new ContextProcessor);
        $logger->pushHandler($handler);

        $logger->info('Test message');

        $record = $handler->getRecords()[0];
        $this->assertNull($record['extra']['user_id']);
        $this->assertNull($record['extra']['business_id']);
    }

    public function test_context_processor_injects_user_context_when_authenticated(): void
    {
        $user = \App\Models\User::factory()->create([
            'business_id' => null,
        ]);
        Auth::login($user);

        $logger = new Logger('test');
        $handler = new TestHandler;
        $logger->pushProcessor(new ContextProcessor);
        $logger->pushHandler($handler);

        $logger->info('Authenticated message');

        $record = $handler->getRecords()[0];
        $this->assertSame($user->id, $record['extra']['user_id']);
    }

    // ── Structured channel config ────────────────────────────────────────────

    public function test_structured_channel_is_defined_in_config(): void
    {
        $channels = config('logging.channels');

        $this->assertArrayHasKey('structured', $channels);

        $structured = $channels['structured'];
        $this->assertSame('daily', $structured['driver']);
        $this->assertSame(14, $structured['days']);
        $this->assertSame(JsonFormatter::class, $structured['formatter']);
    }

    public function test_structured_channel_has_context_processor(): void
    {
        $structured = config('logging.channels.structured');

        $this->assertContains(ContextProcessor::class, $structured['processors']);
    }

    // ── JSON output format ────────────────────────────────────────────────────

    public function test_json_formatter_produces_valid_json_output(): void
    {
        $formatter = new JsonFormatter(
            batchMode: JsonFormatter::BATCH_MODE_NEWLINES,
            appendNewline: true
        );

        $logger = new Logger('structured-test');
        $handler = new TestHandler;
        $handler->setFormatter($formatter);
        $logger->pushProcessor(new ContextProcessor);
        $logger->pushHandler($handler);

        app()->instance('request_id', 'json-test-id');
        $logger->info('Structured log test', ['custom_key' => 'custom_value']);

        $this->assertTrue($handler->hasInfoRecords());
        $record = $handler->getRecords()[0];

        // Verify the formatter produces JSON
        $formatted = $handler->getFormatter()->format($record);
        $decoded = json_decode($formatted, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Structured log test', $decoded['message']);
        $this->assertSame('INFO', $decoded['level_name']);
        $this->assertArrayHasKey('datetime', $decoded);
        $this->assertArrayHasKey('extra', $decoded);
        $this->assertSame('json-test-id', $decoded['extra']['request_id']);
    }
}
