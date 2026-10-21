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

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white d-flex align-items-center justify-content-between border-bottom">
                    <strong class="card-title">{{transWord('Edit Product Category')}}</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <form action="{{route('update_product_category', ['product_catgeory' => $product_catgeory])}}" 
                                  method="post" 
                                  enctype="multipart/form-data">
                                @csrf

                                <div class="row">
                                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                                        @foreach (config('app.languages') as $key => $lang)
                                            <li class="nav-item">
                                                <a class="nav-link @if ($loop->index == 0) active @endif" 
                                                   id="nav-{{ $key }}-tab" 
                                                   data-toggle="tab" 
                                                   href="#nav-{{ $key }}" 
                                                   role="tab" 
                                                   aria-controls="nav-{{ $key }}" 
                                                   aria-selected="@if ($loop->index == 0){{ 'true' }}@else{{ 'false' }}@endif">
                                                   {{ $lang }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                    
                                    <div style="border: 1px solid #cccccc" class="tab-content" id="myTabContent">
                                        @foreach (config('app.languages') as $key => $lang)
                                            <div class="tab-pane fade @if ($loop->index == 0) show active @endif" 
                                                 id="nav-{{ $key }}" 
                                                 role="tabpanel" 
                                                 aria-labelledby="nav-{{ $key }}-tab">
                                                <div class="form-group mt-3 col-md-12">
                                                    <label>{{ transWord('Name') }} - {{ $lang }}</label>
                                                    <input type="text" 
                                                           name="{{ $key }}[title]" 
                                                           value="{{ $product_catgeory->translate($key)->title }}" 
                                                           class="form-control" 
                                                           placeholder="{{ transWord('Name') }}" 
                                                           required>
                                                </div>
                                            </div>
                                        @endforeach

                                        <div class="form-group mt-3 col-md-12">
                                            <label>{{ transWord('Currency') }}</label>
                                            <select name="currency" class="form-control" required>
                                                <option value="">{{ transWord('Select Currency') }}</option>
                                                <option value="usd" {{ $product_catgeory->currency == 'usd' ? 'selected' : '' }}>
                                                    USD - {{ transWord('US Dollar') }}
                                                </option>
                                                <option value="egp" {{ $product_catgeory->currency == 'egp' ? 'selected' : '' }}>
                                                    EGP - {{ transWord('Egyptian Pound') }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="modal-footer">
                                        <div class="form-group row">
                                            <button type="submit" class="btn btn-primary">{{transWord('Update')}}</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
@endsection