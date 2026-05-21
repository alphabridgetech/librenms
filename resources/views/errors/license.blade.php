@extends('layouts.error')

@section('title')
    {{ __('License') }}
@endsection

@section('content')
    <div class="container mt-5">
        <div class="alert alert-danger">
            <h4>License Verification Failed</h4>
            <p>{{ $error }}</p>
            <hr>
            <p class="mb-0">Please contact the system administrator to resolve this issue.</p>

            
             <div class="mt-4">
                <h5>Upload License File</h5>
                <form action="{{ route('license.upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <input type="file" name="license_file" class="form-control-file" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload License</button>
                </form>
            </div>

            <div class="mt-4">
                <h5>Upload Public Key</h5>
                <form action="{{ route('license.upload-key') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <input type="file" name="public_key" class="form-control-file" required>
                    </div>
                    <button type="submit" class="btn btn-success">Upload Public Key</button>
                </form>
            </div>

            @php
                $licenseFilePath = base_path('license.key');
                $licenseFileInfo = null;
                $licenseFileContent = null;
                if (file_exists($licenseFilePath)) {
                    $licenseFileInfo = [
                        'filename' => 'license.key',
                        'size' => filesize($licenseFilePath),
                        'last_modified' => date('Y-m-d H:i:s', filemtime($licenseFilePath)),
                    ];
                    $licenseFileContent = json_decode(file_get_contents($licenseFilePath), true);
                }
            @endphp

            @if ($licenseFileInfo)
            <div class="mt-4">
                <h5>Current License File</h5>
                <table class="table table-striped">
                    <tr>
                        <th>Filename:</th>
                        <td>{{ $licenseFileInfo['filename'] }}</td>
                    </tr>
                    <tr>
                        <th>Size:</th>
                        <td>{{ number_format($licenseFileInfo['size']) }} bytes</td>
                    </tr>
                    <tr>
                        <th>Last Modified:</th>
                        <td>{{ $licenseFileInfo['last_modified'] }}</td>
                    </tr>
                </table>

                @if ($licenseFileContent && isset($licenseFileContent['data']))
                <h5>License Data</h5>
                <table class="table table-striped">
                    @foreach ($licenseFileContent['data'] as $key => $value)
                    <tr>
                        <th>{{ ucwords(str_replace('_', ' ', $key)) }}:</th>
                        <td>{{ $value ?? 'N/A' }}</td>
                    </tr>
                    @endforeach
                </table>
                @endif
            </div>
            @endif
        </div>
    </div>
@endsection