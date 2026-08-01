<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()) {
            return redirect()->route('messages.index');
        }
        return view('support.index');
    }

    public function store(Request $request)
    {
        if ($request->user()) {
            $data = $request->validate(['message' => ['required', 'string', 'max:3000']]);
            $conversation = $request->user()->conversation()->firstOrCreate([], ['subject' => $request->input('subject', 'Support AchatHub')]);
            $conversation->messages()->create(['sender_id' => $request->user()->id, 'body' => $data['message']]);
            $conversation->update(['status' => 'Ouverte', 'last_message_at' => now(), 'admin_read_at' => null]);
            return redirect()->route('messages.index')->with('success', 'Message envoyé.');
        }
        $data = $request->validate(['name' => ['required', 'max:120'], 'email' => ['required', 'email'], 'subject' => ['required', 'max:160'], 'message' => ['required', 'max:3000']]);
        SupportMessage::create([...$data, 'user_id' => $request->user()?->id]);

        return back()->with('success', 'Votre message a bien été transmis au support.');
    }
}
