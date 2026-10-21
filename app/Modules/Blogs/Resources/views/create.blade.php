@extends('dashboard.layouts.app')

@section('title',$title)

@section('styles')
{{-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-tagsinput/0.6.0/bootstrap-tagsinput.css"> --}}
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
            <form action="{{ route('store_blogs') }}" method="post" enctype="multipart/form-data">
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
                                    <label>{{ transWord('Name') }} - {{ $lang }}</label>
                                    <input  type="text" name="{{ $key }}[title]" class="form-control" placeholder="{{ transWord('Name') }}" required>
                                </div>

                                <div class="form-group mt-3 col-md-12">
                                    <label for=""> {{transWord('Content')}} - {{$lang}}</label>
                                    <textarea  rows="6" name="{{$key}}[content]" class="form-control"></textarea>
                                </div>


                            </div>
                        @endforeach
                    </div>


                </div>




                <div class="row">


                    <div class="col-md-8">
                        <div class="form-group mt-3">
                            <label for="file" class=""> {{transWord('Image')}}</label>
                            <div id="dropzone2">
                                <div>Click Or Drag Here</div>
                                <input name="image" type="file" id="imageUpload"/>
                            </div>
                        </div>
                    </div>


                    <div class="col-md-4">
                        <a target="_blank" href="">
                            <div id="imagePreview"></div>
                        </a>
                    </div>

                </div>

                <label for="publish">{{ transWord('Publish') }}</label>
                <select name="publish" id="publish" class="form-control" required>
                    <option value="">{{ transWord('Please Select') }}</option>
                    <option value="1">{{ transWord('Publish') }}</option>
                    <option value="0">{{ transWord('Unpublish') }}</option>
                </select>
                <button type="submit" class="btn btn-primary mt-3">{{ transWord('Save') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
{{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-tagsinput/0.6.0/bootstrap-tagsinput.min.js"></script> --}}
@include('dashboard.components.ckeditor')
@endsection
