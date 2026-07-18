<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

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
class BackupDiskConfigTest extends TestCase
{
    /**
     * @param  array<string, string>  $env
     * @return array<string, mixed>
     */
    private function resolveBackupsDisk(array $env): array
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

    public function test_empty_backup_credentials_fall_back_to_the_aws_values(): void
    {
        $disk = $this->resolveBackupsDisk([
            'BACKUP_AWS_ACCESS_KEY_ID' => '',
            'BACKUP_AWS_SECRET_ACCESS_KEY' => '',
            'BACKUP_AWS_DEFAULT_REGION' => '',
            'AWS_ACCESS_KEY_ID' => 'fallback-key',
            'AWS_SECRET_ACCESS_KEY' => 'fallback-secret',
            'AWS_DEFAULT_REGION' => 'sa-east-1',
        ]);

        $this->assertSame('fallback-key', $disk['key']);
        $this->assertSame('fallback-secret', $disk['secret']);
        $this->assertSame('sa-east-1', $disk['region']);
    }

    public function test_explicit_backup_credentials_win_over_the_aws_values(): void
    {
        $disk = $this->resolveBackupsDisk([
            'BACKUP_AWS_ACCESS_KEY_ID' => 'backup-key',
            'AWS_ACCESS_KEY_ID' => 'fallback-key',
        ]);

        $this->assertSame('backup-key', $disk['key']);
    }

    /**
     * An empty string is not a valid endpoint. It must resolve to null so the
     * S3 client uses the AWS default rather than attempting to address "".
     */
    public function test_an_empty_endpoint_resolves_to_null_not_an_empty_string(): void
    {
        $disk = $this->resolveBackupsDisk(['BACKUP_AWS_ENDPOINT' => '']);

        $this->assertNull($disk['endpoint']);
    }

    public function test_a_custom_endpoint_is_preserved_for_s3_compatible_providers(): void
    {
        $disk = $this->resolveBackupsDisk([
            'BACKUP_AWS_ENDPOINT' => 'https://example.r2.cloudflarestorage.com',
        ]);

        $this->assertSame('https://example.r2.cloudflarestorage.com', $disk['endpoint']);
    }

    /**
     * A write that fails must raise. Returning false would let a failed backup
     * look like a successful one, which is the single most dangerous outcome in
     * this feature.
     */
    public function test_the_backups_disk_throws_on_failure(): void
    {
        $this->assertTrue($this->resolveBackupsDisk([])['throw']);
    }
}
