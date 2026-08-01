<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\DataRightsRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProfessionalDisplay;
use App\Models\ProfessionalOrder;
use App\Models\ProfessionalPreorder;
use App\Models\ProfessionalProduct;
use App\Models\ResellerRequest;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\TransactionalMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function index(TransactionalMailer $mailer)
    {
        $classicRevenue = (float) Order::where('payment_status', 'Payé')->sum('total');
        $professionalRevenue = (float) ProfessionalOrder::where('payment_status', 'Payé')->sum('total_ttc');
        $recentClassic = Order::query()->where('created_at', '>=', today()->subDays(6))->get(['created_at', 'total', 'payment_status']);
        $recentProfessional = ProfessionalOrder::query()->where('created_at', '>=', today()->subDays(6))->get(['created_at', 'total_ttc', 'payment_status']);
        $salesByDay = collect(range(6, 0))->map(function (int $daysAgo) use ($recentClassic, $recentProfessional): array {
            $date = today()->subDays($daysAgo);

            return [
                'label' => $date->translatedFormat('D d/m'),
                'orders' => $recentClassic->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])->count()
                    + $recentProfessional->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])->count(),
                'revenue' => (float) $recentClassic->where('payment_status', 'Payé')
                    ->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                    ->sum('total')
                    + (float) $recentProfessional->where('payment_status', 'Payé')
                        ->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                        ->sum('total_ttc'),
            ];
        });

        return view('admin.index', [
            'stats' => [
                'products' => Product::count(), 'orders' => Order::count(), 'professional_orders' => ProfessionalOrder::count(),
                'customers' => User::where('role', '!=', 'admin')->count(),
                'revenue' => $classicRevenue,
                'professional_revenue' => $professionalRevenue,
                'unread_conversations' => Conversation::whereNull('admin_read_at')->whereNotNull('last_message_at')->count(),
                'rights' => DataRightsRequest::whereIn('status', ['Nouvelle', 'En cours'])->count(),
                'pending_orders' => Order::whereIn('status', ['Nouvelle', 'En préparation'])->count(),
                'pending_professional_orders' => ProfessionalOrder::whereIn('status', ['Nouvelle', 'Confirmée', 'En préparation'])->count(),
                'pending_resellers' => ResellerRequest::where('status', 'En attente')->count(),
                'low_stock' => Product::where('active', true)->where('stock', '<=', 5)->count(),
                'unpaid' => Order::where('payment_status', 'En attente')->count() + ProfessionalOrder::where('payment_status', 'En attente')->count(),
                'month_revenue' => (float) Order::where('payment_status', 'Payé')->where('created_at', '>=', now()->startOfMonth())->sum('total')
                    + (float) ProfessionalOrder::where('payment_status', 'Payé')->where('created_at', '>=', now()->startOfMonth())->sum('total_ttc'),
            ],
            'orders' => Order::with('user')->latest()->limit(8)->get(),
            'professionalOrders' => ProfessionalOrder::with('user')->latest()->limit(6)->get(),
            'conversations' => Conversation::with(['user', 'lastMessage'])->orderByDesc('last_message_at')->limit(5)->get(),
            'salesByDay' => $salesByDay,
            'mailReady' => $mailer->isConfigured(),
        ]);
    }

    public function products(Request $request)
    {
        $selectedCategory = $request->category ? Category::with('children.children')->find($request->category) : null;
        $products = Product::with('category')
            ->when($request->q, fn ($q, $v) => $q->where(fn ($x) => $x->where('name', 'like', "%$v%")->orWhere('sku', 'like', "%$v%")))
            ->when($request->category, fn ($q) => $selectedCategory
                ? $q->whereIn('category_id', $selectedCategory->catalogIds())
                : $q->whereRaw('1 = 0'))
            ->when($request->status === 'active', fn ($q) => $q->where('active', true))
            ->when($request->status === 'hidden', fn ($q) => $q->where('active', false))
            ->when($request->status === 'featured', fn ($q) => $q->where('featured', true))
            ->orderByDesc('featured')->orderBy('featured_order')->latest()->paginate(30)->withQueryString();

        return view('admin.products', ['products' => $products, 'categories' => $this->categories()]);
    }

    public function productForm(?Product $product = null)
    {
        return view('admin.product-form', ['product' => $product, 'categories' => $this->categories()]);
    }

    public function saveProduct(Request $request, ?Product $product = null)
    {
        $data = $request->validate(['category_id' => ['required', 'exists:categories,id'], 'name' => ['required', 'max:255'], 'sku' => ['required', 'max:100', 'unique:products,sku,'.($product?->id ?? 'NULL')], 'brand' => ['nullable', 'max:100'], 'model' => ['nullable', 'max:160'], 'family' => ['nullable', 'max:160'], 'subcategory' => ['nullable', 'max:160'], 'price' => ['required', 'numeric', 'min:0'], 'old_price' => ['nullable', 'numeric', 'min:0'], 'discount' => ['nullable', 'integer', 'min:0', 'max:100'], 'stock' => ['required', 'integer', 'min:0'], 'condition' => ['required', 'max:80'], 'tag' => ['nullable', 'max:80'], 'image' => ['nullable', 'url'], 'images_text' => ['nullable', 'string', 'max:10000'], 'description' => ['nullable', 'max:5000'], 'features_text' => ['nullable', 'string', 'max:5000'], 'active' => ['nullable', 'boolean'], 'featured' => ['nullable', 'boolean'], 'featured_order' => ['nullable', 'integer', 'min:0', 'max:999']]);
        $data['slug'] = Str::slug($data['name'].'-'.$data['sku']);
        $data['active'] = $request->boolean('active');
        $data['featured'] = $request->boolean('featured');
        $data['discount'] = $data['discount'] ?? 0;
        $data['featured_order'] = $data['featured_order'] ?? 0;
        $data['images'] = collect(preg_split('/\R/', $data['images_text'] ?? ''))->map(fn ($v) => trim($v))->filter()->values()->all();
        $data['features'] = collect(preg_split('/\R/', $data['features_text'] ?? ''))->map(fn ($v) => trim($v))->filter()->values()->all();
        unset($data['images_text'], $data['features_text']);
        $product ? $product->update($data) : Product::create($data);

        return redirect()->route('admin.products')->with('success', 'Produit enregistré.');
    }

    public function deleteProduct(Product $product)
    {
        if ($product->orderItems()->exists()) {
            $product->update(['active' => false, 'featured' => false]);

            return back()->with('success', 'Produit archivé afin de préserver l’historique des commandes.');
        }
        $product->delete();

        return back()->with('success', 'Produit supprimé.');
    }

    public function orders()
    {
        return view('admin.orders', ['orders' => Order::with('user')->latest()->paginate(30)]);
    }

    public function updateOrder(Request $request, Order $order, TransactionalMailer $mailer)
    {
        $data = $request->validate([
            'status' => ['required', 'in:Nouvelle,En préparation,Expédiée,Livrée,Annulée'],
            'payment_status' => ['required', 'in:En attente,Payé,Remboursé'],
            'carrier' => ['nullable', 'max:100'],
            'tracking_number' => ['nullable', 'max:150'],
        ]);
        $statusChanged = $order->status !== $data['status'];
        if ($data['status'] === 'Expédiée' && ! $order->shipped_at) {
            $data['shipped_at'] = now();
        }
        if ($data['status'] === 'Livrée' && ! $order->delivered_at) {
            $data['delivered_at'] = now();
        }
        $order->update($data);
        if ($statusChanged) {
            $messages = ['En préparation' => 'Votre commande est en cours de préparation.', 'Expédiée' => 'Votre commande a quitté notre entrepôt.', 'Livrée' => 'Votre commande a été livrée.', 'Annulée' => 'Votre commande a été annulée.'];
            $order->statusEvents()->create(['status' => $data['status'], 'message' => $messages[$data['status']] ?? null, 'happened_at' => now()]);
        }
        if ($statusChanged || $order->wasChanged('payment_status')) {
            $mailer->orderUpdated($order->load('user'));
        }

        return back()->with('success', 'Commande mise à jour.');
    }

    public function customers(Request $request)
    {
        return view('admin.customers', ['customers' => User::where('role', '!=', 'admin')->withCount(['orders', 'professionalOrders'])
            ->when($request->q, fn ($q, $v) => $q->where(fn ($x) => $x->where('name', 'like', "%$v%")->orWhere('email', 'like', "%$v%")))
            ->when($request->role, fn ($q, $v) => $q->where('role', $v))->latest()->paginate(30)->withQueryString()]);
    }

    public function toggleCustomer(User $user)
    {
        abort_if($user->role === 'admin', 422);
        $user->update(['blocked' => ! $user->blocked]);

        return back()->with('success', 'Accès client mis à jour.');
    }

    public function messages()
    {
        return view('admin.messages', ['messages' => SupportMessage::latest()->paginate(30)]);
    }

    public function resolveMessage(SupportMessage $message)
    {
        $message->update(['status' => 'Traité']);

        return back()->with('success', 'Message marqué comme traité.');
    }

    public function resellers(Request $request)
    {
        return view('admin.resellers', [
            'requests' => ResellerRequest::with(['user', 'reviewer'])->latest()->paginate(25),
            'displays' => ProfessionalDisplay::withCount('products')->orderBy('sort_order')->get(),
            'orders' => ProfessionalOrder::with(['user', 'items'])->latest()->limit(30)->get(),
            'preorders' => ProfessionalPreorder::with(['user', 'product'])->latest()->limit(50)->get(),
            'professionalProducts' => ProfessionalProduct::query()
                ->when($request->pro_q, fn ($query, $value) => $query->where(fn ($search) => $search
                    ->where('name', 'like', "%{$value}%")
                    ->orWhere('sku', 'like', "%{$value}%")
                    ->orWhere('category', 'like', "%{$value}%")))
                ->orderBy('category')
                ->orderBy('name')
                ->paginate(25, ['*'], 'pro_products_page')
                ->withQueryString(),
        ]);
    }

    public function reviewReseller(Request $request, ResellerRequest $resellerRequest, TransactionalMailer $mailer)
    {
        $data = $request->validate([
            'status' => ['required', 'in:Approuvée,Refusée,Suspendue'],
            'admin_notes' => ['nullable', 'max:2000'],
        ]);
        abort_unless($resellerRequest->user, 422, 'Cette ancienne demande n’est reliée à aucun compte client.');

        DB::transaction(function () use ($request, $resellerRequest, $data) {
            $resellerRequest->update($data + [
                'reviewed_at' => now(),
                'approved_at' => $data['status'] === 'Approuvée' ? now() : null,
                'reviewed_by' => $request->user()->id,
            ]);
            $resellerRequest->user->update(['role' => $data['status'] === 'Approuvée' ? 'reseller' : 'customer']);
        });
        $mailer->professionalApplicationReviewed($resellerRequest->fresh(['user']));

        return back()->with('success', 'Statut du revendeur mis à jour.');
    }

    public function updateDisplay(Request $request, ProfessionalDisplay $display)
    {
        $data = $request->validate(['wholesale_price_ht' => ['required', 'numeric', 'min:0'], 'active' => ['nullable', 'boolean']]);
        $display->update(['wholesale_price_ht' => $data['wholesale_price_ht'], 'active' => $request->boolean('active')]);

        return back()->with('success', 'Offre professionnelle mise à jour.');
    }

    public function updateProfessionalProduct(Request $request, ProfessionalProduct $professionalProduct)
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:120'],
            'wholesale_price_ht' => ['required', 'numeric', 'min:0'],
            'minimum_order_quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'active' => ['nullable', 'boolean'],
        ]);
        $professionalProduct->update($data + ['active' => $request->boolean('active')]);

        return back()->with('success', 'Produit professionnel mis à jour.');
    }

    public function updateProfessionalOrder(Request $request, ProfessionalOrder $professionalOrder, TransactionalMailer $mailer)
    {
        $data = $request->validate([
            'status' => ['required', 'in:Nouvelle,Confirmée,En préparation,Expédiée,Livrée,Annulée'],
            'payment_status' => ['required', 'in:En attente,Payé,Remboursé'],
        ]);
        $professionalOrder->update($data);
        $mailer->professionalOrderUpdated($professionalOrder->load('user'));

        return back()->with('success', 'Commande professionnelle mise à jour.');
    }

    public function updateProfessionalPreorder(Request $request, ProfessionalPreorder $professionalPreorder)
    {
        $data = $request->validate([
            'status' => ['required', 'in:Nouvelle,En cours,Validée,Refusée,Terminée'],
            'admin_notes' => ['nullable', 'max:2000'],
        ]);
        $professionalPreorder->update($data + ['reviewed_at' => now(), 'reviewed_by' => $request->user()->id]);

        return back()->with('success', 'Précommande mise à jour.');
    }

    private function categories()
    {
        return Category::query()->with('parent')->get()
            ->sortBy(fn (Category $category) => ($category->parent?->name ?? $category->name).'|'.($category->parent ? $category->name : ''))
            ->values();
    }
}
