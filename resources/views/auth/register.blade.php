@extends('layouts.app')
@section('title','Créer un compte - AchatHub')
@section('content')
<div class="container py-5"><div class="row justify-content-center"><div class="col-md-7 col-lg-5"><div class="stat-card"><h1 class="section-title h3">Créer mon compte</h1><form method="post" action="{{ route('register.store') }}">@csrf
<label class="form-label">Nom complet</label><input class="form-control mb-3" name="name" value="{{ old('name') }}" required>
<label class="form-label">E-mail</label><input class="form-control mb-3" type="email" name="email" value="{{ old('email') }}" required>
<label class="form-label">Téléphone</label><input class="form-control mb-3" name="phone" value="{{ old('phone') }}">
<div class="row"><div class="col"><label class="form-label">Mot de passe</label><input class="form-control mb-1" type="password" name="password" minlength="12" required><div class="form-text mb-3">12 caractères minimum avec lettres et chiffres.</div></div><div class="col"><label class="form-label">Confirmation</label><input class="form-control mb-3" type="password" name="password_confirmation" required></div></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="terms" value="1" id="terms" required><label class="form-check-label small" for="terms">J’accepte les <a href="{{ route('legal.terms') }}" target="_blank">conditions générales</a> et la <a href="{{ route('legal.privacy') }}" target="_blank">politique de confidentialité</a>.</label></div>
<button class="btn btn-ah w-100">Créer mon compte</button></form></div></div></div></div>
@endsection
