<?php

namespace App\Services\Suppliers;

use App\Models\Category;
use App\Models\SupplierProduct;
use App\Models\SupplierSyncRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SupplierCategoryService
{
    public function categorize(): SupplierSyncRun
    {
        $run = SupplierSyncRun::create([
            'provider' => 'lcd_phone',
            'mode' => 'taxonomy',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $processed = 0;
            $assigned = 0;
            $linked = 0;
            $createdCategoryIds = [];

            SupplierProduct::query()->where('provider', 'lcd_phone')->where('active', true)
                ->whereNull('supplier_catalog_node_id')
                ->with(['product', 'suggestedCategory'])
                ->chunkById(200, function ($supplierProducts) use (&$processed, &$assigned, &$linked, &$createdCategoryIds) {
                    foreach ($supplierProducts as $supplierProduct) {
                        $taxonomy = $this->classify($supplierProduct);
                        [$root, $rootCreated] = $this->category($taxonomy['root'], $taxonomy['root_slug'], null, $taxonomy['root_description']);
                        [$subcategory, $subcategoryCreated] = $this->category(
                            $taxonomy['subcategory'],
                            $taxonomy['subcategory_slug'],
                            $root,
                            $taxonomy['subcategory_description'],
                        );

                        if ($rootCreated) {
                            $createdCategoryIds[] = $root->id;
                        }
                        if ($subcategoryCreated) {
                            $createdCategoryIds[] = $subcategory->id;
                        }

                        DB::transaction(function () use ($supplierProduct, $subcategory, $taxonomy, &$assigned, &$linked) {
                            if ($supplierProduct->suggested_category_id !== $subcategory->id) {
                                $supplierProduct->update(['suggested_category_id' => $subcategory->id]);
                                $assigned++;
                            }

                            $currentCategory = $supplierProduct->product?->category;
                            $canUpdateLinkedProduct = ! $currentCategory
                                || $currentCategory->supplier_managed
                                || ($currentCategory->parent_id === null && in_array($currentCategory->slug, ['accessoires', 'pieces-detachees'], true));
                            if ($supplierProduct->product && $canUpdateLinkedProduct) {
                                $supplierProduct->product->update([
                                    'category_id' => $subcategory->id,
                                    'family' => $taxonomy['root'],
                                    'subcategory' => $taxonomy['subcategory'],
                                ]);
                                $linked++;
                            }
                        });
                        $processed++;
                    }
                });

            $created = count(array_unique($createdCategoryIds));
            $run->update([
                'status' => 'success',
                'products_seen' => $processed,
                'variants_seen' => $processed,
                'mapped_count' => $linked,
                'updated_count' => $assigned,
                'message' => "Classement terminé : {$assigned} référence(s) associée(s), {$created} catégorie(s) créée(s).",
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_count' => 1,
                'message' => Str::limit($exception->getMessage(), 2000),
                'finished_at' => now(),
            ]);
            throw $exception;
        }

        return $run->refresh();
    }

    private function classify(SupplierProduct $supplierProduct): array
    {
        $name = $this->normalize($supplierProduct->name.' '.$supplierProduct->variant_name);
        $source = $this->normalize((string) $supplierProduct->source_category);

        if (str_contains($name, 'batterie')) {
            return $this->taxonomy('Pièces détachées', 'Batteries téléphone', 'Batteries de remplacement pour smartphones.');
        }
        if (str_contains($name, 'ecran') || str_contains($name, 'lcd') || str_contains($name, 'oled')) {
            return $this->taxonomy('Pièces détachées', 'Écrans téléphone', 'Écrans et modules d’affichage pour smartphones.');
        }
        if (str_contains($source, 'pieces detachees')) {
            return $this->taxonomy('Pièces détachées', 'Composants téléphone', 'Composants et pièces techniques pour smartphones.');
        }
        if (str_contains($name, 'verre trempe') || str_contains($name, 'protection ecran')) {
            return $this->taxonomy('Accessoires', 'Protections écran', 'Verres trempés et protections pour écrans.');
        }
        if (str_contains($name, 'coque')) {
            return $this->taxonomy('Accessoires', 'Coques téléphone', 'Coques de protection pour smartphones.');
        }
        if (str_contains($name, 'etui') || str_contains($name, 'rabat') || str_contains($name, 'housse')) {
            return $this->taxonomy('Accessoires', 'Étuis et housses', 'Étuis, housses et protections avec rabat.');
        }
        if (str_contains($name, 'ecouteur') && (str_contains($name, 'bluetooth') || str_contains($name, 'sans fil') || str_contains($name, 'airpods') || str_contains($name, 'ultrapods'))) {
            return $this->taxonomy('Accessoires', 'Écouteurs sans fil', 'Écouteurs Bluetooth et solutions audio sans fil.');
        }
        if (str_contains($name, 'ecouteur')) {
            return $this->taxonomy('Accessoires', 'Écouteurs filaires', 'Écouteurs filaires et kits mains libres.');
        }
        if (str_contains($name, 'cable') || str_contains($source, 'lightning') || str_contains($source, 'type c')) {
            return $this->taxonomy('Accessoires', 'Câbles et connectique', 'Câbles USB, USB-C, Lightning et adaptateurs.');
        }
        if (str_contains($name, 'chargeur') || str_contains($name, 'adaptateur secteur') || str_contains($source, 'prise secteur')) {
            return $this->taxonomy('Accessoires', 'Chargeurs', 'Chargeurs secteur et kits de recharge.');
        }

        return $this->taxonomy('Accessoires', 'Autres accessoires', 'Autres accessoires utiles pour appareils électroniques.');
    }

    private function taxonomy(string $root, string $subcategory, string $description): array
    {
        return [
            'root' => $root,
            'root_slug' => Str::slug($root),
            'root_description' => $root === 'Accessoires'
                ? 'Accessoires pour téléphones, tablettes et appareils électroniques.'
                : 'Pièces de remplacement et composants pour la réparation.',
            'subcategory' => $subcategory,
            'subcategory_slug' => Str::slug($root.'-'.$subcategory),
            'subcategory_description' => $description,
        ];
    }

    private function category(string $name, string $slug, ?Category $parent, string $description): array
    {
        $aliases = [
            'accessoires-cables-et-connectique' => 'cables',
            'accessoires-coques-telephone' => 'coques-et-protections',
        ];
        $category = Category::where('slug', $slug)->first()
            ?? Category::where('slug', $aliases[$slug] ?? '')->first()
            ?? Category::query()->get()->first(fn (Category $candidate) => $this->normalize($candidate->name) === $this->normalize($name))
            ?? new Category(['slug' => $slug]);
        $created = ! $category->exists;
        if ($created) {
            $category->fill([
                'parent_id' => $parent?->id,
                'name' => $name,
                'description' => $description,
                'active' => true,
                'supplier_managed' => true,
            ])->save();
        } elseif ($parent && $category->parent_id === null && $category->id !== $parent->id) {
            $category->update(['parent_id' => $parent->id]);
        }

        return [$category, $created];
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii($value))));
    }
}
