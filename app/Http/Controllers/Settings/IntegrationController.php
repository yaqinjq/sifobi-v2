<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\IntegrationProfile;
use App\Modules\Core\Models\LegalEntity;
use App\Modules\Core\Models\Outlet;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->tenantId($request);

        return view('settings.integrations.index', [
            'profiles' => IntegrationProfile::query()
                ->where('tenant_id', $tenantId)
                ->orderByRaw('COALESCE(code, provider) asc')
                ->orderBy('name')
                ->get(),
            'authTypes' => ['NONE', 'BEARER', 'BASIC'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $this->validated($request);

        $request->validate([
            'code' => [
                'nullable',
                Rule::unique('integration_profiles', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ]);

        DB::transaction(function () use ($tenantId, $data): void {
            IntegrationProfile::withoutGlobalScopes()->create(
                $this->profilePayload($data, $tenantId)
            );
        });

        return back()->with('success', 'Profil integrasi berhasil disimpan.');
    }

    public function update(Request $request, IntegrationProfile $integration): RedirectResponse
    {
        $this->authorizeTenant($request, $integration);
        $data = $this->validated($request);

        $request->validate([
            'code' => [
                'nullable',
                Rule::unique('integration_profiles', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $integration->tenant_id))
                    ->ignore($integration->id),
            ],
        ]);

        DB::transaction(function () use ($integration, $data): void {
            $payload = $this->profilePayload($data, (int) $integration->tenant_id, $integration);

            if (blank($data['auth_password'] ?? null) && blank($data['password'] ?? null)) {
                unset($payload['auth_password'], $payload['password']);
            }

            $integration->update($payload);
        });

        return back()->with('success', 'Profil integrasi berhasil diperbarui.');
    }

    public function destroy(Request $request, IntegrationProfile $integration): RedirectResponse
    {
        $this->authorizeTenant($request, $integration);

        DB::transaction(function () use ($integration): void {
            $integration->delete();
        });

        return back()->with('success', 'Profil integrasi berhasil dihapus.');
    }

    public function testConnection(Request $request, IntegrationProfile $integration): JsonResponse
    {
        $this->authorizeTenant($request, $integration);

        try {
            $path = (string) data_get($integration->meta, 'health_path', '/api/health');
            $response = $this->client($integration)->get($this->url($integration, $path));

            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Koneksi berhasil (HTTP '.$response->status().').'
                    : 'Server merespons tapi error HTTP '.$response->status().'.',
                'latency_ms' => null,
            ], $response->successful() ? 200 : 422);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa konek: '.$exception->getMessage(),
            ], 422);
        }
    }

    public function syncOutlets(Request $request, IntegrationProfile $integration): JsonResponse
    {
        $this->authorizeTenant($request, $integration);
        $tenantId = $this->tenantId($request);

        $defaultBrand = Brand::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->first();
        $defaultLegalEntity = LegalEntity::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->first();

        if (! $defaultBrand || ! $defaultLegalEntity) {
            return response()->json([
                'success' => false,
                'message' => 'Sync outlet butuh minimal 1 brand aktif dan 1 legal entity aktif.',
            ], 422);
        }

        try {
            $path = (string) (data_get($integration->meta, 'outlet_list_path') ?: $integration->outlet_sync_path ?: '/api/outlets');
            $response = $this->client($integration)->get($this->url($integration, $path));

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Server integrasi mengembalikan HTTP '.$response->status().'.',
                ], 422);
            }

            $payload = $response->json();
            $rows = collect(data_get($payload, 'data', $payload))->filter(fn ($row) => is_array($row));
            $inserted = 0;
            $skipped = 0;
            $errors = [];

            DB::transaction(function () use ($rows, $tenantId, $defaultBrand, $defaultLegalEntity, &$inserted, &$skipped, &$errors, $integration): void {
                foreach ($rows as $index => $row) {
                    $code = strtoupper(trim((string) (Arr::get($row, 'kode_outlet') ?: Arr::get($row, 'outlet_code') ?: Arr::get($row, 'code'))));
                    $name = trim((string) (Arr::get($row, 'nama_outlet') ?: Arr::get($row, 'outlet_name') ?: Arr::get($row, 'name')));

                    if ($code === '' || $name === '') {
                        $errors[] = 'Baris '.($index + 1).': kode atau nama outlet kosong.';
                        $skipped++;
                        continue;
                    }

                    $exists = Outlet::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('code', $code)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    Outlet::withoutGlobalScopes()->create([
                        'tenant_id' => $tenantId,
                        'brand_id' => $defaultBrand->id,
                        'legal_entity_id' => $defaultLegalEntity->id,
                        'code' => $code,
                        'name' => $name,
                        'outlet_type' => 'OUTLET',
                        'timezone' => 'Asia/Jakarta',
                        'address' => Arr::get($row, 'address') ?: Arr::get($row, 'alamat'),
                        'contact_phone' => Arr::get($row, 'phone') ?: Arr::get($row, 'contact_phone'),
                        'status' => 'ACTIVE',
                    ]);

                    $inserted++;
                }

                $integration->update(['last_synced_at' => now()]);
            });

            return response()->json([
                'success' => true,
                'inserted' => $inserted,
                'skipped' => $skipped,
                'errors' => $errors,
                'message' => "Sync selesai. Baru: {$inserted}, dilewati: {$skipped}.",
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Sync gagal: '.$exception->getMessage(),
            ], 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'code' => ['nullable', 'required_without:provider', 'string', 'max:50'],
            'provider' => ['nullable', 'required_without:code', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'base_url' => ['required', 'url', 'max:255'],
            'auth_mode' => ['nullable', Rule::in(['NONE', 'BEARER', 'BASIC'])],
            'auth_type' => ['nullable', Rule::in(['NONE', 'BEARER', 'BASIC'])],
            'auth_token' => ['nullable', 'string', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:255'],
            'auth_username' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'auth_password' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'outlet_sync_path' => ['nullable', 'string', 'max:255'],
            'health_path' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
            'meta.outlet_list_path' => ['nullable', 'string', 'max:255'],
            'meta.order_path' => ['nullable', 'string', 'max:255'],
            'meta.po_list_path' => ['nullable', 'string', 'max:255'],
            'meta.timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profilePayload(array $data, int $tenantId, ?IntegrationProfile $existing = null): array
    {
        $code = strtoupper((string) ($data['code'] ?? $data['provider'] ?? $existing?->code ?? $existing?->provider));
        $authMode = (string) ($data['auth_mode'] ?? $data['auth_type'] ?? $existing?->auth_mode ?? $existing?->auth_type ?? 'NONE');
        $token = $data['auth_token'] ?? $data['api_token'] ?? $existing?->auth_token ?? $existing?->api_token;
        $username = $data['auth_username'] ?? $data['username'] ?? $existing?->auth_username ?? $existing?->username;
        $password = $data['auth_password'] ?? $data['password'] ?? null;
        $existingMeta = $existing?->meta ?? [];
        $outletListPath = data_get($data, 'meta.outlet_list_path')
            ?: ($data['outlet_sync_path'] ?? data_get($existingMeta, 'outlet_list_path', $existing?->outlet_sync_path ?? '/api/outlets'));
        $healthPath = $data['health_path'] ?? data_get($existingMeta, 'health_path', '/api/health');
        $timeout = (int) (data_get($data, 'meta.timeout_seconds') ?: data_get($existingMeta, 'timeout_seconds', 10));

        return [
            'tenant_id' => $tenantId,
            'code' => $code,
            'provider' => $code,
            'name' => $data['name'],
            'base_url' => rtrim((string) $data['base_url'], '/'),
            'auth_mode' => $authMode,
            'auth_type' => $authMode,
            'auth_token' => $token,
            'api_token' => $token,
            'auth_username' => $username,
            'username' => $username,
            'auth_password' => blank($password) ? null : $password,
            'password' => blank($password) ? null : $password,
            'outlet_sync_path' => $outletListPath,
            'meta' => [
                'health_path' => $healthPath,
                'outlet_list_path' => $outletListPath,
                'po_list_path' => data_get($data, 'meta.po_list_path') ?: data_get($existingMeta, 'po_list_path', '/api/available-orders'),
                'order_path' => data_get($data, 'meta.order_path') ?: data_get($existingMeta, 'order_path', '/api/outlet-order'),
                'timeout_seconds' => $timeout > 0 ? $timeout : 10,
            ],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function client(IntegrationProfile $profile): PendingRequest
    {
        $client = Http::timeout((int) data_get($profile->meta, 'timeout_seconds', 10))->acceptJson();
        $authMode = $profile->auth_mode ?: $profile->auth_type;
        $token = $profile->auth_token ?: $profile->api_token;
        $username = $profile->auth_username ?: $profile->username;
        $password = $profile->auth_password ?: $profile->password;

        if ($authMode === 'BEARER' && $token) {
            return $client->withToken($token);
        }

        if ($authMode === 'BASIC' && $username) {
            return $client->withBasicAuth($username, (string) $password);
        }

        return $client;
    }

    private function url(IntegrationProfile $profile, string $path): string
    {
        return rtrim($profile->base_url, '/').'/'.ltrim($path ?: '/', '/');
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()?->tenant_id;

        abort_unless($tenantId, 403, 'Tenant belum terpasang pada user.');

        return (int) $tenantId;
    }

    private function authorizeTenant(Request $request, IntegrationProfile $integration): void
    {
        abort_unless((int) $integration->tenant_id === $this->tenantId($request), 404);
    }
}
