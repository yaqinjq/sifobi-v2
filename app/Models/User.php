<?php

namespace App\Models;

use App\Models\Concerns\HasOutletAccess;
use App\Models\Scopes\TenantScope;
use App\Modules\Core\Models\Outlet;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasOutletAccess, HasRoles, Notifiable, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'department_id',
        'name',
        'email',
        'password',
        'phone',
        'photo',
        'status',
        'last_login_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'avatar_url',
        'status_badge_class',
        'primary_role',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logOnly(['name', 'email', 'phone', 'status', 'outlet_id', 'department_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->dontLogIfAttributesChangedOnly(['last_login_at']);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    /**
     * Daftar outlet yang boleh diakses user ini (fitur multi-outlet).
     * `outlet_id` di atas TETAP ditulis saat user disimpan (sebagai
     * cerminan "outlet pertama yang dipilih"), supaya modul yang belum
     * memakai accessibleOutletIds()/soleOutletId() (lihat
     * HasOutletAccess) tetap dapat jawaban yang valid & dapat
     * diprediksi, bukan basi begitu user ini py lebih dari 1 outlet.
     */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'user_outlets', 'user_id', 'outlet_id')->withTimestamps();
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->photo) {
            return Storage::url($this->photo);
        }

        return 'https://ui-avatars.com/api/?name='
            .urlencode($this->name)
            .'&background=1B4332&color=fff&size=128';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return strtoupper((string) $this->status) === 'ACTIVE'
            ? 'badge-active'
            : 'badge-draft';
    }

    public function getPrimaryRoleAttribute(): string
    {
        return $this->roles->first()?->name ?? 'No Role';
    }
}
