@extends('dashboard.layouts.app')

@section('title',$title)

@section('styles')
    <style>
        .modal-dialog-scrollable {
            height: auto !important;
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
                    <strong class="card-title">{{ transWord('Products') }}</strong>
                    @can('create_product')
                        <div style="text-align: end" class="add_btn">
                            <a class="btn btn-primary" href="javascript:void(0);" data-toggle="modal" data-target="#productModal">
                                <i class="fas fa-plus"></i>
                                {{ transWord('Add New') }}
                            </a>
                        </div>
                    @endcan
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="table-responsive pt-0">
                                <table id="example" class="table table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>{{ transWord('Title') }}</th>
                                            <th>{{ transWord('Category') }}</th>
                                            <th>{{ transWord('Metal') }}</th>
                                            <th>{{ transWord('Karat') }}</th>
                                            <th>{{ transWord('Gram Weight') }}</th>
                                            <th>{{ transWord('Mfg. Fee') }}</th>
                                            <th>{{ transWord('Shipping') }}</th>
                                            <th>{{ transWord('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($products as $index => $product)
                                            <tr class="unitRow_{{ $product->id }}">
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $product->title }}</td>
                                                <td>{{ $product->category->title ?? '-' }}</td>
                                                <td>
                                                    <span class="badge badge-{{ $product->metal === 'XAU' ? 'warning' : 'secondary' }}">
                                                        {{ $product->metal === 'XAU' ? transWord('Gold') : transWord('Silver') }}
                                                    </span>
                                                </td>
                                                <td>{{ $product->karat }}K</td>
                                                <td>{{ number_format($product->gram_weight, 3) }} {{ transWord('g') }}</td>
                                                <td>{{ number_format($product->manufacturing_fee, 2) }} {{ transWord('EGP') }}</td>
                                                <td>
                                                    @if($product->has_delivery)
                                                        <span class="badge badge-success">
                                                            {{ transWord('Delivery') }}: {{ number_format($product->shipping_cost, 2) }} {{ transWord('EGP') }}
                                                        </span>
                                                    @else
                                                        <span class="badge badge-secondary">{{ transWord('Pickup Only') }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <ul style="max-width: 50px" class="dtr-details mx-1">
                                                        @can('update_product')
                                                            <li>
                                                                <a href="{{ route('edit_product', ['product' => $product]) }}"
                                                                   class="btn btn-sm btn-primary mx-1"
                                                                   title="Edit">
                                                                    <i class="mdi mdi-pencil font-size-14"></i>
                                                                </a>
                                                            </li>
                                                        @endcan

                                                        @can('delete_product')
                                                            <li>
                                                                <a onclick="return confirm('{{ transWord('Are You Sure?') }}')"
                                                                   href="{{ route('delete_product', ['product' => $product]) }}"
                                                                   class="btn btn-sm btn-danger sa-delete"
                                                                   title="Delete">
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

    {{-- Modal: Add New Product --}}
    @can('create_product')
        <div class="modal fade" id="productModal" tabindex="-1" role="dialog" aria-labelledby="productModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title mt-0" id="productModalTitle">{{ transWord('New Product') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body p-3">
                        <form action="{{ route('store_product') }}" method="post">
                            @csrf

                            {{-- Language Tabs --}}
                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                @foreach (config('app.languages') as $key => $lang)
                                    <li class="nav-item">
                                        <a class="nav-link @if ($loop->index == 0) active @endif"
                                           id="nav-{{ $key }}-tab"
                                           data-toggle="tab"
                                           href="#nav-{{ $key }}"
                                           role="tab">{{ $lang }}</a>
                                    </li>
                                @endforeach
                            </ul>
                            
                            <div style="border: 1px solid #cccccc" class="tab-content" id="myTabContent">
                                @foreach (config('app.languages') as $key => $lang)
                                    <div class="tab-pane fade @if ($loop->index == 0) show active @endif"
                                         id="nav-{{ $key }}"
                                         role="tabpanel">
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
                            </div>

                            {{-- Category --}}
                            <div class="row mt-3 mb-3">
                                <div class="col-12">
                                    <label>{{ transWord('Category') }}</label>
                                    <select name="product_category_id" class="form-control" required>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- Metal, Karat, Gram Weight --}}
                            <div class="row">
                                <div class="col-md-4">
                                    <label>{{ transWord('Metal') }}</label>
                                    <select name="metal" class="form-control" required>
                                        <option value="XAU">{{ transWord('Gold') }} (XAU)</option>
                                        <option value="XAG">{{ transWord('Silver') }} (XAG)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>{{ transWord('Karat') }}</label>
                                    <select name="karat" class="form-control" required>
                                        <option value="24">24K</option>
                                        <option value="22">22K</option>
                                        <option value="21" selected>21K</option>
                                        <option value="20">20K</option>
                                        <option value="18">18K</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>{{ transWord('Gram Weight') }}</label>
                                    <input type="number"
                                           name="gram_weight"
                                           class="form-control"
                                           step="0.001"
                                           min="0.001"
                                           placeholder="5.000"
                                           required>
                                    <small class="text-muted">{{ transWord('Weight per unit in grams') }}</small>
                                </div>
                            </div>
                            {{-- Manufacturing Fee & Shipping --}}
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label>{{ transWord('Manufacturing Fee') }}</label>
                                    <div class="input-group">
                                        <input type="number"
                                            name="manufacturing_fee"
                                            class="form-control"
                                            step="0.01"
                                            min="0"
                                            value="0"
                                            placeholder="0.00"
                                            required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">{{ transWord('EGP') }}</span>
                                        </div>
                                    </div>
                                    <small class="text-muted">{{ transWord('Added per unit on top of metal price') }}</small>
                                </div>
                                <div class="col-md-6">
                                    <label>{{ transWord('Delivery Option') }}</label>
                                    <select name="has_delivery" id="hasDeliveryModal" class="form-control" required
                                            onchange="toggleShippingModal(this.value)">
                                        <option value="0">{{ transWord('Pickup from Office Only') }}</option>
                                        <option value="1">{{ transWord('Delivery Available') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mt-3" id="shippingCostRowModal" style="display:none;">
                                <div class="col-md-6">
                                    <label>{{ transWord('Shipping Cost') }}</label>
                                    <div class="input-group">
                                        <input type="number"
                                            name="shipping_cost"
                                            id="shippingCostInputModal"
                                            class="form-control"
                                            step="0.01"
                                            min="0"
                                            placeholder="0.00">
                                        <div class="input-group-append">
                                            <span class="input-group-text">{{ transWord('EGP') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer mt-4">
                                <button type="button" class="btn btn-light waves-effect" data-dismiss="modal">
                                    {{ transWord('Close') }}
                                </button>
                                <button type="submit" class="btn btn-primary waves-effect waves-light">
                                    {{ transWord('Save') }}
                                </button>
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
    function toggleShippingModal(value) {
        const row = document.getElementById('shippingCostRowModal');
        const input = document.getElementById('shippingCostInputModal');
        if (value == '1') {
            row.style.display = '';
            input.required = true;
        } else {
            row.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }
</script>
@endsection
