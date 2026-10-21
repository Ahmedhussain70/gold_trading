<?php

namespace App\Modules\APIS;
use App\Http\Controllers\Controller;
use App\Http\Resources\FAQResource;
use App\Http\Resources\ProductResource;
use FaqSection\Models\FaqSetting;
use Product\Models\Product;

class FaqController extends Controller
{
    public function faq(){
        $FAQs = FaqSetting::where('publish','1')->get();
        $data = FAQResource::collection($FAQs);
        return response()->json([
            'success' => true,
            'message' => transWord("FAQs"),
            'result' => $data,
        ],200);
    }

}
