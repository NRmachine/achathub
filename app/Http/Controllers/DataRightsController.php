<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DataRightsController extends Controller
{
    public function index(Request $request)
    {
        return view('legal.data-rights', [
            'requests' => $request->user()->dataRightsRequests()->latest()->get(),
            'layout' => $request->user()->role === 'reseller' ? 'layouts.pro' : 'layouts.account',
            'section' => $request->user()->role === 'reseller' ? 'pro-content' : 'account-content',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:Accès,Rectification,Suppression,Limitation,Opposition,Portabilité'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);
        $request->user()->dataRightsRequests()->create($data);

        return back()->with('success', 'Votre demande a été enregistrée. Notre équipe vous répondra dans votre espace sécurisé.');
    }
}
