<?php

namespace ProductCategory\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ProductCategory\Models\ProductCategory;
use App\Http\Trait\UploadImage;



class ProductCategoryController extends Controller
{
    public $path;
    use UploadImage;


    public function __construct()
    {
        $this->middleware('auth');
        $this->path = 'ProductCategory::';
    }

    public function index()
    {
       hasPermissions('show_product_category');
        $title = transWord('Product Category');
        $pages = [
            [transWord('Product Category'),'show_product_category']
        ];

        $categories =ProductCategory::latest()->get();

        return view($this->path.'index',compact('categories','pages','title'));
    }



    public function  edit(ProductCategory $product_catgeory){
        hasPermissions('update_product_category');

        $title = transWord('Product Category');
        $pages = [
            [transWord('Product Category'),'show_product_category']
        ];


        if($product_catgeory){
            return view($this->path.'edit',compact('product_catgeory','pages','title'));

        }
    }



    public function store(Request $request){
        hasPermissions('create_product_category');
        $request->validate([
            'title.*' =>'required',
            'currency' => 'required|in:usd,egp',
        ]);

        $catgeory= ProductCategory::create($request->except('_token'));

        return redirect()->route('show_product_category')->with("success" ,"");
    }


    public function update(Request $request , ProductCategory $product_catgeory){
        hasPermissions('update_product_category');
        $request->validate([
            'title.*' =>'required',
            'currency' => 'required|in:usd,egp',
        ]);
        if($product_catgeory){
            $product_catgeory->update($request->except('_token'));
        }
        return redirect()->route('show_product_category')->with("success" ,"");
    }


    public function delete(ProductCategory $product_catgeory){
        hasPermissions('delete_product_category');

        if($product_catgeory){
            $product_catgeory->delete();
        }

        return redirect()->route('show_product_category')->with("success" ,"");
    }










}
