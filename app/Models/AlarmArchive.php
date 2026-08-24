<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlarmArchive extends Model
{
    protected $fillable = [
        'filename',
        'file_path',
        'file_size',
        'line_count',
        'start_date',
        'end_date',
    ];
}
