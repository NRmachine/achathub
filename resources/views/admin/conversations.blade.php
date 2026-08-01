@extends('layouts.admin')
@section('title','Messagerie - Administration')
@section('admin-content')
<div class="admin-page-heading"><div><small>SERVICE CLIENT</small><h1>Messagerie</h1><p>Conversations privées avec les clients et revendeurs.</p></div></div>
<form class="admin-filter" method="get"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Nom ou e-mail"><select class="form-select" name="status"><option value="">Tous les états</option><option @selected(request('status')==='Ouverte')>Ouverte</option><option @selected(request('status')==='Fermée')>Fermée</option></select><button class="btn btn-dark">Filtrer</button></form>
<div class="admin-surface table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Client</th><th>Dernier message</th><th>Type</th><th>État</th><th></th></tr></thead><tbody>
@forelse($conversations as $conversation)<tr><td><strong>{{ $conversation->user->name }}</strong><br><small>{{ $conversation->user->email }}</small></td><td><span class="text-clamp">{{ $conversation->lastMessage?->body ?: 'Conversation ouverte' }}</span><small>{{ $conversation->last_message_at?->diffForHumans() }}</small>@if($conversation->unread_count)<span class="badge text-bg-danger ms-2">{{ $conversation->unread_count }}</span>@endif</td><td>{{ $conversation->user->role === 'reseller' ? 'Revendeur pro' : 'Client' }}</td><td><span class="badge {{ $conversation->status==='Ouverte'?'text-bg-success':'text-bg-secondary' }}">{{ $conversation->status }}</span></td><td><a class="btn btn-sm btn-dark" href="{{ route('admin.conversations.show',$conversation) }}">Ouvrir</a></td></tr>
@empty<tr><td colspan="5" class="text-center py-5">Aucune conversation.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $conversations->links() }}</div>
@endsection
