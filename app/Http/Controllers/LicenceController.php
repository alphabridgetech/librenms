<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LicenceController extends Controller
{
    
    public function licenceUpload(Request $request)
    {
        $request->validate([
            'license_file' => 'required|file',
        ]);

        $file = $request->file('license_file');
        $destinationPath = base_path();

        $file->move($destinationPath, 'license.key');

        return redirect()->back()->with('success', 'License file uploaded successfully.');
    }

    public function uploadPublicKey(Request $request)
    {
        $request->validate([
            'public_key' => 'required|file',
        ]);

        $file = $request->file('public_key');
        $destinationPath = base_path();

        $file->move($destinationPath, 'public.key');

        return redirect()->back()->with('success', 'Public key uploaded successfully.');
    }

}
