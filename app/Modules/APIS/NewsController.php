<?php

namespace App\Modules\APIS;
use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use Blogs\Models\Blog;

class NewsController extends Controller
{
    public function index(){
        $blogs = Blog::all();
        $data = NewsResource::collection($blogs);
        return response()->json([
            'success' => true,
            'message' => transWord("News"),
            'result' => $data,
        ],200);
    }
    
    public function single($id){
        $blogs = Blog::where('id',$id)->first();
        $data = new NewsResource($blogs);
        return response()->json([
            'success' => true,
            'message' => transWord("News"),
            'result' => $data,
        ],200);
    }
    
}
