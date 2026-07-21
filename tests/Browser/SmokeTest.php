<?php

namespace Tests\Browser\SmokeTest;

test('the homepage loads in a real browser with no javascript errors', function () {
    visit('/')
        ->assertNoJavaScriptErrors();
});
