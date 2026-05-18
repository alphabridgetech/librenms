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
            <div class="col-md-12">
                <a href="/" class="btn btn-default">Back to Home</a>
            </div>
        </div>
    </div>
@endsection