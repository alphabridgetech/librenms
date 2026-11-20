@extends('layouts.error')

@section('title')
    {{ __('License') }}
@endsection

@php
    $licenseFilePath = base_path('license.key');
    $licenseFileInfo = null;
    $licenseData = [];
    if (file_exists($licenseFilePath)) {
        $licenseFileInfo = [
            'filename' => 'license.key',
            'size' => filesize($licenseFilePath),
            'last_modified' => date('Y-m-d H:i:s', filemtime($licenseFilePath)),
        ];
        $licenseData = json_decode(file_get_contents($licenseFilePath), true)['data'] ?? [];
    }

    // Suggest what to do based on the error
    $message = $error ?? 'Unknown license error';
    $lower = strtolower($message);
    if (str_contains($lower, 'signature')) {
        $hint = 'The license file has been changed or does not match the public key. Do not edit license.key by hand; upload the original file or ask for a new one.';
    } elseif (str_contains($lower, 'expir')) {
        $hint = 'This license has expired. Upload a renewed license file.';
    } elseif (str_contains($lower, 'not found') || str_contains($lower, 'missing') || ! $licenseFileInfo) {
        $hint = 'No license file was found. Upload your license.key file below.';
    } elseif (str_contains($lower, 'public key')) {
        $hint = 'The public key is missing or invalid. Upload the public key you received with the license.';
    } elseif (str_contains($lower, 'mac') || str_contains($lower, 'ip') || str_contains($lower, 'domain')) {
        $hint = 'This license is issued for a different server. Ask for a license for this server.';
    } else {
        $hint = 'Upload a valid license file and public key, or contact your system administrator.';
    }

    $labels = [
        'product' => 'Product',
        'client_company' => 'Company',
        'client_name' => 'Licensed to',
        'client_email' => 'Email',
        'domain' => 'Domain',
        'server_ip' => 'Server IP',
        'mac_address' => 'MAC address',
        'expiry' => 'Expiry date',
        'license_key' => 'License key',
    ];
@endphp

