<?php

namespace App\Services\Contracts;

/**
 * Swappable order-quantity suggestion engine. `SmartOrderService` is the
 * statistical implementation used today; a future LLM-backed implementation
 * can bind against this same contract without touching any call sites.
 */
interface OrderSuggestionEngine
{
    /**
     * @return array<string, mixed>
     */
    public function getSuggestion(int $itemId, int $outletId, int $tenantId): array;
}
