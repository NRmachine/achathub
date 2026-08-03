<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()?->role === 'reseller') {
            return redirect()->route('pro.index')->with('error', 'Le panier particulier est séparé de votre espace professionnel.');
        }

        return view('cart.index', ['cart' => $this->hydrate($request)]);
    }

    public function add(Request $request, Product $product)
    {
        if ($request->user()?->role === 'reseller') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Utilisez votre espace AchatHub Pro pour vos demandes professionnelles.'], 403);
            }

            return redirect()->route('pro.index')->with('error', 'Le panier particulier est séparé de votre espace professionnel.');
        }

        abort_if(! $product->active || $product->stock < 1, 422, 'Produit indisponible');
        $data = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.$product->stock],
        ], ['quantity.max' => 'La quantité demandée dépasse le stock disponible.']);
        $cart = $request->session()->get('cart', []);
        $cart[$product->id] = min(($cart[$product->id] ?? 0) + (int) ($data['quantity'] ?? 1), $product->stock);
        $request->session()->put('cart', $cart);

        if ($request->expectsJson()) {
            $subtotal = Product::whereIn('id', array_keys($cart))->get()->sum(fn ($item) => $item->price * ($cart[$item->id] ?? 0));

            return response()->json([
                'message' => 'Produit ajouté au panier.',
                'cart_count' => array_sum($cart),
                'item_quantity' => $cart[$product->id],
                'subtotal' => number_format($subtotal, 2, ',', ' ').' €',
                'product' => [
                    'name' => $product->name,
                    'price' => number_format($product->price, 2, ',', ' ').' €',
                    'image' => $product->image ?: asset('assets/category-accessoires.webp').'?v=20260802b',
                    'url' => route('products.show', $product),
                ],
            ]);
        }

        return back()->with('success', 'Produit ajouté au panier.');
    }

    public function update(Request $request, Product $product)
    {
        if ($request->user()?->role === 'reseller') {
            return redirect()->route('pro.index')->with('error', 'Le panier particulier est séparé de votre espace professionnel.');
        }

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$product->stock],
        ], [
            'quantity.min' => 'Utilisez le bouton Supprimer pour retirer un article.',
            'quantity.max' => 'La quantité demandée dépasse le stock disponible.',
        ]);
        $quantity = (int) $data['quantity'];
        $cart = $request->session()->get('cart', []);
        $cart[$product->id] = $quantity;
        $request->session()->put('cart', $cart);

        return back()->with('success', 'Panier mis à jour.');
    }

    public function buyNow(Request $request, Product $product)
    {
        if ($request->user()?->role === 'reseller') {
            return redirect()->route('pro.index')->with('error', 'Les achats particuliers sont séparés de votre espace professionnel.');
        }
        abort_if(! $product->active || $product->stock < 1, 422, 'Produit indisponible');
        $data = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.$product->stock],
        ], ['quantity.max' => 'La quantité demandée dépasse le stock disponible.']);
        $cart = $request->session()->get('cart', []);
        $cart[$product->id] = (int) ($data['quantity'] ?? 1);
        $request->session()->put('cart', $cart);

        return redirect()->route('checkout.index');
    }

    public function remove(Request $request, Product $product)
    {
        if ($request->user()?->role === 'reseller') {
            return redirect()->route('pro.index')->with('error', 'Le panier particulier est séparé de votre espace professionnel.');
        }

        $cart = $request->session()->get('cart', []);
        unset($cart[$product->id]);
        $request->session()->put('cart', $cart);

        return back()->with('success', 'Produit retiré.');
    }

    private function hydrate(Request $request): array
    {
        $stored = $request->session()->get('cart', []);
        $products = Product::whereIn('id', array_keys($stored))->get();
        $items = $products->map(fn ($p) => ['product' => $p, 'quantity' => $stored[$p->id], 'total' => $p->price * $stored[$p->id]]);

        return ['items' => $items, 'subtotal' => $items->sum('total'), 'count' => $items->sum('quantity')];
    }
}
