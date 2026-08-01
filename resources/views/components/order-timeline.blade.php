@php
    $steps = ['Nouvelle' => 'Confirmée', 'En préparation' => 'Préparation', 'Expédiée' => 'Expédiée', 'Livrée' => 'Livrée'];
    $positions = array_keys($steps);
    $current = $order->status === 'Annulée' ? -1 : max(0, array_search($order->status, $positions, true));
@endphp
@if($order->status === 'Annulée')
    <div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Cette commande a été annulée.</div>
@else
<div class="order-timeline" aria-label="Suivi de commande">
    @foreach($steps as $status => $label)
    <div class="order-timeline-step {{ $loop->index <= $current ? 'active' : '' }}"><i class="bi {{ $loop->index <= $current ? 'bi-check' : 'bi-circle' }}"></i><span>{{ $label }}</span></div>
    @endforeach
</div>
@endif
