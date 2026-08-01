@extends('layouts.app')
@section('title', 'Confirmer mon e-mail - AchatHub')
@section('content')
<div class="container py-5"><div class="row justify-content-center"><div class="col-md-7 col-lg-6"><div class="stat-card text-center">
    <i class="bi bi-envelope-check display-4 text-success"></i>
    <h1 class="section-title h3 mt-3">Confirmez votre adresse e-mail</h1>
    <p class="text-secondary">Nous avons envoyé un lien à <strong>{{ auth()->user()->email }}</strong>. Cette confirmation protège vos commandes et vos factures.</p>
    @if(auth()->user()->hasVerifiedEmail())
        <div class="alert alert-success">Votre adresse est déjà confirmée.</div>
    @else
        <form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn btn-ah">Renvoyer le lien</button></form>
    @endif
</div></div></div></div>
@endsection
