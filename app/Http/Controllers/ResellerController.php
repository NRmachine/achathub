<?php

namespace App\Http\Controllers;

use App\Models\ResellerRequest;
use App\Services\TransactionalMailer;
use Illuminate\Http\Request;

class ResellerController extends Controller
{
    public function index(Request $request)
    {
        $application = $request->user()?->resellerRequest;

        return view('reseller.index', compact('application'));
    }

    public function store(Request $request, TransactionalMailer $mailer)
    {
        abort_if($request->user()->resellerRequest()->whereIn('status', ['En attente', 'Approuvée'])->exists(), 422, 'Une demande active existe déjà pour ce compte.');

        $data = $request->validate(['business_name' => ['required', 'max:160'], 'manager_name' => ['required', 'max:120'], 'city' => ['required', 'max:100'], 'address' => ['required', 'max:500'], 'phone' => ['required', 'max:30'], 'email' => ['required', 'email'], 'business_type' => ['required', 'max:120'], 'formula' => ['required', 'in:Dépôt-vente,Achat en gros'], 'display_type' => ['required', 'in:Petit,Moyen,Grand'], 'categories' => ['nullable', 'max:1000'], 'message' => ['nullable', 'max:2000']]);
        $data['user_id'] = $request->user()->id;
        $data['email'] = $request->user()->email;
        $data['status'] = 'En attente';
        $application = ResellerRequest::create($data);
        $mailer->professionalApplicationCreated($request->user(), $application);

        return redirect()->route('reseller.dashboard')->with('success', 'Votre demande est enregistrée. Vous serez informé après sa validation.');
    }

    public function dashboard(Request $request)
    {
        $application = $request->user()->resellerRequest;
        if ($request->user()->role === 'reseller' && $application?->status === 'Approuvée') {
            return redirect()->route('pro.account');
        }

        $orders = $request->user()->professionalOrders()->with('items')->latest()->limit(10)->get();

        return view('reseller.dashboard', compact('application', 'orders'));
    }
}
