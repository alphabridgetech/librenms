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
        </div>
    </div>
@endsection