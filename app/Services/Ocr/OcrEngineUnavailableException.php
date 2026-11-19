<?php

namespace App\Services\Ocr;

use RuntimeException;

/**
 * Dilempar saat engine OCR tidak bisa dipakai (binary tesseract tidak
 * ketemu/gagal, atau API vision merespons error) -- pesan di exception ini
 * aman ditampilkan langsung ke user (bukan detail teknis internal), supaya
 * controller cukup teruskan $e->getMessage() apa adanya ke response JSON.
 */
class OcrEngineUnavailableException extends RuntimeException {}
