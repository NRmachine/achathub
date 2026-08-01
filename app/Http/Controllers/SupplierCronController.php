<?php

namespace App\Http\Controllers;

use App\Models\SupplierCatalogNode;
use App\Models\SupplierProduct;
use App\Services\Suppliers\SupplierCatalogService;
use App\Services\Suppliers\SupplierSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SupplierCronController extends Controller
{
    public function __invoke(
        Request $request,
        SupplierCatalogService $catalog,
        SupplierSyncService $stock,
    ): JsonResponse {
        $secret = (string) config('services.vercel.cron_secret');
        $authorization = (string) $request->header('Authorization');

        if ($secret === '' || ! hash_equals('Bearer '.$secret, $authorization)) {
            return response()->json(['ok' => false, 'message' => 'Non autorisé.'], 401);
        }

        try {
            if (! SupplierCatalogNode::query()->where('provider', 'lcd_phone')->where('active', true)->exists()) {
                $catalogRun = $catalog->refreshTree();
            } else {
                $catalogRun = $catalog->crawl(
                    null,
                    (int) config('suppliers.serverless.catalog_nodes', 1),
                    (int) config('suppliers.serverless.catalog_pages', 1),
                    (int) config('suppliers.serverless.catalog_products', 3),
                );
            }

            $stockRun = SupplierProduct::query()->where('provider', 'lcd_phone')->where('active', true)->exists()
                ? $stock->syncStock()
                : null;

            return response()->json([
                'ok' => true,
                'catalog_run' => $catalogRun->only(['id', 'mode', 'status', 'products_seen', 'variants_seen', 'error_count']),
                'stock_run' => $stockRun?->only(['id', 'mode', 'status', 'updated_count', 'error_count']),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'Le cycle fournisseur a échoué. Consultez les journaux Vercel.',
            ], 500);
        }
    }
}
