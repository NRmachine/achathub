@extends('layouts.account')
@section('title', 'Espace revendeur AchatHub')
@section('account-content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><span class="text-uppercase small text-secondary">Compte professionnel</span><h1 class="h2 fw-bold mb-0">Mon espace revendeur</h1></div>
    @if($application?->status === 'Approuvée')<a class="btn btn-warning" href="{{ route('pro.index') }}">Accéder au catalogue pro</a>@endif
</div>

@unless(auth()->user()->hasVerifiedEmail())
<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-3">
    <span><strong>Confirmez votre adresse professionnelle.</strong> Les décisions sur votre dossier seront également envoyées par e-mail.</span>
    <form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn btn-sm btn-dark">Renvoyer la confirmation</button></form>
</div>
@endunless

@if(!$application)
<div class="alert alert-info"><h2 class="h5">Aucune demande professionnelle</h2><p>Déposez votre dossier pour accéder aux tarifs grossistes.</p><a class="btn btn-dark" href="{{ route('reseller.index') }}#demande">Faire ma demande</a></div>
@else
<div class="card shadow-sm mb-4"><div class="card-body p-4 d-flex flex-wrap justify-content-between gap-3"><div><span class="badge {{ $application->status === 'Approuvée' ? 'text-bg-success' : ($application->status === 'En attente' ? 'text-bg-warning' : 'text-bg-danger') }} mb-2">{{ $application->status }}</span><h2 class="h5 mb-1">{{ $application->business_name }}</h2><p class="text-secondary mb-0">Demande déposée le {{ $application->created_at->format('d/m/Y') }} · {{ $application->formula }}</p></div><div class="text-secondary">{{ $application->city }}<br>{{ $application->phone }}</div></div></div>
@if($application->status === 'En attente')<div class="alert alert-warning">Votre dossier est en cours de vérification. Les prix grossistes seront ouverts dès sa validation.</div>@endif
@if(in_array($application->status, ['Refusée','Suspendue']))<div class="alert alert-danger">L’accès professionnel n’est pas actif. {{ $application->admin_notes ?: 'Contactez le support pour obtenir plus d’informations.' }}</div>@endif
@endif

<h2 class="h4 fw-bold mt-5">Mes commandes professionnelles</h2>
@if($orders->isEmpty())<div class="border rounded p-4 text-secondary">Aucune commande professionnelle pour le moment.</div>@else
<div class="table-responsive border rounded"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Référence</th><th>Date</th><th>Montant TTC</th><th>Paiement</th><th>Statut</th></tr></thead><tbody>@foreach($orders as $order)<tr><td class="fw-semibold">{{ $order->number }}</td><td>{{ $order->created_at->format('d/m/Y') }}</td><td>{{ number_format($order->total_ttc,2,',',' ') }} €</td><td>{{ $order->payment_status }}</td><td>{{ $order->status }}</td></tr>@endforeach</tbody></table></div>
@endif
@endsection
