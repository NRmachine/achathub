<?php

namespace App\Http\Controllers;

use App\Models\ProductReturn;
use Illuminate\Http\Request;

class AdminReturnController extends Controller
{
    public function index()
    {
        return view('admin.returns', ['returns' => ProductReturn::with(['user', 'order', 'items'])->latest()->paginate(30)]);
    }

    public function update(Request $request, ProductReturn $productReturn)
    {
        $data = $request->validate([
            'status' => ['required', 'in:Demandé,Autorisé,En transit,Reçu,Remboursé,Refusé'],
            'admin_notes' => ['nullable', 'max:2000'],
        ]);
        if ($data['status'] === 'Reçu' && ! $productReturn->received_at) {
            $data['received_at'] = now();
        }
        if ($data['status'] === 'Remboursé' && ! $productReturn->refunded_at) {
            $data['refunded_at'] = now();
        }
        $productReturn->update($data);

        return back()->with('success', 'Demande de retour mise à jour.');
    }
}
