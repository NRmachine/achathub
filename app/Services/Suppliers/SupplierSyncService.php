<?php

namespace App\Services\Suppliers;

use App\Models\Product;
use App\Models\SupplierCatalogNode;
use App\Models\SupplierProduct;
use App\Models\SupplierStockChange;
use App\Models\SupplierSyncRun;
use Illuminate\Cache\Lock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SupplierSyncService
{
    private ?Collection $catalog = null;

    public function __construct(private readonly LcdPhoneClient $client) {}

    public function discover(array $urls, int $maxPages = 1, int $limit = 25): SupplierSyncRun
    {
        $lock = $this->lock();
        $run = $this->startRun('discover');

        try {
            $seenProductIds = [];
            $processed = 0;
            $pages = 0;
            $variants = 0;
            $mapped = 0;
            $outOfStock = 0;
            $errors = [];

            foreach (array_unique(array_filter($urls)) as $categoryUrl) {
                for ($page = 1; $page <= max(1, $maxPages) && $processed < $limit; $page++) {
                    try {
                        $listing = $this->client->discoverPage($categoryUrl, $page);
                        $pages++;
                    } catch (Throwable $exception) {
                        $errors[] = "Catalogue page {$page}: ".$exception->getMessage();
                        break;
                    }

                    foreach ($listing['products'] as $listingProduct) {
                        $productKey = (string) $listingProduct['supplier_product_id'];
                        if (isset($seenProductIds[$productKey]) || $processed >= $limit) {
                            continue;
                        }
                        $seenProductIds[$productKey] = true;
                        $processed++;

                        try {
                            $detail = $this->client->product($listingProduct['url']);
                            foreach ($detail['variants'] as $variant) {
                                $variant['image'] ??= $listingProduct['image'] ?? null;
                                $supplierProduct = $this->storeVariant($variant);
                                $variants++;
                                $mapped += $supplierProduct->product_id ? 1 : 0;
                                $outOfStock += $supplierProduct->supplier_stock === 0 ? 1 : 0;
                            }
                        } catch (Throwable $exception) {
                            $errors[] = Str::limit(($listingProduct['name'] ?? $productKey).': '.$exception->getMessage(), 400);
                        }

                        $this->pause();
                    }

                    if ($page >= (int) ($listing['max_page'] ?? 1)) {
                        break;
                    }
                }
            }

            return $this->finishRun($run, [
                'status' => $errors === [] ? 'success' : 'partial',
                'pages_scanned' => $pages,
                'products_seen' => $processed,
                'variants_seen' => $variants,
                'mapped_count' => $mapped,
                'out_of_stock_count' => $outOfStock,
                'error_count' => count($errors),
                'message' => $errors === [] ? 'Découverte terminée.' : implode("\n", array_slice($errors, 0, 12)),
            ]);
        } catch (Throwable $exception) {
            $this->failRun($run, $exception);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function syncStock(): SupplierSyncRun
    {
        $lock = $this->lock();
        $run = $this->startRun('stock');

        try {
            $urls = SupplierProduct::query()
                ->where('provider', 'lcd_phone')
                ->where('active', true)
                ->select('supplier_url')
                ->selectRaw('MAX(sync_stock) as sync_priority')
                ->selectRaw('MIN(last_synced_at) as oldest_sync')
                ->groupBy('supplier_url')
                ->orderByDesc('sync_priority')
                ->orderBy('oldest_sync')
                ->limit(max(1, (int) config('suppliers.lcd_phone.sync_url_limit', 100)))
                ->pluck('supplier_url');

            $supplierProducts = SupplierProduct::query()
                ->where('provider', 'lcd_phone')
                ->where('active', true)
                ->whereIn('supplier_url', $urls)
                ->with('product')
                ->get()
                ->groupBy('supplier_url');

            $productsSeen = 0;
            $variantsSeen = 0;
            $mapped = 0;
            $updated = 0;
            $outOfStock = 0;
            $errors = [];

            foreach ($supplierProducts as $url => $storedVariants) {
                $productsSeen++;
                try {
                    $detail = $this->client->product($url);
                    $byId = collect($detail['variants'])->keyBy(fn (array $variant) => (string) $variant['supplier_variant_id']);
                    $byReference = collect($detail['variants'])
                        ->filter(fn (array $variant) => filled($variant['supplier_reference'] ?? null))
                        ->keyBy(fn (array $variant) => $this->normalize((string) $variant['supplier_reference']));

                    foreach ($storedVariants as $stored) {
                        $fresh = $byId->get((string) $stored->supplier_variant_id);
                        if (! $fresh && $stored->supplier_reference) {
                            $fresh = $byReference->get($this->normalize($stored->supplier_reference));
                        }
                        if (! $fresh) {
                            $stored->update(['last_error' => 'Variante absente de la fiche fournisseur lors du dernier contrôle.']);
                            $errors[] = 'Variante introuvable : '.($stored->supplier_reference ?: $stored->id);

                            continue;
                        }

                        $oldStock = $stored->supplier_stock;
                        $newStock = (int) $fresh['supplier_stock'];
                        $stored->update([
                            'supplier_reference' => $fresh['supplier_reference'] ?? $stored->supplier_reference,
                            'ean' => $fresh['ean'] ?? $stored->ean,
                            'brand' => $fresh['brand'] ?? $stored->brand,
                            'source_category' => $fresh['source_category'] ?? $stored->source_category,
                            'variant_name' => $fresh['variant_name'] ?? $stored->variant_name,
                            'description' => $fresh['description'] ?? $stored->description,
                            'image' => $fresh['image'] ?? $stored->image,
                            'images' => $fresh['images'] ?? $stored->images,
                            'purchase_price' => $fresh['purchase_price'],
                            'minimum_order_quantity' => max(1, (int) ($fresh['minimum_order_quantity'] ?? $stored->minimum_order_quantity)),
                            'supplier_stock' => $newStock,
                            'available' => (bool) $fresh['available'],
                            'last_seen_at' => now(),
                            'last_synced_at' => now(),
                            'last_error' => null,
                        ]);

                        if ($oldStock !== $newStock) {
                            $this->recordStockChange($stored, $oldStock, $newStock);
                            $updated++;
                        }
                        if ($stored->product_id && $stored->sync_stock) {
                            $stored->product?->update(['stock' => $this->sellableStock($newStock, $stored->stock_divisor)]);
                        }

                        $variantsSeen++;
                        $mapped += $stored->product_id ? 1 : 0;
                        $outOfStock += $newStock === 0 ? 1 : 0;
                    }
                } catch (Throwable $exception) {
                    foreach ($storedVariants as $stored) {
                        $stored->update(['last_error' => Str::limit($exception->getMessage(), 1000)]);
                    }
                    $errors[] = Str::limit($url.': '.$exception->getMessage(), 500);
                }

                $this->pause();
            }

            return $this->finishRun($run, [
                'status' => $errors === [] ? 'success' : 'partial',
                'products_seen' => $productsSeen,
                'variants_seen' => $variantsSeen,
                'mapped_count' => $mapped,
                'updated_count' => $updated,
                'out_of_stock_count' => $outOfStock,
                'error_count' => count($errors),
                'message' => $supplierProducts->isEmpty()
                    ? 'Aucun produit fournisseur à contrôler. Lancez une découverte.'
                    : ($errors === [] ? 'Contrôle des stocks terminé.' : implode("\n", array_slice($errors, 0, 12))),
            ]);
        } catch (Throwable $exception) {
            $this->failRun($run, $exception);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function applyMapping(SupplierProduct $supplierProduct, ?Product $product, bool $syncStock, int $stockDivisor = 1): void
    {
        if ($syncStock && ! $product) {
            throw new RuntimeException('Choisissez un produit AchatHub avant d’activer la synchronisation.');
        }

        $supplierProduct->update([
            'product_id' => $product?->id,
            'match_method' => $product ? 'manual' : null,
            'match_score' => $product ? 100 : $supplierProduct->match_score,
            'sync_stock' => $product ? $syncStock : false,
            'stock_divisor' => max(1, $stockDivisor),
        ]);

        if ($product && $syncStock) {
            $product->update(['stock' => $this->sellableStock($supplierProduct->supplier_stock, $stockDivisor)]);
        }
    }

    public function suggestedSalePrice(SupplierProduct $supplierProduct): float
    {
        $cost = max(0, (float) $supplierProduct->purchase_price * max(1, $supplierProduct->minimum_order_quantity));
        $factor = match (true) {
            $cost <= 5 => 2.5,
            $cost <= 15 => 2,
            $cost <= 50 => 1.7,
            $cost <= 100 => 1.55,
            default => 1.4,
        };
        $rounded = ceil($cost * $factor) - 0.01;

        return round(max(4.99, $rounded), 2);
    }

    public function storeVariant(
        array $variant,
        ?SupplierCatalogNode $catalogNode = null,
        iterable $catalogNodes = [],
        ?int $replaceCatalogParentId = null,
    ): SupplierProduct {
        $supplierProduct = SupplierProduct::firstOrNew([
            'provider' => 'lcd_phone',
            'supplier_product_id' => (string) $variant['supplier_product_id'],
            'supplier_variant_id' => (string) ($variant['supplier_variant_id'] ?? '0'),
        ]);
        $oldStock = $supplierProduct->exists ? $supplierProduct->supplier_stock : null;
        $shouldUseSupplierMinimum = ! $supplierProduct->exists || (! $supplierProduct->product_id && ! $supplierProduct->sync_stock);
        $minimumOrderQuantity = max(1, (int) ($variant['minimum_order_quantity'] ?? 1));

        $supplierProduct->fill([
            'supplier_reference' => $variant['supplier_reference'] ?? null,
            'ean' => $variant['ean'] ?? null,
            'brand' => filled($variant['brand'] ?? null) ? Str::limit((string) $variant['brand'], 255, '') : null,
            'source_category' => filled($variant['source_category'] ?? null) ? Str::limit((string) $variant['source_category'], 255, '') : null,
            'supplier_path' => $variant['supplier_path'] ?? $catalogNode?->path,
            'name' => Str::limit((string) $variant['name'], 255, ''),
            'variant_name' => filled($variant['variant_name'] ?? null) ? Str::limit((string) $variant['variant_name'], 255, '') : null,
            'description' => filled($variant['description'] ?? null) ? Str::limit((string) $variant['description'], 5000, '') : null,
            'supplier_url' => $variant['supplier_url'],
            'image' => $variant['image'] ?? null,
            'images' => $variant['images'] ?? [],
            'purchase_price' => $variant['purchase_price'],
            'minimum_order_quantity' => $minimumOrderQuantity,
            'supplier_stock' => (int) $variant['supplier_stock'],
            'available' => (bool) $variant['available'],
            'last_seen_at' => now(),
            'last_synced_at' => now(),
            'last_error' => null,
            'active' => true,
        ]);
        if ($catalogNode) {
            $supplierProduct->supplier_catalog_node_id = $catalogNode->id;
            $supplierProduct->suggested_category_id = $catalogNode->category_id;
        }
        if ($shouldUseSupplierMinimum) {
            $supplierProduct->stock_divisor = $minimumOrderQuantity;
        }

        if (! $supplierProduct->product_id) {
            $match = $this->matchProduct($variant);
            if ($match['exact']) {
                $supplierProduct->product_id = $match['product']?->id;
                $supplierProduct->match_method = $match['method'];
                $supplierProduct->match_score = 100;
                $supplierProduct->sync_stock = (bool) config('suppliers.lcd_phone.auto_sync_exact', true);
            } elseif ($match['product']) {
                $supplierProduct->suggested_product_id = $match['product']->id;
                $supplierProduct->match_method = 'suggestion';
                $supplierProduct->match_score = $match['score'];
            }
        }

        $supplierProduct->save();
        $assignmentIds = collect($catalogNodes)
            ->push($catalogNode)
            ->filter()
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
        if ($assignmentIds !== []) {
            if ($replaceCatalogParentId) {
                $supplierProduct->catalogNodes()->detach(
                    SupplierCatalogNode::where('parent_id', $replaceCatalogParentId)->pluck('id'),
                );
            }
            $supplierProduct->catalogNodes()->syncWithoutDetaching($assignmentIds);
        }
        if ($oldStock !== null && $oldStock !== $supplierProduct->supplier_stock) {
            $this->recordStockChange($supplierProduct, $oldStock, $supplierProduct->supplier_stock);
        }
        if ($supplierProduct->product_id && $supplierProduct->sync_stock) {
            $supplierProduct->product?->update([
                'stock' => $this->sellableStock($supplierProduct->supplier_stock, $supplierProduct->stock_divisor),
            ]);
        }
        if ($catalogNode?->category_id && $supplierProduct->product) {
            $currentCategory = $supplierProduct->product->category;
            if (! $currentCategory || $currentCategory->supplier_managed) {
                $supplierProduct->product->update([
                    'category_id' => $catalogNode->category_id,
                    'family' => $catalogNode->path[0] ?? $catalogNode->name,
                    'subcategory' => $catalogNode->name,
                ]);
            }
        }

        return $supplierProduct;
    }

    private function matchProduct(array $variant): array
    {
        $catalog = $this->catalog();
        $reference = $this->normalize((string) ($variant['supplier_reference'] ?? ''));
        $ean = $this->normalize((string) ($variant['ean'] ?? ''));

        if ($reference !== '') {
            $exact = $catalog->first(fn (Product $product) => $this->normalize($product->sku) === $reference);
            if ($exact) {
                return ['product' => $exact, 'exact' => true, 'method' => 'exact_sku', 'score' => 100];
            }
        }
        if ($ean !== '') {
            $exact = $catalog->first(fn (Product $product) => $this->normalize((string) $product->legacy_id) === $ean);
            if ($exact) {
                return ['product' => $exact, 'exact' => true, 'method' => 'exact_ean', 'score' => 100];
            }
        }

        $needle = $this->normalize(($variant['name'] ?? '').' '.($variant['variant_name'] ?? ''));
        if (mb_strlen($needle) < 8) {
            return ['product' => null, 'exact' => false, 'method' => null, 'score' => null];
        }

        $bestProduct = null;
        $bestScore = 0;
        foreach ($catalog as $product) {
            $candidate = $this->normalize($product->name.' '.$product->brand.' '.$product->model);
            similar_text($needle, $candidate, $similarity);
            $needleTokens = array_unique(array_filter(explode(' ', $needle), fn ($token) => mb_strlen($token) >= 3));
            $candidateTokens = array_unique(explode(' ', $candidate));
            $tokenScore = $needleTokens === [] ? 0 : count(array_intersect($needleTokens, $candidateTokens)) / count($needleTokens) * 100;
            $score = (int) round(($similarity * .55) + ($tokenScore * .45));
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestProduct = $product;
            }
        }

        return $bestScore >= 58
            ? ['product' => $bestProduct, 'exact' => false, 'method' => 'suggestion', 'score' => min(99, $bestScore)]
            : ['product' => null, 'exact' => false, 'method' => null, 'score' => null];
    }

    private function catalog(): Collection
    {
        return $this->catalog ??= Product::query()->select(['id', 'sku', 'legacy_id', 'name', 'brand', 'model', 'stock'])->get();
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii($value))));
    }

    private function sellableStock(int $supplierStock, int $stockDivisor): int
    {
        return intdiv(max(0, $supplierStock), max(1, $stockDivisor));
    }

    private function recordStockChange(SupplierProduct $supplierProduct, int $oldStock, int $newStock): void
    {
        SupplierStockChange::create([
            'supplier_product_id' => $supplierProduct->id,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'difference' => $newStock - $oldStock,
            'observed_at' => now(),
        ]);
    }

    private function startRun(string $mode): SupplierSyncRun
    {
        return SupplierSyncRun::create(['provider' => 'lcd_phone', 'mode' => $mode, 'status' => 'running', 'started_at' => now()]);
    }

    private function finishRun(SupplierSyncRun $run, array $data): SupplierSyncRun
    {
        $run->update($data + ['finished_at' => now()]);

        return $run->refresh();
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
        $milliseconds = max(0, (int) config('suppliers.lcd_phone.delay_ms', 250));
        if ($milliseconds > 0 && app()->environment() !== 'testing') {
            usleep($milliseconds * 1000);
        }
    }
}
