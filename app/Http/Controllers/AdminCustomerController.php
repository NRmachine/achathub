<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCustomerController extends Controller
{
    public function show(User $user)
    {
        abort_if($user->role === 'admin', 404);
        $user->load(['resellerRequest', 'conversation']);

        return view('admin.customer-show', [
            'customer' => $user,
            'orders' => $user->orders()->with('items.product')->latest()->paginate(10, ['*'], 'boutique'),
            'professionalOrders' => $user->professionalOrders()->with('items')->latest()->paginate(10, ['*'], 'pro'),
            'preorders' => $user->professionalPreorders()->with('product')->latest()->limit(20)->get(),
            'classicTotal' => $user->orders()->where('payment_status', 'Payé')->sum('total'),
            'professionalTotal' => $user->professionalOrders()->where('payment_status', 'Payé')->sum('total_ttc'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->role === 'admin', 422);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'role' => ['required', 'in:customer,reseller'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
            'blocked' => ['nullable', 'boolean'],
        ]);
        if ($data['role'] === 'reseller') {
            abort_unless($user->resellerRequest()->where('status', 'Approuvée')->exists(), 422, 'Le dossier professionnel doit être approuvé avant d’activer cet accès.');
        }
        $data['blocked'] = $request->boolean('blocked');
        $data['last_admin_update_at'] = now();
        $user->update($data);

        return back()->with('success', 'Profil et accès mis à jour sans modifier le mot de passe.');
    }
}
