@extends('layouts.pro')
@section('title', 'Commandes et compte - AchatHub Pro')
@section('pro-content')
<div class="pro-page-head">
    <div><span class="badge text-bg-success mb-2">Compte professionnel actif</span><h1>{{ $application->business_name }}</h1><p>{{ $application->formula }} · {{ $application->city }} · Client depuis le {{ $application->approved_at?->format('d/m/Y') }}</p></div>
</div>

@unless(auth()->user()->hasVerifiedEmail())
<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-3">
    <span><strong>E-mail professionnel non confirmé.</strong> Confirmez-le pour sécuriser les factures et les notifications.</span>
    <form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn btn-sm btn-dark">Renvoyer le lien</button></form>
</div>
@endunless

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="bg-white border p-3 h-100"><small class="text-secondary">COMMANDES PRO</small><strong class="d-block fs-2">{{ $stats['orders'] }}</strong></div></div>
    <div class="col-6 col-xl-3"><div class="bg-white border p-3 h-100"><small class="text-secondary">EN COURS</small><strong class="d-block fs-2">{{ $stats['pending'] }}</strong></div></div>
    <div class="col-6 col-xl-3"><div class="bg-white border p-3 h-100"><small class="text-secondary">TOTAL PAYÉ TTC</small><strong class="d-block fs-2">{{ number_format($stats['paid'],2,',',' ') }} €</strong></div></div>
    <div class="col-6 col-xl-3"><div class="bg-white border p-3 h-100"><small class="text-secondary">PRÉCOMMANDES ACTIVES</small><strong class="d-block fs-2">{{ $stats['preorders'] }}</strong></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="bg-white border p-3 h-100"><small class="text-secondary">RESPONSABLE</small><strong class="d-block mt-1">{{ $application->manager_name }}</strong><span>{{ $application->phone }}</span></div></div>
    <div class="col-md-4"><div class="bg-white border p-3 h-100"><small class="text-secondary">ADRESSE DE LIVRAISON</small><strong class="d-block mt-1">{{ $application->address }}</strong><span>{{ $application->city }}</span></div></div>
    <div class="col-md-4"><div class="bg-white border p-3 h-100"><small class="text-secondary">BESOIN D’AIDE ?</small><strong class="d-block mt-1">Support professionnel</strong><a href="{{ route('pro.support') }}">Envoyer un message</a></div></div>
</div>

<div class="d-flex justify-content-between align-items-center gap-3 mb-2">
    <h2 class="h5 fw-bold mb-0">Mes précommandes de produits</h2>
    <a class="btn btn-sm btn-outline-dark" href="{{ route('pro.index') }}">Nouvelle précommande</a>
</div>

@if($preorders->isEmpty())
    <div class="bg-white border p-4 mb-4 text-secondary">Aucune précommande. Le bouton « Précommander » du catalogue permet de transmettre une demande sans paiement.</div>
@else
    <div class="table-responsive bg-white border mb-4 d-none d-md-block">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Référence</th><th>Produit demandé</th><th>Date</th><th>Statut</th><th>Réponse</th><th>Action</th></tr></thead>
            <tbody>
            @foreach($preorders as $preorder)
                <tr>
                    <td class="fw-bold">{{ $preorder->number }}</td>
                    <td><div class="d-flex align-items-center gap-2"><img src="{{ $preorder->product->image }}" width="44" height="44" style="object-fit:contain" alt=""><span>{{ $preorder->product->name }}<small class="d-block text-secondary">{{ $preorder->product->sku }}</small></span></div></td>
                    <td>{{ $preorder->created_at->format('d/m/Y') }}</td>
                    <td><span class="badge {{ $preorder->status_badge }}">{{ $preorder->status_label }}</span></td>
                    <td>{{ $preorder->admin_notes ?: 'Notre équipe vous contactera.' }}</td>
                    <td><span class="small text-secondary">Historique permanent</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="pro-preorder-list d-md-none mb-4">
        @foreach($preorders as $preorder)
            <article class="pro-preorder-item">
                <div class="d-flex justify-content-between align-items-start gap-2"><div><small class="text-secondary">{{ $preorder->created_at->format('d/m/Y') }}</small><strong class="d-block">{{ $preorder->number }}</strong></div><span class="badge {{ $preorder->status_badge }}">{{ $preorder->status_label }}</span></div>
                <div class="pro-preorder-product"><img src="{{ $preorder->product->image }}" alt=""><div><strong>{{ $preorder->product->name }}</strong><small>{{ $preorder->product->sku }}</small></div></div>
                <p class="pro-preorder-response">{{ $preorder->admin_notes ?: 'Notre équipe vous contactera pour la suite.' }}</p>
                <div class="small text-secondary"><i class="bi bi-lock me-1"></i>Cette demande reste dans votre historique. Contactez-nous pour l’annuler.</div>
            </article>
        @endforeach
    </div>
@endif

<h2 class="h5 fw-bold">Mes commandes professionnelles</h2>
@if($orders->isEmpty())
    <div class="bg-white border p-5 text-center"><p class="text-secondary">Aucune commande professionnelle.</p><a class="pro-primary-btn btn" href="{{ route('pro.displays') }}">Voir les présentoirs</a></div>
@else
    <div class="table-responsive bg-white border"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Référence</th><th>Date</th><th>Articles</th><th>Paiement</th><th>Total TTC</th><th>Statut</th><th></th></tr></thead><tbody>@foreach($orders as $order)<tr><td class="fw-bold">{{ $order->number }}</td><td>{{ $order->created_at->format('d/m/Y') }}</td><td>{{ $order->items->sum('quantity') }}</td><td><span class="d-block">{{ $order->payment_method }}</span><small class="text-secondary">{{ $order->payment_status }}</small></td><td class="fw-bold">{{ number_format($order->total_ttc,2,',',' ') }} €</td><td><span class="badge text-bg-light">{{ $order->status }}</span></td><td><a class="btn btn-sm btn-outline-dark" target="_blank" href="{{ route('pro.invoice',$order) }}"><i class="bi bi-receipt"></i> Facture</a></td></tr>@endforeach</tbody></table></div><div class="mt-3">{{ $orders->links() }}</div>
@endif
@endsection
