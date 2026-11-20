@extends('layouts.librenmsv1')

@section('title')
    License
@endsection

@php
    $data = $licenseFileContent['data'] ?? [];

    // Days left until expiry (null when the date can't be read)
    $daysLeft = null;
    $expiryLabel = $expiry;
    try {
        if ($expiry && $expiry !== 'N/A') {
            $expiryDate = \Carbon\Carbon::parse($expiry)->startOfDay();
            $daysLeft = (int) now()->startOfDay()->diffInDays($expiryDate, false);
            $expiryLabel = $expiryDate->format('d M Y');
        }
    } catch (\Throwable $e) {
        $daysLeft = null;
    }

    if ($daysLeft !== null && $daysLeft < 0) {
        $status = ['label' => 'Expired', 'class' => 'lic-bad', 'icon' => 'fa-times-circle'];
    } elseif ($daysLeft !== null && $daysLeft <= 30) {
        $status = ['label' => 'Expiring soon', 'class' => 'lic-warn', 'icon' => 'fa-exclamation-triangle'];
    } else {
        $status = ['label' => 'Active', 'class' => 'lic-ok', 'icon' => 'fa-check-circle'];
    }

    if ($daysLeft === null) {
        $daysValue = '—';
        $daysSub = 'No expiry date';
    } elseif ($daysLeft < 0) {
        $daysValue = number_format(abs($daysLeft));
        $daysSub = 'days since expiry';
    } else {
        $daysValue = number_format($daysLeft);
        $daysSub = $daysLeft >= 365 ? 'about ' . floor($daysLeft / 365) . ' years' : 'days remaining';
    }

    $tiles = [
        ['label' => 'Status', 'value' => $status['label'], 'sub' => $product, 'icon' => $status['icon'], 'class' => $status['class']],
        ['label' => 'Days left', 'value' => $daysValue, 'sub' => $daysSub, 'icon' => 'fa-hourglass-half', 'class' => $daysLeft !== null && $daysLeft <= 30 ? $status['class'] : 'lic-blue'],
        ['label' => 'Max users', 'value' => $maxUsers, 'sub' => 'user limit', 'icon' => 'fa-users', 'class' => 'lic-purple'],
        ['label' => 'Expiry', 'value' => $expiryLabel, 'sub' => 'valid until', 'icon' => 'fa-calendar', 'class' => 'lic-teal'],
    ];

    $details = [
        'Product' => $product,
        'Company' => $data['client_company'] ?? null,
        'Licensed to' => $data['client_name'] ?? null,
        'Email' => $data['client_email'] ?? null,
        'Domain' => $domain,
        'Server IP' => $data['server_ip'] ?? null,
        'MAC address' => $data['mac_address'] ?? null,
    ];
@endphp

