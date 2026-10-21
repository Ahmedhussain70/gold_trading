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

@include('dashboard.components.errors')

<div class="col-lg-12">
    <div class="card card-bordered card-full">
        <div class="card-header">
            <img src="{{ $blog->blog_img }}" class="img-thumbnail img-display-block">
        </div>
        <div class="card-body">
            <div class="row">
                <p>{{ transWord('Title').' : '.$blog->title }}</p>
                <div>
                    {{ transWord('Content') }}
                    {!! $blog->content !!}
                </div>
                <hr>
                <div class="row">
                    <div class="col">
                        {{ transWord('Slug').': '.$blog->slug }}
                    </div>
                    <div class="col">
                        @if ($blog->publish == 1)
                        <span class="badge bg-primary">{{ transWord('Publish') }}</span>
                        @else
                        <span class="badge bg-danger">{{ transWord('Un Publish') }}</span>
                        @endif
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="card card-bordered card-full">
                        <div class="card-header">
                            <h4>{{ transWord('Meta Data') }}</h4>
                        </div>
                        <div class="card-body">
                            <div class="col-lg-12"><h6 class="mb-3">{{ transWord('Title') }}: {{ $blog->meta_title }}</h6></div>
                            <div class="col-lg-12"><h6 class="mb-3">{{ transWord('Descriptions') }}: {{ $blog->meta_desc }}</h6></div>
                            <div class="col-lg-12"><h6 class="mb-3">{{ transWord('Keywords') }}: {{ $blog->meta_keywords }}</h6></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@section('javascript')

@endsection
