<?php

namespace App\Http\Controllers\Receiving;

use App\Http\Controllers\Controller;
use App\Services\Ocr\DocumentOcrService;
use App\Services\Ocr\OcrEngineUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Baca 1 foto surat jalan dan balikkan baris item SARAN (belum disimpan
 * apa pun -- ini cuma preview utk mengisi form, lihat
 * DocumentOcrService::suggestRows()). File diproses langsung dari path
 * sementara upload, tidak pernah ditulis permanen.
 */
class GoodsReceiptOcrController extends Controller
{
    public function __invoke(Request $request, DocumentOcrService $service): JsonResponse
    {
        $tenantId = (int) $request->user()?->tenant_id;

        abort_unless($tenantId, 403);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        try {
            $rows = $service->suggestRows($tenantId, $request->file('photo')->getRealPath());

            return response()->json(['success' => true, 'rows' => $rows]);
        } catch (OcrEngineUnavailableException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('[OCR] scan-photo gagal', ['message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'OCR gagal diproses: '.$e->getMessage()], 422);
        }
    }
}
