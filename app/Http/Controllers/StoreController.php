<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\StorefrontNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StoreController extends Controller
{
    public function index(Request $request, StorefrontNavigation $navigation)
    {
        $selectedCategory = $request->filled('category')
            ? Category::with('children.children')->where('slug', $request->category)->first()
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
        $products = $productsQuery->paginate(24)->withQueryString();

        $categories = $navigation->data()['menuCategories'];
        if (app()->environment('testing')) {
            Cache::store('file')->forget('storefront.catalog-metadata.v3');
        }
        $metadata = Cache::store('file')->remember(
            'storefront.catalog-metadata.v3',
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
        );

        return view('store.index', [
            'products' => $products,
            'categories' => $categories->sortByDesc('products_count')->values(),
            'selectedCategory' => $selectedCategory,
            'brands' => $metadata['brands'],
            'maxCatalogPrice' => $metadata['maxCatalogPrice'],
            'heroTitle' => $metadata['settings']['hero_title'] ?? 'Tout acheter, au meilleur prix.',
            'heroText' => $metadata['settings']['hero_text'] ?? 'Téléphonie, accessoires, pièces détachées et équipements sélectionnés.',
            'catalogTitle' => $request->family ?: ($request->subcategory ?: ($selectedCategory?->name ?: 'Découvrez nos produits')),
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

    public function show(Request $request, Product $product)
    {
        abort_unless($product->active, 404);
        $product->load(['category', 'reviews' => fn ($query) => $query->where('published', true)->with('user')->latest()->limit(20)]);
        $gallery = collect([$product->image])->merge($product->images ?? [])->filter()->unique()->values();
        $similarProducts = Product::query()->where('active', true)->where('stock', '>', 0)->whereKeyNot($product->id)
            ->where(function ($query) use ($product) {
                $query->where('category_id', $product->category_id);
                if ($product->family) {
                    $query->orWhere('family', $product->family);
                }
            })->orderByDesc('featured')->limit(4)->get();
        $canReview = $request->user()?->role === 'customer' && $request->user()->orders()
            ->where('status', 'Livrée')->whereHas('items', fn ($query) => $query->where('product_id', $product->id))->exists();

        return view('store.show', compact('product', 'gallery', 'similarProducts', 'canReview'));
    }
}
