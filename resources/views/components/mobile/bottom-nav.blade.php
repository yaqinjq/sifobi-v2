<nav class="fixed inset-x-0 bottom-0 z-40 bg-white border-t border-gray-100 lg:hidden"
     style="padding-bottom: env(safe-area-inset-bottom); box-shadow: var(--shadow-bottom-nav);">
    <div class="flex h-16">
        @php
        $navItems = [
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
            [
                'href'    => auth()->user()->can('view_master_data') ? route('master-data.items.index') : '#',
                'label'   => auth()->user()->can('view_master_data') ? 'Master' : 'Profil',
                'pattern' => auth()->user()->can('view_master_data') ? 'master-data.*' : 'profile.*',
                'icon'    => auth()->user()->can('view_master_data') ? 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4' : 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
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
    </div>
</nav>
