<?php

it('returns api health status', function () {
    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertJsonStructure(['status', 'timestamp'])
        ->assertJson(['status' => 'ok']);
});

it('serves swagger documentation', function () {
    $this->get('/api/documentation')
        ->assertOk();
});
