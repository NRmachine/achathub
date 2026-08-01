<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;

class AdminConversationController extends Controller
{
    public function index(Request $request)
    {
        $conversations = Conversation::with(['user', 'lastMessage.sender'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->whereColumn('sender_id', 'conversations.user_id')])
            ->when($request->q, fn ($q, $value) => $q->whereHas('user', fn ($users) => $users->where(fn ($u) => $u->where('name', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%"))))
            ->when($request->status, fn ($q, $value) => $q->where('status', $value))
            ->orderByDesc('last_message_at')->paginate(30)->withQueryString();

        return view('admin.conversations', compact('conversations'));
    }

    public function show(Conversation $conversation)
    {
        $conversation->messages()->where('sender_id', $conversation->user_id)->whereNull('read_at')->update(['read_at' => now()]);
        $conversation->update(['admin_read_at' => now()]);

        return view('admin.conversation-show', [
            'conversation' => $conversation->load(['user.resellerRequest', 'messages.sender']),
            'classicOrders' => $conversation->user->orders()->latest()->limit(5)->get(),
            'professionalOrders' => $conversation->user->professionalOrders()->latest()->limit(5)->get(),
        ]);
    }

    public function store(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $conversation->messages()->create(['sender_id' => $request->user()->id, 'body' => trim($data['body'])]);
        $conversation->update(['status' => 'Ouverte', 'last_message_at' => now(), 'user_read_at' => null]);

        return back()->with('success', 'Réponse envoyée.');
    }

    public function status(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['status' => ['required', 'in:Ouverte,Fermée']]);
        $conversation->update($data);

        return back()->with('success', 'État de la conversation mis à jour.');
    }
}
