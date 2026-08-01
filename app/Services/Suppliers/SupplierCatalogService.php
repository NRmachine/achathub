<?php

namespace App\Services\Suppliers;

use App\Models\Category;
use App\Models\SupplierCatalogNode;
use App\Models\SupplierSyncRun;
use Illuminate\Cache\Lock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SupplierCatalogService
{
    private ?Collection $leafNodes = null;

    public function __construct(
        private readonly LcdPhoneClient $client,
        private readonly SupplierSyncService $syncService,
    ) {}

    public function refreshTree(?string $sourceUrl = null): SupplierSyncRun
    {
        $lock = $this->lock();
        $run = $this->startRun('catalog_tree');

        try {
            $tree = $this->client->catalogTree($sourceUrl);
            if ($tree === []) {
                throw new RuntimeException('LCD Phone a renvoyé une arborescence vide.');
            }

            $stats = DB::transaction(function () use ($tree) {
                SupplierCatalogNode::where('provider', 'lcd_phone')->update(['active' => false]);
                $stats = ['nodes' => 0, 'leaves' => 0, 'created' => 0, 'updated' => 0];
                $this->storeTree($tree, null, null, $stats);

                return $stats;
            });

            $run->update([
                'status' => 'success',
                'pages_scanned' => 1,
                'products_seen' => $stats['nodes'],
                'variants_seen' => $stats['leaves'],
                'updated_count' => $stats['created'] + $stats['updated'],
                'message' => "Arborescence synchronisée : {$stats['nodes']} catégorie(s), {$stats['leaves']} chemin(s) final(aux), {$stats['created']} nouveau(x).",
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->failRun($run, $exception);
            throw $exception;
        } finally {
            $lock->release();
        }

        return $run->refresh();
    }

    public function crawl(?string $path = null, int $maxNodes = 1, int $maxPagesPerNode = 1, int $maxProducts = 100): SupplierSyncRun
    {
        $lock = $this->lock();
        $run = $this->startRun('catalog_crawl');

        try {
            SupplierCatalogNode::query()
                ->where('provider', 'lcd_phone')
                ->where('crawl_status', 'crawling')
                ->where('updated_at', '<', now()->subMinutes(35))
                ->update(['crawl_status' => 'partial', 'last_error' => 'Traitement interrompu, repris automatiquement.']);

            $nodes = $this->nodesToCrawl($path, $maxNodes);
            if ($nodes->isEmpty()) {
                $run->update([
                    'status' => 'success',
                    'message' => $path
                        ? 'Ce chemin est déjà entièrement parcouru.'
                        : 'Aucun chemin en attente. Actualisez d’abord l’arborescence fournisseur.',
                    'finished_at' => now(),
                ]);

                return $run->refresh();
            }

            $totals = ['pages' => 0, 'products' => 0, 'variants' => 0, 'errors' => []];
            foreach ($nodes as $node) {
                $this->crawlNode($node, $maxPagesPerNode, $maxProducts, $totals);
            }

            $run->update([
                'status' => $totals['errors'] === [] ? 'success' : 'partial',
                'pages_scanned' => $totals['pages'],
                'products_seen' => $totals['products'],
                'variants_seen' => $totals['variants'],
                'error_count' => count($totals['errors']),
                'message' => $totals['errors'] === []
                    ? 'Parcours hiérarchique terminé pour '.$nodes->count().' chemin(s).'
                    : implode("\n", array_slice($totals['errors'], 0, 12)),
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->failRun($run, $exception);
            throw $exception;
        } finally {
            $lock->release();
        }

        return $run->refresh();
    }

    private function storeTree(array $nodes, ?SupplierCatalogNode $parentNode, ?Category $parentCategory, array &$stats): void
    {
        foreach ($nodes as $item) {
            $node = SupplierCatalogNode::firstOrNew([
                'provider' => 'lcd_phone',
                'supplier_category_id' => (string) $item['supplier_category_id'],
            ]);
            $wasCreated = ! $node->exists;
            $pathChanged = $node->exists && (
                $node->path_hash !== $this->pathHash($item['path'])
                || $node->source_url !== $item['url']
                || $node->is_leaf !== (bool) $item['is_leaf']
            );
            $category = $this->mirrorCategory($item, $node, $parentCategory);

            $node->fill([
                'parent_id' => $parentNode?->id,
                'category_id' => $category->id,
                'name' => $item['name'],
                'node_type' => $this->nodeType($item['path']),
                'depth' => count($item['path']) - 1,
                'source_url' => $item['url'],
                'path' => $item['path'],
                'path_hash' => $this->pathHash($item['path']),
                'is_leaf' => (bool) $item['is_leaf'],
                'last_discovered_at' => now(),
                'active' => true,
            ]);
            if ($wasCreated || $pathChanged) {
                $node->crawl_status = 'pending';
                $node->next_page = 1;
                $node->next_product_offset = 0;
                $node->max_page = null;
                $node->last_error = null;
            }
            $node->save();

            $stats['nodes']++;
            $stats['leaves'] += $node->is_leaf ? 1 : 0;
            $stats[$wasCreated ? 'created' : 'updated']++;

            $this->storeTree($item['children'] ?? [], $node, $category, $stats);
        }
    }

    private function mirrorCategory(array $item, SupplierCatalogNode $node, ?Category $parent): Category
    {
        if ($node->category && $node->category->parent_id === $parent?->id) {
            $category = $node->category;
        } else {
            $query = Category::query();
            $parent ? $query->where('parent_id', $parent->id) : $query->whereNull('parent_id');
            $category = $query->get()->first(
                fn (Category $candidate) => $this->normalize($candidate->name) === $this->normalize($item['name']),
            );
        }

        if (! $category) {
            $baseSlug = Str::slug(implode('-', $item['path'])) ?: 'categorie-fournisseur';
            $slug = $baseSlug;
            if (Category::where('slug', $slug)->exists()) {
                $slug = Str::limit($baseSlug, 230, '').'-lcd-'.$item['supplier_category_id'];
            }
            $category = new Category(['slug' => $slug]);
        }

        $category->fill([
            'parent_id' => $parent?->id,
            'name' => $item['name'],
            'description' => 'Catalogue fournisseur : '.implode(' › ', $item['path']),
            'active' => true,
            'supplier_managed' => true,
        ])->save();

        return $category;
    }

    private function nodesToCrawl(?string $path, int $limit): Collection
    {
        $query = SupplierCatalogNode::query()
            ->where('provider', 'lcd_phone')
            ->where('active', true)
            ->where('is_leaf', true);

        if (filled($path)) {
            $segments = array_values(array_filter(array_map('trim', preg_split('/\s*(?:>|›)\s*/u', $path) ?: [])));
            $node = $query->where('path_hash', $this->pathHash($segments))->first();
            if (! $node) {
                throw new RuntimeException("Chemin LCD Phone introuvable : {$path}");
            }
            if ($node->crawl_status === 'complete') {
                return collect();
            }

            return collect([$node]);
        }

        return $query
            ->whereIn('crawl_status', ['pending', 'partial'])
            ->get()
            ->sortBy(fn (SupplierCatalogNode $node) => sprintf('%03d-%010d', $this->crawlPriority($node), $node->id))
            ->take(max(1, $limit))
            ->values();
    }

    private function crawlPriority(SupplierCatalogNode $node): int
    {
        $path = implode(' > ', array_map(fn ($part) => $this->normalize((string) $part), $node->path));

        return match (true) {
            $path === 'pieces detachees > apple > iphone > iphone 11' => 0,
            $path === 'pieces detachees > apple > iphone > iphone 11 pro' => 1,
            $path === 'pieces detachees > apple > iphone > iphone 11 pro max' => 2,
            str_starts_with($path, 'pieces detachees > apple > iphone >') => 10,
            str_starts_with($path, 'pieces detachees > apple >') => 20,
            str_starts_with($path, 'pieces detachees >') => 30,
            str_starts_with($path, 'appareils complets >') => 40,
            str_starts_with($path, 'accessoires >') => 50,
            str_starts_with($path, 'equipements >') => 60,
            default => 90,
        };
    }

    private function crawlNode(SupplierCatalogNode $node, int $maxPages, int $maxProducts, array &$totals): void
    {
        $node->update(['crawl_status' => 'crawling', 'last_error' => null]);
        $pagesProcessed = 0;
        $productsProcessed = 0;
        $errors = [];

        try {
            while ($pagesProcessed < max(1, $maxPages) && $productsProcessed < max(1, $maxProducts)) {
                $page = max(1, $node->next_page);
                $listing = $this->client->discoverPage($node->source_url, $page);
                $node->max_page = max(1, (int) ($listing['max_page'] ?? 1));
                $products = array_slice($listing['products'], $node->next_product_offset);
                $totals['pages']++;
                $pagesProcessed++;

                foreach ($products as $listingProduct) {
                    if ($productsProcessed >= max(1, $maxProducts)) {
                        break;
                    }

                    try {
                        $detail = $this->client->product($listingProduct['url']);
                        foreach ($detail['variants'] as $variant) {
                            $variant['image'] ??= $listingProduct['image'] ?? null;
                            $variantNodes = $this->resolveVariantNodes($variant, $node);
                            $variantNode = $variantNodes->firstWhere('id', $node->id) ?? $variantNodes->first() ?? $node;
                            $variant['supplier_path'] = $variantNode->path;
                            $this->syncService->storeVariant(
                                $variant,
                                $variantNode,
                                $variantNodes,
                                $node->node_type === 'model' ? $node->parent_id : null,
                            );
                            $node->variants_seen++;
                            $totals['variants']++;
                        }
                    } catch (Throwable $exception) {
                        $errors[] = Str::limit(($listingProduct['name'] ?? $listingProduct['url']).' : '.$exception->getMessage(), 500);
                    }

                    $node->next_product_offset++;
                    $node->products_seen++;
                    $productsProcessed++;
                    $totals['products']++;
                    $node->save();
                    $this->pause();
                }

                if ($node->next_product_offset >= count($listing['products'])) {
                    $node->next_page = $page + 1;
                    $node->next_product_offset = 0;
                }

                if ($node->next_page > $node->max_page) {
                    $node->crawl_status = 'complete';
                    break;
                }
            }

            if ($node->crawl_status !== 'complete') {
                $node->crawl_status = 'partial';
            }
            $node->last_error = $errors === [] ? null : implode("\n", array_slice($errors, 0, 8));
            $node->last_crawled_at = now();
            $node->save();
            $totals['errors'] = [...$totals['errors'], ...$errors];
        } catch (Throwable $exception) {
            $node->update([
                'crawl_status' => 'partial',
                'last_error' => Str::limit($exception->getMessage(), 2000),
                'last_crawled_at' => now(),
            ]);
            $totals['errors'][] = $node->pathLabel().' : '.$exception->getMessage();
        }
    }

    private function resolveVariantNodes(array $variant, SupplierCatalogNode $fallback): Collection
    {
        if ($fallback->node_type !== 'model') {
            return collect([$fallback]);
        }

        $variantName = $this->canonicalModelText((string) ($variant['variant_name'] ?? ''));
        $haystack = $this->canonicalModelText(implode(' ', array_filter([
            $variant['brand'] ?? null,
            $variant['variant_name'] ?? null,
            $variant['name'] ?? null,
        ])));
        $candidates = $this->leafNodes()
            ->where('parent_id', $fallback->parent_id)
            ->values();
        $matches = fn (string $text) => $candidates
            ->filter(fn (SupplierCatalogNode $candidate) => $this->matchesModel($text, $candidate, $fallback))
            ->values();
        $matched = $variantName !== '' ? $matches($variantName) : collect();
        if ($matched->isEmpty()) {
            $matched = $matches($haystack);
        }

        return $matched->isEmpty() ? collect([$fallback]) : $matched;
    }

    private function matchesModel(string $text, SupplierCatalogNode $candidate, SupplierCatalogNode $context): bool
    {
        $family = $this->normalize($context->path[count($context->path) - 2] ?? '');
        $name = $this->normalize($candidate->name);
        if ($family !== 'iphone') {
            return preg_match('/(?:^| )'.preg_quote($name, '/').'(?: |$)/u', $text) === 1;
        }

        $model = trim((string) preg_replace('/^iphone\s+/', '', $name));
        $aliases = [$model];
        if (preg_match('/^(\d+)\s+pro\s+max$/', $model, $match)) {
            $aliases[] = $match[1].'pm';
        } elseif (preg_match('/^(\d+)\s+pro$/', $model, $match)) {
            $aliases[] = $match[1].'p';
        } elseif (preg_match('/^(\d+)\s+plus$/', $model, $match)) {
            $aliases[] = $match[1].'p';
        } elseif (preg_match('/^(\d+s)\s+plus$/', $model, $match)) {
            $aliases[] = $match[1].'p';
        }
        if (preg_match('/\b(se\d+)\b/', $model, $match)) {
            $aliases[] = $match[1];
        }

        foreach (array_unique($aliases) as $alias) {
            $suffixGuard = match (true) {
                preg_match('/^\d+$/', $alias) === 1 => '(?!\s+(?:pro|max|plus|mini|air))',
                str_ends_with($alias, ' pro'), $alias === 'xs' => '(?!\s+max)',
                default => '',
            };
            if (preg_match('/(?:^| )'.preg_quote($alias, '/').$suffixGuard.'(?: |$)/u', $text)) {
                return true;
            }
        }

        return false;
    }

    private function canonicalModelText(string $value): string
    {
        $value = (string) preg_replace('/\([^)]*\)/u', ' ', $value);
        $value = $this->normalize($value);

        return trim((string) preg_replace('/\bip(?=\s|\d)/', 'iphone ', $value));
    }

    private function leafNodes(): Collection
    {
        return $this->leafNodes ??= SupplierCatalogNode::query()
            ->where('provider', 'lcd_phone')
            ->where('active', true)
            ->where('is_leaf', true)
            ->with('category')
            ->get();
    }

    private function nodeType(array $path): string
    {
        $depth = count($path) - 1;
        if ($depth === 0) {
            return 'department';
        }
        if ($this->normalize($path[0] ?? '') === 'pieces detachees') {
            return match ($depth) {
                1 => 'brand',
                2 => 'device_family',
                default => 'model',
            };
        }

        return $depth === 1 ? 'category' : 'subcategory';
    }

    private function pathHash(array $path): string
    {
        return hash('sha256', implode(' > ', array_map(fn ($part) => $this->normalize((string) $part), $path)));
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii($value))));
    }

    private function startRun(string $mode): SupplierSyncRun
    {
        return SupplierSyncRun::create([
            'provider' => 'lcd_phone',
            'mode' => $mode,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    private function failRun(SupplierSyncRun $run, Throwable $exception): void
    {
        $run->update([
            'status' => 'failed',
            'error_count' => 1,
            'message' => Str::limit($exception->getMessage(), 2000),
            'finished_at' => now(),
        ]);
    }

    private function lock(): Lock
    {
        $lock = Cache::lock('supplier:lcd-phone:agent', 1800);
        if (! $lock->get()) {
            throw new RuntimeException('Une synchronisation fournisseur est déjà en cours.');
        }

        return $lock;
    }

    private function pause(): void
    {
        $milliseconds = max(0, (int) config('suppliers.lcd_phone.delay_ms', 1000));
        if ($milliseconds > 0 && ! app()->environment('testing')) {
            usleep($milliseconds * 1000);
        }
    }
}
