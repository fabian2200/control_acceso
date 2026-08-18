<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccesoSyncCheckpoint extends Model
{
    protected $table = 'acceso_sync_checkpoints';

    protected $primaryKey = 'tabla';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pulled_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
