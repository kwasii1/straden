<?php

use App\Services\ScriptOptionsResolver;

function resolveOptions(string $options): ScriptOptionsResolver
{
    return new ScriptOptionsResolver("export const options = {$options};\n\nexport default function () {}\n");
}

test('resolves a simple duration', function () {
    expect(resolveOptions("{ vus: 5, duration: '30s' }")->durationSeconds())->toBe(30);
});

test('resolves a compound duration', function () {
    expect(resolveOptions("{ duration: '1m30s' }")->durationSeconds())->toBe(90);
});

test('resolves hours in a duration', function () {
    expect(resolveOptions("{ duration: '1h30m' }")->durationSeconds())->toBe(5400);
});

test('sums stage durations when no top-level duration exists', function () {
    $source = <<<'JS'
export const options = {
  stages: [
    { duration: '10s', target: 10 },
    { duration: '1m30s', target: 20 },
    { duration: '20s', target: 0 },
  ],
};
JS;

    expect((new ScriptOptionsResolver($source))->durationSeconds())->toBe(120);
});

test('returns null when no duration or stages are present', function () {
    expect(resolveOptions('{ vus: 5 }')->durationSeconds())->toBeNull();
});

test('returns null when the script has no options block', function () {
    expect((new ScriptOptionsResolver('export default function () {}'))->durationSeconds())->toBeNull();
});

test('resolves vus and iterations', function () {
    $resolver = resolveOptions("{ vus: 25, duration: '1m', iterations: 100 }");

    expect($resolver->vus())->toBe(25);
    expect($resolver->iterations())->toBe(100);
});

test('toRunConfig omits unresolved values', function () {
    expect(resolveOptions("{ vus: 25, duration: '2m' }")->toRunConfig())->toBe([
        'vus' => 25,
        'duration_seconds' => 120,
    ]);
});
