<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use App\Gallery;
use App\Album;
use Carbon\Carbon;
use Toastr;

class GalleryController extends Controller
{
    public function album()
    {
        $albums = Album::latest()->with('galleryimages')->get();
        return view('admin.galleries.album', compact('albums'));
    }

    public function albumStore(Request $request)
    {
        Album::create([
            'name'    => $request->name,
            'user_id' => \Auth::id()
        ]);
        return back();
    }

    public function albumGallery($id)
    {
        $album_id  = $id;
        $galleries = Gallery::latest()->where('album_id', $album_id)->get();
        return view('admin.galleries.gallery', compact('galleries', 'album_id'));
    }

    public function Gallerystore(Request $request)
    {
        $albumid = $request->input('albumid');
        $image   = $request->file('file');

        if ($image) {
            $currentDate = Carbon::now()->toDateString();
            $imagename   = 'gallery-' . $currentDate . '-' . uniqid() . '.' . $image->getClientOriginalExtension();
            $imagesize   = $image->getSize(); // FIX: was getClientSize() (deprecated)
            $imagetype   = $image->getClientMimeType();

            if (!Storage::disk('public')->exists('gallery')) {
                Storage::disk('public')->makeDirectory('gallery');
            }
            $imagegallery = Image::make($image)->stream();
            Storage::disk('public')->put('gallery/' . $imagename, $imagegallery);

            $imagelink = Storage::url('gallery/' . $imagename); // FIX: was missing 'gallery/' prefix

            Gallery::create([
                'album_id' => $albumid,
                'image'    => $imagename,
                'size'     => $imagesize,
                'type'     => $imagetype,
                'link'     => $imagelink
            ]);
        }

        Toastr::success('message', 'Images uploadées avec succès.');
        return back();
    }
}
