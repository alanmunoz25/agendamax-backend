<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\AppointmentCancelled;
use App\Mail\AppointmentConfirmation;
use App\Mail\AppointmentReminder;
use App\Mail\AppointmentRescheduled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param  string  $to  Email address
     * @param  string  $templateName  Template identifier
     * @param  array<string, mixed>  $data  Template data
     */
    public function __construct(
        public string $to,
        public string $templateName,
        public array $data
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $mailable = $this->buildMailable();

            Mail::to($this->to)->send($mailable);

            Log::info('Email sent successfully', [
                'template' => $this->templateName,
                'to' => $this->to,
                'appointment_id' => $this->data['appointment_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Email send failed', [
                'template' => $this->templateName,
                'to' => $this->to,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Build the appropriate Mailable based on template name.
     */
    private function buildMailable(): \Illuminate\Mail\Mailable
    {
        return match ($this->templateName) {
            'appointment_confirmation' => new AppointmentConfirmation($this->data),
            'appointment_reminder' => new AppointmentReminder($this->data),
            'appointment_cancelled' => new AppointmentCancelled($this->data),
            'appointment_rescheduled' => new AppointmentRescheduled($this->data),
            default => throw new \InvalidArgumentException("Invalid template: {$this->templateName}"),
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Email job failed permanently', [
            'template' => $this->templateName,
            'to' => $this->to,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // TODO: Notify admins of email failure
        // TODO: Store failed email in database for retry/review
    }
}
