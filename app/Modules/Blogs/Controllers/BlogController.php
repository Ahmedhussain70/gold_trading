<?php

namespace Blogs\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Trait\UploadImage;
use Blogs\Models\Blog;
use Blogs\Repositories\BlogRepository;
use Breadcrumb\Models\Breadcrumb;
use Illuminate\Http\Request;
use File;

class BlogController extends Controller
{
    use UploadImage;
    public $path;
    private $blogRepository;

    public function __construct(BlogRepository $blogRepository)
    {
        $this->middleware('auth');
        $this->path = 'Blogs::';
        $this->blogRepository = $blogRepository;
    }

    public function index()
    {
        hasPermissions('show_blogs');
        $title = transWord('Blogs');
        $pages = [
            [transWord('Blogs'),route('blogs')]
        ];
        $blogs = Blog::all();
        // $section = Section::where('type','Blogs')->first();
        // $breadcrumb = Breadcrumb::where('type','Blogs')->first();
        return view($this->path.'index',compact('blogs','pages','title'));
    }

    public function create()
    {
        hasPermissions('create_blogs');
        $title = transWord('Create Blogs');
        
        $pages = [
            [transWord('All Blogs'),route('blogs')],
            [transWord('Create Blogs'),route('create_blogs')]
        ];


        return view($this->path.'create',compact('pages','title'));
    }

    public function store(Request $request)
    {
        hasPermissions('create_blogs');
        $request->validate([
            'title.*' =>'required|string',
            'content.*' => 'required|string',
            'image' => 'image|mimes:jpg,png,jpeg'
        ]);

        $blog = Blog::create($request->all());



        if($request->hasFile('image')){
            $file = $request->image;
            $blog->update(
                ['image'=>$this->upload($file,'blogs')]
            );
        }

        return redirect()->route('blogs')->with('success','');
    }

    public function show(Blog $blog)
    {
        hasPermissions('show_blogs');
        $title = transWord('Show Blog Details');
        $pages = [
            [transWord('Blogs'),route('blogs')],
        ];
        return view($this->path.'.show',compact('blog','title','pages'));
    }

    public function edit(Blog $blog)
    {
        hasPermissions('update_blogs');
        $title = transWord('Edit Blog Data');
        $pages = [
            [transWord('Blogs'),route('blogs')],
        ];
        return view($this->path.'.edit',compact('blog','title','pages'));
    }

    public function update(Request $request,Blog $blog)
    {
        hasPermissions('update_blogs');
        $request->validate([
            'title.*' =>'required|string',
            'content.*' => 'required|string',
            'image' => 'image|mimes:jpg,png,jpeg'
        ]);
        if($blog){
            $blog->update($request->all());

            if($request->hasFile('image')){
                if(File::exists($blog->image)){
                    File::delete($blog->image);
                }
                $file = $request->image;
                $blog->update(
                    ['image'=> $this->upload($file,'blogs')]
                );
            }
            return redirect()->back()->with('success','');
        }else{
            return redirect()->back();
        }
    }

    // public function destroy(Blog $blog){
    //     hasPermissions('delete_blogs');
    //     File::deleteDirectory($blog->blog_img);
    //     $blog->delete();
    //     return back()->with('success','');
    // }

    public function destroy(Blog $blog)
    {
        hasPermissions('delete_blogs');
        
        // حذف الصورة
        if($blog->getRawOriginal('image') && File::exists($blog->getRawOriginal('image'))){
            File::delete($blog->getRawOriginal('image'));
        }
        
        $blog->delete();
        return back()->with('success', '');
    }
}
