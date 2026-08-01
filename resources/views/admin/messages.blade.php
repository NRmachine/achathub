@extends('layouts.admin')
@section('title', 'Support - Administration AchatHub')
@section('admin-content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><span class="text-secondary small text-uppercase">Service client</span><h1 class="h2 fw-bold mb-0">Messages de support</h1></div></div>
<div class="border rounded bg-white">
@forelse($messages as $message)
<article class="p-4 border-bottom"><div class="d-flex flex-wrap justify-content-between gap-2"><div><h2 class="h6 fw-bold mb-1">{{ $message->subject }}</h2><small class="text-secondary">{{ $message->name }} · {{ $message->email }} · {{ $message->created_at->format('d/m/Y H:i') }}</small></div><span class="badge {{ $message->status === 'Traité' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $message->status }}</span></div><p class="my-3">{{ $message->message }}</p>@if($message->status !== 'Traité')<form method="post" action="{{ route('admin.messages.resolve',$message) }}">@csrf @method('patch')<button class="btn btn-sm btn-outline-success">Marquer comme traité</button></form>@endif</article>
@empty<div class="p-5 text-center text-secondary">Aucun message.</div>@endforelse
</div><div class="mt-3">{{ $messages->links() }}</div>
@endsection
