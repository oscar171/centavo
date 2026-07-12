<?php

use Laravel\Ai\AnonymousAgent;

use function Laravel\Ai\agent;

it('uses anthropic as the default ai provider', function () {
    expect(config('ai.default'))->toBe('anthropic');
    expect(config('ai.providers.anthropic.driver'))->toBe('anthropic');
});

it('exposes the centavo ai model configuration', function () {
    expect(config('centavo.ai_model'))->toBe('claude-sonnet-5');
    expect(config('centavo'))->toHaveKey('delete_pdf_after_processing');
});

it('stores uploaded pdfs on the private local disk', function () {
    expect(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'));
});

it('has a database queue connection available for async processing', function () {
    expect(config('queue.connections.database.driver'))->toBe('database');
});

it('can prompt a laravel ai agent with a fake response', function () {
    AnonymousAgent::fake(['pong']);

    $response = agent(instructions: 'Responde en una palabra.')->prompt('ping');

    expect((string) $response)->toBe('pong');
});
