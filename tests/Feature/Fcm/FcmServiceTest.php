<?php

declare(strict_types=1);

namespace Tests\Feature\Fcm;

use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Tests\TestCase;

class FcmServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_sends_to_user_with_push_token(): void
    {
        $user = User::factory()->create(['push_token' => 'valid-device-token-123']);

        $messagingMock = $this->mock(Messaging::class);
        $messagingMock->shouldReceive('send')->once()->andReturn(['name' => 'projects/demo/messages/123']);

        $service = app(FcmService::class);
        $result = $service->sendToUser($user, 'Test Title', 'Test Body', ['type' => 'test']);

        $this->assertTrue($result);
    }

    /** @test */
    public function test_skips_user_without_push_token(): void
    {
        $user = User::factory()->create(['push_token' => null]);

        $messagingMock = $this->mock(Messaging::class);
        $messagingMock->shouldNotReceive('send');

        $service = app(FcmService::class);
        $result = $service->sendToUser($user, 'Test Title', 'Test Body');

        $this->assertFalse($result);
    }

    /** @test */
    public function test_clears_invalid_token_when_not_found(): void
    {
        $user = User::factory()->create(['push_token' => 'expired-token-xyz']);

        $notFound = new NotFound('Token not found');

        $messagingMock = $this->mock(Messaging::class);
        $messagingMock->shouldReceive('send')->once()->andThrow($notFound);

        $service = app(FcmService::class);
        $result = $service->sendToUser($user, 'Test Title', 'Test Body');

        $this->assertFalse($result);
        $this->assertNull($user->fresh()->push_token);
    }

    /** @test */
    public function test_returns_false_when_fcm_disabled(): void
    {
        config(['firebase.fcm_enabled' => false]);

        $user = User::factory()->create(['push_token' => 'any-token']);

        $messagingMock = $this->mock(Messaging::class);
        $messagingMock->shouldNotReceive('send');

        $service = app(FcmService::class);
        $result = $service->sendToUser($user, 'Test Title', 'Test Body');

        $this->assertFalse($result);
    }

    /** @test */
    public function test_returns_false_and_does_not_rethrow_on_generic_error(): void
    {
        $user = User::factory()->create(['push_token' => 'some-token']);

        $messagingMock = $this->mock(Messaging::class);
        $messagingMock->shouldReceive('send')->once()->andThrow(new \RuntimeException('Network timeout'));

        $service = app(FcmService::class);

        $threwException = false;

        try {
            $result = $service->sendToUser($user, 'Test Title', 'Test Body');
        } catch (\Throwable) {
            $threwException = true;
            $result = null;
        }

        $this->assertFalse($threwException, 'FcmService must not rethrow exceptions');
        $this->assertFalse($result);
        // push_token is NOT cleared on generic errors (only on NotFound)
        $this->assertNotNull($user->fresh()->push_token);
    }
}
