<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class StorefrontNavigation
{
    private const CACHE_KEY = 'storefront.navigation.v5';

    private const WARMED_KEY = 'storefront.container-warmed.v1';

    private ?array $resolved = null;

    public function data(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        // This menu is read on every storefront page. On Vercel, keeping the
        // ten-minute snapshot in the container avoids a remote PostgreSQL cache
        // lookup for pages such as login, support and legal notices.
        $cache = app()->isProduction() ? Cache::store('file') : Cache::store();

        if (app()->environment('testing')) {
            $cache->forget(self::CACHE_KEY);
        }

        $data = $cache->get(self::CACHE_KEY);
        if (! is_array($data)
            && app()->isProduction()
            && ! $cache->get(self::WARMED_KEY)
            && ! request()->boolean('warmup')) {
            $data = $this->bundledData();
        }

        $data ??= $cache->remember(
            self::CACHE_KEY,
            now()->addMinutes(10),
            function (): array {
                $menuCategories = Category::query()
                    ->whereNull('parent_id')
                    ->where('active', true)
                    ->with([
                        'children' => fn ($query) => $query->where('active', true)->orderBy('name'),
                        'children.children' => fn ($query) => $query->where('active', true)->orderBy('name'),
                    ])
                    ->orderBy('name')
                    ->get();

                $productCounts = Product::query()
                    ->where('active', true)
                    ->selectRaw('category_id, COUNT(*) as aggregate')
                    ->groupBy('category_id')
                    ->pluck('aggregate', 'category_id');

                $menuCategories->each(function (Category $category) use ($productCounts): void {
                    $count = collect($this->loadedCatalogIds($category))
                        ->sum(fn (int $id): int => (int) ($productCounts[$id] ?? 0));
                    $category->setAttribute('products_count', $count);
                });

                $menuCategories = $menuCategories
                    ->where('products_count', '>', 0)
                    ->sortByDesc('products_count')
                    ->values();

                $menuSubcategories = Category::query()
                    ->whereNotNull('parent_id')
                    ->where('active', true)
                    ->whereHas('products', fn ($query) => $query->where('active', true))
                    ->withCount(['products' => fn ($query) => $query->where('active', true)])
                    ->orderBy('name')
                    ->get()
                    ->groupBy('parent_id');

                $menuFamilyCounts = Product::query()
                    ->selectRaw('category_id, family, subcategory, COUNT(*) as total')
                    ->where('active', true)
                    ->groupBy('category_id', 'family', 'subcategory')
                    ->get()
                    ->map(function (Product $item): object {
                        $source = $item->family ?: ($item->subcategory ?: 'Autres produits');

                        return (object) [
                            'category_id' => $item->category_id,
                            'filter_type' => $item->family ? 'family' : 'subcategory',
                            'menu_family' => trim(str($source)->before('>')),
                            'total' => (int) $item->total,
                        ];
                    })
                    ->groupBy('category_id')
                    ->map(fn ($items) => $items
                        ->groupBy(fn ($item) => $item->filter_type.'|'.$item->menu_family)
                        ->map(fn ($group) => (object) [
                            'filter_type' => $group->first()->filter_type,
                            'menu_family' => $group->first()->menu_family,
                            'total' => $group->sum('total'),
                        ])
                        ->sortBy('menu_family')
                        ->values());

                return [
                    'menuCategories' => $menuCategories
                        ->map(fn (Category $category): array => [
                            'id' => $category->id,
                            'slug' => $category->slug,
                            'name' => $category->name,
                            'products_count' => $category->products_count,
                        ])
                        ->all(),
                    'menuSubcategories' => $menuSubcategories
                        ->map(fn ($items) => $items->map(fn (Category $category): array => [
                            'id' => $category->id,
                            'slug' => $category->slug,
                            'name' => $category->name,
                            'products_count' => $category->products_count,
                        ])->all())
                        ->all(),
                    'menuFamilyCounts' => $menuFamilyCounts
                        ->map(fn ($items) => $items->map(fn (object $item): array => (array) $item)->all())
                        ->all(),
                ];
            }
        );

        return $this->resolved = [
            'menuCategories' => collect($data['menuCategories'])->map(fn (array $item): object => (object) $item),
            'menuSubcategories' => collect($data['menuSubcategories'])
                ->map(fn (array $items) => collect($items)->map(fn (array $item): object => (object) $item)),
            'menuFamilyCounts' => collect($data['menuFamilyCounts'])
                ->map(fn (array $items) => collect($items)->map(fn (array $item): object => (object) $item)),
        ];
    }

    /**
     * Build an instant, database-free menu while a new container warms Neon.
     * The HTTP warmup replaces this fallback with the live catalog snapshot.
     */
    private function bundledData(): array
    {
        $catalog = json_decode((string) file_get_contents(database_path('data/public-catalog.json')), true);
        $categories = collect($catalog['categories'] ?? [])
            ->filter(fn (array $category): bool => (bool) ($category['active'] ?? true));
        $products = collect($catalog['products'] ?? [])
            ->filter(fn (array $product): bool => (bool) ($product['active'] ?? true));
        $productCounts = $products->countBy(fn (array $product): int => (int) $product['category_id']);
        $childrenByParent = $categories->groupBy(fn (array $category): int => (int) ($category['parent_id'] ?? 0));

        $descendantIds = function (int $categoryId) use (&$descendantIds, $childrenByParent): array {
            return collect([$categoryId])
                ->merge(collect($childrenByParent->get($categoryId, []))
                    ->flatMap(fn (array $child): array => $descendantIds((int) $child['id'])))
                ->unique()
                ->values()
                ->all();
        };

        $menuCategories = $categories
            ->filter(fn (array $category): bool => empty($category['parent_id']))
            ->map(function (array $category) use ($descendantIds, $productCounts): array {
                $category['products_count'] = collect($descendantIds((int) $category['id']))
                    ->sum(fn (int $id): int => (int) ($productCounts[$id] ?? 0));

                return $category;
            })
            ->filter(fn (array $category): bool => $category['products_count'] > 0)
            ->sortByDesc('products_count')
            ->map(fn (array $category): array => [
                'id' => (int) $category['id'],
                'slug' => $category['slug'],
                'name' => $category['name'],
                'products_count' => (int) $category['products_count'],
            ])
            ->values()
            ->all();

        $menuSubcategories = $categories
            ->filter(fn (array $category): bool => ! empty($category['parent_id']) && ($productCounts[(int) $category['id']] ?? 0) > 0)
            ->groupBy(fn (array $category): int => (int) $category['parent_id'])
            ->map(fn ($items) => $items->sortBy('name')->map(fn (array $category): array => [
                'id' => (int) $category['id'],
                'slug' => $category['slug'],
                'name' => $category['name'],
                'products_count' => (int) ($productCounts[(int) $category['id']] ?? 0),
            ])->values()->all())
            ->all();

        $menuFamilyCounts = $products
            ->filter(fn (array $product): bool => ! empty($product['family']) || ! empty($product['subcategory']))
            ->map(function (array $product): array {
                $source = $product['family'] ?: $product['subcategory'];

                return [
                    'category_id' => (int) $product['category_id'],
                    'filter_type' => ! empty($product['family']) ? 'family' : 'subcategory',
                    'menu_family' => str($source)->before('>')->trim()->toString(),
                ];
            })
            ->groupBy('category_id')
            ->map(fn ($items) => $items
                ->groupBy(fn (array $item): string => $item['filter_type'].'|'.$item['menu_family'])
                ->map(fn ($group): array => [
                    'filter_type' => $group->first()['filter_type'],
                    'menu_family' => $group->first()['menu_family'],
                    'total' => $group->count(),
                ])
                ->sortBy('menu_family')
                ->values()
                ->all())
            ->all();

        return compact('menuCategories', 'menuSubcategories', 'menuFamilyCounts');
    }

    /**
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
}
