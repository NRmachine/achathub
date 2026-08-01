<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $conversation = Conversation::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['subject' => 'Échange avec AchatHub', 'last_message_at' => now()]
        );
        $conversation->messages()->whereHas('sender', fn ($query) => $query->where('role', 'admin'))
            ->whereNull('read_at')->update(['read_at' => now()]);
        $conversation->update(['user_read_at' => now()]);

        return view('messages.index', [
            'conversation' => $conversation->load(['messages.sender']),
            'layout' => $request->user()->role === 'reseller' ? 'layouts.pro' : 'layouts.account',
            'section' => $request->user()->role === 'reseller' ? 'pro-content' : 'account-content',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $conversation = Conversation::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['subject' => 'Échange avec AchatHub']
        );
        $conversation->messages()->create(['sender_id' => $request->user()->id, 'body' => trim($data['body'])]);
        $conversation->update(['status' => 'Ouverte', 'last_message_at' => now(), 'admin_read_at' => null]);

        return back()->with('success', 'Message envoyé à l’équipe AchatHub.');
    }
}
