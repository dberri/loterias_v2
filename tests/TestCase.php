<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureViteManifest();
    }

    private function ensureViteManifest(): void
    {
        $buildPath = public_path('build');
        $assetsPath = $buildPath.'/assets';
        $manifestPath = $buildPath.'/manifest.json';

        if (file_exists($manifestPath)) {
            return;
        }

        if (! is_dir($assetsPath)) {
            mkdir($assetsPath, 0777, true);
        }

        file_put_contents($assetsPath.'/app.css', '');
        file_put_contents($assetsPath.'/app.js', '');
        file_put_contents($manifestPath, json_encode([
            'resources/css/app.css' => [
                'file' => 'assets/app.css',
                'src' => 'resources/css/app.css',
                'isEntry' => true,
            ],
            'resources/js/app.js' => [
                'file' => 'assets/app.js',
                'src' => 'resources/js/app.js',
                'isEntry' => true,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
