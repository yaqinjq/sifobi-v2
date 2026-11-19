<?php

use App\Services\Ocr\OcrTextRowParser;

test('parse membaca baris dengan qty & satuan di akhir baris', function (): void {
    $parser = new OcrTextRowParser();

    $rows = $parser->parse("Smoked Chicken 10 pack\nOnde-Onde 5 pack");

    expect($rows)->toHaveCount(2);
    expect($rows[0]['raw_name'])->toBe('Smoked Chicken');
    expect($rows[0]['qty'])->toBe(10.0);
    expect($rows[0]['unit_text'])->toBe('pack');
    expect($rows[0]['confidence'])->toBe(0.6);
    expect($rows[1]['raw_name'])->toBe('Onde-Onde');
    expect($rows[1]['qty'])->toBe(5.0);
});

test('parse membaca baris dengan qty di awal baris', function (): void {
    $parser = new OcrTextRowParser();

    $rows = $parser->parse('2 box Premix Sup Ayam');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['raw_name'])->toBe('Premix Sup Ayam');
    expect($rows[0]['qty'])->toBe(2.0);
    expect($rows[0]['unit_text'])->toBe('box');
});

test('parse menerima qty desimal berkoma', function (): void {
    $parser = new OcrTextRowParser();

    $rows = $parser->parse('Ajinomoto 500gr 1,5 kg');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['qty'])->toBe(1.5);
});

test('parse melewati baris noise (header/footer)', function (): void {
    $parser = new OcrTextRowParser();

    $rows = $parser->parse("DETAIL ORDER\nTanggal Order: 13/11/2026\nSmoked Chicken 10 pack\nTotal: 150000\nDiterima oleh: Admin WIP");

    expect($rows)->toHaveCount(1);
    expect($rows[0]['raw_name'])->toBe('Smoked Chicken');
});

test('parse tetap menampilkan baris tanpa qty yang terdeteksi, confidence rendah', function (): void {
    $parser = new OcrTextRowParser();

    $rows = $parser->parse('Nama Barang Tanpa Angka Jelas');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['qty'])->toBeNull();
    expect($rows[0]['confidence'])->toBe(0.3);
});

test('parse mengabaikan baris kosong & baris terlalu pendek', function (): void {
    $parser = new OcrTextRowParser();

    $rows = $parser->parse("\n\n  \nAB\nSmoked Chicken 10 pack");

    expect($rows)->toHaveCount(1);
    expect($rows[0]['raw_name'])->toBe('Smoked Chicken');
});
