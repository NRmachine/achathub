<?php

namespace App\Http\Controllers;

use App\Models\ProfessionalDisplay;
use App\Models\ProfessionalOrder;
use App\Models\ProfessionalPreorder;
use App\Models\ProfessionalProduct;
use App\Services\TransactionalMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProfessionalStoreController extends Controller
{
    private const VAT_RATE = 20;

    public function index(Request $request)
    {
        $products = ProfessionalProduct::query()
            ->where('active', true)
            ->when($request->q, fn ($query, $value) => $query->where(fn ($search) => $search->where('name', 'like', "%{$value}%")->orWhere('sku', 'like', "%{$value}%")))
            ->when($request->category, fn ($query, $value) => $query->where('category', $value))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(16)
            ->withQueryString();

        if (app()->environment('testing')) {
            Cache::store('file')->forget('professional.catalog-categories.v1');
        }

        $categories = Cache::store('file')->remember(
            'professional.catalog-categories.v1',
            now()->addMinutes(10),
            fn (): array => ProfessionalProduct::query()
                ->where('active', true)
                ->selectRaw('category, count(*) as total')
                ->groupBy('category')
                ->orderBy('category')
                ->get()
                ->map(fn (ProfessionalProduct $category): array => [
                    'category' => $category->category,
                    'total' => (int) $category->total,
                ])
                ->all(),
        );

        return view('professional.index', [
            'products' => $products,
            'categories' => collect($categories)->map(fn (array $category): object => (object) $category),
        ]);
    }

    public function displays()
    {
        return view('professional.displays', ['displays' => ProfessionalDisplay::withCount('products')->where('active', true)->orderBy('sort_order')->get()]);
    }

    public function show(ProfessionalDisplay $display)
    {
        abort_unless($display->active, 404);
        $display->load(['items.product']);

        return view('professional.show', compact('display'));
    }

    public function account(Request $request)
    {
        $ordersQuery = $request->user()->professionalOrders();

        return view('professional.account', [
            'application' => $request->user()->resellerRequest,
            'orders' => (clone $ordersQuery)->with('items')->latest()->paginate(20),
            'preorders' => $request->user()->professionalPreorders()->with('product')->latest()->limit(30)->get(),
            'stats' => [
                'orders' => (clone $ordersQuery)->count(),
                'pending' => (clone $ordersQuery)->whereNotIn('status', ['Livrée', 'Annulée'])->count(),
                'paid' => (float) (clone $ordersQuery)->where('payment_status', 'Payé')->sum('total_ttc'),
                'preorders' => $request->user()->professionalPreorders()->whereIn('status', ['Nouvelle', 'En cours', 'Validée'])->count(),
            ],
        ]);
    }

    public function invoice(Request $request, ProfessionalOrder $professionalOrder)
    {
        abort_unless($professionalOrder->user_id === $request->user()->id, 403);

        return view('professional.invoice', [
            'order' => $professionalOrder->load(['items', 'resellerRequest']),
        ]);
    }

    public function support()
    {
        return view('professional.support');
    }

    public function cart(Request $request)
    {
        return view('professional.cart', $this->cartData($request));
    }

    public function addDisplay(Request $request, ProfessionalDisplay $display)
    {
        abort_unless($display->active, 404);
        $quantity = $request->validate(['quantity' => ['nullable', 'integer', 'min:1', 'max:20']])['quantity'] ?? 1;
        $cart = $this->normalizedCart($request);
        $cart['displays'][$display->id] = min(20, ($cart['displays'][$display->id] ?? 0) + $quantity);
        $request->session()->put('professional_cart', $cart);

        return redirect()->route('pro.cart')->with('success', 'Présentoir ajouté au panier professionnel.');
    }

    public function updateDisplay(Request $request, ProfessionalDisplay $display)
    {
        $quantity = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:20']])['quantity'];
        $cart = $this->normalizedCart($request);
        if (isset($cart['displays'][$display->id])) {
            $cart['displays'][$display->id] = $quantity;
            $request->session()->put('professional_cart', $cart);
        }

        return back()->with('success', 'Quantité mise à jour.');
    }

    public function removeDisplay(Request $request, ProfessionalDisplay $display)
    {
        $cart = $this->normalizedCart($request);
        unset($cart['displays'][$display->id]);
        $request->session()->put('professional_cart', $cart);

        return back()->with('success', 'Présentoir retiré.');
    }

    public function addProduct(Request $request, ProfessionalProduct $product)
    {
        abort_unless($product->active, 404);
        abort_if($product->stock < $product->minimum_order_quantity, 422, 'Ce produit est temporairement indisponible à la commande.');

        $quantity = $request->validate([
            'quantity' => ['required', 'integer', 'min:'.$product->minimum_order_quantity, 'max:'.$product->stock],
        ])['quantity'];
        $cart = $this->normalizedCart($request);
        $cart['products'][$product->id] = $quantity;
        $request->session()->put('professional_cart', $cart);

        return redirect()->route('pro.cart')->with('success', 'Produit ajouté au panier professionnel.');
    }

    public function updateProduct(Request $request, ProfessionalProduct $product)
    {
        abort_unless($product->active, 404);
        $quantity = $request->validate([
            'quantity' => ['required', 'integer', 'min:'.$product->minimum_order_quantity, 'max:'.$product->stock],
        ])['quantity'];
        $cart = $this->normalizedCart($request);

        if (isset($cart['products'][$product->id])) {
            $cart['products'][$product->id] = $quantity;
            $request->session()->put('professional_cart', $cart);
        }

        return back()->with('success', 'Quantité mise à jour.');
    }

    public function removeProduct(Request $request, ProfessionalProduct $product)
    {
        $cart = $this->normalizedCart($request);
        unset($cart['products'][$product->id]);
        $request->session()->put('professional_cart', $cart);

        return back()->with('success', 'Produit retiré.');
    }

    public function preorder(Request $request, ProfessionalProduct $product)
    {
        abort_unless($product->active, 404);
        $existing = ProfessionalPreorder::where('user_id', $request->user()->id)
            ->where('professional_product_id', $product->id)
            ->whereIn('status', ['Nouvelle', 'En cours', 'Validée'])
            ->first();
        if ($existing) {
            return back()->with('success', "Votre précommande {$existing->number} pour ce produit est déjà en cours de traitement.");
        }

        $preorder = ProfessionalPreorder::create([
            'number' => 'PRE-'.now()->format('ymd').'-'.str()->upper(str()->random(6)),
            'user_id' => $request->user()->id,
            'reseller_request_id' => $request->user()->resellerRequest->id,
            'professional_product_id' => $product->id,
            'status' => 'Nouvelle',
        ]);

        return redirect()->route('pro.account')->with('success', "Précommande {$preorder->number} transmise. Notre équipe vous contactera pour définir le volume, le délai et le tarif final.");
    }

    public function destroyPreorder(Request $request, ProfessionalPreorder $professionalPreorder)
    {
        abort_unless($professionalPreorder->user_id === $request->user()->id, 403);
        abort_unless($professionalPreorder->canBeDeletedBy($request->user()), 422, 'Une précommande validée ou terminée ne peut plus être supprimée.');

        $number = $professionalPreorder->number;
        $professionalPreorder->delete();

        return back()->with('success', "Précommande {$number} supprimée.");
    }

    public function checkout(Request $request)
    {
        $data = $this->cartData($request);
        abort_if($data['itemCount'] === 0, 422, 'Votre panier professionnel est vide.');

        return view('professional.checkout', $data + ['application' => $request->user()->resellerRequest]);
    }

    public function order(Request $request, TransactionalMailer $mailer)
    {
        $cartData = $this->cartData($request);
        abort_if($cartData['itemCount'] === 0, 422, 'Votre panier professionnel est vide.');
        $data = $request->validate([
            'contact_name' => ['required', 'max:160'],
            'phone' => ['required', 'max:40'],
            'address' => ['required', 'max:500'],
            'city' => ['required', 'max:120'],
            'payment_method' => ['required', 'in:Espèces à la livraison,Carte bancaire à la livraison,Virement bancaire'],
            'notes' => ['nullable', 'max:2000'],
        ]);

        $order = DB::transaction(function () use ($request, $cartData, $data) {
            $stockRequirements = [];
            foreach ($cartData['displays'] as $display) {
                foreach ($display->items as $item) {
                    $stockRequirements[$item->professional_product_id] = ($stockRequirements[$item->professional_product_id] ?? 0) + ($item->quantity * $display->cart_quantity);
                }
            }
            foreach ($cartData['products'] as $product) {
                $stockRequirements[$product->id] = ($stockRequirements[$product->id] ?? 0) + $product->cart_quantity;
            }

            foreach ($stockRequirements as $productId => $required) {
                $product = ProfessionalProduct::lockForUpdate()->findOrFail($productId);
                abort_if($product->stock < $required, 422, "Stock insuffisant pour {$product->name}.");
                $product->decrement('stock', $required);
            }

            $order = ProfessionalOrder::create($data + [
                'number' => 'PRO-'.now()->format('ymd').'-'.str()->upper(str()->random(6)),
                'user_id' => $request->user()->id,
                'reseller_request_id' => $request->user()->resellerRequest->id,
                'subtotal_ht' => $cartData['subtotalHt'],
                'vat_amount' => $cartData['vatAmount'],
                'total_ttc' => $cartData['totalTtc'],
                'status' => 'Confirmée',
                'payment_status' => 'En attente',
            ]);

            foreach ($cartData['displays'] as $display) {
                $order->items()->create([
                    'professional_display_id' => $display->id,
                    'name' => $display->name,
                    'price_ht' => $display->wholesale_price_ht,
                    'quantity' => $display->cart_quantity,
                    'vat_rate' => $display->vat_rate,
                ]);
            }
            foreach ($cartData['products'] as $product) {
                $order->items()->create([
                    'professional_product_id' => $product->id,
                    'name' => $product->name,
                    'price_ht' => $product->wholesale_price_ht,
                    'quantity' => $product->cart_quantity,
                    'vat_rate' => self::VAT_RATE,
                ]);
            }

            return $order;
        });

        $request->session()->forget('professional_cart');
        $mailer->professionalOrderCreated($order->load('user'));

        return redirect()->route('pro.account')->with('success', "Commande {$order->number} confirmée. Paiement choisi : {$order->payment_method}.");
    }

    private function normalizedCart(Request $request): array
    {
        $cart = $request->session()->get('professional_cart', []);
        if (isset($cart['displays']) || isset($cart['products'])) {
            return [
                'displays' => $cart['displays'] ?? [],
                'products' => $cart['products'] ?? [],
            ];
        }

        return ['displays' => $cart, 'products' => []];
    }

    private function cartData(Request $request): array
    {
        $cart = $this->normalizedCart($request);
        $displays = ProfessionalDisplay::with('items')->whereIn('id', array_keys($cart['displays']))->where('active', true)->orderBy('sort_order')->get();
        $displays->each(fn ($display) => $display->cart_quantity = (int) $cart['displays'][$display->id]);
        $products = ProfessionalProduct::whereIn('id', array_keys($cart['products']))->where('active', true)->orderBy('name')->get();
        $products->each(fn ($product) => $product->cart_quantity = (int) $cart['products'][$product->id]);

        $displaySubtotal = $displays->sum(fn ($display) => (float) $display->wholesale_price_ht * $display->cart_quantity);
        $productSubtotal = $products->sum(fn ($product) => (float) $product->wholesale_price_ht * $product->cart_quantity);
        $displayVat = $displays->sum(fn ($display) => (float) $display->wholesale_price_ht * $display->cart_quantity * ((float) $display->vat_rate / 100));
        $productVat = $productSubtotal * (self::VAT_RATE / 100);
        $subtotalHt = round($displaySubtotal + $productSubtotal, 2);
        $vatAmount = round($displayVat + $productVat, 2);
        $itemCount = $displays->sum('cart_quantity') + $products->sum('cart_quantity');

        return compact('displays', 'products', 'subtotalHt', 'vatAmount', 'itemCount') + [
            'totalTtc' => round($subtotalHt + $vatAmount, 2),
            'vatRate' => self::VAT_RATE,
        ];
    }
}
