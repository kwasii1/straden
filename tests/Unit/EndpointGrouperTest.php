<?php

use App\Services\EndpointGrouper;

test('integer segments collapse to a pattern', function () {
    expect(EndpointGrouper::normalize('http://localhost:3000/todos/123'))
        ->toBe('http://localhost:3000/todos/{id}');
});

test('uuid segments collapse to a pattern', function () {
    expect(EndpointGrouper::normalize('https://api.example.com/v1/todos/550e8400-e29b-41d4-a716-446655440000'))
        ->toBe('https://api.example.com/v1/todos/{id}');
});

test('object id segments collapse to a pattern', function () {
    expect(EndpointGrouper::normalize('https://api.example.com/todos/64b64c9e8f2a4b3c9d0e1f2a'))
        ->toBe('https://api.example.com/todos/{id}');
});

test('long opaque tokens collapse to a pattern', function () {
    expect(EndpointGrouper::normalize('https://api.example.com/todos/cm3k9x2p1000008qy7v9x2'))
        ->toBe('https://api.example.com/todos/{id}');
});

test('static segments are preserved', function () {
    expect(EndpointGrouper::normalize('https://api.example.com/v1/users'))
        ->toBe('https://api.example.com/v1/users');

    expect(EndpointGrouper::normalize('http://localhost:3000/health'))
        ->toBe('http://localhost:3000/health');
});

test('short slugs are not treated as identifiers', function () {
    expect(EndpointGrouper::normalize('https://api.example.com/v1/status'))
        ->toBe('https://api.example.com/v1/status');
});

test('non url values pass through', function () {
    expect(EndpointGrouper::normalize('custom-check'))
        ->toBe('custom-check');
});

test('grouping aggregates dynamic urls with counts', function () {
    $groups = EndpointGrouper::group([
        'https://api.example.com/v1/users',
        'http://localhost:3000/todos/1',
        'http://localhost:3000/todos/2',
        'http://localhost:3000/todos/3',
        'http://localhost:3000/todos/1',
    ]);

    expect($groups)->toHaveCount(2);

    // Static routes sort first.
    expect($groups[0]['pattern'])->toBe('https://api.example.com/v1/users')
        ->and($groups[0]['count'])->toBe(1)
        ->and($groups[1]['pattern'])->toBe('http://localhost:3000/todos/{id}')
        ->and($groups[1]['count'])->toBe(3)
        ->and($groups[1]['names'])->toBe([
            'http://localhost:3000/todos/1',
            'http://localhost:3000/todos/2',
            'http://localhost:3000/todos/3',
        ])
        ->and($groups[1]['label'])->toBe('todos/{id}');
});

test('toRegex covers every name behind a pattern', function () {
    $names = [
        'http://localhost:3000/todos/1',
        'http://localhost:3000/todos/550e8400-e29b-41d4-a716-446655440000',
        'http://localhost:3000/todos/64b64c9e8f2a4b3c9d0e1f2a',
        'http://localhost:3000/todos/cm3k9x2p1000008qy7v9x2',
    ];

    $regex = EndpointGrouper::toRegex('http://localhost:3000/todos/{id}');

    foreach ($names as $name) {
        expect(preg_match($regex, $name))->toBe(1, "regex should match {$name}");
    }

    expect(EndpointGrouper::regexForNames($names))->toBe($regex);
});

test('toRegex is anchored against partial matches', function () {
    $regex = EndpointGrouper::toRegex('http://localhost:3000/todos/{id}');

    expect(preg_match($regex, 'http://localhost:3000/todos/1/extra'))->toBe(0);
    expect(preg_match($regex, 'http://localhost:3000/todos'))->toBe(0);
    expect(preg_match($regex, 'http://localhost:3000/other/1'))->toBe(0);
});

test('regexForNames returns null for mixed patterns', function () {
    expect(EndpointGrouper::regexForNames([
        'https://api.example.com/v1/users',
        'https://api.example.com/todos/1',
    ]))->toBeNull();

    expect(EndpointGrouper::regexForNames([]))->toBeNull();
});
