@extends('dashboard.layouts.app')

@section('title',$title)

@section('styles')
    <style>
        .modal-dialog-scrollable {
            height: auto !important;
        }

        #imagePreview{
            width: 100%;
            height: 100%;
            border-radius: 15px;
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
        }
    </style>
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
                    <strong class="card-title">{{transWord('Product Category ')}}</strong>
                     @can('create_product_category')
                        <div style="text-align: end" class="add_btn">
                            <a id="" class="btn btn-primary" href="javascript:void(0);" data-toggle="modal" data-target="#socialcategoryModal">
                                <i class="fas fa-plus"></i>
                                {{transWord('Add New')}}
                            </a>
                        </div>
                    @endcan
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="table-responsive pt-0">
                                <table id="example" class="table table-bordered dt-responsive nowrap"
                                       style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                                    <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>{{transWord('Title')}}</th>
                                        <th>{{transWord('Currency')}}</th>
                                        <th>{{transWord('Actions')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($categories as $index => $category)
                                        <tr class="unitRow_{{$category->id}}">
                                            <td>{{$index + 1}}</td>
                                            <td>{{ $category->title }}</td>
                                            <td>
                                                <span class="badge badge-{{ $category->currency == 'usd' ? 'success' : 'info' }}">
                                                    {{ strtoupper($category->currency) }}
                                                </span>
                                            </td>
                                            <td>
                                                <ul style="max-width: 50px" class="dtr-details mx-1">
                                                    @can('update_product_category')
                                                    <li>
                                                        <a href="{{route('edit_product_category',['product_catgeory' => $category])}}"
                                                           class="btn btn-sm btn-primary mx-1"
                                                           title="Edit"
                                                           data-original-title="Edit">
                                                            <i class="mdi mdi-pencil font-size-14"></i>
                                                        </a>
                                                    </li>
                                                    @endcan

                                                    @can('delete_product_category')
                                                    <li>
                                                        <a onclick="return confirm('{{ transWord('Are You Sure?') }}')" 
                                                           href="{{route('delete_product_category',['product_catgeory' => $category])}}" 
                                                           class="btn btn-sm btn-danger sa-delete" 
                                                           title="Delete" 
                                                           data-original-title="Delete">
                                                            <i class="mdi mdi-trash-can font-size-14"></i>
                                                        </a>
                                                    </li>
                                                    @endcan
                                                </ul>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@can('create_product_category')
    <div class="modal fade" id="socialcategoryModal" tabindex="-1" role="dialog"
         aria-labelledby="exampleModalScrollableTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title mt-0" id="exampleModalScrollableTitle">New Product Category</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body p-3">
                    <form action="{{route('store_product_category')}}" method="post" enctype="multipart/form-data">
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
                                        <option value="usd">USD - {{ transWord('US Dollar') }}</option>
                                        <option value="egp">EGP - {{ transWord('Egyptian Pound') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button id="closeBtn" type="button" class="btn btn-light waves-effect" data-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary waves-effect waves-light">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endcan

@endsection

@section('scripts')
    <script>
        function readURL(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    $("#imagePreview").css(
                        "background-image",
                        "url(" + e.target.result + ")"
                    );
                    $("#imagePreview").hide();
                    $("#imagePreview").fadeIn(650);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#imageUpload").change(function () {
            readURL(this);
        });
    </script>
@endsection