<?php

namespace App\Modules\APIS;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use ProductCategory\Models\ProductCategory;

class CategoryController extends Controller
{
    public function index(){
        $categories = ProductCategory::all();
        $data = CategoryResource::collection($categories);
        return response()->json([
            'success' => true,
            'message' => transWord("Categories"),
            'result' => $data,
        ],200);
    }

}
