<?php

namespace App\Services\Ocr;

use App\Modules\Core\Models\IntegrationProfile;
use Illuminate\Support\Facades\Http;

/**
 * Engine OCR premium/pluggable -- kirim foto langsung ke Anthropic Messages
 * API (vision). Jauh lebih akurat dari TesseractOcrEngine utk foto HP yang
 * miring/buram/format tabel bebas-bebas, karena model langsung "paham"
 * struktur tabel dari gambar, bukan cuma baca teks mentah baris per baris.
 *
 * Dikonstruksi manual oleh DocumentOcrService (bukan di-resolve container)
 * karena butuh IntegrationProfile runtime milik tenant. Anthropic pakai
 * skema auth header x-api-key + anthropic-version -- BUKAN Bearer/Basic --
 * jadi sengaja TIDAK lewat IntegrationController::client() yang cuma
 * mendukung 2 skema itu; panggilan HTTP-nya dibikin sendiri di sini.
 */
class ClaudeVisionOcrEngine implements DocumentOcrEngine
{
    public function __construct(private IntegrationProfile $profile) {}

    public function extractRows(string $imagePath): array
    {
        $mime = $this->detectMime($imagePath);
        $base64 = base64_encode((string) file_get_contents($imagePath));
        $model = (string) (data_get($this->profile->meta, 'model') ?: 'claude-haiku-5-5');
        $timeout = (int) (data_get($this->profile->meta, 'timeout_seconds') ?: 30);

        $response = Http::withHeaders([
            'x-api-key' => (string) $this->profile->auth_token,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout($timeout)
            ->post(rtrim((string) $this->profile->base_url, '/').'/v1/messages', [
                'model' => $model,
                'max_tokens' => 1024,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $base64]],
                        ['type' => 'text', 'text' => $this->prompt()],
                    ],
                ]],
            ]);

        if ($response->failed()) {
            throw new OcrEngineUnavailableException(
                'OCR premium gagal: '.(data_get($response->json(), 'error.message') ?: $response->body())
            );
        }

        $text = (string) data_get($response->json(), 'content.0.text', '');
        $decoded = json_decode($this->stripCodeFence($text), true);

        if (! is_array($decoded)) {
            throw new OcrEngineUnavailableException('Respons OCR premium tidak valid (bukan JSON baris item).');
        }

        return array_values(array_map(fn (array $row): array => [
            'raw_name' => trim((string) ($row['name'] ?? '')),
            'qty' => isset($row['qty']) && $row['qty'] !== null ? (float) $row['qty'] : null,
            'unit_text' => isset($row['unit']) && $row['unit'] !== null ? mb_strtolower(trim((string) $row['unit'])) : null,
            'confidence' => 0.85,
        ], array_filter($decoded, fn ($row) => is_array($row) && trim((string) ($row['name'] ?? '')) !== '')));
    }

    private function prompt(): string
    {
        return 'Ini foto dokumen surat jalan/tanda terima pengiriman barang dalam Bahasa Indonesia. '
            .'Baca tabel daftar barangnya dan balas HANYA dengan JSON array, tanpa teks lain, tanpa code fence, '
            .'format: [{"name": "nama barang", "qty": angka atau null kalau tidak terbaca, "unit": "satuan atau null"}]. '
            .'Satu elemen array per baris item. Abaikan header/footer/tanda tangan/total.';
    }

    private function stripCodeFence(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/```\s*$/', '', $text) ?? $text;

        return trim($text);
    }

    private function detectMime(string $imagePath): string
    {
        $size = @getimagesize($imagePath);

        return is_array($size) && isset($size['mime']) ? (string) $size['mime'] : 'image/jpeg';
    }
}
