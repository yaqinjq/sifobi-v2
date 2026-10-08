@extends('layouts.app')

@section('title', 'Isi Inventory')

@section('content')
<x-sf.page-header title="{{ $document->template?->name }}" subtitle="{{ $document->outlet?->name }} -- {{ $document->department?->name }}" back="{{ route('operations.inventory-count.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-4 pb-28"
     x-data="{ mode: 'list' }"
     x-init="Alpine.store('invCount', {
        docId: {{ $document->id }},
        docVersion: {{ $document->version }},
        counted: {{ $countedCount }},
        total: {{ $totalCount }},
        status: @js($document->status),
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
    @endif

    <div class="flex items-center gap-1.5">
        <button type="button" @click="mode = 'list'" class="{{ true }} sf-btn-secondary text-xs px-3 py-1.5 min-h-9" :class="mode === 'list' ? 'sf-btn-primary' : 'sf-btn-secondary'">
            <i class="ti ti-list text-sm" aria-hidden="true"></i> List
        </button>
        <button type="button" @click="mode = 'card'" class="sf-btn-secondary text-xs px-3 py-1.5 min-h-9" :class="mode === 'card' ? 'sf-btn-primary' : 'sf-btn-secondary'">
            <i class="ti ti-layout-grid text-sm" aria-hidden="true"></i> Kartu
        </button>
    </div>

    @foreach($linesBySubcategory as $subcategory => $lines)
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">{{ $subcategory }}</h3>
            <div class="space-y-3" :class="mode === 'card' ? 'lg:grid lg:grid-cols-2 lg:gap-3 lg:space-y-0' : ''">
                @foreach($lines as $line)
                    @include('operations.inventory-count.partials.line-card', ['line' => $line, 'document' => $document])
                @endforeach
            </div>
        </div>
    @endforeach

    @if($document->status === 'DRAFT')
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-100 bg-white px-4 py-3 lg:left-64">
            <form method="POST" action="{{ route('operations.inventory-count.submit', $document) }}"
                  @submit="$event.target.querySelector('[name=expected_version]').value = $store.invCount.docVersion">
                @csrf
                <input type="hidden" name="expected_version" value="{{ $document->version }}">
                <button type="submit" class="sf-btn-primary min-h-12 w-full">
                    Kirim Hasil Hitung (<span x-text="$store.invCount.counted"></span>/<span x-text="$store.invCount.total"></span>)
                </button>
            </form>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function inventoryCountLine(config) {
    return {
        lineId: config.lineId,
        url: config.url,
        qtyWhole: config.qtyWhole,
        qtyLoose: config.qtyLoose,
        requiresConversion: config.requiresConversion,
        configValid: config.configValid,
        isCounted: config.isCounted,
        wasCountedBefore: config.isCounted,
        saved: false,
        saving: false,
        conflict: false,
        // Alpine store 'invCount' diinisialisasi di wrapper x-init (lihat
        // show.blade.php) -- dibaca via $store, BUKAN lewat $root/__x yang
        // tidak reliable lintas komponen Alpine 3.
        async save() {
            if (Alpine.store('invCount').status === 'SUBMITTED') return;
            this.saving = true;
            this.conflict = false;

            try {
                const response = await fetch(this.url, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        qty_whole: this.qtyWhole,
                        qty_loose: this.qtyLoose,
                        expected_version: Alpine.store('invCount').docVersion,
                    }),
                });

                if (response.status === 409) {
                    this.conflict = true;
                    return;
                }
                if (!response.ok) return;

                const data = await response.json();
                this.isCounted = data.line.is_counted;
                window.dispatchEvent(new CustomEvent('inv-line-saved', {
                    detail: { documentVersion: data.document_version, wasCounted: this.wasCountedBefore, nowCounted: this.isCounted },
                }));
                this.wasCountedBefore = this.isCounted;
                this.saved = true;
                setTimeout(() => this.saved = false, 1200);
            } finally {
                this.saving = false;
            }
        },
        async markZero() {
            this.qtyWhole = '0';
            this.qtyLoose = this.requiresConversion ? '0' : null;
            await this.save();
        },
    };
}
</script>
@endpush
