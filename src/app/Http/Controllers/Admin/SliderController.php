<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use App\Slider;
use Carbon\Carbon;
use Toastr;

class SliderController extends Controller
{
    public function index()
    {
        $sliders = Slider::latest()->get();
        return view('admin.sliders.index', compact('sliders'));
    }

    public function create()
    {
        return view('admin.sliders.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|unique:sliders|max:255',
            'image' => 'required|mimetypes:image/jpeg,image/png,image/gif,image/webp'
        ]);

        $image = $request->file('image');
        $slug  = str_slug($request->title);

        if ($image) {
            $currentDate = Carbon::now()->toDateString();
            $imagename = $slug . '-' . $currentDate . '-' . uniqid() . '.' . $image->getClientOriginalExtension();

            if (!Storage::disk('public')->exists('slider')) {
                Storage::disk('public')->makeDirectory('slider');
            }
            $slider = Image::make($image)->resize(1600, 480, function ($c) {
                $c->aspectRatio();
                $c->upsize();
            })->stream();
            Storage::disk('public')->put('slider/' . $imagename, $slider);
        } else {
            $imagename = 'default.png';
        }

        $slider = new Slider();
        $slider->title       = $request->title;
        $slider->description = $request->description;
        $slider->image       = $imagename;
        $slider->save();

        Toastr::success('message', 'Slider créé avec succès.');
        return redirect()->route('admin.sliders.index');
    }

    public function edit($slider)
    {
        $slider = Slider::find($slider);
        return view('admin.sliders.edit', compact('slider'));
    }

    public function update(Request $request, $slider)
    {
        $request->validate([
            'title' => 'required|max:255',
            'image' => 'mimetypes:image/jpeg,image/png,image/gif,image/webp'
        ]);

        $image  = $request->file('image');
        $slug   = str_slug($request->title);
        $slider = Slider::find($slider);

        if ($image) {
            $currentDate = Carbon::now()->toDateString();
            $imagename   = $slug . '-' . $currentDate . '-' . uniqid() . '.' . $image->getClientOriginalExtension();

            if (!Storage::disk('public')->exists('slider')) {
                Storage::disk('public')->makeDirectory('slider');
            }
            if (Storage::disk('public')->exists('slider/' . $slider->image)) {
                Storage::disk('public')->delete('slider/' . $slider->image);
            }
            $sliderimg = Image::make($image)->resize(1600, 480, function ($c) {
                $c->aspectRatio();
                $c->upsize();
            })->stream();
            Storage::disk('public')->put('slider/' . $imagename, $sliderimg);
        } else {
            $imagename = $slider->image;
        }

        $slider->title       = $request->title;
        $slider->description = $request->description;
        $slider->image       = $imagename;
        $slider->save();

        Toastr::success('message', 'Slider mis à jour avec succès.');
        return redirect()->route('admin.sliders.index');
    }

    public function destroy($slider)
    {
        $slider = Slider::find($slider);

        if (Storage::disk('public')->exists('slider/' . $slider->image)) {
            Storage::disk('public')->delete('slider/' . $slider->image);
        }

        $slider->delete();

        Toastr::success('message', 'Slider supprimé avec succès.');
        return back();
    }
}
