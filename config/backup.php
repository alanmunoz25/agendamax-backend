<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

return [

    'backup' => [
        'name' => env('APP_NAME', 'crezer'),

        'source' => [
            'files' => [
                'include' => [
                    // Include DGII/FCM certificates stored in private storage
                    storage_path('app/private/certificates'),
                ],

                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    storage_path('framework'),
                    storage_path('logs'),
                    storage_path('app/backup-temp'),
                ],

                'follow_links' => false,
                'ignore_unreadable_directories' => true,
                'relative_path' => null,
            ],

            'databases' => [
                env('DB_CONNECTION', 'mariadb'),
            ],
        ],

        'database_dump_compressor' => null,
        'database_dump_file_timestamp_format' => null,
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => '',

            /*
             * Primary destination: S3 bucket (production).
             * TODO: switch 'disks' from ['local'] to ['s3'] once AWS credentials
             * are provisioned in the production environment.
             * Required .env vars: AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY,
             *                     AWS_DEFAULT_REGION, BACKUP_S3_BUCKET
             */
            'disks' => [
                env('BACKUP_DISK', 'local'),
            ],

            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',
        'verify_backup' => false,
        'tries' => 1,
        'retry_delay' => 0,
    ],

    /*
     * Notifications go to the structured log channel in production.
     * Mail notifications require BACKUP_NOTIFICATION_EMAIL to be set.
     */
    'notifications' => [
        'notifications' => [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            CleanupHasFailedNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => ['mail'],
            HealthyBackupWasFoundNotification::class => ['mail'],
            CleanupWasSuccessfulNotification::class => ['mail'],
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', 'ops@crezer.app'),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'noreply@crezer.app'),
                'name' => env('MAIL_FROM_NAME', 'Crezer Backup'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],

        'webhook' => [
            'url' => '',
        ],
    ],

    /*
     * Use the structured log channel to record backup events in production.
     * Set LOG_CHANNEL=structured in production .env.
     */
    'log_channel' => env('LOG_CHANNEL', null),

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'crezer'),
            'disks' => [env('BACKUP_DISK', 'local')],
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            // Keep all backups for 7 days
            'keep_all_backups_for_days' => 7,

            // Keep one daily backup for 4 weeks (28 days total window)
            'keep_daily_backups_for_days' => 28,

            // Keep one weekly backup for 4 weeks
            'keep_weekly_backups_for_weeks' => 4,

            // Keep one monthly backup for 3 months
            'keep_monthly_backups_for_months' => 3,

            // Keep one yearly backup for 1 year
            'keep_yearly_backups_for_years' => 1,

            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],

        'tries' => 1,
        'retry_delay' => 0,
    ],

];
