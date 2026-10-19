@extends('layouts.app')

@section('title', 'Isi Inventory')

{{-- Tombol "Kirim Hasil Hitung" ada di bar fixed bottom-0 z-30 miliknya
     sendiri (lihat bawah) -- tanpa ini, bottom nav mobile (z-40, juga
     fixed bottom-0) menimpa & memblokir klik tombol Kirim di viewport
     mobile. Pola yang sama sudah dipakai operations/open-stocks/create
     & edit utk masalah identik. --}}
@section('hide-bottom-nav', 'true')

@section('content')
<x-sf.page-header title="{{ $document->template?->name }}" subtitle="{{ $document->outlet?->name }} -- {{ $document->department?->name }}" back="{{ route('operations.inventory-count.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-4 pb-28"
     x-data
     x-init="Alpine.store('invCount', {
        docId: {{ $document->id }},
        docVersion: {{ $document->version }},
        counted: {{ $countedCount }},
        total: {{ $totalCount }},
        status: @js($document->status),
        pendingSaves: 0,
        mode: 'list',
        search: '',
        locationFilter: '',
        categoryFilter: '',
     })"
     @inv-line-saved.window="
        $store.invCount.docVersion = $event.detail.documentVersion;
        if ($event.detail.wasCounted !== $event.detail.nowCounted) {
            $store.invCount.counted += $event.detail.nowCounted ? 1 : -1;
        }
     ">
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold mb-1">Belum bisa dikirim:</p>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach(($errors->get('lines') ?: $errors->all()) as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
        Pendataan fisik -- belum mengubah saldo stok sistem.
    </div>

    <a href="{{ route('operations.inventory-count.recap.index', ['template_id' => $document->template_id, 'outlet_id' => $document->outlet_id, 'department_id' => $document->department_id]) }}"
       class="sf-btn-secondary min-h-10 w-full flex items-center justify-center gap-1.5 text-sm">
        <i class="ti ti-table" aria-hidden="true"></i> Lihat Rekap Hasil Hitung Mingguan
    </a>

    <x-sf.card>
        <div class="flex items-center justify-between gap-3">
            <div>
                <span class="{{ $document->status_badge_class }}">{{ $document->status_label }}</span>
                <p class="text-sm text-gray-500 mt-2">{{ $document->business_date->translatedFormat('l, d M Y') }}</p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Progress</p>
                <p class="text-lg font-bold text-gray-900"><span x-text="$store.invCount.counted"></span> / <span x-text="$store.invCount.total"></span> item</p>
            </div>
        </div>
        <div class="mt-4 h-2 rounded-full bg-gray-100 overflow-hidden">
            <div class="h-full bg-primary-700 transition-all" :style="`width: ${$store.invCount.total === 0 ? 0 : ($store.invCount.counted / $store.invCount.total) * 100}%`"></div>
        </div>
    </x-sf.card>

    @if($document->status === 'SUBMITTED')
        <x-sf.card>
            <p class="text-sm text-gray-600">Dokumen ini sudah terkirim (read-only). Koreksi harus lewat revisi berizin.</p>
            @if($canRequestRevision)
                <form method="POST" action="{{ route('operations.inventory-count.request-revision', $document) }}" class="mt-3 space-y-2">
                    @csrf
                    <textarea name="reason" rows="2" class="sf-input text-sm" placeholder="Alasan revisi (wajib)" required></textarea>
                    <button type="submit" class="sf-btn-secondary min-h-11 w-full text-sm">Ajukan Revisi</button>
                </form>
            @endif
        </x-sf.card>
    @endif

    {{-- Riwayat revisi TIDAK digembok ke status SUBMITTED -- sebelumnya
         kartu ini hilang total begitu dokumen kembali ke Draft (lihat
         temuan B-F1, docs/technical/uat-inventory-simple-2026-10-13.md),
         padahal justru saat sedang direvisi staf paling butuh tahu
         alasannya. Akses lihat dokumen ini sendiri sudah digerbangi
         assertDocumentInScope() di controller, jadi riwayat di sini ikut
         izin akses yang sama, tidak perlu pengecekan tambahan. --}}
    @if($document->revisions->isNotEmpty())
        <x-sf.card title="Riwayat Revisi">
            @foreach($document->revisions as $revision)
                <div class="text-sm py-2 border-b border-gray-50 last:border-0">
                    <p class="text-gray-700">{{ $revision->from_status }} &rarr; {{ $revision->to_status }} oleh {{ $revision->performedBy?->name }}</p>
                    <p class="text-xs text-gray-500">{{ $revision->performed_at->translatedFormat('d M Y H:i') }} -- {{ $revision->reason }}</p>
                </div>
            @endforeach
        </x-sf.card>
    @endif

    <div class="flex items-center gap-1.5">
        <button type="button" @click="$store.invCount.mode = 'list'" class="{{ true }} sf-btn-secondary text-xs px-3 py-1.5 min-h-9" :class="$store.invCount.mode === 'list' ? 'sf-btn-primary' : 'sf-btn-secondary'">
            <i class="ti ti-list text-sm" aria-hidden="true"></i> List
        </button>
        <button type="button" @click="$store.invCount.mode = 'card'" class="sf-btn-secondary text-xs px-3 py-1.5 min-h-9" :class="$store.invCount.mode === 'card' ? 'sf-btn-primary' : 'sf-btn-secondary'">
            <i class="ti ti-layout-grid text-sm" aria-hidden="true"></i> Kartu
        </button>
        <button type="button" @click="$store.invCount.mode = 'table'" class="sf-btn-secondary text-xs px-3 py-1.5 min-h-9" :class="$store.invCount.mode === 'table' ? 'sf-btn-primary' : 'sf-btn-secondary'">
            <i class="ti ti-table text-sm" aria-hidden="true"></i> Tabel
        </button>
    </div>

    @php
        $locationLabels = [
            'GUDANG' => 'Gudang',
            'BAR_AREA' => 'Bar Area',
            'KITCHEN_AREA' => 'Kitchen Area',
            'GENERIC' => 'Umum',
        ];
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
        <div class="relative sm:col-span-1">
            <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm" aria-hidden="true"></i>
            <input type="search" x-model="$store.invCount.search" placeholder="Cari nama item..." class="sf-input pl-9 min-h-11 text-sm">
        </div>
        <select x-model="$store.invCount.locationFilter" class="sf-input min-h-11 text-sm">
            <option value="">Semua Lokasi</option>
            @foreach($distinctLocations as $locationCode)
                <option value="{{ $locationCode }}">{{ $locationLabels[$locationCode] ?? $locationCode }}</option>
            @endforeach
        </select>
        <select x-model="$store.invCount.categoryFilter" class="sf-input min-h-11 text-sm">
            <option value="">Semua Kategori</option>
            @foreach($distinctCategories as $category)
                <option value="{{ $category }}">{{ $category }}</option>
            @endforeach
        </select>
    </div>

    @php $rowNum = 0; @endphp
    <div x-show="$store.invCount.mode !== 'table'">
        @foreach($linesBySubcategory as $subcategory => $lines)
            <div class="mb-4" x-show="$store.invCount.categoryFilter === '' || $store.invCount.categoryFilter === @js($subcategory)">
                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">{{ $subcategory }}</h3>
                <div class="space-y-3" :class="$store.invCount.mode === 'card' ? 'lg:grid lg:grid-cols-2 lg:gap-3 lg:space-y-0' : ''">
                    @foreach($lines as $line)
                        @php $rowNum++; @endphp
                        @include('operations.inventory-count.partials.line-card', ['line' => $line, 'document' => $document, 'rowNum' => $rowNum])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    @php $rowNum = 0; @endphp
    <div x-show="$store.invCount.mode === 'table'" x-cloak class="flex items-center justify-between">
        <p class="text-xs text-gray-500">Klik header kolom untuk urutkan.</p>
        <button type="button" onclick="resetInvTableOrder()" class="text-xs font-semibold text-primary-700">
            <i class="ti ti-arrow-back-up" aria-hidden="true"></i> Urutan Asli
        </button>
    </div>
    <div x-show="$store.invCount.mode === 'table'" x-cloak class="overflow-x-auto rounded-xl border border-gray-200 -mx-4 lg:mx-0">
        <table id="inv-table" class="min-w-full text-sm border-collapse">
            <thead>
                <tr class="bg-gray-50">
                    <th class="sticky left-0 z-10 bg-gray-50 px-2 py-2.5 text-center font-semibold text-gray-600 w-10">No.</th>
                    <th class="sticky left-10 z-10 bg-gray-50 px-3 py-2.5 text-left font-semibold text-gray-600 w-[180px] cursor-pointer select-none" onclick="sortInvTable('itemName', 'text')">
                        Item <i class="ti ti-arrows-sort text-xs text-gray-400" data-sort-icon="itemName" aria-hidden="true"></i>
                    </th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-600 whitespace-nowrap w-[110px] cursor-pointer select-none" onclick="sortInvTable('location', 'text')">
                        Lokasi <i class="ti ti-arrows-sort text-xs text-gray-400" data-sort-icon="location" aria-hidden="true"></i>
                    </th>
                    <th class="px-3 py-2.5 text-center font-semibold text-gray-600 w-[110px] cursor-pointer select-none" onclick="sortInvTable('qtyWhole', 'number')">
                        Utuh <i class="ti ti-arrows-sort text-xs text-gray-400" data-sort-icon="qtyWhole" aria-hidden="true"></i>
                    </th>
                    <th class="px-3 py-2.5 text-center font-semibold text-gray-600 w-[110px] cursor-pointer select-none" onclick="sortInvTable('qtyLoose', 'number')">
                        Ecer <i class="ti ti-arrows-sort text-xs text-gray-400" data-sort-icon="qtyLoose" aria-hidden="true"></i>
                    </th>
                    <th class="px-3 py-2.5 text-center font-semibold text-gray-600 whitespace-nowrap w-[100px] cursor-pointer select-none" onclick="sortInvTable('isCounted', 'bool')">
                        Status <i class="ti ti-arrows-sort text-xs text-gray-400" data-sort-icon="isCounted" aria-hidden="true"></i>
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($linesBySubcategory as $subcategory => $lines)
                    <tr class="bg-primary-50" x-show="$store.invCount.categoryFilter === '' || $store.invCount.categoryFilter === @js($subcategory)">
                        <td colspan="6" class="sticky left-0 px-3 py-2 text-xs font-bold uppercase tracking-wide text-primary-800 bg-primary-50">{{ $subcategory }}</td>
                    </tr>
                    @foreach($lines as $line)
                        @php $rowNum++; @endphp
                        @include('operations.inventory-count.partials.table-row', ['line' => $line, 'document' => $document, 'rowNum' => $rowNum])
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    @if($document->status === 'DRAFT')
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-100 bg-white px-4 py-3 lg:left-64">
            <form method="POST" action="{{ route('operations.inventory-count.submit', $document) }}"
                  @submit.prevent="await waitForPendingSaves(); $event.target.querySelector('[name=expected_version]').value = $store.invCount.docVersion; $event.target.submit();">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $document->version }}">
                <button type="submit" class="sf-btn-primary min-h-12 w-full" :disabled="$store.invCount.pendingSaves > 0">
                    <span x-show="$store.invCount.pendingSaves === 0">Kirim Hasil Hitung (<span x-text="$store.invCount.counted"></span>/<span x-text="$store.invCount.total"></span>)</span>
                    <span x-show="$store.invCount.pendingSaves > 0">Menunggu autosave selesai...</span>
                </button>
            </form>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
