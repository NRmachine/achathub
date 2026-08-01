<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function create(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->status === 'Livrée', 422, 'Un retour peut être demandé après la livraison.');

        return view('account.returns.create', ['order' => $order->load(['items', 'returns.items'])]);
    }

    public function store(Request $request, Order $order)
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->status === 'Livrée', 422, 'Un retour peut être demandé après la livraison.');
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'in:Ne convient pas,Produit endommagé,Produit incorrect,Produit défectueux,Autre'],
            'solution' => ['required', 'in:Remboursement,Échange,Avoir'],
            'return_method' => ['required', 'in:Point relais,Envoi postal,Dépôt en boutique'],
            'details' => ['nullable', 'max:1500'],
        ]);

        $selectedItems = collect($data['items'])->filter(fn ($quantity) => (int) $quantity > 0);
        abort_if($selectedItems->isEmpty(), 422, 'Sélectionnez au moins un article.');
        $orderItems = $order->items()->whereIn('id', $selectedItems->keys())->get()->keyBy('id');
        abort_unless($orderItems->count() === $selectedItems->count(), 403);
        foreach ($selectedItems as $itemId => $quantity) {
            abort_if((int) $quantity > $orderItems[$itemId]->quantity, 422, 'La quantité retournée est trop élevée.');
        }

        $return = DB::transaction(function () use ($request, $order, $data, $selectedItems) {
            $return = ProductReturn::create([
                'number' => 'RET-'.now()->format('Y').'-'.str()->upper(str()->random(7)),
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'reason' => $data['reason'],
                'solution' => $data['solution'],
                'return_method' => $data['return_method'],
                'details' => $data['details'] ?? null,
            ]);
            foreach ($selectedItems as $itemId => $quantity) {
                $return->items()->create(['order_item_id' => $itemId, 'quantity' => $quantity]);
            }

            return $return;
        });

        return redirect()->route('account.returns.show', $return)->with('success', 'Votre demande de retour a bien été enregistrée.');
    }

    public function show(Request $request, ProductReturn $productReturn)
    {
        abort_unless($productReturn->user_id === $request->user()->id || $request->user()->role === 'admin', 403);

        return view('account.returns.show', ['return' => $productReturn->load(['order', 'items.orderItem'])]);
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 403);
    }
}
