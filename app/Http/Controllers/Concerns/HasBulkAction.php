<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Jalankan aksi bulk per-baris dengan isolasi kegagalan (1 baris gagal tidak
 * menggagalkan baris lain), mengikuti pola yang pertama kali dibangun di
 * OpenStockService::bulkPost(). Dipakai oleh semua controller yang punya
 * checkbox bulk-action di halaman list-nya.
 */
trait HasBulkAction
{
    /**
     * @param  iterable<int, mixed>  $models
     * @param  callable(mixed): void  $action
     * @param  callable(mixed): string  $label
     * @return array{processed: int, failed: int, errors: list<string>}
     */
    protected function runBulkAction(iterable $models, callable $action, callable $label): array
    {
        $processed = 0;
        $errors = [];

        foreach ($models as $model) {
            try {
                $action($model);
                $processed++;
            } catch (ValidationException $exception) {
                $errors[] = $label($model).': '.collect($exception->errors())->flatten()->implode(' ');
            } catch (Throwable $throwable) {
                $errors[] = $label($model).': '.$throwable->getMessage();
            }
        }

        return [
            'processed' => $processed,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array{processed: int, failed: int, errors: list<string>}  $result
     */
    protected function bulkActionRedirect(string $routeName, array $result, string $successMessage): RedirectResponse
    {
        if ($result['failed'] === 0) {
            return redirect()->route($routeName)->with('success', $successMessage);
        }

        $shown = array_slice($result['errors'], 0, 5);
        $message = $successMessage." {$result['failed']} gagal: ".implode(' | ', $shown);

        if (count($result['errors']) > count($shown)) {
            $message .= ' (dan '.(count($result['errors']) - count($shown)).' lainnya)';
        }

        return redirect()->route($routeName)->with('error', $message);
    }
}