@section('content')
<div class="container-fluid lic-page">

    <div class="lic-header">
        <h2><i class="fa fa-shield"></i> License</h2>
        <a href="/" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back to home</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible lic-alert">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fa fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if (session('error') || (isset($errors) && $errors->any()))
        <div class="alert alert-danger alert-dismissible lic-alert">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fa fa-times-circle"></i> {{ session('error') ?? $errors->first() }}
        </div>
    @endif

    {{-- Stat tiles --}}
    <div class="lic-tiles">
        @foreach ($tiles as $t)
            <div class="lic-tile {{ $t['class'] }}">
                <div class="lic-tile-top">
                    <span class="lic-tile-label">{{ $t['label'] }}</span>
                    <i class="fa {{ $t['icon'] }} lic-tile-icon"></i>
                </div>
                <div class="lic-tile-value">{{ $t['value'] }}</div>
                <div class="lic-tile-sub">{{ $t['sub'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="lic-columns">
        {{-- Details --}}
        <div class="lic-card">
            <div class="lic-card-head"><i class="fa fa-id-card-o"></i> Details</div>
            <table class="lic-table">
                @foreach ($details as $label => $value)
                    <tr>
                        <th>{{ $label }}</th>
                        <td>{{ ($value ?? '') !== '' ? $value : '—' }}</td>
                    </tr>
                @endforeach
                <tr>
                    <th>License key</th>
                    <td>
                        <div class="lic-key-row">
                            <code id="lic-key-value">{{ $licenseKey }}</code>
                            <button type="button" class="btn btn-default btn-xs" id="lic-copy" title="Copy license key">
                                <i class="fa fa-clipboard"></i> <span>Copy</span>
                            </button>
                        </div>
                    </td>
                </tr>
            </table>
            @if ($licenseFileInfo)
                <div class="lic-file">
                    <i class="fa fa-file-o"></i>
                    <strong>{{ $licenseFileInfo['filename'] }}</strong>
                    <span>{{ number_format($licenseFileInfo['size']) }} bytes</span>
                    <span>Updated {{ $licenseFileInfo['last_modified'] }}</span>
                </div>
            @endif
        </div>

        {{-- Uploads --}}
        <div class="lic-uploads">
            <div class="lic-card">
                <div class="lic-card-head"><i class="fa fa-file-text-o"></i> Update license</div>
                <form action="{{ route('license.upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <label class="lic-drop">
                        <input type="file" name="license_file" required>
                        <i class="fa fa-cloud-upload"></i>
                        <span class="lic-drop-text">Choose license file or drag it here</span>
                    </label>
                    <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-upload"></i> Upload license</button>
                </form>
            </div>
            <div class="lic-card">
                <div class="lic-card-head"><i class="fa fa-lock"></i> Update public key</div>
                <form action="{{ route('license.upload-key') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <label class="lic-drop">
                        <input type="file" name="public_key" required>
                        <i class="fa fa-cloud-upload"></i>
                        <span class="lic-drop-text">Choose public key or drag it here</span>
                    </label>
                    <button type="submit" class="btn btn-success btn-block"><i class="fa fa-upload"></i> Upload public key</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .lic-page {
        --lic-bg: #ffffff;
        --lic-border: #e3e7ed;
        --lic-text: #1f2933;
        --lic-muted: #6b7785;
        --lic-soft: #f5f7fa;
        --lic-ok: #1a8a4a;
        --lic-warn: #c77700;
        --lic-bad: #c62828;
        --lic-blue: #1e6fd9;
        --lic-purple: #7048c8;
        --lic-teal: #0f8a8a;
        max-width: 1300px;
        padding-top: 16px;
        padding-bottom: 30px;
        color: var(--lic-text);
    }
    .dark .lic-page {
        --lic-bg: #23272e;
        --lic-border: #363c45;
        --lic-text: #e4e7eb;
        --lic-muted: #9aa5b1;
        --lic-soft: #2b3038;
        --lic-ok: #4cc47f;
        --lic-warn: #f0a93a;
        --lic-bad: #ef7d74;
        --lic-blue: #5b9cf0;
        --lic-purple: #a58af0;
        --lic-teal: #3cc4c4;
    }

    .lic-header {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 10px; margin-bottom: 16px;
    }
    .lic-header h2 { margin: 0; font-size: 24px; font-weight: 600; color: var(--lic-text); }
    .lic-header h2 .fa { color: var(--lic-blue); margin-right: 4px; }
    .lic-alert { border-radius: 8px; }

    /* Tiles */
    .lic-tiles {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }
    .lic-tile {
        --lic-accent: var(--lic-blue);
        background: var(--lic-bg);
        border: 1px solid var(--lic-border);
        border-top: 4px solid var(--lic-accent);
        border-radius: 10px;
        padding: 16px 18px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
        min-width: 0;
    }
    .lic-tile.lic-ok { --lic-accent: var(--lic-ok); }
    .lic-tile.lic-warn { --lic-accent: var(--lic-warn); }
    .lic-tile.lic-bad { --lic-accent: var(--lic-bad); }
    .lic-tile.lic-blue { --lic-accent: var(--lic-blue); }
    .lic-tile.lic-purple { --lic-accent: var(--lic-purple); }
    .lic-tile.lic-teal { --lic-accent: var(--lic-teal); }
    .lic-tile-top { display: flex; justify-content: space-between; align-items: center; }
    .lic-tile-label {
        font-size: 12px; font-weight: 600; text-transform: uppercase;
        letter-spacing: .07em; color: var(--lic-muted);
    }
    .lic-tile-icon {
        font-size: 18px; color: var(--lic-accent);
        width: 36px; height: 36px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: var(--lic-soft);
    }
    .lic-tile-value {
        font-size: 26px; font-weight: 700; color: var(--lic-accent);
        margin-top: 6px; line-height: 1.2; overflow-wrap: anywhere;
    }
    .lic-tile-sub { font-size: 13px; color: var(--lic-muted); margin-top: 2px; overflow-wrap: anywhere; }

    /* Columns */
    .lic-columns {
        display: grid;
        grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
        gap: 20px;
        align-items: start;
    }
    .lic-uploads { display: grid; gap: 20px; }
    .lic-card {
        background: var(--lic-bg);
        border: 1px solid var(--lic-border);
        border-radius: 10px;
        padding: 18px 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
    }
    .lic-card-head { font-size: 15px; font-weight: 600; margin-bottom: 12px; }
    .lic-card-head .fa { color: var(--lic-muted); margin-right: 6px; }

    /* Details table */
    .lic-table { width: 100%; }
    .lic-table th, .lic-table td {
        padding: 9px 4px; border-bottom: 1px solid var(--lic-border);
        vertical-align: middle; background: transparent;
    }
    .lic-table tr:last-child th, .lic-table tr:last-child td { border-bottom: 0; }
    .lic-table th { width: 34%; color: var(--lic-muted); font-weight: 500; }
    .lic-table td { font-weight: 600; overflow-wrap: anywhere; }
    .lic-key-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .lic-key-row code {
        min-width: 0; overflow-wrap: anywhere;
        background: var(--lic-soft); color: var(--lic-text);
        border: 1px solid var(--lic-border); border-radius: 6px;
        padding: 4px 8px; font-size: 12px;
    }
    .lic-file {
        margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--lic-border);
        display: flex; flex-wrap: wrap; gap: 4px 14px; align-items: center;
        font-size: 13px; color: var(--lic-muted);
    }
    .lic-file strong { color: var(--lic-text); }

    /* Upload drop areas */
    .lic-drop {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 6px; width: 100%;
        border: 2px dashed var(--lic-border); border-radius: 8px;
        padding: 18px 12px; margin-bottom: 12px;
        color: var(--lic-muted); font-weight: normal; cursor: pointer;
        position: relative; text-align: center;
        transition: border-color .15s, background .15s;
    }
    .lic-drop:hover, .lic-drop.lic-drag { border-color: var(--lic-blue); background: var(--lic-soft); }
    .lic-drop.lic-has-file { border-style: solid; border-color: var(--lic-ok); color: var(--lic-text); }
    .lic-drop .fa { font-size: 24px; }
    .lic-drop input[type=file] {
        position: absolute; inset: 0; width: 100%; height: 100%;
        opacity: 0; cursor: pointer;
    }
    .lic-drop-text { overflow-wrap: anywhere; }

    @media (max-width: 991px) {
        .lic-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .lic-columns { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 480px) {
        .lic-tiles { grid-template-columns: minmax(0, 1fr); }
        .lic-table th { width: 40%; }
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        // Copy license key
        var btn = document.getElementById('lic-copy');
        if (btn) {
            btn.addEventListener('click', function () {
                var text = document.getElementById('lic-key-value').innerText.trim();
                var done = function () {
                    btn.querySelector('span').textContent = 'Copied';
                    setTimeout(function () { btn.querySelector('span').textContent = 'Copy'; }, 1500);
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(done);
                } else {
                    var t = document.createElement('textarea');
                    t.value = text; document.body.appendChild(t); t.select();
                    try { document.execCommand('copy'); done(); } catch (e) {}
                    document.body.removeChild(t);
                }
            });
        }

        // Show the chosen file name in the drop areas
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
    })();
</script>
@endpush
