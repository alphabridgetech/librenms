<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigBackupLog extends Model
{
    protected $fillable = [
        'device_id',
        'user_id',
        'filename',
        'tftp_server',
        'status',
        'message',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
