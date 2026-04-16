<?php

namespace App\Http\Controllers\Admin;

use App\Tag;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Toastr;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::latest()->get();
        return view('admin.tags.index', compact('tags'));
    }

    public function create()
    {
        return view('admin.tags.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:tags|max:255'
        ]);

        $tag       = new Tag();
        $tag->name = $request->name;
        $tag->slug = str_slug($request->name);
        $tag->save();

        Toastr::success('message', 'Tag créé avec succès.');
        return redirect()->route('admin.tags.index');
    }

    public function show($tag) {}

    public function edit($tag)
    {
        $tag = Tag::find($tag);
        return view('admin.tags.edit', compact('tag'));
    }

    public function update(Request $request, $tag)
    {
        $request->validate([
            'name' => 'required|max:255'
        ]);

        $tag       = Tag::find($tag);
        $tag->name = $request->name;
        $tag->slug = str_slug($request->name);
        $tag->save();

        Toastr::success('message', 'Tag mis à jour avec succès.');
        return redirect()->route('admin.tags.index');
    }

    public function destroy($tag)
    {
        $tag = Tag::find($tag);
        $tag->posts()->detach();
        $tag->delete();

        Toastr::success('message', 'Tag supprimé avec succès.');
        return back();
    }
}
