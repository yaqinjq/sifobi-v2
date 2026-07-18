@php
    $isEdit = $method === 'PUT';
    $selectedRole = old('role', $user->roles->first()?->name);
    $selectedStatus = old('status', strtoupper((string) ($user->status ?? 'ACTIVE')) === 'INACTIVE' ? 'inactive' : 'active');
    $roleDescriptions = [
        'SUPER_ADMIN' => 'Akses penuh ke seluruh sistem.',
        'ADMIN' => 'Akses penuh ke semua outlet dalam grup.',
        'GENERAL_FINANCE' => 'Akses laporan dan master data keuangan.',
        'FINANCE_ACCOUNTING_STAFF' => 'Input data keuangan per outlet.',
        'MANAGER_AREA' => 'Monitor outlet di area yang ditugaskan.',
        'PIC_OUTLET' => 'Kelola operasional satu outlet.',
        'STAFF_BAR' => 'Input opname, spoil, dan stok area bar.',
        'STAFF_KITCHEN' => 'Input opname, spoil, dan stok area kitchen.',
        'STAFF_SERVICE' => 'Akses operasional service sesuai izin.',
        'STAFF_GUDANG' => 'Penerimaan barang dan stok gudang.',
    ];
@endphp

<form method="POST"
      action="{{ $action }}"
      class="px-4 py-5 pb-28 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-4"
      x-data="{
          role: @js($selectedRole ?: ''),
          descriptions: @js($roleDescriptions)
      }">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-sf.card title="Informasi Akun">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-sf.form-group label="Nama Lengkap" for="name" :required="true">
                <input type="text"
                       name="name"
                       id="name"
                       value="{{ old('name', $user->name) }}"
                       class="sf-input text-base"
                       required
                       maxlength="255">
            </x-sf.form-group>

            <x-sf.form-group label="Email" for="email" :required="true">
                <input type="email"
                       name="email"
                       id="email"
                       value="{{ old('email', $user->email) }}"
                       class="sf-input text-base"
                       required
                       maxlength="255"
                       autocomplete="email">
            </x-sf.form-group>

            <x-sf.form-group label="No. HP" for="phone">
                <input type="tel"
                       inputmode="numeric"
                       name="phone"
                       id="phone"
                       value="{{ old('phone', $user->phone) }}"
                       class="sf-input text-base"
                       maxlength="20"
                       autocomplete="tel">
            </x-sf.form-group>

            <div></div>

            <x-sf.form-group :label="$isEdit ? 'Password Baru' : 'Password'" for="password" :required="! $isEdit" :hint="$isEdit ? 'Kosongkan jika tidak ingin mengubah password.' : 'Minimal 8 karakter.'">
                <div class="relative" x-data="{ showPwd: false }">
                    <input :type="showPwd ? 'text' : 'password'"
                           name="password"
                           id="password"
                           class="sf-input text-base pr-11"
                           @if(! $isEdit) required @endif
                           minlength="8"
                           autocomplete="new-password">
                    <button type="button"
                            @click="showPwd = !showPwd"
                            :title="showPwd ? 'Sembunyikan' : 'Tampilkan'"
                            aria-label="Toggle password visibility"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                        <i :class="showPwd ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base" aria-hidden="true"></i>
                    </button>
                </div>
            </x-sf.form-group>

            <x-sf.form-group label="Konfirmasi Password" for="password_confirmation" :required="! $isEdit">
                <div class="relative" x-data="{ showPwdC: false }">
                    <input :type="showPwdC ? 'text' : 'password'"
                           name="password_confirmation"
                           id="password_confirmation"
                           class="sf-input text-base pr-11"
                           @if(! $isEdit) required @endif
                           minlength="8"
                           autocomplete="new-password">
                    <button type="button"
                            @click="showPwdC = !showPwdC"
                            :title="showPwdC ? 'Sembunyikan' : 'Tampilkan'"
                            aria-label="Toggle password visibility"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                        <i :class="showPwdC ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base" aria-hidden="true"></i>
                    </button>
                </div>
            </x-sf.form-group>
        </div>
    </x-sf.card>

    <x-sf.card title="Role & Akses">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <x-sf.form-group label="Role" for="role" :required="true">
                    <select name="role" id="role" x-model="role" class="sf-input text-base" required>
                        <option value="">Pilih role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" @selected($selectedRole === $role->name)>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </x-sf.form-group>

                <div class="mt-3 rounded-2xl border border-primary-100 bg-primary-50 px-4 py-3 text-sm text-primary-900" x-show="role" x-cloak>
                    <p class="font-semibold" x-text="role"></p>
                    <p class="mt-1 text-primary-700" x-text="descriptions[role] || 'Role khusus sesuai permission yang diberikan.'"></p>
                </div>
            </div>

            <x-sf.form-group label="Outlet yang Ditugaskan" for="outlet_id" hint="Kosongkan untuk role yang mengakses semua outlet.">
                <select name="outlet_id" id="outlet_id" class="sf-input text-base">
                    <option value="">Semua outlet / global</option>
                    @foreach($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected((string) old('outlet_id', $user->outlet_id) === (string) $outlet->id)>
                            {{ $outlet->name }}
                        </option>
                    @endforeach
                </select>
            </x-sf.form-group>

            <div>
                <p class="sf-label mb-1.5">Status</p>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer">
                        <input type="radio"
                               name="status"
                               value="active"
                               class="sr-only peer"
                               @checked($selectedStatus === 'active')>
                        <span class="flex min-h-11 items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-500 transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-50 peer-checked:text-primary-800">
                            Aktif
                        </span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio"
                               name="status"
                               value="inactive"
                               class="sr-only peer"
                               @checked($selectedStatus === 'inactive')>
                        <span class="flex min-h-11 items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-500 transition-colors peer-checked:border-gray-500 peer-checked:bg-gray-100 peer-checked:text-gray-800">
                            Non-Aktif
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </x-sf.card>

    <div class="sticky bottom-[calc(5rem+env(safe-area-inset-bottom))] lg:static bg-white/95 backdrop-blur border-t border-gray-100 -mx-4 px-4 py-3 lg:border-0 lg:bg-transparent lg:backdrop-blur-none lg:mx-0 lg:px-0 lg:py-0">
        <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
            <a href="{{ route('settings.users.index') }}" class="sf-btn-secondary min-h-11 text-center">
                Batal
            </a>
            <button type="submit" class="sf-btn-primary min-h-11">
                {{ $submitLabel }}
            </button>
        </div>
    </div>
</form>
