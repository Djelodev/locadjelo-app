<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use App\Testimonial;
use Carbon\Carbon;
use Toastr;

class TestimonialController extends Controller
{
    public function index()
    {
        $testimonials = Testimonial::latest()->get();
        return view('admin.testimonials.index', compact('testimonials'));
    }

    public function create()
    {
        return view('admin.testimonials.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required',
            'image'       => 'required|mimetypes:image/jpeg,image/png,image/gif,image/webp',
            'testimonial' => 'required|max:200'
        ]);

        $image = $request->file('image');
        $slug  = str_slug($request->name); // FIX: was $request->title

        if ($image) {
            $currentDate = Carbon::now()->toDateString();
            $imagename   = $slug . '-' . $currentDate . '-' . uniqid() . '.' . $image->getClientOriginalExtension();

            if (!Storage::disk('public')->exists('testimonial')) {
                Storage::disk('public')->makeDirectory('testimonial');
            }
            $testimonialimg = Image::make($image)->resize(160, 160)->stream(); // FIX: was ->save()
            Storage::disk('public')->put('testimonial/' . $imagename, $testimonialimg);
        } else {
            $imagename = 'default.png';
        }

        $testimonial              = new Testimonial();
        $testimonial->name        = $request->name;
        $testimonial->testimonial = $request->testimonial;
        $testimonial->image       = $imagename;
        $testimonial->save();

        Toastr::success('message', 'Témoignage créé avec succès.');
        return redirect()->route('admin.testimonials.index');
    }

    public function edit($testimonial)
    {
        $testimonial = Testimonial::find($testimonial);
        return view('admin.testimonials.edit', compact('testimonial'));
    }

    public function update(Request $request, $testimonial)
    {
        $request->validate([
            'name'        => 'required',
            'image'       => 'mimetypes:image/jpeg,image/png,image/gif,image/webp',
            'testimonial' => 'required|max:200',
        ]);

        $image       = $request->file('image');
        $slug        = str_slug($request->name);
        $testimonial = Testimonial::find($testimonial);

        if ($image) {
            $currentDate = Carbon::now()->toDateString();
            $imagename   = $slug . '-' . $currentDate . '-' . uniqid() . '.' . $image->getClientOriginalExtension();

            if (!Storage::disk('public')->exists('testimonial')) {
                Storage::disk('public')->makeDirectory('testimonial');
            }
            if (Storage::disk('public')->exists('testimonial/' . $testimonial->image)) {
                Storage::disk('public')->delete('testimonial/' . $testimonial->image);
            }
            $testimonialimg = Image::make($image)->resize(160, 160)->stream(); // FIX: was ->save()
            Storage::disk('public')->put('testimonial/' . $imagename, $testimonialimg);
        } else {
            $imagename = $testimonial->image;
        }

        $testimonial->name        = $request->name;
        $testimonial->testimonial = $request->testimonial;
        $testimonial->image       = $imagename;
        $testimonial->save();

        Toastr::success('message', 'Témoignage mis à jour avec succès.');
        return redirect()->route('admin.testimonials.index');
    }

    public function destroy($testimonial)
    {
        $testimonial = Testimonial::find($testimonial);

        if (Storage::disk('public')->exists('testimonial/' . $testimonial->image)) {
            Storage::disk('public')->delete('testimonial/' . $testimonial->image);
        }

        $testimonial->delete();

        Toastr::success('message', 'Témoignage supprimé avec succès.');
        return back();
    }
}
