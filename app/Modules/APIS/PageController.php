<?php

namespace App\Modules\APIS;
use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use MainSettings\Models\MainSetting;
use Pages\Models\Page;
use Services\Models\Service;

class PageController extends Controller
{
    public function index(){
        $pages = Page::all();
        $data = PageResource::collection($pages);
        return response()->json([
            'success' => true,
            'message' => transWord("Pages"),
            'result' => $data,
        ],200);
    }

    public function single($id){
        $blogs = Page::where('id',$id)->first();
        $data = new PageResource($blogs);
        return response()->json([
            'success' => true,
            'message' => transWord("Pages"),
            'result' => $data,
        ],200);
    }

}
