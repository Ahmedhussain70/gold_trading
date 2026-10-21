<?php

namespace App\Modules\APIS;
use App\Http\Controllers\Controller;
use App\Http\Resources\SocialMediaResource;
use SocialMedia\Models\SocialMedia;

class SocialMediaController extends Controller
{
    public function index(){
        $socials = SocialMedia::all();
        $data = SocialMediaResource::collection($socials);
        return response()->json([
            'success' => true,
            'message' => transWord("Social Media"),
            'result' => $data,
        ],200);
    }

}
