@extends($layout)
@section('title', 'Messagerie sécurisée - AchatHub')
@section($section)
<div class="chat-page">
    <div class="chat-heading"><div><span class="chat-online"></span><strong>Équipe AchatHub</strong><small>Messagerie privée et sécurisée</small></div><span class="badge text-bg-light">{{ $conversation->status }}</span></div>
    <div class="chat-thread" aria-live="polite">
        @forelse($conversation->messages as $message)
            <article class="chat-bubble {{ $message->sender_id === auth()->id() ? 'mine' : 'theirs' }}">
                <strong>{{ $message->sender_id === auth()->id() ? 'Vous' : 'AchatHub' }}</strong>
                <p>{{ $message->body }}</p><time>{{ $message->created_at->format('d/m/Y à H:i') }}</time>
            </article>
        @empty
            <div class="chat-empty"><i class="bi bi-chat-dots"></i><h1>Comment pouvons-nous vous aider ?</h1><p>Écrivez votre message. L’historique restera disponible dans votre compte.</p></div>
        @endforelse
    </div>
    <form method="post" action="{{ route('messages.store') }}" class="chat-composer">@csrf
        <label class="visually-hidden" for="message-body">Votre message</label>
        <textarea id="message-body" name="body" rows="2" maxlength="3000" required placeholder="Écrivez votre message…">{{ old('body') }}</textarea>
        <button aria-label="Envoyer le message"><i class="bi bi-send-fill"></i><span>Envoyer</span></button>
    </form>
</div>
@endsection
