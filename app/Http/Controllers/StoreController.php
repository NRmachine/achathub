<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\StorefrontNavigation;
use App\Support\OptimizedAsset;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function index(Request $request, StorefrontNavigation $navigation)
    {
        $selectedCategory = $request->filled('category')
            ? Category::with(['parent', 'children.children'])->where('slug', $request->category)->first()
            : null;
        $selectedCategoryIds = $selectedCategory
            ? $this->loadedCatalogIds($selectedCategory)
            : [];

        $productsQuery = Product::query()->with('category')->where('active', true)
            ->when($request->filled('q'), function ($query) use ($request) {
                $terms = collect(preg_split('/\s+/', trim($request->string('q'))))->filter()->take(8);
                foreach ($terms as $term) {
                    $escaped = '%'.addcslashes($term, '%_\\').'%';
                    $query->where(fn ($search) => $search
                        ->where('name', 'like', $escaped)
                        ->orWhere('sku', 'like', $escaped)
                        ->orWhere('legacy_id', 'like', $escaped)
                        ->orWhere('brand', 'like', $escaped)
                        ->orWhere('model', 'like', $escaped)
                        ->orWhere('family', 'like', $escaped)
                        ->orWhere('subcategory', 'like', $escaped));
                }
            })
            ->when($request->filled('category'), fn ($q) => $selectedCategory
                ? $q->whereIn('category_id', $selectedCategoryIds)
                : $q->whereRaw('1 = 0'))
            ->when($request->filled('family'), function ($q) use ($request) {
                $family = addcslashes($request->family, '%_\\');
                $q->where(fn ($filter) => $filter->where('family', $request->family)->orWhere('family', 'like', $family.' > %'));
            })
            ->when($request->filled('subcategory'), function ($q) use ($request) {
                $subcategory = addcslashes($request->subcategory, '%_\\');
                $q->where(fn ($filter) => $filter->where('subcategory', $request->subcategory)->orWhere('subcategory', 'like', $subcategory.' > %'));
            })
            ->when($request->filled('brand'), fn ($q) => $q->where('brand', $request->brand))
            ->when($request->filled('in_stock'), fn ($q) => $q->where('stock', '>', 0))
            ->when($request->filled('min_price'), fn ($q) => $q->where('price', '>=', max(0, (float) $request->min_price)))
            ->when($request->filled('max_price'), fn ($q) => $q->where('price', '<=', max(0, (float) $request->max_price)));

        match ($request->string('sort')->toString()) {
            'price_asc' => $productsQuery->orderBy('price'),
            'price_desc' => $productsQuery->orderByDesc('price'),
            'rating' => $productsQuery->orderByDesc('rating')->orderByDesc('reviews_count'),
            'newest' => $productsQuery->orderByDesc('id'),
            'discount' => $productsQuery->orderByDesc('discount')->orderBy('price'),
            default => $productsQuery->orderByDesc('featured')->orderBy('featured_order')->orderByDesc('discount')->orderByDesc('id'),
        };
        $products = $this->paginateCatalog($request, $productsQuery);

        $categories = $navigation->data()['menuCategories'];
        // Catalog metadata changes infrequently and does not need a Neon round
        // trip on every page rendered by the same Vercel container.
        $catalogCache = app()->isProduction() ? Cache::store('file') : Cache::store();
        $metadataKey = 'storefront.catalog-metadata.v5';
        if (app()->environment('testing')) {
            $catalogCache->forget($metadataKey);
        }
        $metadata = $catalogCache->get($metadataKey);
        if (! is_array($metadata)) {
            $canUseDatabase = ! app()->isProduction()
                || $request->boolean('warmup')
                || (bool) $catalogCache->get('storefront.container-warmed.v1');

            $metadata = $canUseDatabase
                ? $catalogCache->remember(
                    $metadataKey,
                    now()->addMinutes(10),
                    fn (): array => [
                        'settings' => SiteSetting::query()
                            ->whereIn('key', ['hero_title', 'hero_text'])
                            ->pluck('value', 'key')
                            ->all(),
                        'brands' => Product::query()
                            ->where('active', true)
                            ->whereNotNull('brand')
                            ->distinct()
                            ->orderBy('brand')
                            ->limit(30)
                            ->pluck('brand')
                            ->all(),
                        'maxCatalogPrice' => (float) Product::query()
                            ->where('active', true)
                            ->max('price'),
                    ],
                )
                : $this->bundledCatalogMetadata();
        }

        $catalogSeo = Seo::catalog($request, $selectedCategory, $products);
        $catalogBreadcrumbItems = [['name' => 'Accueil', 'url' => '/']];
        if ($selectedCategory?->parent) {
            $catalogBreadcrumbItems[] = [
                'name' => $selectedCategory->parent->name,
                'url' => '/?'.http_build_query(['category' => $selectedCategory->parent->slug]),
            ];
        }
        if ($selectedCategory) {
            $catalogBreadcrumbItems[] = [
                'name' => $selectedCategory->name,
                'url' => '/?'.http_build_query(['category' => $selectedCategory->slug]),
            ];
        }
        $catalogBreadcrumbData = count($catalogBreadcrumbItems) > 1
            ? Seo::breadcrumbs($catalogBreadcrumbItems)
            : null;

        return view('store.index', [
            'products' => $products,
            'categories' => $categories->sortByDesc('products_count')->values(),
            'selectedCategory' => $selectedCategory,
            'brands' => $metadata['brands'],
            'maxCatalogPrice' => $metadata['maxCatalogPrice'],
            'heroTitle' => $metadata['settings']['hero_title'] ?? 'Tout acheter, au meilleur prix.',
            'heroText' => $metadata['settings']['hero_text'] ?? 'Téléphonie, accessoires, pièces détachées et équipements sélectionnés.',
            'catalogTitle' => $request->family ?: ($request->subcategory ?: ($selectedCategory?->name ?: 'Découvrez nos produits')),
            'catalogSeo' => $catalogSeo,
            'catalogBreadcrumbData' => $catalogBreadcrumbData,
        ]);
    }

    /**
     * Return the IDs from the category tree that was already eager loaded.
     *
     * This avoids querying the database once per root category when rendering
     * the catalogue navigation.
     *
     * @return list<int>
     */
    private function loadedCatalogIds(Category $category): array
    {
        $ids = [(int) $category->getKey()];

        if (! $category->relationLoaded('children')) {
            return $ids;
        }

        foreach ($category->children as $child) {
            $ids = [...$ids, ...$this->loadedCatalogIds($child)];
        }

        return array_values(array_unique($ids));
    }

    private function paginateCatalog(Request $request, Builder $query): LengthAwarePaginator
    {
        $filters = ['q', 'category', 'family', 'subcategory', 'brand', 'in_stock', 'min_price', 'max_price', 'sort', 'page'];
        if ($request->hasAny($filters)) {
            return $query->paginate(24)->withQueryString();
        }

        $cache = app()->isProduction() ? Cache::store('file') : Cache::store();
        $cacheKey = 'storefront.catalog-default.v1';
        $warmedKey = 'storefront.container-warmed.v1';

        if (app()->environment('testing')) {
            $cache->forget($cacheKey);
        }

        $snapshot = $cache->get($cacheKey);
        if (! is_array($snapshot)) {
            $canUseDatabase = ! app()->isProduction()
                || $request->boolean('warmup')
                || (bool) $cache->get($warmedKey);

            $snapshot = $canUseDatabase
                ? $cache->remember($cacheKey, now()->addMinutes(5), fn (): array => $this->databaseCatalogSnapshot($query))
                : $this->bundledCatalogSnapshot();
        }

        if ($request->boolean('warmup')) {
            $cache->forever($warmedKey, true);
        }

        $items = collect($snapshot['items'])->map(function (array $item): Product {
            $product = (new Product)->newFromBuilder($item['attributes']);
            if ($item['category']) {
                $product->setRelation('category', (new Category)->newFromBuilder($item['category']));
            }

            return $product;
        });

        return (new LengthAwarePaginator(
            $items,
            (int) $snapshot['total'],
            (int) $snapshot['per_page'],
            (int) $snapshot['current_page'],
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page'],
        ))->withQueryString();
    }

    private function databaseCatalogSnapshot(Builder $query): array
    {
        $page = $query->paginate(24);

        return [
            'items' => $page->getCollection()->map(fn (Product $product): array => [
                'attributes' => $product->getAttributes(),
                'category' => $product->category?->getAttributes(),
            ])->all(),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
        ];
    }

    private function bundledCatalogSnapshot(): array
    {
        $catalog = json_decode((string) file_get_contents(database_path('data/public-catalog.json')), true);
        $categories = collect($catalog['categories'] ?? [])->keyBy(fn (array $category): int => (int) $category['id']);
        $products = collect($catalog['products'] ?? [])
            ->filter(fn (array $product): bool => (bool) ($product['active'] ?? true))
            ->sort(function (array $left, array $right): int {
                return [(int) ($right['featured'] ?? 0), (int) ($left['featured_order'] ?? 0), (int) ($right['discount'] ?? 0), (int) ($right['id'] ?? 0)]
                    <=> [(int) ($left['featured'] ?? 0), (int) ($right['featured_order'] ?? 0), (int) ($left['discount'] ?? 0), (int) ($left['id'] ?? 0)];
            })
            ->values();

        return [
            'items' => $products->take(24)->map(fn (array $product): array => [
                'attributes' => $product,
                'category' => $categories->get((int) $product['category_id']),
            ])->all(),
            'total' => $products->count(),
            'per_page' => 24,
            'current_page' => 1,
        ];
    }

    private function bundledCatalogMetadata(): array
    {
        $catalog = json_decode((string) file_get_contents(database_path('data/public-catalog.json')), true);
        $products = collect($catalog['products'] ?? [])
            ->filter(fn (array $product): bool => (bool) ($product['active'] ?? true));

        return [
            'settings' => [],
            'brands' => $products->pluck('brand')->filter()->unique()->sort()->take(30)->values()->all(),
            'maxCatalogPrice' => (float) $products->max('price'),
        ];
    }

    public function show(Request $request, Product $product)
    {
        abort_unless($product->active, 404);
        $product->load(['category', 'reviews' => fn ($query) => $query->where('published', true)->with('user')->latest()->limit(20)]);
        $gallery = collect([$product->image])
            ->merge($product->images ?? [])
            ->map(fn (?string $image): ?string => OptimizedAsset::image($image))
            ->filter()
            ->unique()
            ->values();
        $similarProducts = Product::query()->where('active', true)->where('stock', '>', 0)->whereKeyNot($product->id)
            ->where(function ($query) use ($product) {
                $query->where('category_id', $product->category_id);
                if ($product->family) {
                    $query->orWhere('family', $product->family);
                }
            })->orderByDesc('featured')->limit(4)->get();
        $canReview = $request->user()?->role === 'customer' && $request->user()->orders()
            ->where('status', 'Livrée')->whereHas('items', fn ($query) => $query->where('product_id', $product->id))->exists();

        $productSeoDescription = Str::limit(trim($product->description ?: collect([
            $product->name,
            $product->brand,
            $product->model,
            $product->condition,
            'Référence '.$product->sku,
        ])->filter()->unique()->implode(' · ').'.'), 160, '');
        $productStructuredData = Seo::product($product);
        $productBreadcrumbItems = [
            ['name' => 'Accueil', 'url' => '/'],
            [
                'name' => $product->category->name,
                'url' => '/?'.http_build_query(['category' => $product->category->slug]),
            ],
        ];
        if ($product->family) {
            $family = Str::of($product->family)->before('>')->trim()->toString();
            $productBreadcrumbItems[] = [
                'name' => $family,
                'url' => '/?'.http_build_query([
                    'category' => $product->category->slug,
                    'family' => $family,
                ]),
            ];
        }
        $productBreadcrumbItems[] = ['name' => $product->name];
        $productBreadcrumbData = Seo::breadcrumbs($productBreadcrumbItems);

        return view('store.show', compact(
            'product',
            'gallery',
            'similarProducts',
            'canReview',
            'productSeoDescription',
            'productStructuredData',
            'productBreadcrumbData',
        ));
    }
}
