<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Carbon\Carbon;
use App\Comment;
use App\Message;
use App\User;
use Auth;
use Hash;
use Toastr;

class DashboardController extends Controller
{
    public function index()
    {
        $comments = Comment::latest()
                           ->with('commentable')
                           ->where('user_id', Auth::id())
                           ->paginate(10);

        $commentcount = Comment::where('user_id', Auth::id())->count();

        return view('user.dashboard', compact('comments', 'commentcount'));
    }

    public function profile()
    {
        $profile = Auth::user();
        return view('user.profile', compact('profile'));
    }

    public function profileUpdate(Request $request)
    {
        $request->validate([
            'name'     => 'required',
            'username' => 'required',
            'email'    => 'required|email',
            'image'    => 'mimetypes:image/jpeg,image/png,image/gif,image/webp',
            'about'    => 'max:250'
        ]);

        $user  = User::find(Auth::id());
        $image = $request->file('image');
        $slug  = str_slug($request->name);

        if ($image) {
            $currentDate = Carbon::now()->toDateString();
            $imagename   = $slug . '-user-' . Auth::id() . '-' . $currentDate . '.' . $image->getClientOriginalExtension();

            if (!Storage::disk('public')->exists('users')) {
                Storage::disk('public')->makeDirectory('users');
            }
            if (Storage::disk('public')->exists('users/' . $user->image) && $user->image != 'default.png') {
                Storage::disk('public')->delete('users/' . $user->image);
            }
            $userimage   = Image::make($image)->stream();
            Storage::disk('public')->put('users/' . $imagename, $userimage);
            $user->image = $imagename; // FIX: only update image if a new one was uploaded
        }
        // FIX: removed $user->image = $imagename outside the if block (was undefined when no image)

        $user->name     = $request->name;
        $user->username = $request->username;
        $user->email    = $request->email;
        $user->about    = $request->about;
        $user->save();

        return back();
    }

    public function changePassword()
    {
        return view('user.changepassword');
    }

    public function changePasswordUpdate(Request $request)
    {
        if (!Hash::check($request->get('currentpassword'), Auth::user()->password)) {
            Toastr::error('message', 'Mot de passe actuel incorrect.');
            return redirect()->back();
        }
        if (strcmp($request->get('currentpassword'), $request->get('newpassword')) == 0) {
            Toastr::error('message', 'Le nouveau mot de passe doit être différent de l\'actuel.');
            return redirect()->back();
        }

        $this->validate($request, [
            'currentpassword' => 'required',
            'newpassword'     => 'required|string|min:6|confirmed',
        ]);

        $user           = Auth::user();
        $user->password = Hash::make($request->get('newpassword')); // FIX: was bcrypt()
        $user->save();

        Toastr::success('message', 'Mot de passe modifié avec succès.');
        return redirect()->back();
    }

    public function message()
    {
        $messages = Message::latest()->where('agent_id', Auth::id())->paginate(10);
        return view('user.messages.index', compact('messages'));
    }

    public function messageRead($id)
    {
        $message = Message::findOrFail($id);
        return view('user.messages.read', compact('message'));
    }

    public function messageReplay($id)
    {
        $message = Message::findOrFail($id);
        return view('user.messages.replay', compact('message'));
    }

    public function messageSend(Request $request)
    {
        $request->validate([
            'agent_id' => 'required',
            'user_id'  => 'required',
            'name'     => 'required',
            'email'    => 'required',
            'phone'    => 'required',
            'message'  => 'required'
        ]);

        Message::create($request->all());

        Toastr::success('message', 'Message envoyé avec succès.');
        return back();
    }

    public function messageReadUnread(Request $request)
    {
        $status  = $request->status;
        $msgid   = $request->messageid;
        $status  = $status ? 0 : 1;

        $message         = Message::findOrFail($msgid);
        $message->status = $status;
        $message->save();

        return redirect()->route('user.message');
    }

    public function messageDelete($id)
    {
        Message::findOrFail($id)->delete();
        Toastr::success('message', 'Message supprimé avec succès.');
        return back();
    }
}
