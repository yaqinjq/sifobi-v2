<?php

use App\Support\Decimal;

test('decimal normalizes comma and dot input', function (): void {
    expect(Decimal::normalize('1,5'))->toBe('1.5')
        ->and(Decimal::normalize('1.5'))->toBe('1.5')
        ->and(Decimal::normalize('0,25'))->toBe('0.25')
        ->and(Decimal::normalize('10'))->toBe('10');
});

test('decimal rejects ambiguous thousands formats', function (): void {
    expect(fn () => Decimal::normalize('1.000,50'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Decimal::normalize('1,000.50'))->toThrow(InvalidArgumentException::class);
});
