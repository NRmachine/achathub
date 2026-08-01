@extends('layouts.admin')
@section('title','Importer un produit | AchatHub')
@section('admin-content')
<div class="admin-page-heading">
    <div><small>APPROVISIONNEMENT</small><h1>Importer dans AchatHub</h1><p>Transformez cette variante fournisseur en fiche boutique, puis contrôlez son contenu avant publication.</p></div>
    <a class="btn btn-outline-dark" href="{{ route('admin.supplier.index') }}"><i class="bi bi-arrow-left me-1"></i> Retour à l’agent</a>
</div>

@if($errors->any())<div class="alert alert-danger"><strong>L’importation n’a pas été enregistrée.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<form method="post" action="{{ route('admin.supplier.store-product', $supplierProduct) }}">@csrf
    <div class="row g-4">
        <div class="col-xl-4">
            <section class="admin-surface supplier-import-summary">
                <div class="supplier-preview-main">
                    @if($supplierProduct->image)<img src="{{ $supplierProduct->image }}" alt="{{ $supplierProduct->name }}">@else<i class="bi bi-image fs-1 text-secondary"></i>@endif
                </div>
                @if(count($supplierProduct->images ?? []) > 1)<div class="supplier-gallery mt-2">@foreach(array_slice($supplierProduct->images,0,5) as $image)<img src="{{ $image }}" alt="">@endforeach</div>@endif
                <div class="pt-3">
                    <span class="badge text-bg-light mb-2">LCD Phone</span>
                    <h2 class="h5">{{ $supplierProduct->name }}</h2>
                    @if($supplierProduct->variant_name)<p class="text-secondary">{{ $supplierProduct->variant_name }}</p>@endif
                    <dl class="supplier-data-list">
                        <div><dt>Référence</dt><dd>{{ $supplierProduct->supplier_reference ?: 'Non fournie' }}</dd></div>
                        <div><dt>Prix d’achat unitaire</dt><dd>{{ $supplierProduct->purchase_price !== null ? number_format((float)$supplierProduct->purchase_price,2,',',' ').' €' : 'Indisponible' }}</dd></div>
                        <div><dt>Commande minimale</dt><dd>{{ number_format($supplierProduct->minimum_order_quantity,0,',',' ') }} unité(s)</dd></div>
                        <div><dt>Coût du lot minimum</dt><dd>{{ number_format((float)$supplierProduct->purchase_price * max(1,$supplierProduct->minimum_order_quantity),2,',',' ') }} €</dd></div>
                        <div><dt>Stock fournisseur brut</dt><dd>{{ number_format($supplierProduct->supplier_stock,0,',',' ') }}</dd></div>
                    </dl>
                    <a href="{{ $supplierProduct->supplier_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary w-100">Ouvrir la fiche fournisseur <i class="bi bi-box-arrow-up-right ms-1"></i></a>
                </div>
            </section>
        </div>

        <div class="col-xl-8">
            <section class="admin-surface mb-3">
                <div class="d-flex align-items-start gap-3 mb-3"><span class="supplier-icon"><i class="bi bi-tags"></i></span><div><h2 class="h5 mb-1">Identification et classement</h2><p class="text-secondary small mb-0">Le nom, la catégorie et le SKU seront visibles dans votre catalogue.</p></div></div>
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Nom du produit</label><input class="form-control" name="name" value="{{ old('name',$defaultName) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Référence SKU AchatHub</label><input class="form-control" name="sku" value="{{ old('sku',$defaultSku) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Catégorie</label><select class="form-select" name="category_id" required><option value="">Choisir</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id',$supplierProduct->suggested_category_id)==$category->id)>{{ $category->path_label }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Famille</label><input class="form-control" name="family" value="{{ old('family',$defaultFamily) }}" placeholder="Ex. Accessoires"></div>
                    <div class="col-md-4"><label class="form-label">Marque</label><input class="form-control" name="brand" value="{{ old('brand',$supplierProduct->brand) }}"></div>
                </div>
            </section>

            <section class="admin-surface mb-3">
                <div class="d-flex align-items-start gap-3 mb-3"><span class="supplier-icon"><i class="bi bi-calculator"></i></span><div><h2 class="h5 mb-1">Prix et stock vendable</h2><p class="text-secondary small mb-0">Le prix proposé est une aide basée sur le coût d’achat. Vous gardez la décision finale.</p></div></div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Prix de vente TTC</label><div class="input-group"><input class="form-control" type="number" step=".01" min=".01" name="price" value="{{ old('price',number_format($suggestedPrice,2,'.','')) }}" required><span class="input-group-text">€</span></div><small class="text-secondary">Suggestion avec marge incluse</small></div>
                    <div class="col-md-4"><label class="form-label">Ancien prix <span class="text-secondary">(facultatif)</span></label><div class="input-group"><input class="form-control" type="number" step=".01" min=".01" name="old_price" value="{{ old('old_price') }}"><span class="input-group-text">€</span></div></div>
                    <div class="col-md-4"><label class="form-label">Unités fournisseur par vente</label><input class="form-control" type="number" min="1" max="100000" name="stock_divisor" value="{{ old('stock_divisor',max(1,$supplierProduct->minimum_order_quantity)) }}" required><small class="text-secondary">Mettez 1 si AchatHub revend à l’unité.</small></div>
                    <div class="col-12"><div class="supplier-stock-note"><i class="bi bi-box-seam"></i><span>Avec le conditionnement proposé, le stock initial sera de <strong>{{ number_format(intdiv($supplierProduct->supplier_stock,max(1,$supplierProduct->minimum_order_quantity)),0,',',' ') }} article(s) vendable(s)</strong>. Il sera recalculé avec la valeur enregistrée.</span></div></div>
                </div>
            </section>

            <section class="admin-surface mb-3">
                <h2 class="h5">Description et publication</h2>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Description client</label><textarea class="form-control" rows="6" name="description" maxlength="5000">{{ old('description',$supplierProduct->description) }}</textarea></div>
                    <div class="col-md-6"><div class="form-check form-switch supplier-switch"><input class="form-check-input" type="checkbox" name="sync_stock" value="1" id="sync_stock" @checked(old('sync_stock',true))><label class="form-check-label" for="sync_stock"><strong>Synchroniser le stock</strong><small>Le stock suivra automatiquement le fournisseur.</small></label></div></div>
                    <div class="col-md-6"><div class="form-check form-switch supplier-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="active" @checked(old('active',false))><label class="form-check-label" for="active"><strong>Publier immédiatement</strong><small>Laissez désactivé pour vérifier le produit en brouillon.</small></label></div></div>
                </div>
            </section>

            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end"><a class="btn btn-light" href="{{ route('admin.supplier.index') }}">Annuler</a><button class="btn btn-ah"><i class="bi bi-plus-circle me-1"></i> Créer le produit AchatHub</button></div>
        </div>
    </div>
</form>
@endsection
