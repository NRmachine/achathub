<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class StorefrontNavigation
{
    private ?array $resolved = null;

    public function data(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        if (app()->environment('testing')) {
            Cache::forget('storefront.navigation.v4');
        }

        $data = Cache::remember(
            'storefront.navigation.v4',
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
