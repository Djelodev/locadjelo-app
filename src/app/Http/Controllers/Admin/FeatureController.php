<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Feature;
use Toastr;

class FeatureController extends Controller
{
    public function index()
    {
        $features = Feature::latest()->get();
        return view('admin.features.index', compact('features'));
    }

    public function create()
    {
        return view('admin.features.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:features|max:255'
        ]);

        $feature       = new Feature();
        $feature->name = $request->name;
        $feature->slug = str_slug($request->name);
        $feature->save();

        Toastr::success('message', 'Caractéristique créée avec succès.');
        return redirect()->route('admin.features.index');
    }

    public function show($feature) {}

    public function edit($feature)
    {
        $feature = Feature::find($feature);
        return view('admin.features.edit', compact('feature'));
    }

    public function update(Request $request, $feature)
    {
        $request->validate([
            'name' => 'required|max:255'
        ]);

        $feature       = Feature::find($feature);
        $feature->name = $request->name;
        $feature->slug = str_slug($request->name);
        $feature->save();

        Toastr::success('message', 'Caractéristique mise à jour avec succès.');
        return redirect()->route('admin.features.index');
    }

    public function destroy($feature)
    {
        $feature = Feature::find($feature);
        $feature->properties()->detach();
        $feature->delete();

        Toastr::success('message', 'Caractéristique supprimée avec succès.');
        return back();
    }
}
