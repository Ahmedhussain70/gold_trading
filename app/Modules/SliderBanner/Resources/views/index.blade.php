@extends('dashboard.layouts.app')

@section('title',$title)

@section('styles')

@endsection

@section('breadcrumb')
    @foreach ($pages as $page)
        <li class="breadcrumb-item active" aria-current="page">
            <a href="{{ $page[1] }}">{{ $page[0] }}</a>
        </li>
    @endforeach
@endsection

@section('content')
<div class="row">
    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">{{ $title }}</h6>
                <div class="card-datatable table-responsive pt-0">
                <table class="datatables-basic table table-bordered table-striped" id="example">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ transWord('Image') }}</th>
                            <th>{{ transWord('Title') }}</th>
                            <th>{{ transWord('Sub Title') }}</th>
                            <th>{{ transWord('Actions') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($slider_banners as $index => $banner)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <img src="{{ $banner->image }}" style="width:70px;height:70px;" class="img-thumbnail">
                            </td>
                            <td>{{ $banner->title }}</td>
                            <td>{{ $banner->sub_title }}</td>
                            <td>
                                <a class="btn btn-primary btn-sm" href="{{ route('edit_slider_banners',['banner'=>$banner]) }}">{{ transWord('Edit') }}</a>
                                <a class="btn btn-danger btn-sm" id="deleteBtn" href="{{ route('destroy_slider_banners',['banner'=>$banner]) }}" onclick="return confirm('{{ transWord('Are You Sure?') }}')">{{ transWord('Delete') }}</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('javascript')

@endsection
