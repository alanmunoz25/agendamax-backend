<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmService
{
    public function __construct(private readonly Container $container) {}

    /**
     * Send a push notification to a user's registered device.
     *
     * Returns true if the message was sent, false if skipped or failed gracefully.
     * All failures are logged but never re-thrown — FCM being down must not
     * break the primary application flow.
     *
     * @param  array<string, string>  $data  Custom data payload (FCM requires string values)
     */
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        array $data = []
    ): bool {
        if (! config('firebase.fcm_enabled', true)) {
            return false;
        }

        if (empty($user->push_token)) {
            return false;
        }

        try {
            /** @var Messaging $messaging */
            $messaging = $this->container->make(Messaging::class);

            $message = CloudMessage::new()
                ->toToken($user->push_token)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $messaging->send($message);

            return true;
        } catch (NotFound $e) {
            // Token is no longer valid (device unregistered or app uninstalled).
            // Clear it so we don't keep sending to a dead token.
            $user->forceFill(['push_token' => null])->save();
            Log::info('FCM token invalidated and cleared', ['user_id' => $user->id]);

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM send failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