/**
 * Dipakai tombol Kirim -- menunggu SEMUA autosave yang sedang berjalan
 * (debounce yang baru terpicu / request yang belum selesai) sebelum
 * benar-benar submit, supaya tidak ada perubahan terakhir yang "ketinggalan"
 * kalau staf mengetik lalu langsung pencet Kirim. Timeout 8 detik sebagai
 * jaga-jaga kalau autosave gagal total (daripada tombol macet selamanya).
 */
async function waitForPendingSaves() {
    const start = Date.now();
    while (Alpine.store('invCount').pendingSaves > 0 && Date.now() - start < 8000) {
        await new Promise((resolve) => setTimeout(resolve, 150));
    }
}

/**
 * Sort Mode Tabel -- baca state LIVE dari komponen Alpine tiap baris
 * (Alpine.$data), bukan nilai server-rendered awal, supaya tetap benar
 * walau staf sudah mengisi beberapa angka sebelum mengurutkan. Baris
 * pembatas kategori disembunyikan selama sort kustom aktif (grouping
 * tidak relevan lagi kalau diurutkan lintas kategori) -- "Urutan Asli"
 * mengembalikan DOM ke urutan semula termasuk baris pembatasnya.
 */
let invTableOriginalOrder = null;
let invTableSortState = { key: null, dir: 'asc' };

