<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminConversationController;
use App\Http\Controllers\AdminCustomerController;
use App\Http\Controllers\AdminReturnController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminSupplierController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DataRightsController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\ProfessionalRegistrationController;
use App\Http\Controllers\ProfessionalStoreController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SupplierCronController;
use App\Services\TransactionalMailer;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/internal/cron/fournisseur', SupplierCronController::class)
    ->middleware('throttle:6,1')
    ->name('internal.cron.supplier');

Route::get('/', [StoreController::class, 'index'])->name('home');
Route::get('/session/csrf', fn () => response()->json(['token' => csrf_token()]))
    ->name('session.csrf');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/produits/{product}', [StoreController::class, 'show'])->name('products.show');
Route::get('/panier', [CartController::class, 'index'])->name('cart.index');
Route::post('/panier/{product}', [CartController::class, 'add'])->name('cart.add');
Route::post('/acheter/{product}', [CartController::class, 'buyNow'])->name('cart.buy-now');
Route::patch('/panier/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/panier/{product}', [CartController::class, 'remove'])->name('cart.remove');
Route::get('/support', [SupportController::class, 'index'])->name('support.index');
Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:6,1')->name('support.store');
Route::get('/devenir-revendeur', [ResellerController::class, 'index'])->name('reseller.index');
Route::get('/conditions-generales', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/confidentialite', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/cookies', [LegalController::class, 'cookies'])->name('legal.cookies');
Route::get('/mentions-legales', [LegalController::class, 'notice'])->name('legal.notice');
Route::post('/cookies/consentement', [LegalController::class, 'consent'])->middleware('throttle:20,1')->name('cookies.consent');
Route::get('/commande', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/commande', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/commande/suivi/{order:access_token}', [CheckoutController::class, 'guestShow'])->name('orders.guest.show');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/connexion', [AuthController::class, 'login'])->middleware('throttle:8,1')->name('login.store');
    Route::get('/connexion-professionnelle', [AuthController::class, 'professionalLoginForm'])->name('professional.login');
    Route::post('/connexion-professionnelle', [AuthController::class, 'professionalLogin'])->middleware('throttle:8,1')->name('professional.login.store');
    Route::get('/administration/connexion', [AuthController::class, 'adminLoginForm'])->name('admin.login');
    Route::post('/administration/connexion', [AuthController::class, 'adminLogin'])->middleware('throttle:5,1')->name('admin.login.store');
    Route::get('/inscription', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/inscription', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/inscription-professionnelle', [ProfessionalRegistrationController::class, 'create'])->name('professional.register');
    Route::post('/inscription-professionnelle', [ProfessionalRegistrationController::class, 'store'])->middleware('throttle:3,1')->name('professional.register.store');
    Route::get('/mot-de-passe-oublie', [AuthController::class, 'forgotPasswordForm'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [AuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reinitialiser-mot-de-passe/{token}', [AuthController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/reinitialiser-mot-de-passe', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/deconnexion', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/confirmer-mon-email', fn () => view('auth.verify-email'))->name('verification.notice');
    Route::get('/confirmer-mon-email/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        $target = $request->user()->resellerRequest()->exists() ? 'reseller.dashboard' : 'account.index';

        return redirect()->route($target)->with('success', 'Votre adresse e-mail est confirmée.');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/confirmation-email', function (Request $request, TransactionalMailer $mailer) {
        if (! $request->user()->hasVerifiedEmail()) {
            $mailer->verification($request->user());
        }

        return back()->with('success', 'Un nouveau lien de confirmation vient d’être envoyé.');
    })->middleware('throttle:6,1')->name('verification.send');

    Route::get('/messagerie', [ConversationController::class, 'index'])->name('messages.index');
    Route::post('/messagerie', [ConversationController::class, 'store'])->middleware('throttle:15,1')->name('messages.store');
    Route::get('/mes-donnees', [DataRightsController::class, 'index'])->name('data-rights.index');
    Route::post('/mes-donnees', [DataRightsController::class, 'store'])->middleware('throttle:3,1')->name('data-rights.store');
    Route::post('/devenir-revendeur', [ResellerController::class, 'store'])->middleware('throttle:3,1')->name('reseller.store');
    Route::get('/espace-revendeur', [ResellerController::class, 'dashboard'])->name('reseller.dashboard');

    Route::middleware('customer.portal')->group(function () {
        Route::get('/mon-compte', [AccountController::class, 'index'])->name('account.index');
        Route::get('/mon-compte/commandes', [AccountController::class, 'orders'])->name('account.orders');
        Route::get('/mon-compte/commandes/{order}', [AccountController::class, 'order'])->name('account.order');
        Route::get('/mon-compte/commandes/{order}/facture', [AccountController::class, 'invoice'])->name('account.invoice');
        Route::get('/mon-compte/commandes/{order}/retour', [ReturnController::class, 'create'])->name('account.returns.create');
        Route::post('/mon-compte/commandes/{order}/retour', [ReturnController::class, 'store'])->name('account.returns.store');
        Route::get('/mon-compte/retours/{productReturn}', [ReturnController::class, 'show'])->name('account.returns.show');
        Route::get('/mon-compte/favoris', [AccountController::class, 'wishlist'])->name('account.wishlist');
        Route::post('/favoris/{product}', [AccountController::class, 'toggleWishlist'])->name('wishlist.toggle');
        Route::post('/produits/{product}/avis', [ProductReviewController::class, 'store'])->middleware('throttle:6,1')->name('products.reviews.store');
        Route::get('/mon-compte/parametres', [AccountController::class, 'settings'])->name('account.settings');
        Route::patch('/mon-compte/parametres', [AccountController::class, 'update'])->name('account.update');
    });
});

