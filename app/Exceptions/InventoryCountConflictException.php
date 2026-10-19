<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar saat optimistic concurrency gagal (dokumen sudah diubah
 * perangkat/tab lain sejak klien terakhir membaca version-nya) atau saat
 * aksi dicoba di status dokumen yang tidak sesuai (mis. edit dokumen yang
 * sudah SUBMITTED).
 */
class InventoryCountConflictException extends RuntimeException
{
}