function sortInvTable(key, type) {
    const tbody = document.querySelector('#inv-table tbody');
    if (!tbody) return;

    if (!invTableOriginalOrder) {
        invTableOriginalOrder = Array.from(tbody.children);
    }

    invTableSortState.dir = (invTableSortState.key === key && invTableSortState.dir === 'asc') ? 'desc' : 'asc';
    invTableSortState.key = key;

    const rows = Array.from(tbody.querySelectorAll('tr[x-data]'));
    const valueOf = (row) => {
        const data = Alpine.$data(row);
        const raw = data ? data[key] : null;
        if (type === 'number') return raw === null || raw === '' || raw === undefined ? -Infinity : parseFloat(raw);
        if (type === 'bool') return raw ? 1 : 0;
        return String(raw ?? '').toLowerCase();
    };

    rows.sort((a, b) => {
        const va = valueOf(a), vb = valueOf(b);
        const cmp = typeof va === 'string' ? va.localeCompare(vb) : va - vb;
        return invTableSortState.dir === 'asc' ? cmp : -cmp;
    });

    rows.forEach((row) => tbody.appendChild(row)); // baris pembatas kategori tertinggal di posisi lama, tidak ikut dipindah -> otomatis tak lagi berdekatan dengan grupnya, disembunyikan di bawah.
    tbody.querySelectorAll('tr:not([x-data])').forEach((divider) => { divider.style.display = 'none'; });

    document.querySelectorAll('[data-sort-icon]').forEach((icon) => {
        icon.className = 'ti ti-arrows-sort text-xs text-gray-400';
    });
    const activeIcon = document.querySelector(`[data-sort-icon="${key}"]`);
    if (activeIcon) {
        activeIcon.className = 'ti text-xs text-primary-700 ' + (invTableSortState.dir === 'asc' ? 'ti-sort-ascending' : 'ti-sort-descending');
    }
}

