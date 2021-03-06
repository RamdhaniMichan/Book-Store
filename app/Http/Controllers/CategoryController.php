<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{

    public function __construct(){
        $this->middleware(function($request, $next){
            if(Gate::allows('manage-categories')) return $next($request);
            abort(403, 'Anda tidak memiliki cukup hak akses');
        });
    }

    public function index(Request $request)
    {
        $categories = Category::paginate(10);
        $filterKeyword = $request->get('name');
        if($filterKeyword) {
            $categories = Category::where("name", "LIKE", "%$filterKeyword%")->paginate(10);
        }
        return view('categories.index', ['categories' => $categories]);
    }

    public function ajaxSearch(Request $request){
        $keyword = $request->get('q');
        $categories = Category::where("name", "LIKE", "%$keyword%")->get();
        return $categories;
    }

    public function create()
    {
        return view("categories.create");
    }

    public function store(Request $request)
    {
        \Validator::make($request->all(), [
            "name" => "required|min:3|max:20",
            "image" => "required"
        ])->validate();

        $name = $request->get('name');
        $new_category = new Category;
        $new_category->name = $name;
        if($request->file('image')) {
            $image_path = $request->file('image')->store('category_images', 'public');
            $new_category->image = $image_path;
        }
        $new_category->created_by = \Auth::user()->id;
        $new_category->slug = \Str::slug($name, '-');
        $new_category->save();
        return redirect()->route('categories.create')->with('status', 'Category successfully created');
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);
        return view('categories.show', ['category' => $category]);
    }

    public function edit($id)
    {
        $category = Category::findOrFail($id);
        return view('categories.edit', ['category' => $category]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        
        \Validator::make($request->all(), [
            "name" => "required|min:3|max:20",
            "image" => "required",
            "slug" => [
                "required",
                Rule::unique("categories")->ignore($category->slug, "slug")
            ]
        ])->validate();

        $name = $request->get('name');
        $slug = $request->get('slug');
        $category->name = $name;
        $category->slug = $slug;
        if($request->file('image')) {
            if($category->image && file_exists(storage_path('app/public/' .$category->image))) {
                \Storage::delete('public/' . $category->name);
            }

            $new_image = $request->file('image')->store('category_images', 'public');
            $category->image = $new_image;
        }
        $category->updated_by = \Auth::user()->id;
        $category->save();
        return redirect()->route('categories.edit', [$id])->with('status', 'Category successfully updated');
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->deleted_by = \Auth::user()->id;
        $category->delete();
        return redirect()->route('categories.index')->with('status', 'Category successfully moved to trash');
    }

    public function trash()
    {
        $deleted_category = Category::onlyTrashed()->paginate(10);
        return view('categories.trash', ['categories' => $deleted_category]);
    }

    public function restore($id)
    {
        $category = \App\Models\Category::withTrashed()->findOrFail($id);
        if($category->trashed()) {
            $category->restore();
        } else {
            return redirect()->route('categories.index')->with('status', 'Category is not in trash');
        }
        return redirect()->route('categories.index')->with('status', 'Category successfully restored');
    }

    public function deletePermanent($id)
    {
        $category = \App\Models\Category::withTrashed()->findOrFail($id);
        if(!$category->trashed()) {
            return redirect()->route('categories.index')->with('status', 'Can not delete permanent active category');
        } else {
        $category->forceDelete();
        return redirect()->route('categories.index')->with('status', 'Category permanently deleted');
        }
    }

}
