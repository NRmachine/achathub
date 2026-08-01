<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $ordersQuery = $request->user()->orders();

        return view('account.index', [
            'orders' => (clone $ordersQuery)->latest()->limit(5)->get(),
            'stats' => [
                'orders' => (clone $ordersQuery)->count(),
                'pending' => (clone $ordersQuery)->whereNotIn('status', ['Livrée', 'Annulée'])->count(),
                'spent' => (float) (clone $ordersQuery)->where('payment_status', 'Payé')->sum('total'),
                'wishlist' => $request->user()->wishlistItems()->count(),
            ],
        ]);
    }

    public function orders(Request $request)
    {
        return view('account.orders', ['orders' => $request->user()->orders()->with('items')->latest()->paginate(20)]);
    }

    public function order(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->role === 'admin', 403);

        return view('account.order', ['order' => $order->load(['items', 'statusEvents', 'returns.items'])]);
    }

    public function invoice(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return view('account.invoice', ['order' => $order->load('items')]);
    }

    public function wishlist(Request $request)
    {
        return view('account.wishlist', ['items' => $request->user()->wishlistItems()->with('product')->latest()->get()]);
    }

    public function toggleWishlist(Request $request, Product $product)
    {
        $item = WishlistItem::where(['user_id' => $request->user()->id, 'product_id' => $product->id])->first();
        if ($item) {
            $item->delete();
        } else {
            WishlistItem::create(['user_id' => $request->user()->id, 'product_id' => $product->id]);
        }

        return back()->with('success', $item ? 'Retiré des favoris.' : 'Ajouté aux favoris.');
    }

    public function settings(Request $request)
    {
        return view('account.settings');
    }

    public function update(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'max:120'], 'phone' => ['nullable', 'max:30'], 'address' => ['nullable', 'max:500']]);
        $request->user()->update($data);

        return back()->with('success', 'Profil mis à jour.');
    }
}