Route::prefix('pro')->name('pro.')->middleware(['auth', 'reseller'])->group(function () {
    Route::get('/', [ProfessionalStoreController::class, 'index'])->name('index');
    Route::get('/produits/{product}', [ProfessionalStoreController::class, 'showProduct'])->name('products.show');
    Route::get('/presentoirs', [ProfessionalStoreController::class, 'displays'])->name('displays');
    Route::get('/presentoirs/{display:slug}', [ProfessionalStoreController::class, 'show'])->name('show');
    Route::get('/compte', [ProfessionalStoreController::class, 'account'])->name('account');
    Route::get('/commandes/{professionalOrder}/facture', [ProfessionalStoreController::class, 'invoice'])->name('invoice');
    Route::get('/support', [ProfessionalStoreController::class, 'support'])->name('support');
    Route::get('/panier', [ProfessionalStoreController::class, 'cart'])->name('cart');
    Route::post('/panier/presentoirs/{display}', [ProfessionalStoreController::class, 'addDisplay'])->name('cart.add');
    Route::patch('/panier/presentoirs/{display}', [ProfessionalStoreController::class, 'updateDisplay'])->name('cart.update');
    Route::delete('/panier/presentoirs/{display}', [ProfessionalStoreController::class, 'removeDisplay'])->name('cart.remove');
    Route::post('/panier/produits/{product}', [ProfessionalStoreController::class, 'addProduct'])->name('cart.products.add');
    Route::patch('/panier/produits/{product}', [ProfessionalStoreController::class, 'updateProduct'])->name('cart.products.update');
    Route::delete('/panier/produits/{product}', [ProfessionalStoreController::class, 'removeProduct'])->name('cart.products.remove');
    Route::post('/precommandes/{product}', [ProfessionalStoreController::class, 'preorder'])->name('products.preorder');
    Route::delete('/precommandes/{professionalPreorder}', [ProfessionalStoreController::class, 'destroyPreorder'])->name('preorders.destroy');
    Route::get('/commande', [ProfessionalStoreController::class, 'checkout'])->name('checkout');
    Route::post('/commande', [ProfessionalStoreController::class, 'order'])->name('order');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::get('/produits', [AdminController::class, 'products'])->name('products');
    Route::get('/produits/nouveau', [AdminController::class, 'productForm'])->name('products.create');
    Route::post('/produits', [AdminController::class, 'saveProduct'])->name('products.store');
    Route::get('/produits/{product}/modifier', [AdminController::class, 'productForm'])->name('products.edit');
    Route::put('/produits/{product}', [AdminController::class, 'saveProduct'])->name('products.update');
    Route::delete('/produits/{product}', [AdminController::class, 'deleteProduct'])->name('products.delete');
    Route::get('/commandes', [AdminController::class, 'orders'])->name('orders');
    Route::get('/commandes/{order}', [AccountController::class, 'order'])->name('orders.show');
    Route::patch('/commandes/{order}', [AdminController::class, 'updateOrder'])->name('orders.update');
    Route::get('/retours', [AdminReturnController::class, 'index'])->name('returns.index');
    Route::patch('/retours/{productReturn}', [AdminReturnController::class, 'update'])->name('returns.update');
    Route::get('/clients', [AdminController::class, 'customers'])->name('customers');
    Route::patch('/clients/{user}', [AdminController::class, 'toggleCustomer'])->name('customers.toggle');
    Route::get('/clients/{user}/profil', [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::patch('/clients/{user}/profil', [AdminCustomerController::class, 'update'])->name('customers.update');
    Route::get('/messagerie', [AdminConversationController::class, 'index'])->name('conversations.index');
    Route::get('/messagerie/{conversation}', [AdminConversationController::class, 'show'])->name('conversations.show');
    Route::post('/messagerie/{conversation}', [AdminConversationController::class, 'store'])->name('conversations.store');
    Route::patch('/messagerie/{conversation}/statut', [AdminConversationController::class, 'status'])->name('conversations.status');
    Route::get('/reglages', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/reglages', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::get('/droits-rgpd', [AdminSettingsController::class, 'rights'])->name('data-rights.index');
    Route::patch('/droits-rgpd/{dataRightsRequest}', [AdminSettingsController::class, 'updateRight'])->name('data-rights.update');
    Route::get('/messages', [AdminController::class, 'messages'])->name('messages');
    Route::patch('/messages/{message}', [AdminController::class, 'resolveMessage'])->name('messages.resolve');
    Route::get('/revendeurs', [AdminController::class, 'resellers'])->name('resellers');
    Route::patch('/revendeurs/{resellerRequest}', [AdminController::class, 'reviewReseller'])->name('resellers.review');
    Route::patch('/presentoirs/{display}', [AdminController::class, 'updateDisplay'])->name('displays.update');
    Route::patch('/produits-pro/{professionalProduct}', [AdminController::class, 'updateProfessionalProduct'])->name('professional-products.update');
    Route::patch('/commandes-pro/{professionalOrder}', [AdminController::class, 'updateProfessionalOrder'])->name('professional-orders.update');
    Route::patch('/precommandes-pro/{professionalPreorder}', [AdminController::class, 'updateProfessionalPreorder'])->name('professional-preorders.update');
    Route::get('/fournisseur', [AdminSupplierController::class, 'index'])->name('supplier.index');
    Route::post('/fournisseur/decouvrir', [AdminSupplierController::class, 'discover'])->name('supplier.discover');
    Route::post('/fournisseur/synchroniser', [AdminSupplierController::class, 'sync'])->name('supplier.sync');
    Route::post('/fournisseur/classer', [AdminSupplierController::class, 'categorize'])->name('supplier.categorize');
    Route::post('/fournisseur/arborescence', [AdminSupplierController::class, 'refreshCatalog'])->name('supplier.catalog.refresh');
    Route::post('/fournisseur/parcourir', [AdminSupplierController::class, 'crawlCatalog'])->name('supplier.catalog.crawl');
    Route::get('/fournisseur/produits/{supplierProduct}/importer', [AdminSupplierController::class, 'import'])->name('supplier.import');
    Route::post('/fournisseur/produits/{supplierProduct}/importer', [AdminSupplierController::class, 'storeProduct'])->name('supplier.store-product');
    Route::patch('/fournisseur/produits/{supplierProduct}', [AdminSupplierController::class, 'update'])->name('supplier.update');
});
