<?php

namespace App\Services\Ocr;

use Symfony\Component\Process\Exception\ExceptionInterface as ProcessExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * Engine OCR gratis/default -- shell ke binary `tesseract` (pola shell-out
 * yang sama dengan WiproIntegrationService::curlPost(), bukan hal baru di
 * codebase ini). Perlu `tesseract-ocr` + paket bahasa `ind` terpasang di
 * server (lihat plan deployment) -- kalau belum, gagal lunak lewat
 * OcrEngineUnavailableException, bukan 500.
 */
class TesseractOcrEngine implements DocumentOcrEngine
{
    public function __construct(private OcrTextRowParser $parser) {}

    public function extractRows(string $imagePath): array
    {
        return $this->parser->parse($this->runCli($imagePath));
    }

    private function runCli(string $imagePath): string
    {
        $process = new Process(['tesseract', $imagePath, 'stdout', '-l', 'ind+eng']);
        $process->setTimeout(30);

        try {
            $process->run();
        } catch (ProcessExceptionInterface) {
            throw new OcrEngineUnavailableException(
                'OCR tidak bisa dijalankan (tesseract tidak ditemukan di server ini). Hubungi admin untuk memasang tesseract-ocr.'
            );
        }

        if (! $process->isSuccessful()) {
            throw new OcrEngineUnavailableException('OCR gagal membaca foto: '.trim($process->getErrorOutput()));
        }

        return $process->getOutput();
    }
}
