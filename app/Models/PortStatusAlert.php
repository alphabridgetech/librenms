<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortStatusAlert extends Model
{
    protected $fillable = [
        'device_id',
        'port_id',
        'ifname',
        'ifdescr',
        'ifalias',
        'open',
        'down_at',
        'recovered_at',
    ];

    protected $casts = [
        'open' => 'boolean',
        'down_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function port()
    {
        return $this->belongsTo(Port::class, 'port_id', 'port_id');
    }
}
