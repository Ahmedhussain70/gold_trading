<?php

namespace Product\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Product\Models\Product;
use App\Http\Trait\UploadImage;
use ProductCategory\Models\ProductCategory;

class ProductController extends Controller
{
    public $path;
    use UploadImage;


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
            [transWord('Products'),'show_product']
        ];

        $products =Product::latest()->get();
        $categories = ProductCategory::all();

        return view($this->path.'index',compact('products','pages','title','categories'));
    }



    public function  edit(Product $product){
        hasPermissions('update_product');

        $title = transWord('Products');
        $pages = [
            [transWord('Products'),'show_product']
        ];

        $categories = ProductCategory::all();
        if($product){
            return view($this->path.'edit',compact('product','pages','title','categories'));

        }
    }

    public function store(Request $request)
    {
        hasPermissions('create_product');

        // الفاليديشن الأساسي (العناوين متعددة اللغات + الحقول الجديدة)
        $rules = [
            'product_category_id' => ['required','exists:product_category,id'],
            'lowest_price'        => ['nullable','numeric','min:0'],
            'highest_price'       => ['nullable','numeric','min:0'],

            // NEW
            'metal'       => ['required','in:XAU,XAG'],
            'karat'       => ['required','in:18,20,21,22,24'],
            'gram_weight' => ['required','numeric','gt:0','max:999999.999'],
        ];

        // اللغات
        foreach (config('app.languages') as $key => $lang) {
            $rules["{$key}.title"] = ['required','string','max:255'];
        }

        $validated = $request->validate($rules);

        // الإنشاء (Astrotomic Translatable يدعم مصفوفات اللغات مباشرة)
        Product::create($validated);

        return redirect()->route('show_product')->with('success', transWord('Created successfully'));
    }

    public function update(Request $request, Product $product)
    {
        hasPermissions('update_product');

        $rules = [
            'product_category_id' => ['required','exists:product_category,id'],
            'lowest_price'        => ['nullable','numeric','min:0'],
            'highest_price'       => ['nullable','numeric','min:0'],

            // NEW
            'metal'       => ['required','in:XAU,XAG'],
            'karat'       => ['required','in:18,20,21,22,24'],
            'gram_weight' => ['required','numeric','gt:0','max:999999.999'],
        ];
        foreach (config('app.languages') as $key => $lang) {
            $rules["{$key}.title"] = ['required','string','max:255'];
        }

        $validated = $request->validate($rules);

        $product->update($validated);

        return redirect()->route('show_product')->with('success', transWord('Updated successfully'));
    }

    // public function store(Request $request){
    //     hasPermissions('create_product');
    //     $request->validate([
    //         'title.*' =>'required',
    //     ]);

    //     $product= Product::create($request->except('_token'));

    //     return redirect()->route('show_product')->with("success" ,"");
    // }


    // public function update(Request $request , Product $product){
    //     hasPermissions('update_product');
    //     $request->validate([
    //         'title.*' =>'required',
    //     ]);
    //     if($product){
    //         $product->update($request->except('_token'));
    //     }
    //     return redirect()->route('show_product')->with("success" ,"");
    // }


    public function delete(Product $product){
        hasPermissions('delete_product');

        if($product){
            $product->delete();
        }

        return redirect()->route('show_product')->with("success" ,"");
    }

}
