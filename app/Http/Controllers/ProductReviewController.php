<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductReviewController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $hasPurchased = $request->user()->orders()
            ->where('status', 'Livrée')
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->exists();
        abort_unless($hasPurchased, 403, 'Seuls les clients ayant reçu ce produit peuvent publier un avis.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'max:120'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $product, $data) {
            ProductReview::updateOrCreate(
                ['product_id' => $product->id, 'user_id' => $request->user()->id],
                $data + ['verified_purchase' => true, 'published' => true]
            );
            $product->update([
                'rating' => round($product->reviews()->where('published', true)->avg('rating'), 1),
                'reviews_count' => $product->reviews()->where('published', true)->count(),
            ]);
        });

        return redirect()->route('products.show', $product)->with('success', 'Merci, votre avis vérifié est publié.');
    }
}