function resetInvTableOrder() {
    const tbody = document.querySelector('#inv-table tbody');
    if (!tbody || !invTableOriginalOrder) return;

    invTableOriginalOrder.forEach((row) => {
        row.style.display = '';
        tbody.appendChild(row);
    });
    invTableSortState = { key: null, dir: 'asc' };
    document.querySelectorAll('[data-sort-icon]').forEach((icon) => {
        icon.className = 'ti ti-arrows-sort text-xs text-gray-400';
    });
}

function inventoryCountLine(config) {
    return {
        lineId: config.lineId,
        url: config.url,
        zeroUrl: config.zeroUrl,
        itemName: config.itemName ?? '',
        location: config.location ?? '',
        category: config.category ?? '',
        qtyWhole: config.qtyWhole,
        qtyLoose: config.qtyLoose,
        requiresConversion: config.requiresConversion,
        configValid: config.configValid,
        isCounted: config.isCounted,
        wasCountedBefore: config.isCounted,
        saved: false,
        saving: false,
        saveFailed: false,
        conflict: false,
        // Dipakai x-show di line-card.blade.php dan table-row.blade.php --
        // search/locationFilter/categoryFilter ada di Alpine.store('invCount')
        // (BUKAN $parent) supaya tidak kena masalah "$parent is not defined"
        // yang sama persis dengan bug mode Kartu yang pernah ditemukan.
        matchesSearch() {
            const q = Alpine.store('invCount').search.trim().toLowerCase();
            return q === '' || this.itemName.toLowerCase().includes(q);
        },
        // Mode List/Kartu: filter Kategori JUGA ditangani di level
        // pembungkus grup subkategori (x-show di show.blade.php, grup = div
        // nyata membungkus kartu-kartunya, jadi otomatis ikut tersembunyi).
        // Mode Tabel: baris <tr> adalah SIBLING dari baris pembatas kategori
        // (bukan child -- <tbody> tidak bisa membungkus per-grup), jadi
        // kategori WAJIB dicek di sini juga supaya tetap benar di Tabel.
        // Lokasi SELALU dicek di sini (bukan di grup) karena 1 subkategori
        // bisa berisi lebih dari 1 lokasi (mis. BAR: Gudang & Bar Area).
        isVisible() {
            const store = Alpine.store('invCount');
            const locOk = store.locationFilter === '' || this.location === store.locationFilter;
            const catOk = store.categoryFilter === '' || this.category === store.categoryFilter;
            return this.matchesSearch() && locOk && catOk;
        },
        // Alpine store 'invCount' diinisialisasi di wrapper x-init (lihat
        // show.blade.php) -- dibaca via $store, BUKAN lewat $root/__x yang
        // tidak reliable lintas komponen Alpine 3. pendingSaves dipakai
        // tombol Kirim supaya menunggu autosave yang sedang berjalan
        // selesai dulu (lihat waitForPendingSaves() di bawah) -- submit
        // TIDAK PERNAH jalan sementara ada autosave yang belum selesai.
        async save() {
            if (Alpine.store('invCount').status === 'SUBMITTED') return;
            await this._persist(this.url, 'PATCH', {
                qty_whole: this.qtyWhole,
                qty_loose: this.qtyLoose,
                expected_version: Alpine.store('invCount').docVersion,
            });
        },
        // Tombol "Stok Nol" -- panggil endpoint dedicated POST .../zero
        // (bukan reuse save() dgn qty di-set manual) supaya nilai nol
        // dipaksa di sisi SERVER (InventoryCountService::markZero()),
        // bukan sekadar dikirim client sebagai qty_whole='0' biasa --
        // cegah divergensi diam-diam antara tombol ini dan endpoint
        // dedicated yang memang disediakan utk aksi ini (lihat temuan
        // B-F2, docs/technical/uat-inventory-simple-2026-10-13.md).
        async markZero() {
            if (Alpine.store('invCount').status === 'SUBMITTED') return;
            const ok = await this._persist(this.zeroUrl, 'POST', {
                expected_version: Alpine.store('invCount').docVersion,
            });
            if (ok) {
                this.qtyWhole = '0';
                this.qtyLoose = this.requiresConversion ? '0' : null;
            }
        },
        async _persist(url, method, body) {
            this.saving = true;
            this.saveFailed = false;
            this.conflict = false;
            Alpine.store('invCount').pendingSaves++;

            try {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(body),
                });

                if (response.status === 409) {
                    this.conflict = true;
                    return false;
                }
                if (!response.ok) {
                    // Gagal tersimpan (validasi/server error) -- JANGAN
                    // pernah menampilkan "Tersimpan" untuk ini, tampilkan
                    // indikator gagal yang jelas supaya staf tahu harus
                    // coba lagi, bukan mengira datanya sudah aman.
                    this.saveFailed = true;
                    return false;
                }

                const data = await response.json();
                this.isCounted = data.line.is_counted;
                window.dispatchEvent(new CustomEvent('inv-line-saved', {
                    detail: { documentVersion: data.document_version, wasCounted: this.wasCountedBefore, nowCounted: this.isCounted },
                }));
                this.wasCountedBefore = this.isCounted;
                this.saved = true;
                setTimeout(() => this.saved = false, 1200);
                return true;
            } catch (networkError) {
                // fetch() melempar exception kalau koneksi benar-benar
                // putus (bukan cuma response non-200) -- tanpa catch ini,
                // "saving" akan macet true selamanya dan tidak ada
                // feedback sama sekali ke staf.
                this.saveFailed = true;
                return false;
            } finally {
                this.saving = false;
                Alpine.store('invCount').pendingSaves--;
            }
        },
    };
}
</script>
@endpush
