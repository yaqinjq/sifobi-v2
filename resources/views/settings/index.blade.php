@extends('layouts.app')

@section('title', 'Pengaturan')

@push('styles')
<style>
.sh-section-label{font-size:11px;font-weight:600;color:#94A3B8;text-transform:uppercase;letter-spacing:.07em;margin:0 0 8px 2px}
.sh-group{background:white;border:1px solid #E2E8F0;border-radius:14px;overflow:hidden;margin-bottom:20px}
.sh-item{display:flex;align-items:center;gap:14px;padding:14px 18px;border-bottom:1px solid #F1F5F9;text-decoration:none;transition:background .12s;color:inherit}
.sh-item:last-child{border-bottom:none}
.sh-item:hover{background:#F8FAFC}
.sh-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px}
.sh-icon-green{background:#D8F3DC;color:#1B4332}
.sh-icon-blue{background:#EFF6FF;color:#1D4ED8}
.sh-icon-amber{background:#FFFBEB;color:#92400E}
.sh-icon-purple{background:#F5F3FF;color:#6D28D9}
.sh-icon-teal{background:#F0FDFA;color:#0F766E}
.sh-icon-gray{background:#F8FAFC;color:#64748B}
.sh-text{flex:1;min-width:0}
.sh-name{font-size:14px;font-weight:600;color:#0F172A;margin:0 0 2px}
.sh-desc{font-size:12px;color:#64748B;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sh-badge{font-size:11px;font-weight:600;padding:2px 9px;border-radius:100px;background:#DBEAFE;color:#1E40AF;flex-shrink:0}
.sh-arrow{color:#CBD5E1;font-size:16px;flex-shrink:0}
</style>
@endpush

@section('content')
<div class="p-4 lg:p-6 pb-24" style="max-width:640px;margin:0 auto">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 font-heading">Pengaturan</h1>
        <span class="sr-only">Pengaturan Sistem</span>
        <p class="text-sm text-gray-500 mt-1">Konfigurasi sistem, data master, dan integrasi</p>
    </div>

    <p class="sh-section-label">Aplikasi</p>
    <div class="sh-group">
        @can('manage_settings')
            <a href="{{ route('settings.app') }}" class="sh-item">
                <div class="sh-icon sh-icon-green"><i class="ti ti-paint" aria-hidden="true"></i></div>
                <div class="sh-text">
                    <p class="sh-name">Tampilan aplikasi</p>
                    <p class="sh-desc">Logo, nama, favicon, dan warna utama</p>
                </div>
                <i class="ti ti-chevron-right sh-arrow" aria-hidden="true"></i>
            </a>
        @endcan
        @can('manage_integrations')
            <a href="{{ route('settings.integrations.index') }}" class="sh-item">
                <div class="sh-icon sh-icon-blue"><i class="ti ti-plug" aria-hidden="true"></i></div>
                <div class="sh-text">
                    <p class="sh-name">Integrasi & API</p>
                    <p class="sh-desc">OCIA, OMEO, dan koneksi sistem eksternal</p>
                </div>
                <i class="ti ti-chevron-right sh-arrow" aria-hidden="true"></i>
            </a>
        @endcan
    </div>

    <p class="sh-section-label">Master data</p>
    <div class="sh-group">
        @can('manage_settings')
            <a href="{{ route('settings.item-jenises.index') }}" class="sh-item">
                <div class="sh-icon sh-icon-amber"><i class="ti ti-tag" aria-hidden="true"></i></div>
                <div class="sh-text">
                    <p class="sh-name">Jenis bahan</p>
                    <p class="sh-desc">Drygood, Raw Material, WIP, dan lainnya</p>
                </div>
                <i class="ti ti-chevron-right sh-arrow" aria-hidden="true"></i>
            </a>
            <a href="{{ route('settings.item-categories.index') }}" class="sh-item">
                <div class="sh-icon sh-icon-amber"><i class="ti ti-folder" aria-hidden="true"></i></div>
                <div class="sh-text">
                    <p class="sh-name">Kategori bahan</p>
                    <p class="sh-desc">Coffee & Tea, Milk, Syrup, Packaging, dan lainnya</p>
                </div>
                <i class="ti ti-chevron-right sh-arrow" aria-hidden="true"></i>
            </a>
            <a href="{{ route('settings.departments.index') }}" class="sh-item">
                <div class="sh-icon sh-icon-gray"><i class="ti ti-building" aria-hidden="true"></i></div>
                <div class="sh-text">
                    <p class="sh-name">Departemen</p>
                    <p class="sh-desc">Bar, Kitchen, Service, Office, dan Pastry</p>
                </div>
                <i class="ti ti-chevron-right sh-arrow" aria-hidden="true"></i>
            </a>
        @endcan
        <a href="{{ route('master-data.units.index') }}" class="sh-item">
            <div class="sh-icon sh-icon-gray"><i class="ti ti-ruler" aria-hidden="true"></i></div>
            <div class="sh-text">
                <p class="sh-name">Satuan & konversi</p>
                <p class="sh-desc">Gram, liter, sachet, karton, dan konversinya</p>
            </div>
            <i class="ti ti-chevron-right sh-arrow" aria-hidden="true"></i>
        </a>
    </div>

    @if(auth()->user()->can('manage_brands_outlets') || auth()->user()->can('manage_settings'))
        <p class="sh-section-label">Bisnis</p>
        <div class="sh-group">
            @can('manage_brands_outlets')
                <a href="{{ route('settings.brands.index') }}" class="sh-item">
                    <div class="sh-icon sh-icon-green"><i class="ti ti-badge" aria-hidden="true"></i></div>
                    <div class="sh-text">
                        <p class="sh-name">Brand</p>
                        <p class="sh-desc">Daftar identitas brand dalam grup bisnis</p>
                    </div>
                    @php $brandCount = \App\Modules\Core\Models\Brand::query()->count(); @endphp
                    @if($brandCount > 0)
                        <span class="sh-badge">{{ $brandCount }}</span>
                    @endif
                    <i class="ti ti-chevron-right sh-arrow" aria-hidden="true"></i>
                </a>
                <a href="{{ route('settings.outlets.index') }}" class="sh-item">
                    <div class="sh-icon sh-icon-green"><i class="ti ti-store" aria-hidden="true"></i></div>
                    <div class="sh-text">
                        <p class="sh-name">Outlet</p>
                        <p class="sh-desc">Daftar dan konfigurasi semua gerai aktif</p>
                    </div>
                    @php $outletCount = \App\Modules\Core\Models\Outlet::query()->count(); @endphp
                    @if($outletCount > 0)
                        <span class="sh-badge">{{ $outletCount }}</span>
                    @endif
                    <i class="ti ti-chevron-right sh-arrow" aria-hidden="true"></i>
                </a>
            @endcan
            @can('manage_settings')
                <a href="{{ route('settings.suppliers.index') }}" class="sh-item">
                    <div class="sh-icon sh-icon-teal"><i class="ti ti-truck" aria-hidden="true"></i></div>
                    <div class="sh-text">
                        <p class="sh-name">Supplier</p>
                        <p class="sh-desc">Daftar vendor dan supplier eksternal</p>
                    </div>
                    <i class="ti ti-chevron-right sh-arrow" aria-hidden="true"></i>
                </a>
            @endcan
        </div>
    @endif

    <p class="sh-section-label">Operasional</p>
    <div class="sh-group">
        @can('manage_stock_configs')
            <a href="{{ route('settings.stock-configs.index') }}" class="sh-item">
                <div class="sh-icon sh-icon-purple"><i class="ti ti-chart-bar" aria-hidden="true"></i></div>
                <div class="sh-text">
                    <p class="sh-name">Konfigurasi stok</p>
                    <p class="sh-desc">Min, maks, dan reorder point per item per outlet</p>
                </div>
                <i class="ti ti-chevron-right sh-arrow" aria-hidden="true"></i>
            </a>
        @endcan
        @can('manage_calendar_events')
            <a href="{{ route('settings.calendar-events.index') }}" class="sh-item">
                <div class="sh-icon sh-icon-purple"><i class="ti ti-calendar" aria-hidden="true"></i></div>
                <div class="sh-text">
                    <p class="sh-name">Kalender event</p>
                    <p class="sh-desc">Hari raya, promo, dan peak season dengan multiplier</p>
                </div>
                <i class="ti ti-chevron-right sh-arrow" aria-hidden="true"></i>
            </a>
        @endcan
    </div>

    @can('manage_users')
        <p class="sh-section-label">Pengguna</p>
        <div class="sh-group">
            <a href="{{ route('settings.users.index') }}" class="sh-item">
                <div class="sh-icon sh-icon-blue"><i class="ti ti-users" aria-hidden="true"></i></div>
                <div class="sh-text">
                    <p class="sh-name">Manajemen user</p>
                    <p class="sh-desc">Akun, role, dan hak akses per jabatan</p>
                </div>
                @php $userCount = \App\Models\User::query()->count(); @endphp
                @if($userCount > 0)
                    <span class="sh-badge">{{ $userCount }}</span>
                @endif
                <i class="ti ti-chevron-right sh-arrow" aria-hidden="true"></i>
            </a>
        </div>
    @endcan
</div>
@endsection
