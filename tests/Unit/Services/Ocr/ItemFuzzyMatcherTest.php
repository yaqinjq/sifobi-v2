<?php

use App\Services\Ocr\ItemFuzzyMatcher;
use Illuminate\Support\Collection;

function ocrFakeItem(int $id, string $name, string $sku): object
{
    return (object) ['id' => $id, 'name' => $name, 'canonical_sku' => $sku];
}

test('match menemukan item dengan nama persis sama', function (): void {
    $matcher = new ItemFuzzyMatcher();
    $items = new Collection([
        ocrFakeItem(1, 'Smoked Chicken', 'SKU-001'),
        ocrFakeItem(2, 'Onde-Onde', 'SKU-002'),
    ]);

    $result = $matcher->match('Smoked Chicken', $items);

    expect($result)->not->toBeNull();
    expect($result['item_id'])->toBe(1);
    expect($result['score'])->toBeGreaterThan(0.9);
});

test('match toleran terhadap typo ringan hasil OCR', function (): void {
    $matcher = new ItemFuzzyMatcher();
    $items = new Collection([
        ocrFakeItem(1, 'Premix Sup Ayam', 'SKU-003'),
    ]);

    $result = $matcher->match('Premix Sup Ayarn', $items);

    expect($result)->not->toBeNull();
    expect($result['item_id'])->toBe(1);
});

test('match mengembalikan null kalau tidak ada kandidat yang cukup mirip', function (): void {
    $matcher = new ItemFuzzyMatcher();
    $items = new Collection([
        ocrFakeItem(1, 'Smoked Chicken', 'SKU-001'),
    ]);

    $result = $matcher->match('Bawang Merah Super Jumbo', $items);

    expect($result)->toBeNull();
});

test('match bisa cocok lewat canonical_sku', function (): void {
    $matcher = new ItemFuzzyMatcher();
    $items = new Collection([
        ocrFakeItem(1, 'Ajinomoto 500gr', 'MKO-AJINOMOTO-500GR'),
    ]);

    $result = $matcher->match('MKO-AJINOMOTO-500GR', $items);

    expect($result)->not->toBeNull();
    expect($result['item_id'])->toBe(1);
});
