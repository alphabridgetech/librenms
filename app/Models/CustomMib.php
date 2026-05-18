<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomMib extends Model
{
    protected $fillable = [
        'filename',  // the filename only (e.g. CISCO-SMI.mib)
        'path',      // full filesystem path (/opt/librenms/mibs/CISCO-SMI.mib)
        'user_id',   // uploader
        'model_name', // nullable, model_name
    ];

    // uploader relation
    public function uploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

}
