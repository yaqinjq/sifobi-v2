<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Group extends Model
{
    use LogsActivity;

    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master-data')
            ->logUnguarded()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
