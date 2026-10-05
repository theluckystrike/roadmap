<?php

use Illuminate\Support\Arr;

test('every language file returns an array without printing output', function (string $file) {
    ob_start();
    $translations = require $file;
    $output = ob_get_clean();

    expect($output)->toBe('')
        ->and($translations)->toBeArray();
})->with(fn () => collect(glob(dirname(__DIR__, 2).'/lang/*/*.php'))
    ->mapWithKeys(fn (string $file) => [basename(dirname($file)).'/'.basename($file) => $file])
    ->all());

test('translations only use placeholders that exist in the english source', function (string $group) {
    /**
     * Laravel replaces placeholders by prefix, so ":attributeは" still resolves to ":attribute".
     * Translated names (":તારીખ") and full-width colons ("：values") are never replaced.
     */
    $placeholders = function (string $line): array {
        preg_match_all('/(?<![\p{L}\p{N}_])[:：][\p{L}\p{M}_]+/u', $line, $matches);

        return collect($matches[0])
            ->map(fn (string $token) => preg_match('/^:([A-Za-z_]+)/', $token, $name) ? strtolower($name[1]) : $token)
            ->all();
    };

    $source = Arr::dot(require lang_path("en/{$group}.php"));
    $alwaysProvided = $group === 'validation' ? ['attribute', 'count'] : ['count'];
    $invalid = [];

    foreach (glob(lang_path("*/{$group}.php")) as $file) {
        foreach (Arr::dot(require $file) as $key => $line) {
            if (! is_string($line) || ! is_string($source[$key] ?? null)) {
                continue;
            }

            $unknown = array_diff($placeholders($line), $placeholders($source[$key]), $alwaysProvided);

            foreach ($unknown as $placeholder) {
                $invalid[] = basename(dirname($file))."/{$group}.{$key}: {$placeholder}";
            }
        }
    }

    expect($invalid)->toBe([]);
})->with(fn () => collect(glob(dirname(__DIR__, 2).'/lang/en/*.php'))
    ->map(fn (string $file) => basename($file, '.php'))
    ->all());
