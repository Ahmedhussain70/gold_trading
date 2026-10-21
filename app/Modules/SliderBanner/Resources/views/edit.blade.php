@extends('dashboard.layouts.app')

@section('title',$title)

@section('styles')
<link rel="stylesheet" href="{{ setPublic() }}dashboard/assets/css/taginput.css">
@endsection

@section('breadcrumb')
    @foreach ($pages as $page)
        <li class="breadcrumb-item active" aria-current="page">
            <a href="{{ $page[1] }}">{{ $page[0] }}</a>
        </li>
    @endforeach
@endsection

@section('content')

@include('dashboard.components.errors')

<div class="col-lg-12">
    <div class="card card-bordered card-full">
        <div class="card-header">
            {{ $title }}
        </div>
        <div class="card-body">
            <form action="{{ route('update_slider_banners',['banner'=>$slider_banner]) }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="nav-align-top mb-4">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        @foreach (config('app.languages') as $key => $lang)
                            <li class="nav-item">
                                <a class="nav-link @if ($loop->index == 0) active @endif" id="nav-{{ $key }}-tab" data-toggle="tab" href="#nav-{{ $key }}" role="tab" aria-controls="nav-{{ $key }}" aria-selected="@if ($loop->index == 0){{ 'true' }}@else{{ 'false' }}@endif">{{ $lang }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <div style="border: 1px solid #cccccc" class="tab-content" id="myTabContent">
                        @foreach (config('app.languages') as $key => $lang)
                        <div class="tab-pane fade  @if ($loop->index == 0) show active @endif" id="nav-{{ $key }}" role="tabpanel" aria-labelledby="nav-{{ $key }}-tab">
                            <div class="form-group mt-3 col-md-12">
                                <label>{{ transWord('Title') }} - {{ $lang }}</label>
                                <input type="text" name="{{ $key }}[title]" value="{{ $slider_banner->translate($key)->title }}" class="form-control" placeholder="{{ transWord('Title') }}" required>
                            </div>
                            <div class="form-group mt-3 col-md-12">
                                <label>{{ transWord('Sub Title') }} - {{ $lang }}</label>
                                <input type="text" name="{{ $key }}[sub_title]" value="{{ $slider_banner->translate($key)->sub_title }}" class="form-control" placeholder="{{ transWord('Sub Title') }}" required>
                            </div>
                            <div class="form-group mt-3 col-md-12">
                                <label>{{ transWord('Link') }} - {{ $lang }}</label>
                                <input type="text" name="{{ $key }}[link]" value="{{ $slider_banner->translate($key)->link }}" class="form-control" placeholder="{{ transWord('Link') }}" required>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                
                <div class="row mb-3 mt-3">
                    <div class="col-12">
                        <img src="{{ $slider_banner->image }}" style="width:200px;">
                        <br>
                        <label for="image">{{ transWord('Image') }}</label>
                        <input type="file" name="image" id="image" class="form-control" accept="image/*">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3">{{ transWord('Save') }}</button>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ setPublic() }}dashboard/assets/js/taginput.js"></script>
@include('dashboard.components.ckeditor')
@endsection
