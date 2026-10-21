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
                    <strong class="card-title">{{transWord('Edit Product')}}</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <form action="{{route('update_product' , ['product' => $product])}}" method="post" enctype="multipart/form-data">
                                @csrf
                                <div class="row">
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
                                                    <input  type="text" name="{{ $key }}[title]" value="{{ $product->translate($key)->title }}" class="form-control" placeholder="{{ transWord('Name') }}" required>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="row mt-3 mb-3">
                                        <div class="col-12">
                                            <label for="">{{ transWord('Category') }}</label>
                                            <select name="product_category_id" class="form-control" id="" required>
                                                @foreach ($categories as $category)
                                                    <option value="{{ $category->id }}" @if($category->id == $product->product_category_id) selected @endif>{{ $category->title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
        
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <label for="">{{ transWord('Lowest Price') }}</label>
                                            <input type="number" name="lowest_price" step="0.1" min="0.1" value="{{ $product->lowest_price }}" id="" class="form-control">
                                        </div>
                                        <div class="col-6">
                                            <label for="">{{ transWord('Highest Price') }}</label>
                                            <input type="number" name="highest_price" step="0.1" min="0.1" value="{{ $product->highest_price }}" id="" class="form-control">
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-md-4">
                                            <label>{{ transWord('Metal') }}</label>
                                            <select name="metal" class="form-control" required>
                                                <option value="XAU" @selected($product->metal === 'XAU')>XAU (Gold)</option>
                                                <option value="XAG" @selected($product->metal === 'XAG')>XAG (Silver)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label>{{ transWord('Karat') }}</label>
                                            <select name="karat" class="form-control" required>
                                                @foreach ([24,22,21,20,18] as $k)
                                                    <option value="{{ $k }}" @selected((int)$product->karat === $k)>{{ $k }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label>{{ transWord('Gram Weight') }}</label>
                                            <input type="number" name="gram_weight" class="form-control"
                                                    step="0.001" min="0.001" value="{{ $product->gram_weight }}" required>
                                        </div>
                                    </div>

                                    <div class="modal-footer">
                                        <div class="form-group row">
                                            <button type="submit" class="btn btn-primary">{{transWord('Update')}}</button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                        </div> <!-- end col -->
                    </div> <!-- end row -->
                </div>
            </div>
        </div> <!-- end col -->



    </div>








@endsection

@section('scripts')
    <script>
        // function readURL(input) {
        //     if (input.files && input.files[0]) {
        //         var reader = new FileReader();
        //         reader.onload = function (e) {
        //             $("#imagePreview").css(
        //                 "background-image",
        //                 "url(" + e.target.result + ")"
        //             );
        //             $("#imagePreview").hide();
        //             $("#imagePreview").fadeIn(650);
        //         };
        //         reader.readAsDataURL(input.files[0]);
        //     }
        // }
        //
        // $("#imageUpload").change(function () {
        //     readURL(this);
        // });

    </script>
@endsection
