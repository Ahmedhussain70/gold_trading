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
                    <strong class="card-title">{{ transWord('Edit Product') }}</strong>
                    <a href="{{ route('show_product') }}" class="btn btn-secondary btn-sm">
                        <i class="mdi mdi-arrow-left"></i> {{ transWord('Back') }}
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ route('update_product', ['product' => $product]) }}" method="post">
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

                        <div style="border: 1px solid #cccccc" class="tab-content mb-3" id="myTabContent">
                            @foreach (config('app.languages') as $key => $lang)
                                <div class="tab-pane fade @if ($loop->index == 0) show active @endif"
                                     id="nav-{{ $key }}"
                                     role="tabpanel">
                                    <div class="form-group mt-3 col-md-12">
                                        <label>{{ transWord('Name') }} - {{ $lang }}</label>
                                        <input type="text"
                                               name="{{ $key }}[title]"
                                               value="{{ $product->translate($key)->title ?? '' }}"
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
                                        <option value="{{ $category->id }}"
                                                @if($category->id == $product->product_category_id) selected @endif>
                                            {{ $category->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Metal, Karat, Gram Weight --}}
                        <div class="row">
                            <div class="col-md-4">
                                <label>{{ transWord('Metal') }}</label>
                                <select name="metal" class="form-control" required>
                                    <option value="XAU" @selected($product->metal === 'XAU')>
                                        {{ transWord('Gold') }} (XAU)
                                    </option>
                                    <option value="XAG" @selected($product->metal === 'XAG')>
                                        {{ transWord('Silver') }} (XAG)
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>{{ transWord('Karat') }}</label>
                                <select name="karat" class="form-control" required>
                                    @foreach ([24, 22, 21, 20, 18] as $k)
                                        <option value="{{ $k }}" @selected((int)$product->karat === $k)>
                                            {{ $k }}K
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>{{ transWord('Gram Weight') }}</label>
                                <input type="number"
                                       name="gram_weight"
                                       class="form-control"
                                       step="0.001"
                                       min="0.001"
                                       value="{{ $product->gram_weight }}"
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
                                        value="{{ $product->manufacturing_fee }}"
                                        required>
                                    <div class="input-group-append">
                                        <span class="input-group-text">{{ transWord('EGP') }}</span>
                                    </div>
                                </div>
                                <small class="text-muted">{{ transWord('Added per unit on top of metal price') }}</small>
                            </div>
                            <div class="col-md-6">
                                <label>{{ transWord('Delivery Option') }}</label>
                                <select name="has_delivery" id="hasDeliveryEdit" class="form-control" required
                                        onchange="toggleShippingEdit(this.value)">
                                    <option value="0" @selected(!$product->has_delivery)>{{ transWord('Pickup from Office Only') }}</option>
                                    <option value="1" @selected($product->has_delivery)>{{ transWord('Delivery Available') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mt-3" id="shippingCostRowEdit" 
                            style="{{ $product->has_delivery ? '' : 'display:none;' }}">
                            <div class="col-md-6">
                                <label>{{ transWord('Shipping Cost') }}</label>
                                <div class="input-group">
                                    <input type="number"
                                        name="shipping_cost"
                                        id="shippingCostInputEdit"
                                        class="form-control"
                                        step="0.01"
                                        min="0"
                                        value="{{ $product->shipping_cost }}">
                                    <div class="input-group-append">
                                        <span class="input-group-text">{{ transWord('EGP') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Product Info (Read Only) --}}
                        @if($product->orders()->exists())
                            <div class="alert alert-info mt-3">
                                <i class="mdi mdi-information-outline"></i>
                                {{ transWord('This product has existing orders. Changing gram weight will only affect new orders.') }}
                            </div>
                        @endif

                        <div class="form-group row mt-4">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-content-save"></i> {{ transWord('Update') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
<script>
    function toggleShippingEdit(value) {
        const row = document.getElementById('shippingCostRowEdit');
        const input = document.getElementById('shippingCostInputEdit');
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
