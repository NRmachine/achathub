<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\TransactionalMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()?->role === 'reseller') {
            return redirect()->route('pro.index')->with('error', 'Le paiement particulier est séparé de votre espace professionnel.');
        }
        $cart = $this->cart($request);
        abort_if($cart['items']->isEmpty(), 422, 'Votre panier est vide.');

        return view('checkout.index', [
            'cart' => $cart,
            'shippingOptions' => $this->shippingOptions($cart['subtotal']),
            'paymentOptions' => $this->paymentOptions(),
        ]);
    }

    public function store(Request $request, TransactionalMailer $mailer)
    {
        if ($request->user()?->role === 'reseller') {
            return redirect()->route('pro.index')->with('error', 'Le paiement particulier est séparé de votre espace professionnel.');
        }
        $data = $request->validate([
            'email' => ['required', 'email', 'max:160'], 'name' => ['required', 'max:120'], 'phone' => ['required', 'max:30'],
            'address' => ['required', 'max:500'], 'postal_code' => ['required', 'max:10'], 'city' => ['required', 'max:100'],
            'shipping_method' => ['required', 'in:standard,relay,express'],
            'payment_method' => ['required', Rule::in(array_keys($this->paymentOptions()))],
            'notes' => ['nullable', 'max:1000'],
        ]);
        $cart = $this->cart($request);
        abort_if($cart['items']->isEmpty(), 422, 'Votre panier est vide.');
        $shippingOption = $this->shippingOptions($cart['subtotal'])[$data['shipping_method']];

        $order = DB::transaction(function () use ($request, $data, $cart, $shippingOption) {
            foreach ($cart['items'] as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product']->id);
                abort_if($product->stock < $item['quantity'], 422, 'Stock insuffisant pour '.$product->name);
            }
            $order = Order::create([
                'number' => 'AH-'.now()->format('Y').'-'.str()->upper(str()->random(7)), 'access_token' => Str::random(48),
                'user_id' => $request->user()?->id, 'guest_email' => $data['email'], 'subtotal' => $cart['subtotal'],
                'shipping' => $shippingOption['price'], 'shipping_method' => $data['shipping_method'], 'estimated_delivery_date' => $shippingOption['date'],
                'total' => $cart['subtotal'] + $shippingOption['price'], 'payment_method' => $data['payment_method'],
                'shipping_name' => $data['name'], 'shipping_phone' => $data['phone'], 'shipping_address' => $data['address'],
                'shipping_postal_code' => $data['postal_code'], 'shipping_city' => $data['city'], 'notes' => $data['notes'] ?? null,
            ]);
            foreach ($cart['items'] as $item) {
                $product = $item['product'];
                $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'price' => $product->price, 'quantity' => $item['quantity']]);
                $product->decrement('stock', $item['quantity']);
            }
            $order->statusEvents()->create(['status' => 'Nouvelle', 'message' => 'Votre commande est confirmée.', 'happened_at' => now()]);

            return $order;
        });
        $request->session()->forget('cart');
        $mailer->orderCreated($order->load('user'));

        return $request->user() ? redirect()->route('account.order', $order)->with('success', 'Commande confirmée. Nous commençons sa préparation.') : redirect()->route('orders.guest.show', ['order' => $order->access_token]);
    }

    public function guestShow(Order $order)
    {
        return view('checkout.confirmation', ['order' => $order->load(['items', 'statusEvents'])]);
    }

    private function shippingOptions(float $subtotal): array
    {
        return [
            'standard' => ['label' => 'Livraison standard', 'price' => $subtotal >= 80 ? 0 : 4.90, 'date' => today()->addWeekdays(3)],
            'relay' => ['label' => 'Point relais', 'price' => 3.90, 'date' => today()->addWeekdays(4)],
            'express' => ['label' => 'Livraison express', 'price' => 9.90, 'date' => today()->addWeekdays(1)],
        ];
    }

    private function paymentOptions(): array
    {
        $options = [
            'livraison' => ['Paiement à la livraison', 'Réglez lors de la réception', 'bi-cash-coin'],
            'virement' => ['Virement bancaire', 'Les coordonnées seront transmises après validation', 'bi-bank'],
        ];

        if (filled(config('services.stripe.secret'))) {
            $options = ['card' => ['Carte bancaire', 'Visa, Mastercard et cartes bancaires', 'bi-credit-card'], ...$options];
        }

        if (config('commerce.mobile_payment_enabled')) {
            $options['mobile'] = ['Paiement mobile', 'Selon les moyens disponibles dans votre pays', 'bi-phone'];
        }

        return $options;
    }

    private function cart(Request $request): array
    {
        $stored = $request->session()->get('cart', []);
        $products = Product::whereIn('id', array_keys($stored))->get();
        $items = $products->map(fn ($product) => ['product' => $product, 'quantity' => $stored[$product->id], 'total' => $product->price * $stored[$product->id]]);

        return ['items' => $items, 'subtotal' => (float) $items->sum('total')];
    }
}
