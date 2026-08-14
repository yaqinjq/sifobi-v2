@extends('layouts.app')

@section('title', 'Changelog SIFOBI')

@section('content')
<x-sf.page-header title="Changelog" subtitle="Riwayat pembaruan sistem SIFOBI"
    back="{{ route('dashboard') }}" />

<div class="px-4 py-5 pb-28 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-6">

    {{-- v2.6 --}}
    <x-sf.card>
        <x-slot:header>
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h3 class="font-heading font-bold text-gray-900">v2.6 &mdash; Agustus 2026</h3>
                <span class="badge-info text-xs">Terbaru</span>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm">

            <div>
                <p class="font-semibold text-gray-800 mb-1.5">🔒 Perbaikan Keamanan — Opname Lintas Departemen</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Staff kini hanya bisa melihat dan mengisi opname untuk departemennya sendiri (sebelumnya, staff dengan role tertentu bisa melihat dan menimpa hasil hitung departemen lain)</li>
                    <li>Item yang dipakai lebih dari satu departemen (mis. Tepung Terigu dipakai Kitchen &amp; Pastry) sekarang otomatis muncul di opname setiap departemen yang memakainya</li>
                    <li>PIC Outlet / Manager Area / Admin tetap bisa melihat opname semua departemen seperti biasa</li>
                </ul>
            </div>

            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Menu Mobile — Penerimaan Barang &amp; Gudang</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Menu "Penerimaan Barang" di mobile (sheet "Lainnya") sekarang muncul untuk semua role yang punya akses, tidak hanya yang bisa membuat PO</li>
                    <li>Menu baru "Gudang" (cek saldo stok) ditambahkan ke mobile — sebelumnya hanya tersedia di tampilan desktop</li>
                </ul>
            </div>

            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Perbaikan Tampilan Halaman Stok (Mobile)</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Panel "Analisis Stok &amp; Saran Order" di halaman detail stok tidak lagi terpotong/tumpang tindih di layar HP sempit</li>
                </ul>
            </div>

            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Sinkronisasi Outlet — Integrasi Wipro</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Outlet SIFOBI kini bisa disinkronkan otomatis ke sistem Wipro, mengurangi kegagalan kirim PO Central Kitchen akibat kode outlet tidak dikenali</li>
                </ul>
            </div>

            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">PIC Bisa Edit Item &amp; Qty Sebelum PO Dikirim ke Vendor</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Selama PO masih berstatus <em>Diajukan</em> atau <em>Disetujui</em> (belum dikirim ke vendor), PIC/approver sekarang bisa menambah, mengganti, atau menghapus item, serta mengubah qty — langsung dari halaman detail PO</li>
                    <li>Staf pembuat PO tetap tidak bisa mengedit lagi setelah mengajukan — hak edit di tahap ini khusus untuk yang berwenang menyetujui PO</li>
                </ul>
            </div>

            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Popup Konfirmasi PO — Tombol Selalu Terlihat</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Popup konfirmasi saat membuat PO dengan banyak item kini punya scroll sendiri di dalam popup — tombol "Kirim PO" tidak lagi terdorong ke luar layar</li>
                </ul>
            </div>

            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Penerimaan Barang — Konfirmasi Selisih Qty Real-time</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Saat qty terima beda dari qty PO (baik diketik manual maupun hasil scan barcode), sistem langsung menandai "✓ Sesuai" atau "⚠ Selisih" per item — tidak perlu tunggu submit</li>
                    <li>Kalau ada selisih, wajib pilih alasan (Vendor kirim kurang/lebih, Barang rusak, Salah item, Lainnya) + catatan tambahan sebelum bisa submit</li>
                    <li>Scan barcode item yang ada di PO yang dipilih kini otomatis mengambil qty seharusnya dari PO, jadi selisihnya langsung ketahuan</li>
                    <li>Field "Qty PO" sekarang tidak bisa diedit manual — cuma menampilkan qty asli dari PO sebagai pembanding</li>
                    <li>Alasan selisih ditampilkan di halaman detail GR supaya PIC/approver tahu alasan kelebihan/kekurangan sebelum approve</li>
                    <li>Berlaku untuk penerimaan yang terhubung PO (WIP/OCIA/Drygood); Supplier Luar tetap seperti sebelumnya</li>
                </ul>
            </div>
        </div>
    </x-sf.card>

    {{-- v2.5 --}}
    <x-sf.card>
        <x-slot:header>
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h3 class="font-heading font-bold text-gray-900">v2.5 &mdash; Agustus 2026</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm">

            <div>
                <p class="font-semibold text-gray-800 mb-1.5">Scan QR Box — Auto-isi Form Penerimaan</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Tombol "Scan QR / Barcode" di form penerimaan barang — buka kamera langsung dari browser</li>
                    <li>Scan QR yang tertempel di box pengiriman → PO, DO, Invoice, dan semua item otomatis terisi sekaligus (seperti scan paket Shopee)</li>
                    <li>QR per box tersedia di halaman detail PO (bagian Pengiriman dari Vendor) — klik "Tampilkan QR Box"</li>
                    <li>QR berisi data lengkap: nomor PO, DO, Invoice, dan daftar item + qty — dibaca offline tanpa koneksi</li>
                </ul>
            </div>

            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Scan Barcode Item — Verifikasi per Item</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Tombol "Scan Barcode Item" di bagian daftar item form GR</li>
                    <li>Scan barcode EAN-13, EAN-8, atau Code128 pada kemasan item → item langsung ditambah ke baris penerimaan</li>
                    <li>Scan item yang sama berulang → qty bertambah +1 per scan</li>
                    <li>Butuh field Barcode diisi di Master Data → Item terlebih dahulu</li>
                </ul>
            </div>

            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Foto Dokumen Terpisah (SJ & Invoice)</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Form GR kini punya dua field foto: Foto Surat Jalan dan Foto Invoice (terpisah)</li>
                    <li>Tombol kamera di setiap field — buka kamera belakang langsung untuk foto dokumen</li>
                    <li>Foto tersimpan sebagai arsip digital per GR</li>
                </ul>
            </div>

            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">SOP & Tutorial</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Halaman baru: <a href="{{ route('tutorial') }}" class="text-primary-600 underline">SOP & Tutorial</a> — panduan lengkap penggunaan semua fitur per alur dan per role</li>
                    <li>Mencakup: setup master data, alur PO, alur GR, cara scan, opname, laporan, roles, integrasi Wipro, dan troubleshooting</li>
                </ul>
            </div>
        </div>
    </x-sf.card>

    {{-- v2.4 --}}
    <x-sf.card>
        <x-slot:header>
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h3 class="font-heading font-bold text-gray-900">v2.4 &mdash; Agustus 2026</h3>
            </div>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm">

            <div>
                <p class="font-semibold text-gray-800 mb-1.5">Multi DO / Invoice per PO</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>1 PO kini bisa memiliki banyak Delivery Order (DO) dan Invoice — sesuai jumlah box pengiriman dari vendor</li>
                    <li>Setiap notifikasi pengiriman dari Wipro membuat 1 catatan shipment baru (bukan menimpa data lama)</li>
                    <li>Halaman detail PO menampilkan semua DO yang masuk beserta status konfirmasi penerimaan</li>
                    <li>Halaman detail PO juga menampilkan semua GR yang terkait dengan PO tersebut</li>
                </ul>
            </div>

            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Penerimaan Barang — Picker Pengiriman (DO)</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Saat memilih PO di form penerimaan, muncul dropdown DO/shipment yang belum diterima</li>
                    <li>Pilih salah satu DO → nomor SJ dan invoice langsung otomatis terisi</li>
                    <li>Jika hanya ada 1 DO yang belum diterima, langsung dipilih otomatis</li>
                    <li>GR yang di-approve akan otomatis menandai shipment/DO sebagai sudah diterima</li>
                </ul>
            </div>

            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Roles — Pembatasan Akses per Departemen</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Staf yang memiliki departemen (misal: Pastry) hanya bisa melihat PO departemennya sendiri</li>
                    <li>Staf tanpa permission <code class="bg-gray-100 px-1 rounded text-xs">view_all_po</code> tidak bisa melihat PO departemen lain</li>
                    <li>Admin dan Manager (tanpa department_id) tetap melihat semua PO</li>
                </ul>
            </div>

            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Laporan Stok Menipis</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Halaman baru: Laporan → Stok Menipis</li>
                    <li>Menampilkan semua item dengan saldo di bawah minimum stok yang dikonfigurasi</li>
                    <li>Filter per outlet dan kategori; progress bar visual per item</li>
                    <li>Minimum stok diatur di Master Data → Item (field <em>Minimum Stok</em>)</li>
                </ul>
            </div>
        </div>
    </x-sf.card>

    {{-- v2.3 --}}
    <x-sf.card>
        <x-slot:header>
            <h3 class="font-heading font-bold text-gray-900">v2.3 &mdash; Agustus 2026</h3>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm">
            <div>
                <p class="font-semibold text-gray-800 mb-1.5">Redesign Purchase Order</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Navigasi PO berbasis tab: Draft, Diajukan, Disetujui, Terkirim, Dikirim Vendor, Selesai, Ditolak</li>
                    <li>Badge hitungan per tab; icon status per baris</li>
                    <li>Status baru <strong>SHIPPED</strong> (Dikirim Vendor) — muncul saat Wipro/vendor konfirmasi pengiriman</li>
                </ul>
            </div>
            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Integrasi Wipro — Webhook Inbound</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Endpoint baru: <code class="bg-gray-100 px-1 rounded text-xs">POST /api/wipro/dispatch-notification</code></li>
                    <li>Wipro memanggil endpoint ini saat mengirim barang → status PO otomatis berubah ke SHIPPED</li>
                    <li>PO yang berstatus SHIPPED / SENT tampil di dropdown picker form Penerimaan Barang</li>
                </ul>
            </div>
            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">PO Picker di Penerimaan Barang</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Pilih PO di form GR → item otomatis terisi dari PO (jumlah dan satuan)</li>
                    <li>Berlaku untuk sumber WIP Central Kitchen dan OCIA/Roastery</li>
                </ul>
            </div>
        </div>
    </x-sf.card>

    {{-- v2.2 --}}
    <x-sf.card>
        <x-slot:header>
            <h3 class="font-heading font-bold text-gray-900">v2.2 &mdash; Juli 2026</h3>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm">
            <div>
                <p class="font-semibold text-gray-800 mb-1.5">Integrasi Wipro — Kirim PO</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>PO tipe Central Kitchen dikirim otomatis ke sistem Wipro via queue worker</li>
                    <li>Custom User-Agent agar tidak diblokir Cloudflare WAF</li>
                    <li>Konfirmasi penerimaan barang dikirim ke Wipro saat GR di-approve (<code class="bg-gray-100 px-1 rounded text-xs">confirmReceipt</code>)</li>
                </ul>
            </div>
            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Laporan</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Laporan Mutasi Stok dengan filter item, outlet, tipe mutasi, dan export Excel</li>
                    <li>Laporan Spoil & Waste per departemen dan periode</li>
                    <li>Laporan Penerimaan Barang per sumber dan outlet</li>
                    <li>Ringkasan Stok Semua Outlet (untuk Admin)</li>
                </ul>
            </div>
        </div>
    </x-sf.card>

    {{-- v2.1 --}}
    <x-sf.card>
        <x-slot:header>
            <h3 class="font-heading font-bold text-gray-900">v2.1 &mdash; Juli 2026</h3>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm">
            <div>
                <p class="font-semibold text-gray-800 mb-1.5">Purchase Order — Full Flow</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Alur lengkap: Draft → Diajukan → Disetujui → Terkirim → Selesai</li>
                    <li>Tipe PO: OCIA Roastery, Central Kitchen (Wipro), Drygood</li>
                    <li>Tambah item live search (Google-style); batch create multi-tipe sekaligus</li>
                    <li>Tagging tujuan PO per item master (export/import Excel)</li>
                </ul>
            </div>
            <div class="border-t border-gray-50 pt-3">
                <p class="font-semibold text-gray-800 mb-1.5">Penerimaan Barang (Goods Receipt)</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Multi-sumber: OCIA, Central Kitchen, Drygood, Supplier Luar</li>
                    <li>Alur approval: Draft → Submitted → Approved → Posted ke stock ledger</li>
                    <li>Tracking expiry date dan batch code per item</li>
                </ul>
            </div>
        </div>
    </x-sf.card>

    {{-- v2.0 --}}
    <x-sf.card>
        <x-slot:header>
            <h3 class="font-heading font-bold text-gray-900">v2.0 &mdash; Juni 2026</h3>
        </x-slot:header>
        <div class="px-4 pb-4 space-y-3 text-sm">
            <div>
                <p class="font-semibold text-gray-800 mb-1.5">Fondasi Sistem</p>
                <ul class="space-y-1 text-gray-600 pl-4 list-disc">
                    <li>Multi-tenant architecture dengan scope otomatis per tenant</li>
                    <li>RBAC (Role-Based Access Control) via Spatie Permission</li>
                    <li>Open Stock, Opname Harian, Spoil & Waste, Transfer Stok</li>
                    <li>Stock Ledger immutable — setiap perubahan stok tercatat permanen</li>
                    <li>Dashboard dengan widget ringkasan dan badge notifikasi</li>
                    <li>Android APK via Capacitor (WebView ke server live)</li>
                    <li>Master Data: Item, Unit, Outlet, Supplier, Kategori</li>
                </ul>
            </div>
        </div>
    </x-sf.card>

</div>
@endsection
