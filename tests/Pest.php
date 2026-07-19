<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests get the full Laravel test case plus RefreshDatabase.
| Unit tests get the Laravel test case without RefreshDatabase — several
| Unit tests are pure (e.g. NulSafeJsonTest, BlockRegistrationTest) and
| adding a DB transaction to them would be a silent slowdown and a
| behavior change.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Vite manifest stub
|--------------------------------------------------------------------------
|
| A real browser actually fetches these assets, so ensuring a Vite
| manifest exists is load-bearing, not just a convenience. Runs for
| every test in every suite.
|
*/

beforeEach(fn () => $this->ensureViteManifest());
