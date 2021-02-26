<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

class BookController extends Controller
{

    public function __construct(){
        $this->middleware(function($request, $next){
            if(Gate::allows('manage-books')) return $next($request);
            abort(403, 'Anda tidak memiliki cukup hak akses');
        });
    }
    
    public function index(Request $request)
    {
        $status = $request->get('status');
        $keyword = $request->get('keyword') ? $request->get('keyword') : '';
        if($status) {
            $books = \App\Models\Book::with('categories')->where('title',
            "LIKE", "%$keyword%")->where('status', strtoupper($status))->paginate(10);
        } else {
            $books = \App\Models\Book::with('categories')->where("title",
            "LIKE", "%$keyword%")->paginate(10);
        }
        return view('books.index', ['books'=> $books]);
    }

    public function create()
    {
        return view('books.create');
    }

    public function store(Request $request)
    {
        \Validator::make($request->all(), [
            "title" => "required|min:5|max:200",
            "description" => "required|min:20|max:1000",
            "author" => "required|min:3|max:100",
            "publisher" => "required|min:3|max:200",
            "price" => "required|digits_between:0,10",
            "stock" => "required|digits_between:0,10",
            "cover" => "required"
        ])->validate();

        $new_book = new Book;
        $new_book->title = $request->get('title');
        $new_book->description = $request->get('description');
        $new_book->author = $request->get('author');
        $new_book->publisher = $request->get('publisher');
        $new_book->price = $request->get('price');
        $new_book->stock = $request->get('stock');
        $new_book->status = $request->get('save_action');
        $cover = $request->file('cover');
        if($cover) {
            $cover_path = $cover->store('book-covers', 'public');
            $new_book->cover = $cover_path;
        }
        $new_book->slug = \Str::slug($request->get('title'));
        $new_book->created_by = \Auth::user()->id;
        $new_book->categories()->attach($request->get('categories'));
        $new_book->save();
        if($request->get('save_action') == 'PUBLISH') {
            return redirect()->route('books.create')->with('status', 'Book successfully saved and published');
        } else {
            return redirect()->route('books.create')->with('status', 'Book saved as draft');
        }
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        $book = Book::findOrFail($id);
        return view('books.edit', ['book' => $book]);
    }

    public function update(Request $request, $id)
    {
        $book = Book::findOrFail($id);

        \Validator::make($request->all(), [
            "title" => "required|min:5|max:200",
            "slug" => [
            "required",
            Rule::unique("books")->ignore($book->slug, "slug")
            ],
            "description" => "required|min:20|max:1000",
            "author" => "required|min:3|max:100",
            "publisher" => "required|min:3|max:200",
            "price" => "required|digits_between:0,10",
            "stock" => "required|digits_between:0,10",
        ])->validate();

        $book->title = $request->get('title');
        $book->slug = $request->get('slug');
        $book->description = $request->get('description');
        $book->author = $request->get('author');
        $book->publisher = $request->get('publisher');
        $book->stock = $request->get('stock');
        $book->price = $request->get('price');
        $new_cover = $request->file('cover');
        if($new_cover) {
            if($book->cover && file_exists(storage_path('app/public/' . $book->cover))) {
                \Storage::delete('public/'. $book->cover);
            }
            $new_cover_path = $new_cover->store('book-covers', 'public');
            $book->cover = $new_cover_path;
        }
        $book->updated_by = \Auth::user()->id;
        $book->status = $request->get('status');
        $book->save();
        $book->categories()->sync($request->get('categories'));
        return redirect()->route('books.edit', [$book->id])->with('status', 'Book successfully updated');
    }

    public function destroy($id)
    {
        $book = Book::findOrFail($id);
        $book->delete();
        return redirect()->route('books.index')->with('status', 'Book moved to trash');
    }

    public function trash()
    {
        $books = Book::onlyTrashed()->paginate(10);
        return view('books.trash', ['books' => $books]);
    }

    public function restore($id){
        $book = \App\Models\Book::withTrashed()->findOrFail($id);
        if($book->trashed()) {
            $book->restore();
            return redirect()->route('books.trash')->with('status', 'Book successfully restored');
        } else {
            return redirect()->route('books.trash')->with('status', 'Book is not in trash');
        }
    }

    public function deletePermanent($id){
        $book = \App\Models\Book::withTrashed()->findOrFail($id);
        if(!$book->trashed()) {
            return redirect()->route('books.trash')->with('status', 'Book is not in trash!')->with('status_type', 'alert');
        } else {
            $book->categories()->detach();
            $book->forceDelete();
            return redirect()->route('books.trash')->with('status', 'Book permanently deleted!');
        }
    }
}
