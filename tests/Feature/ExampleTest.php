<?php

namespace Tests\Feature\ExampleTest;

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
