<?php

namespace Product\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Product\Models\Product;
use ProductCategory\Models\ProductCategory;

class ProductController extends Controller
{
    public $path;

    public function __construct()
    {
        $this->middleware('auth');
        $this->path = 'Product::';
    }

    public function index()
    {
        hasPermissions('show_product');
        
        $title = transWord('Products');
        $pages = [
            [transWord('Products'), 'show_product']
        ];

        $products = Product::with('category')->latest()->get();
        $categories = ProductCategory::all();

        return view($this->path . 'index', compact('products', 'pages', 'title', 'categories'));
    }

    public function edit(Product $product)
    {
        hasPermissions('update_product');

        $title = transWord('Products');
        $pages = [
            [transWord('Products'), 'show_product']
        ];

        $categories = ProductCategory::all();
        
        if ($product) {
            return view($this->path . 'edit', compact('product', 'pages', 'title', 'categories'));
        }
    }

    public function store(Request $request)
    {
        hasPermissions('create_product');

        // ✅ Validation بدون lowest_price, highest_price
        $rules = [
            'product_category_id' => ['required', 'exists:product_category,id'],
            'metal'               => ['required', 'in:XAU,XAG'],
            'karat'               => ['required', 'in:18,20,21,22,24'],
            'gram_weight'         => ['required', 'numeric', 'gt:0', 'max:999999.999'],
            'manufacturing_fee'   => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'has_delivery'        => ['required', 'boolean'],
            'shipping_cost'       => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'required_if:has_delivery,1'],
        ];

        // اللغات
        foreach (config('app.languages') as $key => $lang) {
            $rules["{$key}.title"] = ['required', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);
        // تحويل has_delivery لـ boolean صحيح
        $validated['has_delivery'] = (bool)($request->has_delivery ?? false);

        // لو مفيش توصيل، اجعل shipping_cost = null
        if (!$validated['has_delivery']) {
            $validated['shipping_cost'] = null;
        }

        Product::create($validated);

        return redirect()->route('show_product')->with('success', transWord('Created successfully'));
    }

    public function update(Request $request, Product $product)
    {
        hasPermissions('update_product');

        // ✅ Validation بدون lowest_price, highest_price
        $rules = [
            'product_category_id' => ['required', 'exists:product_category,id'],
            'metal'               => ['required', 'in:XAU,XAG'],
            'karat'               => ['required', 'in:18,20,21,22,24'],
            'gram_weight'         => ['required', 'numeric', 'gt:0', 'max:999999.999'],
            'manufacturing_fee'   => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'has_delivery'        => ['required', 'boolean'],
            'shipping_cost'       => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'required_if:has_delivery,1'],
        ];

        foreach (config('app.languages') as $key => $lang) {
            $rules["{$key}.title"] = ['required', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);
        // تحويل has_delivery لـ boolean صحيح
        $validated['has_delivery'] = (bool)($request->has_delivery ?? false);

        // لو مفيش توصيل، اجعل shipping_cost = null
        if (!$validated['has_delivery']) {
            $validated['shipping_cost'] = null;
        }
        
        $product->update($validated);

        return redirect()->route('show_product')->with('success', transWord('Updated successfully'));
    }

    public function delete(Product $product)
    {
        hasPermissions('delete_product');

        if ($product) {
            // Check if product has orders
            if ($product->orders()->exists()) {
                return redirect()->route('show_product')
                    ->with('error', transWord('Cannot delete product with existing orders'));
            }
            
            $product->delete();
        }

        return redirect()->route('show_product')->with('success', transWord('Deleted successfully'));
    }
}
