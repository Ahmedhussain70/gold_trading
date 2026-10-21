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
            @if($blog->image)
                <img src="{{ $blog->image }}" class="img-responsive img-thumbnail mb-3" width="200px;">
            @endif
            
            <form action="{{ route('update_blogs',['blog'=>$blog]) }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="nav-align-top mb-4">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="en-tab" data-toggle="tab" href="#en" role="tab">English</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="ar-tab" data-toggle="tab" href="#ar" role="tab">Arabic</a>
                        </li>
                    </ul>
                    
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="en" role="tabpanel">
                            <div class="form-group mt-3 col-md-12">
                                <label>{{ transWord('Title') }} - English</label>
                                <input type="text" name="en[title]" value="{{ $blog->translate('en')->title ?? '' }}" class="form-control" required>
                            </div>
                            <div class="form-group mt-3 col-md-12">
                                <label>{{ transWord('Content') }} - English</label>
                                <textarea name="en[content]" class="form-control">{{ $blog->translate('en')->content ?? '' }}</textarea>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="ar" role="tabpanel">
                            <div class="form-group mt-3 col-md-12">
                                <label>{{ transWord('Title') }} - Arabic</label>
                                <input type="text" name="ar[title]" value="{{ $blog->translate('ar')->title ?? '' }}" class="form-control" required>
                            </div>
                            <div class="form-group mt-3 col-md-12">
                                <label>{{ transWord('Content') }} - Arabic</label>
                                <textarea name="ar[content]" class="form-control">{{ $blog->translate('ar')->content ?? '' }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mt-3 col-md-12">
                    @if($blog->image)
                        <img src="{{ $blog->image }}" class="img-thumbnail mb-2" style="width:70px;" alt="">
                    @endif
                    <label>{{ transWord('Image') }}</label>
                    <input type="file" name="image" class="form-control">
                </div>

                <div class="form-group mt-3 col-md-12">
                    <label for="publish">{{ transWord('Publish') }}</label>
                    <select name="publish" id="publish" class="form-control" required>
                        <option value="">{{ transWord('Please Select') }}</option>
                        <option value="1" @if($blog->publish == 1) selected @endif>{{ transWord('Publish') }}</option>
                        <option value="0" @if($blog->publish == 0) selected @endif>{{ transWord('Unpublish') }}</option>
                    </select>
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