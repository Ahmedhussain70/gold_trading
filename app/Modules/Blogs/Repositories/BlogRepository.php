<?php


namespace Blogs\Repositories;

use Blogs\Models\Blog;
use File;
use Illuminate\Support\Str;

class BlogRepository implements BlogRepositoryInterface
{
    
    public function allData(){
        return Blog::all();
    }

    public function dataWithConditions( $conditions){
        $wheres = '';
        foreach ($conditions as $key => $value){
            $wheres .= '->where("'.$key.'","'.$key.'")';
        }
        $wheres = str_replace("'","",$wheres);
        return Blog::$wheres->get();
    }

    public function getDataId($id){
        return Blog::findOrfail($id);
    }

    public function saveData($request,$id = null)
    {
        // $data = [];
        // foreach (getLang() as $lang) {
        //     $data[$lang] = [
        //         'title' => $request->title[$lang],
        //         'slug' =>Str::slug($request->title[$lang]),
        //         'content' => $request->content[$lang],
        //         'tags' => $request->tags[$lang],
        //         'meta_title' => $request->meta_title[$lang],
        //         'meta_content' => $request->meta_desc[$lang],
        //         'meta_keywords' => $request->meta_keywords[$lang],
        //     ];
        // }
        

        // if($request->hasFile('blog_img')){
        //     $filenameWithExt = $request->file('blog_img')->getClientOriginalName();
        //     $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
        //     $extension = $request->file('blog_img')->getClientOriginalExtension();
        //     $fileNameToStore = $filename.'_'.time().'.'.$extension;
        //     $path = $request->file('blog_img')->storeAs('public/uploads/blogs',$fileNameToStore);
        //     $data['blog_img'] = $path;
        // }

        // $data['publish'] = $request->publish;
        if ($id == null) {
            $blog = Blog::create($request->except('_token','blog_img'));
            if($request->hasFile('blog_img')){
                $blog->update(['blog_img'=>$this->upload($request->blog_img)]);
            }
        }else{
            // Blog::whereId($id)->update($data);
        }
        
    }

    public function deleteData($id)
    {
        $blog = Blog::findOrfail($id);
        File::deleteDirectory($blog->blog_img);
        $blog->delete();
    }
}