@section('content')
<div class="lic-err">

    @if (session('success'))
        <div class="lic-flash lic-flash-ok">{{ session('success') }}</div>
    @endif
    @if (session('error') || (isset($errors) && $errors->any()))
        <div class="lic-flash lic-flash-bad">{{ session('error') ?? $errors->first() }}</div>
    @endif

    {{-- Problem --}}
    <div class="lic-card lic-problem">
        <div class="lic-problem-icon">
            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
        </div>
        <div class="lic-problem-body">
            <div class="lic-eyebrow">License verification failed</div>
            <h2 class="lic-problem-title">{{ $message }}</h2>
            <p class="lic-problem-hint">{{ $hint }}</p>
        </div>
    </div>

    {{-- Uploads --}}
    <div class="lic-uploads">
        <div class="lic-card">
            <div class="lic-card-head">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Upload license file
            </div>
            <form action="{{ route('license.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <label class="lic-drop">
                    <input type="file" name="license_file" required>
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                    <span class="lic-drop-text">Choose license.key or drag it here</span>
                </label>
                <button type="submit" class="btn btn-primary btn-block">Upload license</button>
            </form>
        </div>
        <div class="lic-card">
            <div class="lic-card-head">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Upload public key
            </div>
            <form action="{{ route('license.upload-key') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <label class="lic-drop">
                    <input type="file" name="public_key" required>
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                    <span class="lic-drop-text">Choose public key or drag it here</span>
                </label>
                <button type="submit" class="btn btn-success btn-block">Upload public key</button>
            </form>
        </div>
    </div>

    {{-- Current file --}}
    @if ($licenseFileInfo)
        <div class="lic-card">
            <div class="lic-card-head lic-file-head">
                <span>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Current license file
                </span>
                <span class="lic-file-meta">
                    {{ $licenseFileInfo['filename'] }} &middot; {{ number_format($licenseFileInfo['size']) }} bytes &middot; updated {{ $licenseFileInfo['last_modified'] }}
                </span>
            </div>
            @if ($licenseData)
                <div class="lic-grid">
                    @foreach ($labels as $key => $label)
                        @if (array_key_exists($key, $licenseData))
                            <div class="lic-field">
                                <div class="lic-field-label">{{ $label }}</div>
                                <div class="lic-field-value">{{ ($licenseData[$key] ?? '') !== '' ? $licenseData[$key] : '—' }}</div>
                            </div>
                        @endif
                    @endforeach
                    @foreach ($licenseData as $key => $value)
                        @if (! array_key_exists($key, $labels) && is_scalar($value))
                            <div class="lic-field">
                                <div class="lic-field-label">{{ ucwords(str_replace('_', ' ', $key)) }}</div>
                                <div class="lic-field-value">{{ $value !== '' ? $value : '—' }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <p class="lic-muted">The license file could not be read.</p>
            @endif
        </div>
    @endif

    <p class="lic-footer">Need help? Contact your system administrator with the error message shown above.</p>
</div>

<style>
    .lic-err {
        max-width: 1024px;
        margin: 0 auto;
        padding: 0 15px;
        color: #1f2933;
    }
    .lic-card {
        background: #fff;
        border: 1px solid #e3e7ed;
        border-radius: 10px;
        padding: 18px 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
    }
    .lic-card-head {
        display: flex; align-items: center; gap: 8px;
        font-size: 15px; font-weight: 600; margin-bottom: 12px;
    }
    .lic-card-head svg { color: #6b7785; flex-shrink: 0; }
    .lic-muted { color: #6b7785; margin: 0; }

    .lic-flash { border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-weight: 600; }
    .lic-flash-ok { background: #e6f5ec; color: #1a6b3c; border: 1px solid #bfe3cd; }
    .lic-flash-bad { background: #fdecea; color: #a32020; border: 1px solid #f5c6c2; }

    /* Problem card */
    .lic-problem {
        display: flex; gap: 18px; align-items: flex-start;
        border-left: 5px solid #c62828;
    }
    .lic-problem-icon {
        width: 56px; height: 56px; border-radius: 14px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: #fdecea; color: #c62828;
    }
    .lic-eyebrow {
        font-size: 12px; font-weight: 600; text-transform: uppercase;
        letter-spacing: .08em; color: #c62828;
    }
    .lic-problem-title { font-size: 22px; font-weight: 600; margin: 4px 0 6px; overflow-wrap: anywhere; }
    .lic-problem-hint { color: #4a5560; margin: 0; }

    /* Uploads */
    .lic-uploads {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }
    .lic-drop {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 6px; width: 100%;
        border: 2px dashed #d3d9e0; border-radius: 8px;
        padding: 18px 12px; margin-bottom: 12px;
        color: #6b7785; cursor: pointer; position: relative; text-align: center;
        transition: border-color .15s, background .15s;
    }
    .lic-drop:hover, .lic-drop.lic-drag { border-color: #1e6fd9; background: #f5f9ff; }
    .lic-drop.lic-has-file { border-style: solid; border-color: #1a8a4a; color: #1f2933; }
    .lic-drop input[type=file] {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        opacity: 0; cursor: pointer;
    }
    .lic-drop-text { overflow-wrap: anywhere; }

    /* Current file */
    .lic-file-head { justify-content: space-between; flex-wrap: wrap; }
    .lic-file-head > span:first-child { display: flex; align-items: center; gap: 8px; }
    .lic-file-meta { font-size: 13px; font-weight: normal; color: #6b7785; }
    .lic-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 10px;
    }
    .lic-field { background: #f5f7fa; border-radius: 8px; padding: 9px 12px; min-width: 0; }
    .lic-field-label { font-size: 12px; color: #6b7785; margin-bottom: 2px; }
    .lic-field-value { font-weight: 600; overflow-wrap: anywhere; }

    .lic-footer { color: #6b7785; text-align: center; font-size: 13px; margin-top: 4px; }

    @media (max-width: 700px) {
        .lic-uploads { grid-template-columns: minmax(0, 1fr); }
        .lic-problem { flex-direction: column; }
        .lic-problem-title { font-size: 19px; }
    }
</style>

<script>
    document.querySelectorAll('.lic-drop').forEach(function (drop) {
        var input = drop.querySelector('input[type=file]');
        var label = drop.querySelector('.lic-drop-text');
        var empty = label.textContent;
        input.addEventListener('change', function () {
            var has = input.files && input.files.length;
            label.textContent = has ? input.files[0].name : empty;
            drop.classList.toggle('lic-has-file', !!has);
        });
        ['dragenter', 'dragover'].forEach(function (e) {
            drop.addEventListener(e, function () { drop.classList.add('lic-drag'); });
        });
        ['dragleave', 'drop'].forEach(function (e) {
            drop.addEventListener(e, function () { drop.classList.remove('lic-drag'); });
        });
    });
</script>
@endsection
