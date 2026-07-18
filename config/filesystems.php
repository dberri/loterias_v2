<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Backup Disk
    |--------------------------------------------------------------------------
    |
    | The disk the nightly corpus export writes its artifacts to. It is kept
    | separate from the default disk so backups can live in a different bucket
    | — and therefore a different failure domain — than anything else the app
    | stores.
    |
    */

    'backup_disk' => env('BACKUP_DISK', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'backups' => [
            'driver' => 's3',
            /*
             * `?:` rather than env()'s default argument, for the same reason as
             * mail.alerts.recipient: a key that is PRESENT but EMPTY returns an
             * empty string, not null, so the default never fires. Shipping
             * `BACKUP_AWS_ACCESS_KEY_ID=` in an .env would otherwise silently
             * disable the documented fallback to the AWS_* credentials and
             * authenticate with an empty key.
             */
            'key' => env('BACKUP_AWS_ACCESS_KEY_ID') ?: env('AWS_ACCESS_KEY_ID'),
            'secret' => env('BACKUP_AWS_SECRET_ACCESS_KEY') ?: env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('BACKUP_AWS_DEFAULT_REGION') ?: env('AWS_DEFAULT_REGION'),
            'bucket' => env('BACKUP_AWS_BUCKET') ?: null,

            // An empty string is not a valid endpoint; null means "use the AWS default".
            'endpoint' => env('BACKUP_AWS_ENDPOINT') ?: null,
            'use_path_style_endpoint' => (bool) env('BACKUP_AWS_USE_PATH_STYLE_ENDPOINT', false),

            /*
             * Deliberately true, unlike every other disk here. A backup that
             * fails to write must raise, not return false — silently swallowing
             * a storage error is exactly how a backup becomes a rumour.
             */
            'throw' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
