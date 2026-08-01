@extends('layouts.app')
@section('title', 'Nouveau mot de passe - AchatHub')
@section('content')
<div class="container py-5"><div class="row justify-content-center"><div class="col-md-6 col-lg-5"><div class="stat-card">
    <h1 class="section-title h3">Choisir un nouveau mot de passe</h1>
    <form method="post" action="{{ route('password.update') }}">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label class="form-label" for="reset_email">Adresse e-mail</label>
        <input id="reset_email" class="form-control mb-3" type="email" name="email" value="{{ old('email', $email) }}" required>
        <label class="form-label" for="reset_password">Nouveau mot de passe</label>
        <input id="reset_password" class="form-control mb-3" type="password" name="password" minlength="12" autocomplete="new-password" required>
        <label class="form-label" for="reset_password_confirmation">Confirmation</label>
        <input id="reset_password_confirmation" class="form-control mb-3" type="password" name="password_confirmation" autocomplete="new-password" required>
        <button class="btn btn-ah w-100">Enregistrer mon mot de passe</button>
    </form>
</div></div></div></div>
@endsection
