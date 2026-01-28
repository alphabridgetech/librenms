<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TftpDownloadController extends Controller
{
    public function download($file)
{
    $file = basename($file); // prevent path traversal
    $path = "/tftpboot/{$file}";

    if (!file_exists($path)) {
        abort(404, 'File not found');
    }

    return response()->download($path, $file, [
        'Content-Type' => 'text/plain'
    ]);
}

}
