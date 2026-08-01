<?php

namespace App\Http\Controllers;

use App\Jobs\CategorizeSupplierProducts;
use App\Jobs\CrawlSupplierCatalog;
use App\Jobs\DiscoverSupplierProducts;
use App\Jobs\RefreshSupplierCatalog;
use App\Jobs\SyncSupplierStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierCatalogNode;
use App\Models\SupplierProduct;
use App\Models\SupplierStockChange;
use App\Models\SupplierSyncRun;
use App\Services\Suppliers\SupplierSyncService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminSupplierController extends Controller
{
    public function index(Request $request)
    {
        $products = SupplierProduct::query()
            ->with(['product', 'suggestedProduct', 'suggestedCategory.parent', 'catalogNode'])
            ->withCount('catalogNodes')
            ->when($request->q, function ($query, $value) {
                $query->where(function ($nested) use ($value) {
                    $nested->where('name', 'like', "%{$value}%")
                        ->orWhere('variant_name', 'like', "%{$value}%")
                        ->orWhere('supplier_reference', 'like', "%{$value}%");
                });
            })
            ->when($request->status === 'mapped', fn ($query) => $query->whereNotNull('product_id'))
            ->when($request->status === 'unmapped', fn ($query) => $query->whereNull('product_id'))
            ->when($request->status === 'ready', fn ($query) => $query->whereNull('product_id')->where('available', true))
            ->when($request->status === 'syncing', fn ($query) => $query->where('sync_stock', true))
            ->when($request->status === 'out', fn ($query) => $query->where('supplier_stock', 0))
            ->orderByDesc('last_synced_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.supplier', [
            'products' => $products,
            'runs' => SupplierSyncRun::latest()->limit(12)->get(),
            'changes' => SupplierStockChange::with('supplierProduct')->latest('observed_at')->limit(8)->get(),
            'catalogNodes' => SupplierCatalogNode::query()->where('active', true)->latest('last_crawled_at')->limit(12)->get(),
            'stats' => [
                'variants' => SupplierProduct::count(),
                'mapped' => SupplierProduct::whereNotNull('product_id')->count(),
                'syncing' => SupplierProduct::where('sync_stock', true)->count(),
                'out' => SupplierProduct::where('supplier_stock', 0)->count(),
                'categorized' => SupplierProduct::whereNotNull('suggested_category_id')->count(),
                'subcategories' => Category::whereNotNull('parent_id')->count(),
                'catalog_nodes' => SupplierCatalogNode::where('active', true)->count(),
                'catalog_complete' => SupplierCatalogNode::where('active', true)->where('is_leaf', true)->where('crawl_status', 'complete')->count(),
                'catalog_pending' => SupplierCatalogNode::where('active', true)->where('is_leaf', true)->whereIn('crawl_status', ['pending', 'partial', 'crawling'])->count(),
            ],
            'defaultUrl' => config('suppliers.lcd_phone.category_urls.0', 'https://lcd-phone.com/fr/49-accessoires'),
        ]);
    }

    public function discover(Request $request)
    {
        $data = $request->validate([
            'source_url' => [
                'required',
                'url:http,https',
                function (string $attribute, mixed $value, Closure $fail) {
                    $host = mb_strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                    if ($host !== 'lcd-phone.com' && ! str_ends_with($host, '.lcd-phone.com')) {
                        $fail('Utilisez uniquement une catégorie officielle lcd-phone.com.');
                    }
                },
            ],
            'pages' => ['required', 'integer', 'min:1', 'max:'.config('suppliers.serverless.discovery_pages', 5)],
            'limit' => ['required', 'integer', 'min:1', 'max:'.config('suppliers.serverless.discovery_products', 100)],
        ]);

        DiscoverSupplierProducts::dispatch([$data['source_url']], (int) $data['pages'], (int) $data['limit']);

        return back()->with('success', 'Découverte lancée. Son avancement apparaît dans l’historique.');
    }

    public function sync()
    {
        SyncSupplierStock::dispatch();

        return back()->with('success', 'Contrôle des stocks lancé.');
    }

    public function categorize()
    {
        CategorizeSupplierProducts::dispatch();

        return back()->with('success', 'Classement automatique lancé.');
    }

    public function refreshCatalog()
    {
        RefreshSupplierCatalog::dispatch();

        return back()->with('success', 'Lecture de l’arborescence LCD Phone lancée.');
    }

    public function crawlCatalog(Request $request)
    {
        $data = $request->validate([
            'path' => ['nullable', 'string', 'max:1000'],
            'nodes' => ['required', 'integer', 'min:1', 'max:'.config('suppliers.serverless.catalog_nodes', 10)],
            'pages' => ['required', 'integer', 'min:1', 'max:'.config('suppliers.serverless.catalog_pages', 5)],
            'products' => ['required', 'integer', 'min:1', 'max:'.config('suppliers.serverless.catalog_products', 100)],
        ]);

        CrawlSupplierCatalog::dispatch(
            filled($data['path'] ?? null) ? trim($data['path']) : null,
            (int) $data['nodes'],
            (int) $data['pages'],
            (int) $data['products'],
        );

        return back()->with('success', 'Parcours hiérarchique lancé. La progression est enregistrée après chaque fiche.');
    }

    public function update(Request $request, SupplierProduct $supplierProduct, SupplierSyncService $service)
    {
        $data = $request->validate([
            'product_sku' => ['nullable', 'string', 'max:100'],
            'sync_stock' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'stock_divisor' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $product = null;
        if (filled($data['product_sku'] ?? null)) {
            $product = Product::where('sku', trim($data['product_sku']))->first();
            if (! $product) {
                return back()->withErrors(['product_sku' => 'Aucun produit AchatHub ne possède ce SKU.'])->withInput();
            }
        }

        $service->applyMapping($supplierProduct, $product, $request->boolean('sync_stock'), (int) $data['stock_divisor']);
        $supplierProduct->update(['active' => $request->boolean('active')]);

        return back()->with('success', 'Correspondance fournisseur mise à jour.');
    }

    public function import(SupplierProduct $supplierProduct, SupplierSyncService $service)
    {
        $supplierProduct->load('suggestedCategory.parent');
        if ($supplierProduct->product) {
            return redirect()->route('admin.products.edit', $supplierProduct->product)
                ->with('success', 'Cette référence est déjà reliée à un produit AchatHub.');
        }

        return view('admin.supplier-import', [
            'supplierProduct' => $supplierProduct,
            'categories' => $this->categories(),
            'defaultName' => $this->defaultName($supplierProduct),
            'defaultSku' => $this->defaultSku($supplierProduct),
            'defaultFamily' => $supplierProduct->supplier_path[0] ?? ($supplierProduct->suggestedCategory?->parent?->name ?: $supplierProduct->source_category),
            'suggestedPrice' => $service->suggestedSalePrice($supplierProduct),
        ]);
    }

    public function storeProduct(Request $request, SupplierProduct $supplierProduct, SupplierSyncService $service)
    {
        abort_if($supplierProduct->product_id, 422, 'Cette référence fournisseur est déjà importée.');

        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'brand' => ['nullable', 'string', 'max:100'],
            'family' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'old_price' => ['nullable', 'numeric', 'min:0.01', 'max:100000'],
            'stock_divisor' => ['required', 'integer', 'min:1', 'max:100000'],
            'sync_stock' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);

        $product = DB::transaction(function () use ($request, $supplierProduct, $service, $data) {
            $selectedCategory = Category::findOrFail($data['category_id']);
            $price = round((float) $data['price'], 2);
            $oldPrice = filled($data['old_price'] ?? null) ? round((float) $data['old_price'], 2) : null;
            $discount = $oldPrice && $oldPrice > $price
                ? min(100, (int) round((1 - ($price / $oldPrice)) * 100))
                : 0;
            $images = collect($supplierProduct->images ?: [$supplierProduct->image])
                ->prepend($supplierProduct->image)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $ean = filled($supplierProduct->ean) && ! Product::where('legacy_id', $supplierProduct->ean)->exists()
                ? $supplierProduct->ean
                : null;

            $product = Product::create([
                'category_id' => $data['category_id'],
                'legacy_id' => $ean,
                'sku' => trim($data['sku']),
                'name' => trim($data['name']),
                'slug' => $this->uniqueSlug($data['name'].'-'.$data['sku']),
                'brand' => filled($data['brand'] ?? null) ? trim($data['brand']) : null,
                'model' => $supplierProduct->variant_name,
                'family' => filled($data['family'] ?? null) ? trim($data['family']) : ($selectedCategory->parent?->name ?: $selectedCategory->name),
                'subcategory' => $selectedCategory->parent ? $selectedCategory->name : $supplierProduct->source_category,
                'price' => $price,
                'old_price' => $oldPrice,
                'discount' => $discount,
                'stock' => intdiv($supplierProduct->supplier_stock, max(1, (int) $data['stock_divisor'])),
                'condition' => 'Neuf',
                'tag' => 'Nouveauté',
                'image' => $images[0] ?? null,
                'images' => $images,
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'features' => array_values(array_filter([
                    $supplierProduct->supplier_reference ? 'Référence fournisseur : '.$supplierProduct->supplier_reference : null,
                    $supplierProduct->ean ? 'EAN : '.$supplierProduct->ean : null,
                    'Conditionnement de vente : '.max(1, (int) $data['stock_divisor']).' unité(s) fournisseur',
                ])),
                'active' => $request->boolean('active'),
                'featured' => false,
                'featured_order' => 0,
            ]);

            $service->applyMapping(
                $supplierProduct,
                $product,
                $request->boolean('sync_stock'),
                (int) $data['stock_divisor'],
            );

            return $product;
        });

        return redirect()->route('admin.products.edit', $product)
            ->with('success', $product->active
                ? 'Produit importé et publié. Vérifiez sa fiche dans la boutique.'
                : 'Produit importé en brouillon. Vous pouvez maintenant finaliser sa fiche.');
    }

    private function defaultName(SupplierProduct $supplierProduct): string
    {
        $variant = trim((string) $supplierProduct->variant_name);
        $name = trim($supplierProduct->name);

        return $variant !== '' && ! str_contains(mb_strtolower($name), mb_strtolower($variant))
            ? Str::limit($name.' - '.$variant, 255, '')
            : $name;
    }

    private function defaultSku(SupplierProduct $supplierProduct): string
    {
        $candidate = $supplierProduct->supplier_reference ?: 'LCD-'.$supplierProduct->supplier_product_id.'-'.$supplierProduct->supplier_variant_id;
        $candidate = Str::upper(trim((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $candidate), '-'));
        $base = Str::limit($candidate, 92, '');
        $sku = $base;
        $suffix = 2;
        while (Product::where('sku', $sku)->exists()) {
            $sku = $base.'-'.$suffix++;
        }

        return $sku;
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'produit';
        $slug = $base;
        $suffix = 2;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function categories()
    {
        $categories = Category::query()->where('active', true)->get();
        $byId = $categories->keyBy('id');

        return $categories
            ->each(function (Category $category) use ($byId) {
                $parts = [];
                $current = $category;
                $visited = [];
                while ($current && ! isset($visited[$current->id])) {
                    $visited[$current->id] = true;
                    array_unshift($parts, $current->name);
                    $current = $current->parent_id ? $byId->get($current->parent_id) : null;
                }
                $category->setAttribute('path_label', implode(' › ', $parts));
            })
            ->sortBy('path_label')
            ->values();
    }
}
