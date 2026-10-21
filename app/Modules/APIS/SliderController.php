<?php

namespace App\Modules\APIS;
use App\Http\Controllers\Controller;
use App\Http\Resources\SliderResource;
use SliderBanner\Models\SliderBanner;

class SliderController extends Controller
{
     public function index(){
        $sliders = SliderBanner::all();
        $data = SliderResource::collection($sliders);
        return response()->json([
            'success' => true,
            'message' => transWord("Slider"),
            'result' => $data,
        ],200);
    }
    
}
