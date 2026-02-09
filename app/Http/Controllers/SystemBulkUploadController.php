<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Facades\LibrenmsConfig;
use App\Models\CustomMib;
use App\Models\AuthLog;
use App\Models\Dashboard;
use App\Models\User;
use App\Models\Device;
use App\Models\UserPref;
use Auth;
use LibreNMS\Config;
use Illuminate\Support\Str;
use LibreNMS\Authentication\LegacyAuth;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Storage;
use URL;

class SystemBulkUploadController extends Controller
{
   public function index(Request $request)
{
    $this->authorize('viewAny', CustomMib::class);

    // dropdown list (unique hardware)
    $deviceFilter = Device::whereNotNull('hardware')
        ->distinct()
        ->orderBy('hardware')
        ->pluck('hardware');

    // selected model_name (hardware)
    $modelName = $request->input('model_name');

    // devices query
    $devicesQuery = Device::orderBy('hostname')
        ->select('device_id', 'hostname', 'sysName', 'sysObjectID', 'hardware');

    // APPLY FILTER ONLY IF SELECTED
    if (!empty($modelName)) {
        $devicesQuery->where('hardware', $modelName);
    }

    $devices = $devicesQuery->get();

    return view('syssoftbulk.index', compact(
        'devices',
        'deviceFilter',
        'modelName'
    ));
}


    public function store(Request $request)
    {
        
        $this->authorize('create', CustomMib::class);

        $request->validate([
            'model_name' => 'nullable|string',
            'mibfile' => 'required|file|max:5120', // 5MB limit
            'overwrite' => 'nullable|boolean',
        ]);

        $model_name = $request->input('model_name') ?: 'general';
        $overwrite = $request->boolean('overwrite', false);

        $file = $request->file('mibfile');
        $filename = basename($file->getClientOriginalName());

        // Base directory
        $baseDir = rtrim(Config::get('install_dir'), '/') . '/mibs/custom/';

        // Sanitize model_name for folder
        $subDir = preg_replace('/[^A-Za-z0-9_\-]/', '_', $model_name);
        $uploadDir = $baseDir . $subDir . '/';

        // Ensure directory exists
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return back()->withErrors(['mibfile' => "Cannot create directory: $uploadDir"])->withInput();
        }

        $targetPath = $uploadDir . $filename;

        // Check if file exists
        if (file_exists($targetPath) && !$overwrite) {
            return back()->withErrors([
                'mibfile' => "A MIB with this filename already exists in this folder. Check 'Overwrite' to replace it."
            ])->withInput();
        }

        try {
            $file->move($uploadDir, $filename);
        } catch (\Exception $e) {
            return back()->withErrors(['mibfile' => 'Failed to save file: ' . $e->getMessage()])->withInput();
        }

        // Update DB: overwrite existing record if file already exists in the same folder
        $mib = CustomMib::updateOrCreate(
            ['filename' => $filename, 'model_name' => $model_name],
            [
                'path' => $targetPath, // store full path including folder
                'user_id' => Auth::id(),
            ]
        );

        return redirect()->route('mibs.index')->with('status', 'MIB uploaded successfully.');
    }







    public function download($id)
    {
        $custommib = CustomMib::findOrFail($id);

        $this->authorize('view', $custommib);

        $filePath = $custommib->path;

        // If the path is relative, prepend the base directory
        if (!Str::startsWith($filePath, '/')) {
            $filePath = rtrim(Config::get('install_dir'), '/') . '/mibs/custom/' . $filePath;
        }

        if (!file_exists($filePath)) {
            return redirect()->route('mibs.index')
                ->withErrors(['download' => 'File not found on disk.']);
        }

        return response()->download($filePath, $custommib->filename);
    }




    public function destroy(CustomMib $mib)
    {
        $this->authorize('delete', $mib);

        // dd($mib->path); 

        if (!empty($mib->path) && file_exists($mib->path)) {
            try {
                unlink($mib->path);
            } catch (\Exception $e) {
                return response()->json('Failed to delete file: ' . $e->getMessage(), 500);
            }
        }

        $mib->delete();

        return response()->json('MIB deleted successfully.');
    }


}
