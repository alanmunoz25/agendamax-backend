<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CalendarProviderInterface;
use App\Models\Appointment;
use App\Models\Employee;
use App\Models\GoogleAccount;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleCalendarService implements CalendarProviderInterface
{
    public function __construct(private readonly GoogleClient $client) {}

    public function listBusySlots(Employee $employee, CarbonInterface $from, CarbonInterface $to): array
    {
        $googleAccount = $this->getGoogleAccount($employee);

        try {
            $calendar = $this->calendar($googleAccount);

            $request = new FreeBusyRequest;
            $request->setTimeMin($from->toRfc3339String());
            $request->setTimeMax($to->toRfc3339String());
            $request->setItems([
                (new FreeBusyRequestItem)->setId($this->calendarId($googleAccount)),
            ]);

            $response = $calendar->freebusy->query($request);
            $calendars = $response->getCalendars();
            $calendarId = $this->calendarId($googleAccount);

            if (! isset($calendars[$calendarId])) {
                return [];
            }

            $calendarBusy = $calendars[$calendarId]->getBusy() ?? [];

            return array_map(static function ($slot) {
                return [
                    'start' => Carbon::parse($slot->getStart()),
                    'end' => Carbon::parse($slot->getEnd()),
                ];
            }, $calendarBusy);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Unable to list Google Calendar busy slots', [
                'employee_id' => $employee->id,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    public function createEvent(Appointment $appointment): ?string
    {
        $googleAccount = $this->getGoogleAccount($appointment->employee);

        try {
            $calendar = $this->calendar($googleAccount);
            $event = $this->buildEvent($appointment);

            $result = $calendar->events->insert($this->calendarId($googleAccount), $event, [
                'sendUpdates' => 'all',
            ]);

            $appointment->forceFill([
                'google_event_id' => $result->getId(),
                'google_synced_at' => now(),
            ])->saveQuietly();

            return $result->getId();
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Unable to create Google Calendar event', [
                'appointment_id' => $appointment->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function updateEvent(Appointment $appointment): bool
    {
        if (empty($appointment->google_event_id)) {
            return (bool) $this->createEvent($appointment);
        }

        $googleAccount = $this->getGoogleAccount($appointment->employee);

        try {
            $calendar = $this->calendar($googleAccount);
            $event = $this->buildEvent($appointment);

            $calendar->events->patch(
                $this->calendarId($googleAccount),
                $appointment->google_event_id,
                $event,
                ['sendUpdates' => 'all']
            );

            $appointment->forceFill([
                'google_synced_at' => now(),
            ])->saveQuietly();

            return true;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Unable to update Google Calendar event', [
                'appointment_id' => $appointment->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function deleteEvent(Appointment $appointment): bool
    {
        if (empty($appointment->google_event_id)) {
            return true;
        }

        $googleAccount = $this->getGoogleAccount($appointment->employee);

        try {
            $calendar = $this->calendar($googleAccount);
            $calendar->events->delete(
                $this->calendarId($googleAccount),
                $appointment->google_event_id
            );

            $appointment->forceFill([
                'google_event_id' => null,
                'google_synced_at' => now(),
            ])->saveQuietly();

            return true;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Unable to delete Google Calendar event', [
                'appointment_id' => $appointment->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function calendar(GoogleAccount $googleAccount): GoogleCalendar
    {
        return new GoogleCalendar($this->authorizedClient($googleAccount));
    }

    private function authorizedClient(GoogleAccount $googleAccount): GoogleClient
    {
        $client = clone $this->client;

        $token = array_filter([
            'access_token' => $this->decrypt($googleAccount->access_token),
            'refresh_token' => $this->decrypt($googleAccount->refresh_token),
        ]);

        if ($googleAccount->expires_at) {
            $token['expiry_date'] = $googleAccount->expires_at->timestamp;
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired() && isset($token['refresh_token'])) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
            $this->persistToken($googleAccount, $newToken);
        }

        return $client;
    }

    private function getGoogleAccount(Employee $employee): GoogleAccount
    {
        if (! $employee?->relationLoaded('googleAccount')) {
            $employee->load('googleAccount');
        }

        if (! $employee->googleAccount) {
            throw new RuntimeException('Employee is not connected to Google.');
        }

        return $employee->googleAccount;
    }

    private function buildEvent(Appointment $appointment): Event
    {
        $timezone = config('app.timezone');

        $event = new Event;
        $event->setSummary(optional($appointment->service)->name ?? 'Appointment');
        $event->setDescription($appointment->notes ?? '');
        $event->setStart($this->eventDateTime($appointment->scheduled_at, $timezone));
        $event->setEnd($this->eventDateTime($appointment->scheduled_until, $timezone));

        $clientEmail = optional($appointment->client)->email;
        if ($clientEmail) {
            $event->setAttendees([
                ['email' => $clientEmail],
            ]);
        }

        return $event;
    }

    private function eventDateTime(CarbonInterface $dateTime, string $timezone): EventDateTime
    {
        return (new EventDateTime)
            ->setDateTime($dateTime->copy()->timezone($timezone)->toRfc3339String())
            ->setTimeZone($timezone);
    }

    private function persistToken(GoogleAccount $googleAccount, array $token): void
    {
        if (! empty($token['access_token'])) {
            $googleAccount->access_token = Crypt::encryptString($token['access_token']);
        }

        if (! empty($token['refresh_token'])) {
            $googleAccount->refresh_token = Crypt::encryptString($token['refresh_token']);
        }

        if (! empty($token['expires_in'])) {
            $googleAccount->expires_at = now()->addSeconds((int) $token['expires_in']);
        } elseif (! empty($token['expiry_date'])) {
            $googleAccount->expires_at = Carbon::createFromTimestamp((int) $token['expiry_date']);
        }

        $googleAccount->save();
    }

    private function decrypt(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    private function calendarId(GoogleAccount $googleAccount): string
    {
        return $googleAccount->calendar_id ?: 'primary';
    }
}
