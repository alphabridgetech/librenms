@extends('layouts.librenmsv1')

@section('title')
    License
@endsection

@section('content')
    <div class="container">
        <div class="page-header">
            <h1>License Verification</h1>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h5 class="panel-title">Valid License</h5>
                    </div>
                    <div class="panel-body">
                        <table class="table table-striped">
                            <tr>
                                <th>Product:</th>
                                <td>{{ $product }}</td>
                            </tr>
                            <tr>
                                <th>Domain:</th>
                                <td>{{ $domain }}</td>
                            </tr>
                            <tr>
                                <th>Expiry Date:</th>
                                <td>{{ $expiry }}</td>
                            </tr>
                            <tr>
                                <th>Max Users:</th>
                                <td>{{ $maxUsers }}</td>
                            </tr>
                            <tr>
                                <th>License Key:</th>
                                <td><code>{{ $licenseKey }}</code></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>

        <div class="row" style="margin-top: 15px;">
            <div class="col-md-6">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h5 class="panel-title">Upgrade License File</h5>
                    </div>
                    <div class="panel-body">
                        <form action="{{ route('license.upload') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <input type="file" name="license_file" class="form-control-file" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Upload License</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h5 class="panel-title">Upgrade Public Key</h5>
                    </div>
                    <div class="panel-body">
                        <form action="{{ route('license.upload-key') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <input type="file" name="public_key" class="form-control-file" required>
                            </div>
                            <button type="submit" class="btn btn-success">Upload Public Key</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if ($licenseFileInfo)
        <div class="row" style="margin-top: 15px;">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h5 class="panel-title">Current License File</h5>
                    </div>
                    <div class="panel-body">
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
                </div>
            </div>
        </div>
        @endif

        <div class="row" style="margin-top: 15px;">
            <div class="col-md-12">
                <a href="/" class="btn btn-default">Back to Home</a>
            </div>
        </div>
    </div>
@endsection