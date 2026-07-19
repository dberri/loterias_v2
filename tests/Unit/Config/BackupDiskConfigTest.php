<?php

namespace Tests\Unit\Config\BackupDiskConfigTest;

/**
 * Guards the empty-string-is-not-null trap in the `backups` disk config.
 *
 * An .env key that is PRESENT but EMPTY (`BACKUP_AWS_ACCESS_KEY_ID=`) reads as
 * "" rather than null, so env()'s default argument never fires. This has now
 * caused one real failure — a blank ALERT_MAIL_TO threw inside the alerting
 * path and masked the error it was reporting, which CI caught on its first run
 * — and was nearly reintroduced here while documenting these same variables.
 *
 * The failure would be quiet in a way that matters: the export would
 * authenticate with an empty credential instead of falling back to the AWS_*
 * values the config documents, so the backup breaks at the only moment anyone
 * checks it.
 */

/**
 * @param  array<string, string>  $env
 * @return array<string, mixed>
 */
function resolveBackupsDisk(array $env): array
{
    $original = [];

    foreach ($env as $key => $value) {
        $original[$key] = $_SERVER[$key] ?? null;
        $_SERVER[$key] = $value;
    }

    try {
        $config = require base_path('config/filesystems.php');
    } finally {
        foreach ($original as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }
    }

    return $config['disks']['backups'];
}

test('empty backup credentials fall back to the aws values', function () {
    $disk = resolveBackupsDisk([
        'BACKUP_AWS_ACCESS_KEY_ID' => '',
        'BACKUP_AWS_SECRET_ACCESS_KEY' => '',
        'BACKUP_AWS_DEFAULT_REGION' => '',
        'AWS_ACCESS_KEY_ID' => 'fallback-key',
        'AWS_SECRET_ACCESS_KEY' => 'fallback-secret',
        'AWS_DEFAULT_REGION' => 'sa-east-1',
    ]);

    expect($disk['key'])->toBe('fallback-key');
    expect($disk['secret'])->toBe('fallback-secret');
    expect($disk['region'])->toBe('sa-east-1');
});

test('explicit backup credentials win over the aws values', function () {
    $disk = resolveBackupsDisk([
        'BACKUP_AWS_ACCESS_KEY_ID' => 'backup-key',
        'AWS_ACCESS_KEY_ID' => 'fallback-key',
    ]);

    expect($disk['key'])->toBe('backup-key');
});

test('an empty endpoint resolves to null not an empty string', function () {
    $disk = resolveBackupsDisk(['BACKUP_AWS_ENDPOINT' => '']);

    expect($disk['endpoint'])->toBeNull();
});

test('a custom endpoint is preserved for s3 compatible providers', function () {
    $disk = resolveBackupsDisk([
        'BACKUP_AWS_ENDPOINT' => 'https://example.r2.cloudflarestorage.com',
    ]);

    expect($disk['endpoint'])->toBe('https://example.r2.cloudflarestorage.com');
});

test('the backups disk throws on failure', function () {
    expect(resolveBackupsDisk([])['throw'])->toBeTrue();
});
