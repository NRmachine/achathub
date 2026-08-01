<?php

namespace App\Http\Controllers;

use App\Models\ResellerRequest;
use App\Models\User;
use App\Services\TransactionalMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProfessionalRegistrationController extends Controller
{
    public function create()
    {
        return view('auth.register-professional');
    }

    public function store(Request $request, TransactionalMailer $mailer)
    {
        $validator = Validator::make($request->all(), [
            'company_type' => ['required', 'in:Micro-entreprise,Entreprise individuelle,Société,Association'],
            'legal_form' => ['required', 'in:Micro-entrepreneur,EI,EURL,SARL,SASU,SAS,SA,SCI,Association,Autre'],
            'business_name' => ['required', 'string', 'max:160'],
            'commercial_name' => ['nullable', 'string', 'max:160'],
            'siren' => ['required', 'regex:/^\d{9}$/', 'unique:reseller_requests,siren'],
            'siret' => ['required', 'regex:/^\d{14}$/', 'unique:reseller_requests,siret'],
            'vat_number' => ['nullable', 'regex:/^FR[A-Z0-9]{2}\d{9}$/i', 'max:20'],
            'activity' => ['required', 'string', 'max:160'],
            'manager_first_name' => ['required', 'string', 'max:80'],
            'manager_last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'postal_code' => ['required', 'regex:/^\d{5}$/'],
            'city' => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
            'terms' => ['accepted'],
        ], [
            'siren.regex' => 'Le SIREN doit contenir exactement 9 chiffres.',
            'siret.regex' => 'Le SIRET doit contenir exactement 14 chiffres.',
            'vat_number.regex' => 'Le numéro de TVA intracommunautaire français est invalide.',
            'postal_code.regex' => 'Le code postal doit contenir 5 chiffres.',
            'terms.accepted' => 'Vous devez accepter les conditions du programme professionnel.',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->siren && $request->siret && substr($request->siret, 0, 9) !== $request->siren) {
                $validator->errors()->add('siret', 'Les 9 premiers chiffres du SIRET doivent correspondre au SIREN.');
            }
        });
        $data = $validator->validate();

        $user = DB::transaction(function () use ($data) {
            $manager = trim($data['manager_first_name'].' '.$data['manager_last_name']);
            $user = User::create([
                'name' => $manager,
                'email' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'].', '.$data['postal_code'].' '.$data['city'],
                'password' => Hash::make($data['password']),
                'role' => 'customer',
                'terms_accepted_at' => now(),
                'privacy_version' => '2026-07-15',
            ]);

            ResellerRequest::create([
                'user_id' => $user->id,
                'business_name' => $data['business_name'],
                'company_type' => $data['company_type'],
                'legal_form' => $data['legal_form'],
                'commercial_name' => $data['commercial_name'] ?? null,
                'siren' => $data['siren'],
                'siret' => $data['siret'],
                'vat_number' => $data['vat_number'] ?? null,
                'manager_name' => $manager,
                'city' => $data['city'],
                'address' => $data['address'],
                'postal_code' => $data['postal_code'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'business_type' => $data['activity'],
                'activity' => $data['activity'],
                'formula' => 'Achat en gros',
                'display_type' => 'À définir',
                'status' => 'En attente',
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $application = $user->resellerRequest()->firstOrFail();
        $mailer->verification($user);
        $mailer->professionalApplicationCreated($user, $application);

        return redirect()->route('reseller.dashboard')->with('success', 'Votre compte professionnel est créé. Confirmez votre e-mail pendant que notre équipe vérifie votre SIRET.');
    }
}
