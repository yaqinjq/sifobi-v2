@extends('layouts.app')

@section('title', 'SOP & Tutorial SIFOBI')

@section('topbar')
<x-sf.page-header
    title="SOP & Tutorial"
    subtitle="Panduan lengkap penggunaan sistem inventori SIFOBI"
    back="{{ route('dashboard') }}"
/>
@endsection

@section('content')
<div class="px-4 py-5 pb-28 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-6">

    {{-- DAFTAR ISI --}}
    <x-sf.card>
        <x-slot:header>
            <h3 class="font-heading font-bold text-gray-900">Daftar Isi</h3>
        </x-slot:header>
        <div class="px-4 pb-4">
            <ol class="space-y-1.5 text-sm text-primary-700 list-decimal list-inside">
                <li><a href="#setup" class="hover:underline">Persiapan Awal — Setup Master Data</a></li>
                <li><a href="#po" class="hover:underline">Alur Purchase Order (PO)</a></li>
                <li><a href="#gr" class="hover:underline">Alur Penerimaan Barang (GR)</a></li>
                <li><a href="#scan" class="hover:underline">Cara Pakai Fitur Scan QR & Barcode</a></li>
                <li><a href="#opname" class="hover:underline">Opname Stok Harian</a></li>
                <li><a href="#spoil" class="hover:underline">Pencatatan Spoil & Waste</a></li>
                <li><a href="#laporan" class="hover:underline">Laporan & Monitoring</a></li>
                <li><a href="#gudang" class="hover:underline">Cek Saldo Stok (Gudang)</a></li>
                <li><a href="#roles" class="hover:underline">Roles & Hak Akses</a></li>
                <li><a href="#wipro" class="hover:underline">Integrasi Wipro (Central Kitchen)</a></li>
                <li><a href="#troubleshoot" class="hover:underline">Troubleshooting Umum</a></li>
            </ol>
        </div>
    </x-sf.card>

    {{-- 1. SETUP --}}
    <x-sf.card id="setup">
        <x-slot:header>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-bold text-sm shrink-0">1</span>
                <h3 class="font-heading font-bold text-gray-900">Persiapan Awal — Setup Master Data</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-4 text-sm text-gray-700">
            <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-amber-800 text-xs">
                Lakukan setup ini sekali di awal. Tanpa master data yang lengkap, PO dan penerimaan barang tidak bisa berjalan.
            </div>

            <div>
                <p class="font-semibold text-gray-800 mb-2">Urutan Setup yang Benar</p>
                <ol class="space-y-2 list-decimal list-inside text-gray-600">
                    <li>
                        <strong>Satuan (Unit)</strong> — Pengaturan → Satuan<br>
                        <span class="text-xs text-gray-400 ml-5">Buat semua satuan yang dipakai: KG, GR, PCS, BTL, LITER, dll.</span>
                    </li>
                    <li>
                        <strong>Jenis Item & Kategori</strong> — Pengaturan → Jenis Item dan Kategori Item<br>
                        <span class="text-xs text-gray-400 ml-5">Contoh jenis: Bahan Baku, Packaging. Contoh kategori: Dairy, Dry Goods.</span>
                    </li>
                    <li>
                        <strong>Departemen</strong> — Pengaturan → Departemen<br>
                        <span class="text-xs text-gray-400 ml-5">Pastry, Bar, Coffee, Roastery, dsb.</span>
                    </li>
                    <li>
                        <strong>Outlet</strong> — Pengaturan → Outlet<br>
                        <span class="text-xs text-gray-400 ml-5">Setiap cabang/lokasi yang menerima stok.</span>
                    </li>
                    <li>
                        <strong>Supplier</strong> — Pengaturan → Supplier<br>
                        <span class="text-xs text-gray-400 ml-5">Daftar vendor yang mengirimkan barang.</span>
                    </li>
                    <li>
                        <strong>Item (Produk/Bahan)</strong> — Master Data → Item → Tambah Item<br>
                        <span class="text-xs text-gray-400 ml-5">Isi lengkap: nama, SKU, satuan dasar, satuan pembelian, rasio konversi.</span>
                    </li>
                </ol>
            </div>

            <div class="border-t border-gray-100 pt-3">
                <p class="font-semibold text-gray-800 mb-2">Field Penting di Master Data Item</p>
                <div class="space-y-2">
                    <div class="rounded-lg bg-gray-50 px-3 py-2">
                        <p class="font-medium text-gray-700">Satuan Dasar</p>
                        <p class="text-xs text-gray-500 mt-0.5">Satuan terkecil yang digunakan sistem (mis: GR). Semua stok disimpan dalam satuan ini.</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2">
                        <p class="font-medium text-gray-700">Satuan Pembelian + Rasio</p>
                        <p class="text-xs text-gray-500 mt-0.5">Mis: beli per SAK = 25 KG. Isi satuan pembelian: SAK, rasio: 25000 (dalam GR).</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2">
                        <p class="font-medium text-gray-700">Minimum Stok</p>
                        <p class="text-xs text-gray-500 mt-0.5">Isi angka minimum stok untuk aktivasi alert "Stok Menipis". Biarkan 0 jika tidak dipantau.</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2">
                        <p class="font-medium text-gray-700">Barcode</p>
                        <p class="text-xs text-gray-500 mt-0.5">Isi barcode EAN-13/EAN-8 item jika ada. Digunakan untuk fitur scan barcode di penerimaan.</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2">
                        <p class="font-medium text-gray-700">Tujuan PO (Departemen)</p>
                        <p class="text-xs text-gray-500 mt-0.5">Centang tujuan PO yang bisa memesan item ini (OCIA, Central Kitchen, Drygood). Kosong = semua.</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-3">
                <p class="font-semibold text-gray-800 mb-2">Open Stock Awal</p>
                <p class="text-gray-600">Setelah master data selesai, lakukan <strong>Open Stock</strong> untuk memasukkan saldo stok awal:<br>
                Operasi → Open Stock → pilih outlet → isi qty per item → Submit.</p>
            </div>
        </div>
    </x-sf.card>

    {{-- 2. PO --}}
    <x-sf.card id="po">
        <x-slot:header>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-bold text-sm shrink-0">2</span>
                <h3 class="font-heading font-bold text-gray-900">Alur Purchase Order (PO)</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-4 text-sm text-gray-700">

            {{-- Bagan alur --}}
            <div class="flex flex-wrap items-center gap-1 text-xs font-semibold">
                <span class="badge-draft px-2 py-1 rounded-lg">Draft</span>
                <span class="text-gray-400">→</span>
                <span class="badge-pending px-2 py-1 rounded-lg">Diajukan</span>
                <span class="text-gray-400">→</span>
                <span class="badge-approved px-2 py-1 rounded-lg">Disetujui</span>
                <span class="text-gray-400">→</span>
                <span class="badge-info px-2 py-1 rounded-lg">Terkirim ke Vendor</span>
                <span class="text-gray-400">→</span>
                <span class="badge-posted px-2 py-1 rounded-lg">Dikirim Vendor</span>
                <span class="text-gray-400">→</span>
                <span class="badge-posted px-2 py-1 rounded-lg">Selesai</span>
            </div>

            <div class="space-y-3">
                <div class="rounded-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-3 py-2 border-b border-gray-100">
                        <p class="font-semibold text-gray-800 text-xs">Langkah 1 — Buat Draft PO</p>
                        <p class="text-xs text-gray-400 mt-0.5">Dilakukan oleh: Staff Purchasing / Staff Departemen</p>
                    </div>
                    <div class="px-3 py-2 space-y-1 text-xs text-gray-600">
                        <p>1. Buka <strong>Purchase Order → Buat PO Baru</strong></p>
                        <p>2. Pilih tipe PO: OCIA Roastery / Central Kitchen / Drygood</p>
                        <p>3. Isi tanggal dibutuhkan dan catatan</p>
                        <p>4. Tambahkan item yang dibutuhkan beserta jumlah dan satuan</p>
                        <p class="text-amber-600 font-medium">⚠ Staf departemen (mis: Pastry) hanya melihat item tujuan departemennya</p>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-3 py-2 border-b border-gray-100">
                        <p class="font-semibold text-gray-800 text-xs">Langkah 2 — Ajukan PO</p>
                        <p class="text-xs text-gray-400 mt-0.5">Dilakukan oleh: Staff yang sama</p>
                    </div>
                    <div class="px-3 py-2 text-xs text-gray-600">
                        <p>Klik tombol <strong>"Ajukan untuk Persetujuan"</strong> di halaman detail PO. Status berubah ke <em>Diajukan</em>. Staf pembuat tidak bisa mengubah item/qty lagi setelah ini — hanya PIC/approver yang masih bisa (lihat Langkah 3).</p>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-3 py-2 border-b border-gray-100">
                        <p class="font-semibold text-gray-800 text-xs">Langkah 3 — Review, Edit (opsional), lalu Setujui / Tolak PO</p>
                        <p class="text-xs text-gray-400 mt-0.5">Dilakukan oleh: PIC / Manager / Admin (butuh permission <code class="bg-white px-1 rounded">approve_po</code>)</p>
                    </div>
                    <div class="px-3 py-2 text-xs text-gray-600 space-y-1">
                        <p>Buka PO → periksa daftar item. Selama status masih <em>Diajukan</em> atau <em>Disetujui</em> (belum dikirim ke vendor), PIC masih bisa <strong>menambah/mengganti item dan mengubah qty</strong> langsung dari halaman ini sebelum PO benar-benar dikirim.</p>
                        <p>Setelah item sesuai, klik <strong>"Setujui"</strong> atau <strong>"Tolak"</strong> (tolak wajib isi alasan).</p>
                        <p>PO yang ditolak dikembalikan ke pembuat untuk direvisi.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-3 py-2 border-b border-gray-100">
                        <p class="font-semibold text-gray-800 text-xs">Langkah 4 — Kirim ke Vendor</p>
                        <p class="text-xs text-gray-400 mt-0.5">Dilakukan oleh: Manager / Admin</p>
                    </div>
                    <div class="px-3 py-2 text-xs text-gray-600 space-y-1">
                        <p>Klik <strong>"Tandai Terkirim ke Vendor"</strong>. Status berubah ke <em>Terkirim</em>.</p>
                        <p>Untuk PO Central Kitchen (Wipro): PO otomatis dikirim ke sistem Wipro via API. Periksa status integrasi di halaman detail PO.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-3 py-2 border-b border-gray-100">
                        <p class="font-semibold text-gray-800 text-xs">Langkah 5 — Vendor Kirim Barang</p>
                        <p class="text-xs text-gray-400 mt-0.5">Otomatis (dari Wipro) atau manual</p>
                    </div>
                    <div class="px-3 py-2 text-xs text-gray-600 space-y-1">
                        <p>Wipro akan memanggil webhook SIFOBI saat barang dikirim → status PO otomatis berubah ke <em>Dikirim Vendor</em>.</p>
                        <p>Halaman detail PO menampilkan daftar DO yang masuk dari Wipro. Setiap box/paket punya 1 catatan DO dan QR code sendiri.</p>
                    </div>
                </div>
            </div>
        </div>
    </x-sf.card>

    {{-- 3. GR --}}
    <x-sf.card id="gr">
        <x-slot:header>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-bold text-sm shrink-0">3</span>
                <h3 class="font-heading font-bold text-gray-900">Alur Penerimaan Barang (Goods Receipt)</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-4 text-sm text-gray-700">

            <div class="flex flex-wrap items-center gap-1 text-xs font-semibold">
                <span class="badge-draft px-2 py-1 rounded-lg">Draft</span>
                <span class="text-gray-400">→</span>
                <span class="badge-pending px-2 py-1 rounded-lg">Submitted</span>
                <span class="text-gray-400">→</span>
                <span class="badge-approved px-2 py-1 rounded-lg">Approved</span>
                <span class="text-gray-400">→</span>
                <span class="badge-posted px-2 py-1 rounded-lg">Posted (Stok Masuk)</span>
            </div>

            <div class="space-y-3">
                <div class="rounded-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-3 py-2 border-b border-gray-100">
                        <p class="font-semibold text-gray-800 text-xs">Langkah 1 — Buat GR Baru</p>
                        <p class="text-xs text-gray-400 mt-0.5">Dilakukan oleh: Staff Penerima</p>
                    </div>
                    <div class="px-3 py-2 text-xs text-gray-600 space-y-1">
                        <p>Penerimaan Barang → Buat Baru → pilih sumber:</p>
                        <ul class="pl-3 space-y-0.5 list-disc">
                            <li><strong>WIP Central Kitchen</strong> — barang dari Wipro</li>
                            <li><strong>Kopi dari OCIA</strong> — barang dari OCIA Roastery</li>
                            <li><strong>Drygood Purchasing</strong> — bahan kering dari purchasing</li>
                            <li><strong>Supplier Luar</strong> — vendor lain</li>
                        </ul>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-3 py-2 border-b border-gray-100">
                        <p class="font-semibold text-gray-800 text-xs">Langkah 2 — Isi Form (Manual atau Scan QR)</p>
                    </div>
                    <div class="px-3 py-2 text-xs text-gray-600 space-y-1.5">
                        <p><strong>Cara manual:</strong> Pilih PO dari dropdown → item otomatis terisi → pilih DO/pengiriman jika ada → isi qty aktual diterima.</p>
                        <p><strong>Cara scan:</strong> Klik tombol <strong>"Scan QR / Barcode"</strong> → arahkan ke QR box → semua field terisi otomatis. <a href="#scan" class="text-primary-600 underline">Lihat panduan scan →</a></p>
                        <p>Foto dokumen SJ dan Invoice bisa diambil langsung dari kamera menggunakan tombol kamera di sebelah field foto.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-3 py-2 border-b border-gray-100">
                        <p class="font-semibold text-gray-800 text-xs">Langkah 3 — Submit untuk Review</p>
                    </div>
                    <div class="px-3 py-2 text-xs text-gray-600">
                        <p>Klik <strong>"Submit Review"</strong>. GR tidak bisa diubah setelah disubmit. Hubungi atasan jika ada kesalahan.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gray-50 px-3 py-2 border-b border-gray-100">
                        <p class="font-semibold text-gray-800 text-xs">Langkah 4 — Approve (Stok Masuk ke Ledger)</p>
                        <p class="text-xs text-gray-400 mt-0.5">Dilakukan oleh: Manager / Admin</p>
                    </div>
                    <div class="px-3 py-2 text-xs text-gray-600 space-y-1">
                        <p>Buka GR → klik <strong>"Approve"</strong>. Stok otomatis masuk ke ledger (permanen, tidak bisa dihapus).</p>
                        <p>GR yang terhubung ke shipment Wipro → konfirmasi penerimaan dikirim ke Wipro secara otomatis.</p>
                        <p>Status DO di halaman PO berubah menjadi <em>Diterima ✓</em>.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-blue-50 border border-blue-100 px-3 py-2 text-xs text-blue-800">
                <strong>Catatan 1 PO, banyak GR:</strong> Jika PO memiliki 3 box/DO, buat 3 GR terpisah — masing-masing dipilih DO-nya sendiri. Setiap GR = 1 shipment.
            </div>
        </div>
    </x-sf.card>

    {{-- 4. SCAN --}}
    <x-sf.card id="scan">
        <x-slot:header>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-bold text-sm shrink-0">4</span>
                <h3 class="font-heading font-bold text-gray-900">Cara Pakai Fitur Scan QR & Barcode</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-4 text-sm text-gray-700">

            <div class="rounded-xl bg-green-50 border border-green-100 px-3 py-2 text-xs text-green-800">
                Gunakan perangkat dengan kamera belakang (smartphone/tablet) untuk hasil terbaik. Izinkan akses kamera saat diminta browser.
            </div>

            {{-- Skenario 1 --}}
            <div>
                <p class="font-semibold text-gray-800 mb-2">Skenario A — Scan QR Box dari Vendor</p>
                <p class="text-xs text-gray-500 mb-2">Digunakan saat menerima box dari Wipro yang sudah ada QR-nya.</p>
                <ol class="space-y-2 text-xs text-gray-600 list-decimal list-inside">
                    <li>Buka form Penerimaan Barang Baru → sumber: WIP Central Kitchen</li>
                    <li>Klik tombol <strong>"Scan QR / Barcode"</strong> (biru, di atas form)</li>
                    <li>Pilih mode <strong>"Scan QR Box"</strong></li>
                    <li>Arahkan kamera ke QR code yang tertempel di box</li>
                    <li>Form otomatis terisi: PO, DO, Invoice, dan semua item dengan qty</li>
                    <li>Periksa qty aktual yang diterima (ubah jika ada selisih)</li>
                    <li>Foto SJ dan Invoice dengan tombol kamera</li>
                    <li>Submit</li>
                </ol>
            </div>

            <div class="border-t border-gray-100 pt-3">
                <p class="font-semibold text-gray-800 mb-2">Cara Dapatkan QR Box</p>
                <p class="text-xs text-gray-500 mb-2">QR dibuat oleh SIFOBI dan perlu dicetak/dibagikan ke tim vendor (Wipro).</p>
                <ol class="space-y-1.5 text-xs text-gray-600 list-decimal list-inside">
                    <li>Buka halaman detail PO yang sudah ada pengiriman masuk dari Wipro</li>
                    <li>Gulir ke bagian <strong>"Pengiriman dari Vendor (DO)"</strong></li>
                    <li>Klik <strong>"Tampilkan QR Box"</strong> pada baris DO yang sesuai</li>
                    <li>Screenshot atau cetak QR tersebut</li>
                    <li>Tempelkan di box / kirimkan ke tim penerima sebelum barang tiba</li>
                </ol>
            </div>

            <div class="border-t border-gray-100 pt-3">
                <p class="font-semibold text-gray-800 mb-2">Skenario B — Scan Barcode Item untuk Verifikasi</p>
                <p class="text-xs text-gray-500 mb-2">Digunakan untuk memverifikasi item satu per satu saat membongkar box.</p>
                <ol class="space-y-1.5 text-xs text-gray-600 list-decimal list-inside">
                    <li>Di form GR (setelah PO dipilih), gulir ke bagian <strong>"Item Diterima"</strong></li>
                    <li>Klik tombol <strong>"Scan Barcode Item"</strong></li>
                    <li>Pilih mode <strong>"Scan Barcode Item"</strong></li>
                    <li>Scan barcode EAN-13 atau EAN-8 yang ada di kemasan item</li>
                    <li>Item otomatis muncul di baris baru dengan qty 1</li>
                    <li>Scan lagi item yang sama → qty bertambah +1</li>
                    <li>Ulangi untuk setiap item dalam box</li>
                </ol>
                <div class="mt-2 rounded-lg bg-amber-50 border border-amber-100 px-3 py-2 text-xs text-amber-700">
                    ⚠ Pastikan field <strong>Barcode</strong> sudah diisi di Master Data → Item sebelum menggunakan fitur ini.
                </div>
            </div>

            <div class="border-t border-gray-100 pt-3">
                <p class="font-semibold text-gray-800 mb-2">Skenario C — Foto Dokumen</p>
                <ol class="space-y-1.5 text-xs text-gray-600 list-decimal list-inside">
                    <li>Di form GR, temukan field <strong>"Foto Surat Jalan / SJ"</strong> dan <strong>"Foto Invoice"</strong></li>
                    <li>Klik ikon kamera di sebelah kanan field</li>
                    <li>Kamera belakang langsung terbuka</li>
                    <li>Ambil foto dokumen (pastikan terbaca jelas)</li>
                    <li>Foto tersimpan bersama GR sebagai arsip digital</li>
                </ol>
            </div>
        </div>
    </x-sf.card>

    {{-- 5. OPNAME --}}
    <x-sf.card id="opname">
        <x-slot:header>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-bold text-sm shrink-0">5</span>
                <h3 class="font-heading font-bold text-gray-900">Opname Stok Harian</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm text-gray-700">
            <p class="text-xs text-gray-500">Dilakukan setiap hari (atau sesuai jadwal item) untuk menjaga akurasi stok.</p>
            <ol class="space-y-2 text-xs text-gray-600 list-decimal list-inside">
                <li>Buka <strong>Operasi → Opname Stok</strong></li>
                <li>Pilih outlet dan tanggal opname</li>
                <li>Isi jumlah stok aktual yang dihitung secara fisik untuk setiap item</li>
                <li>Sistem menghitung selisih (aktual vs ledger) secara otomatis</li>
                <li>Submit opname → selisih dicatat ke ledger sebagai adjustment</li>
            </ol>
            <div class="rounded-xl bg-blue-50 border border-blue-100 px-3 py-2 text-xs text-blue-800">
                Frekuensi opname per item diatur di Master Data (DAILY / WEEKLY / MONTHLY). Hanya item yang jadwalnya jatuh hari itu yang muncul.
            </div>
        </div>
    </x-sf.card>

    {{-- 6. SPOIL --}}
    <x-sf.card id="spoil">
        <x-slot:header>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-bold text-sm shrink-0">6</span>
                <h3 class="font-heading font-bold text-gray-900">Pencatatan Spoil & Waste</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm text-gray-700">
            <p class="text-xs text-gray-500">Catat setiap kehilangan/kerusakan stok agar ledger akurat.</p>
            <ol class="space-y-1.5 text-xs text-gray-600 list-decimal list-inside">
                <li>Buka <strong>Operasi → Spoil & Waste</strong></li>
                <li>Pilih outlet dan tanggal kejadian</li>
                <li>Tambahkan item yang rusak/terbuang beserta jumlahnya</li>
                <li>Isi alasan (Kadaluarsa, Tumpah, Rusak Proses, dll.)</li>
                <li>Submit → stok berkurang otomatis di ledger</li>
            </ol>
        </div>
    </x-sf.card>

    {{-- 7. LAPORAN --}}
    <x-sf.card id="laporan">
        <x-slot:header>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-bold text-sm shrink-0">7</span>
                <h3 class="font-heading font-bold text-gray-900">Laporan & Monitoring</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm text-gray-700">
            <div class="space-y-2">
                <div class="rounded-lg bg-gray-50 px-3 py-2">
                    <p class="font-medium text-gray-800 text-xs">Mutasi Stok</p>
                    <p class="text-xs text-gray-500 mt-0.5">Riwayat lengkap pergerakan stok per item/outlet. Filter: item, outlet, tipe mutasi, periode. Bisa export Excel.</p>
                </div>
                <div class="rounded-lg bg-gray-50 px-3 py-2">
                    <p class="font-medium text-gray-800 text-xs">Spoil & Waste</p>
                    <p class="text-xs text-gray-500 mt-0.5">Rekapitulasi kerugian stok per departemen dan periode.</p>
                </div>
                <div class="rounded-lg bg-gray-50 px-3 py-2">
                    <p class="font-medium text-gray-800 text-xs">Penerimaan Barang</p>
                    <p class="text-xs text-gray-500 mt-0.5">Rekap semua GR yang sudah diposting, beserta nilai totalnya.</p>
                </div>
                <div class="rounded-lg bg-gray-50 px-3 py-2">
                    <p class="font-medium text-gray-800 text-xs">Ringkasan Stok (Admin)</p>
                    <p class="text-xs text-gray-500 mt-0.5">Saldo stok semua outlet sekarang. Hanya untuk Admin.</p>
                </div>
                <div class="rounded-lg bg-green-50 border border-green-100 px-3 py-2">
                    <p class="font-medium text-green-800 text-xs">Stok Menipis ⚠</p>
                    <p class="text-xs text-green-700 mt-0.5">Daftar item yang stoknya di bawah minimum. Gunakan ini sebelum membuat PO. Atur minimum di Master Data → Item.</p>
                </div>
            </div>
        </div>
    </x-sf.card>

    {{-- 8. GUDANG --}}
    <x-sf.card id="gudang">
        <x-slot:header>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-bold text-sm shrink-0">8</span>
                <h3 class="font-heading font-bold text-gray-900">Cek Saldo Stok (Gudang)</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm text-gray-700">
            <p class="text-xs text-gray-500">Menu <strong>Gudang</strong> menampilkan saldo stok terkini per outlet — termasuk stok harian (OUTLET_DAILY) dan stok gudang (OUTLET_WAREHOUSE) secara terpisah.</p>
            <ol class="space-y-1.5 text-xs text-gray-600 list-decimal list-inside">
                <li>Desktop: menu <strong>Gudang</strong> di sidebar. Mobile: buka <strong>Lainnya → Gudang</strong> di bottom navigation</li>
                <li>Pilih outlet (jika akses lebih dari satu outlet) dan cari item lewat kolom pencarian</li>
                <li>Klik <strong>"Riwayat"</strong> pada salah satu item untuk lihat detail: saldo per target stok, riwayat mutasi, dan rekomendasi order (rata-rata pemakaian harian &amp; estimasi sisa hari)</li>
            </ol>
            <div class="rounded-xl bg-blue-50 border border-blue-100 px-3 py-2 text-xs text-blue-800">
                Butuh permission <code class="bg-white px-1 rounded">view_stock_balance</code>. Rekomendasi order hanya muncul untuk item yang sudah punya data pola pemakaian.
            </div>
        </div>
    </x-sf.card>

    {{-- 9. ROLES --}}
    <x-sf.card id="roles">
        <x-slot:header>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-bold text-sm shrink-0">9</span>
                <h3 class="font-heading font-bold text-gray-900">Roles & Hak Akses</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm text-gray-700">
            <div class="overflow-x-auto">
                <table class="w-full text-xs border-separate" style="border-spacing: 0">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="text-left px-3 py-2 rounded-tl-lg font-semibold text-gray-600">Permission</th>
                            <th class="text-center px-3 py-2 font-semibold text-gray-600">Admin</th>
                            <th class="text-center px-3 py-2 font-semibold text-gray-600">Manager</th>
                            <th class="text-center px-3 py-2 rounded-tr-lg font-semibold text-gray-600">Staff</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="px-3 py-2 text-gray-700">Buat PO</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                        </tr>
                        <tr class="bg-gray-50/50">
                            <td class="px-3 py-2 text-gray-700">Approve PO</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-red-400">—</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 text-gray-700">Lihat semua PO (lintas dept.)</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-red-400">dept. sendiri</td>
                        </tr>
                        <tr class="bg-gray-50/50">
                            <td class="px-3 py-2 text-gray-700">Submit GR</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 text-gray-700">Approve GR (posting stok)</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-red-400">—</td>
                        </tr>
                        <tr class="bg-gray-50/50">
                            <td class="px-3 py-2 text-gray-700">Kelola Master Data Item</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-red-400">—</td>
                            <td class="text-center px-3 py-2 text-red-400">—</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 text-gray-700">Lihat Laporan Semua Outlet</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-green-600 font-bold">✓</td>
                            <td class="text-center px-3 py-2 text-red-400">outlet sendiri</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-400">Permission diatur di Pengaturan → Users. Permission <code class="bg-gray-100 px-1 rounded">view_all_po</code> perlu dicentang secara eksplisit untuk Manager/Admin yang perlu lintas departemen.</p>
        </div>
    </x-sf.card>

    {{-- 10. WIPRO --}}
    <x-sf.card id="wipro">
        <x-slot:header>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-bold text-sm shrink-0">10</span>
                <h3 class="font-heading font-bold text-gray-900">Integrasi Wipro (Central Kitchen)</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-4 text-sm text-gray-700">

            <div>
                <p class="font-semibold text-gray-800 mb-2">Alur Data Otomatis</p>
                <div class="space-y-2 text-xs">
                    <div class="flex gap-3 items-start">
                        <span class="badge-info px-2 py-0.5 rounded text-xs shrink-0 mt-0.5">SIFOBI → Wipro</span>
                        <p class="text-gray-600">Saat PO Central Kitchen di-<em>send</em>, SIFOBI mengirim data PO ke API Wipro via queue worker. Cek status di halaman detail PO.</p>
                    </div>
                    <div class="flex gap-3 items-start">
                        <span class="badge-posted px-2 py-0.5 rounded text-xs shrink-0 mt-0.5">Wipro → SIFOBI</span>
                        <p class="text-gray-600">Saat Wipro mengirim barang, sistem Wipro memanggil webhook SIFOBI → PO berubah ke SHIPPED, catatan DO baru dibuat, QR box tersedia.</p>
                    </div>
                    <div class="flex gap-3 items-start">
                        <span class="badge-approved px-2 py-0.5 rounded text-xs shrink-0 mt-0.5">SIFOBI → Wipro</span>
                        <p class="text-gray-600">Setelah GR di-approve, SIFOBI otomatis mengirim konfirmasi penerimaan ke Wipro (<code class="bg-gray-100 px-1 rounded">confirmReceipt</code>).</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-3">
                <p class="font-semibold text-gray-800 mb-2">Jika Integrasi Gagal</p>
                <ul class="space-y-1.5 text-xs text-gray-600 list-disc list-inside">
                    <li>Halaman detail PO menampilkan pesan error dan tombol <strong>"Kirim Ulang"</strong></li>
                    <li>Pastikan Wipro sudah menambahkan IP server SIFOBI ke whitelist Cloudflare</li>
                    <li>Cek log queue worker: <code class="bg-gray-100 px-1 rounded">php artisan queue:work</code></li>
                </ul>
            </div>

            <div class="border-t border-gray-100 pt-3">
                <p class="font-semibold text-gray-800 mb-2">Error "Outlet not found or inactive"</p>
                <p class="text-xs text-gray-500 mb-2">Terjadi kalau kode outlet SIFOBI belum dikenali sistem Wipro.</p>
                <ul class="space-y-1.5 text-xs text-gray-600 list-disc list-inside">
                    <li>SIFOBI kini otomatis menyediakan daftar outlet lewat <code class="bg-gray-100 px-1 rounded">GET /api/outlets</code> yang bisa ditarik Wipro (menu Integrasi &rarr; FBI Integration &rarr; "Sync Outlets" di sisi Wipro)</li>
                    <li>Outlet baru/berganti nama sebaiknya di-sync ulang dari sisi Wipro setelah dibuat/diubah di SIFOBI</li>
                    <li>Kalau nama outlet SIFOBI dan Wipro terlalu berbeda untuk dicocokkan otomatis, mapping perlu diisi manual di halaman Outlet Mapping milik Wipro</li>
                </ul>
            </div>
        </div>
    </x-sf.card>

    {{-- 11. TROUBLESHOOT --}}
    <x-sf.card id="troubleshoot">
        <x-slot:header>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-red-100 text-red-700 font-bold text-sm shrink-0">!</span>
                <h3 class="font-heading font-bold text-gray-900">Troubleshooting Umum</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm text-gray-700">
            <div class="space-y-3">
                <div class="rounded-xl border border-red-50 bg-red-50/50 px-3 py-2">
                    <p class="font-semibold text-gray-800 text-xs mb-1">Kamera tidak muncul saat scan</p>
                    <ul class="text-xs text-gray-600 space-y-0.5 list-disc list-inside">
                        <li>Pastikan browser mendapat izin kamera (lihat ikon gembok/kamera di address bar)</li>
                        <li>Gunakan HTTPS — scan tidak bisa di HTTP</li>
                        <li>Coba di Chrome (Android) atau Safari (iOS)</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-red-50 bg-red-50/50 px-3 py-2">
                    <p class="font-semibold text-gray-800 text-xs mb-1">Barcode item tidak ditemukan</p>
                    <ul class="text-xs text-gray-600 space-y-0.5 list-disc list-inside">
                        <li>Cek Master Data → Item → pastikan field Barcode sudah diisi</li>
                        <li>Barcode harus persis sama (case sensitive)</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-red-50 bg-red-50/50 px-3 py-2">
                    <p class="font-semibold text-gray-800 text-xs mb-1">QR Box scan tapi PO tidak ditemukan</p>
                    <ul class="text-xs text-gray-600 space-y-0.5 list-disc list-inside">
                        <li>PO harus berstatus <em>Terkirim ke Vendor</em> atau <em>Dikirim Vendor</em></li>
                        <li>User harus punya akses ke outlet PO tersebut</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-red-50 bg-red-50/50 px-3 py-2">
                    <p class="font-semibold text-gray-800 text-xs mb-1">Item tidak muncul di form PO/GR</p>
                    <ul class="text-xs text-gray-600 space-y-0.5 list-disc list-inside">
                        <li>Pastikan item berstatus Aktif dan <em>Track Stock</em> dicentang</li>
                        <li>Cek field <em>Tujuan PO</em> di item — kosong berarti semua tipe PO, isi berarti hanya tipe tertentu</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-red-50 bg-red-50/50 px-3 py-2">
                    <p class="font-semibold text-gray-800 text-xs mb-1">Stok tidak berubah setelah GR disetujui</p>
                    <ul class="text-xs text-gray-600 space-y-0.5 list-disc list-inside">
                        <li>Cek status GR — harus sampai <em>Posted</em> bukan hanya <em>Approved</em></li>
                        <li>Lihat laporan Mutasi Stok untuk konfirmasi pencatatan</li>
                    </ul>
                </div>
            </div>
        </div>
    </x-sf.card>

    <div class="text-center text-xs text-gray-400 pb-4">
        SIFOBI v2.6 &mdash; Panduan ini diperbarui: {{ now()->format('d M Y') }}
        &middot; <a href="{{ route('changelog') }}" class="text-primary-600 hover:underline">Lihat Changelog</a>
    </div>

</div>
@endsection
