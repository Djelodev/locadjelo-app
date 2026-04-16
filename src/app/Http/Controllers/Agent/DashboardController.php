<?php

namespace App\Http\Controllers\Agent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use App\Mail\Contact;
use Carbon\Carbon;
use App\Property;
use App\Message;
use App\User;
use Auth;
use Hash;
use Toastr;

class DashboardController extends Controller
{
    public function index()
    {
        $properties    = Property::latest()->where('agent_id', Auth::id())->take(5)->get();
        $propertytotal = Property::latest()->where('agent_id', Auth::id())->count();
        $messages      = Message::latest()->where('agent_id', Auth::id())->take(5)->get();
        $messagetotal  = Message::latest()->where('agent_id', Auth::id())->count();

        return view('agent.dashboard', compact('properties', 'propertytotal', 'messages', 'messagetotal'));
    }

    public function profile()
    {
        $profile = Auth::user();
        return view('agent.profile', compact('profile'));
    }

    public function profileUpdate(Request $request)
    {
        $request->validate([
            'name'     => 'required',
            'username' => 'required',
            'email'    => 'required|email',
            'image'    => 'mimetypes:image/jpeg,image/png,image/gif,image/webp',
            'about'    => 'max:191'
        ]);

        $user  = User::find(Auth::id());
        $image = $request->file('image');
        $slug  = str_slug($request->name);

        if ($image) {
            $currentDate = Carbon::now()->toDateString();
            $imagename   = $slug . '-agent-' . Auth::id() . '-' . $currentDate . '.' . $image->getClientOriginalExtension();

            if (!Storage::disk('public')->exists('users')) {
                Storage::disk('public')->makeDirectory('users');
            }
            if (Storage::disk('public')->exists('users/' . $user->image) && $user->image != 'default.png') {
                Storage::disk('public')->delete('users/' . $user->image);
            }
            $userimage = Image::make($image)->stream(); // FIX: was ->encode($ext, 90)
            Storage::disk('public')->put('users/' . $imagename, $userimage);
            $user->image = $imagename;
        }

        $user->name     = $request->name;
        $user->username = $request->username;
        $user->email    = $request->email;
        $user->about    = $request->about;
        $user->save();

        return back();
    }

    public function changePassword()
    {
        return view('agent.changepassword');
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
        $user->password = Hash::make($request->get('newpassword'));
        $user->save();

        Toastr::success('message', 'Mot de passe modifié avec succès.');
        return redirect()->back();
    }

    public function message()
    {
        $messages = Message::latest()->where('agent_id', Auth::id())->paginate(10);
        return view('agent.messages.index', compact('messages'));
    }

    public function messageRead($id)
    {
        $message = Message::findOrFail($id);
        return view('agent.messages.read', compact('message'));
    }

    public function messageReplay($id)
    {
        $message = Message::findOrFail($id);
        return view('agent.messages.replay', compact('message'));
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

        return redirect()->route('agent.message');
    }

    public function messageDelete($id)
    {
        Message::findOrFail($id)->delete();
        Toastr::success('message', 'Message supprimé avec succès.');
        return back();
    }

    public function contactMail(Request $request)
    {
        Mail::to($request->email)->send(new Contact($request->message, $request->name, $request->mailfrom));
        Toastr::success('message', 'E-mail envoyé avec succès.');
        return back();
    }
}
