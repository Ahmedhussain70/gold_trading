<?php

namespace SliderBanner\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Trait\UploadImage;
use SliderBanner\Models\SliderBanner;
use Illuminate\Http\Request;
use File;

class SliderBannerController extends Controller
{
    use UploadImage;
    public $path;

    public function __construct()
    {
        $this->middleware('auth');
        $this->path = 'SliderBanner::';
    }

    public function index()
    {
        $title = transWord('Edit Slider Banner');
        $pages = [
            [transWord('Slider Banner'),'#'],
        ];
        $slider_banners = SliderBanner::all();
        return view($this->path.'.index',compact('slider_banners','title','pages'));
    }
    
    public function create()
    {
        $title = transWord('New Slider Banner');
        $pages = [
            [transWord('New Slider Banner'),'#'],
        ];
        $slider_banner = SliderBanner::first();
        return view($this->path.'.create',compact('slider_banner','title','pages'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title.*' =>'required|max:100',
            'sub_title.*' => 'required',
            'image' => 'mimes:png,jpg,jpeg,gif,svg,webp|max:2048'
        ]);
        $slider_banner = SliderBanner::create($request->except('_token','image'));
        if($request->hasFile('image')){
            $slider_banner->update(['image'=>$this->upload($request->image,'slider_banners')]);
        }
        return redirect()->route('slider_banners')->with('success','');
    }
    
    public function edit(SliderBanner $banner)
    {
        $title = transWord('Edit Slider Banner');
        $pages = [
            [transWord('Slider Banner'),'#'],
        ];
        $slider_banner = SliderBanner::first();
        return view($this->path.'.edit',compact('slider_banner','title','pages'));
    }

    public function update(Request $request,SliderBanner $banner)
    {
        $request->validate([
            'title.*' =>'required|max:100',
            'content.*' => 'required',
            'image' => 'mimes:png,jpg,jpeg,gif,svg|max:2048'
        ]);
        $banner->update($request->except('_token','image'));
        if($request->hasFile('image')){
            $banner->update(['image'=>$this->upload($request->image,'slider_banners')]);
        }
        return redirect()->route('slider_banners')->with('success','');
    }

    public function destroy(SliderBanner $banner)
    {
        $banner->delete();
        return back()->with('success','');
    }
}
