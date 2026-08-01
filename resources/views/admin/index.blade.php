@extends('layouts.admin')
@section('title','Administration AchatHub')
@section('admin-content')
<div class="admin-page-heading"><div><small>PILOTAGE COMMERCIAL</small><h1>Tableau de bord</h1><p>Commandes, paiements, comptes professionnels et alertes opérationnelles.</p></div><a class="btn btn-dark align-self-start" href="{{ route('admin.products.create') }}"><i class="bi bi-plus-lg me-1"></i> Ajouter un produit</a></div>

@unless($mailReady)
<div class="alert alert-warning"><strong>E-mails non configurés.</strong> Ajoutez les identifiants SMTP dans l’environnement de production avant l’ouverture commerciale.</div>
@endunless

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="admin-kpi"><span>CA payé total</span><strong>{{ number_format($stats['revenue']+$stats['professional_revenue'],2,',',' ') }} €</strong><small>Ce mois : {{ number_format($stats['month_revenue'],2,',',' ') }} €</small></div></div>
    <div class="col-6 col-xl-3"><div class="admin-kpi"><span>Commandes à traiter</span><strong>{{ $stats['pending_orders'] + $stats['pending_professional_orders'] }}</strong><a href="{{ route('admin.orders') }}">{{ $stats['pending_orders'] }} client · {{ $stats['pending_professional_orders'] }} pro</a></div></div>
    <div class="col-6 col-xl-3"><div class="admin-kpi"><span>Paiements en attente</span><strong>{{ $stats['unpaid'] }}</strong><a href="{{ route('admin.orders') }}">Contrôler les règlements</a></div></div>
    <div class="col-6 col-xl-3"><div class="admin-kpi"><span>Stock faible</span><strong>{{ $stats['low_stock'] }}</strong><a href="{{ route('admin.products',['status'=>'active']) }}">Gérer le catalogue</a></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><a class="admin-alert-link" href="{{ route('admin.resellers') }}"><i class="bi bi-briefcase"></i><span><strong>{{ $stats['pending_resellers'] }} compte(s) Pro à valider</strong><small>Vérifier les SIRET et ouvrir les accès</small></span><i class="bi bi-chevron-right ms-auto"></i></a></div>
    <div class="col-md-4"><a class="admin-alert-link" href="{{ route('admin.conversations.index') }}"><i class="bi bi-chat-dots"></i><span><strong>{{ $stats['unread_conversations'] }} conversation(s) à lire</strong><small>Répondre aux clients et revendeurs</small></span><i class="bi bi-chevron-right ms-auto"></i></a></div>
    <div class="col-md-4"><a class="admin-alert-link" href="{{ route('admin.data-rights.index') }}"><i class="bi bi-shield-check"></i><span><strong>{{ $stats['rights'] }} demande(s) RGPD</strong><small>Suivre les droits utilisateurs</small></span><i class="bi bi-chevron-right ms-auto"></i></a></div>
</div>

<div class="admin-surface mb-4">
    <div class="d-flex justify-content-between align-items-center"><div><h2 class="h5 mb-1">Activité des 6 derniers jours</h2><p class="small text-secondary mb-0">Commandes clients et professionnelles réunies.</p></div><strong>{{ $stats['orders'] }} + {{ $stats['professional_orders'] }} pro</strong></div>
    <div class="row g-2 mt-2">@foreach($salesByDay as $day)<div class="col-6 col-md"><div class="border rounded p-3 h-100"><small class="text-secondary">{{ $day['label'] }}</small><strong class="d-block fs-4">{{ $day['orders'] }}</strong><span class="small">{{ number_format($day['revenue'],2,',',' ') }} € payés</span></div></div>@endforeach</div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7"><div class="admin-surface table-responsive"><div class="d-flex justify-content-between"><h2 class="h5">Dernières commandes clients</h2><a href="{{ route('admin.orders') }}">Tout afficher</a></div><table class="table align-middle"><thead><tr><th>N°</th><th>Client</th><th>Paiement</th><th>Statut</th><th>Total</th></tr></thead><tbody>@forelse($orders as $order)<tr><td>{{ $order->number }}</td><td>@if($order->user)<a href="{{ route('admin.customers.show',$order->user) }}">{{ $order->user->name }}</a>@else{{ $order->shipping_name }}@endif</td><td>{{ $order->payment_status }}</td><td>{{ $order->status }}</td><td>{{ number_format($order->total,2,',',' ') }} €</td></tr>@empty<tr><td colspan="5">Aucune commande.</td></tr>@endforelse</tbody></table></div></div>
    <div class="col-xl-5"><div class="admin-surface table-responsive"><div class="d-flex justify-content-between"><h2 class="h5">Dernières commandes Pro</h2><a href="{{ route('admin.resellers') }}">Gérer</a></div><table class="table align-middle"><thead><tr><th>N°</th><th>Revendeur</th><th>Statut</th><th>Total</th></tr></thead><tbody>@forelse($professionalOrders as $order)<tr><td>{{ $order->number }}</td><td>{{ $order->user?->name }}</td><td>{{ $order->status }}</td><td>{{ number_format($order->total_ttc,2,',',' ') }} €</td></tr>@empty<tr><td colspan="4">Aucune commande Pro.</td></tr>@endforelse</tbody></table></div></div>
</div>

<div class="admin-surface"><div class="d-flex justify-content-between"><h2 class="h5">Conversations récentes</h2><a href="{{ route('admin.conversations.index') }}">Ouvrir</a></div><div class="row g-3">@forelse($conversations as $conversation)<div class="col-md-6 col-xl-4"><a class="conversation-preview" href="{{ route('admin.conversations.show',$conversation) }}"><strong>{{ $conversation->user->name }}</strong><span>{{ str($conversation->lastMessage?->body)->limit(54) }}</span></a></div>@empty<p class="text-secondary mb-0">Aucune conversation.</p>@endforelse</div></div>
@endsection
