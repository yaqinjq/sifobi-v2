<nav class="fixed inset-x-0 bottom-0 z-40 bg-white border-t border-gray-100 lg:hidden"
     style="padding-bottom: env(safe-area-inset-bottom); box-shadow: var(--shadow-bottom-nav);"
     x-data="{ moreOpen: false }">

    {{-- More / Lainnya slide-up sheet --}}
    <div x-show="moreOpen"
         x-cloak
         @click="moreOpen = false"
         class="fixed inset-0 z-50 bg-black/40"
         style="display:none;">
    </div>
    <div x-show="moreOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
         class="fixed inset-x-0 bottom-0 z-50 bg-white rounded-t-2xl shadow-xl"
         style="display:none; padding-bottom: env(safe-area-inset-bottom);"
         @click.stop>
        <div class="px-4 pt-3 pb-2">
            <div class="w-10 h-1 rounded-full bg-gray-200 mx-auto mb-4"></div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Bantuan & Info</p>
            <a href="{{ route('tutorial') }}"
               class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-9 h-9 rounded-xl bg-primary-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-primary-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800">SOP & Tutorial</p>
                    <p class="text-xs text-gray-400">Panduan lengkap penggunaan SIFOBI</p>
                </div>
            </a>
            <a href="{{ route('changelog') }}"
               class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-800">Changelog</p>
                    <p class="text-xs text-gray-400">Riwayat pembaruan sistem</p>
                </div>
            </a>

            @php $canPo = auth()->user()->can('create_po'); @endphp
            @if($canPo)
            <div class="border-t border-gray-100 mt-2 pt-2">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">Operasi</p>
                <a href="{{ route('receiving.goods-receipts.index') }}"
                   class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-50 transition-colors">
                    <div class="w-9 h-9 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Penerimaan Barang</p>
                        <p class="text-xs text-gray-400">GR & scan QR/barcode</p>
                    </div>
                </a>
                @can('view_reports')
                <a href="{{ route('laporan.stok-menipis') }}"
                   class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-50 transition-colors">
                    <div class="w-9 h-9 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Stok Menipis</p>
                        <p class="text-xs text-gray-400">Item di bawah minimum stok</p>
                    </div>
                </a>
                @endcan
            </div>
            @endif

            <div class="border-t border-gray-100 mt-2 pt-2">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-red-50 transition-colors text-left">
                        <div class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-red-600">Keluar</p>
                            <p class="text-xs text-gray-400">{{ auth()->user()->name ?? '' }}</p>
                        </div>
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Bottom nav bar --}}
    <div class="flex h-16">
        @php
        $canPo     = auth()->user()->can('create_po');
        $canMaster = auth()->user()->can('view_master_data');

        $navItems = $canPo
            ? [
                [
                    'href'    => route('dashboard'),
                    'label'   => 'Beranda',
                    'pattern' => 'dashboard',
                    'icon'    => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                ],
                [
                    'href'    => route('procurement.purchase-orders.create'),
                    'label'   => 'Buat PO',
                    'pattern' => 'procurement.purchase-orders.create',
                    'icon'    => 'M12 4v16m8-8H4',
                ],
                [
                    'href'    => route('procurement.purchase-orders.index'),
                    'label'   => 'PO Saya',
                    'pattern' => 'procurement.purchase-orders.*',
                    'icon'    => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
                ],
                [
                    'href'    => auth()->user()->can('input_opname') ? route('operations.opname.index') : '#',
                    'label'   => 'Opname',
                    'pattern' => 'operations.opname.*',
                    'icon'    => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
                ],
            ]
            : [
                [
                    'href'    => route('dashboard'),
                    'label'   => 'Beranda',
                    'pattern' => 'dashboard',
                    'icon'    => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                ],
                [
                    'href'    => route('operations.open-stocks.index'),
                    'label'   => 'Stok',
                    'pattern' => 'operations.open-stocks.*',
                    'icon'    => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                ],
                [
                    'href'    => auth()->user()->can('input_opname') ? route('operations.opname.index') : '#',
                    'label'   => 'Opname',
                    'pattern' => 'operations.opname.*',
                    'icon'    => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
                ],
                [
                    'href'    => auth()->user()->can('record_spoil') ? route('operations.spoil-wastes.index') : '#',
                    'label'   => 'Spoil',
                    'pattern' => 'operations.spoil-wastes.*',
                    'icon'    => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
                ],
            ];
        @endphp

        @foreach($navItems as $item)
        @php $isActive = request()->routeIs($item['pattern']); @endphp
        <a href="{{ $item['href'] }}"
           class="relative flex flex-1 flex-col items-center justify-center gap-0.5 transition-colors min-w-0 py-2
                  {{ $isActive ? 'text-primary-700' : 'text-gray-400' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
            </svg>
            <span class="text-[10px] font-medium truncate max-w-full px-1">{{ $item['label'] }}</span>
            @if($isActive)
                <span class="absolute top-0 left-1/2 -translate-x-1/2 w-6 h-0.5 rounded-b-full bg-primary-700"></span>
            @endif
        </a>
        @endforeach

        {{-- Lainnya / More --}}
        <button type="button"
                @click="moreOpen = !moreOpen"
                :class="moreOpen ? 'text-primary-700' : 'text-gray-400'"
                class="relative flex flex-1 flex-col items-center justify-center gap-0.5 transition-colors min-w-0 py-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <span class="text-[10px] font-medium">Lainnya</span>
            <span x-show="moreOpen" class="absolute top-0 left-1/2 -translate-x-1/2 w-6 h-0.5 rounded-b-full bg-primary-700"></span>
        </button>
    </div>
</nav>
