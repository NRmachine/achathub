<?php

namespace App\Http\Controllers;

use App\Models\DataRightsRequest;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminSettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['settings' => SiteSetting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['settings' => ['required', 'array'], 'settings.*' => ['nullable', 'string', 'max:5000']]);
        $settings = SiteSetting::query()->whereIn('key', array_keys($data['settings']))->get()->keyBy('key');

        foreach ($data['settings'] as $key => $value) {
            $setting = $settings->get($key);
            if (! $setting) {
                throw ValidationException::withMessages(["settings.$key" => 'Ce réglage n’est pas autorisé.']);
            }

            $validated = validator(
                ['value' => $value],
                ['value' => ['nullable', $setting->type === 'email' ? 'email:rfc' : ($setting->type === 'url' ? 'url:http,https' : 'string'), 'max:5000']],
            )->validate();

            $setting->update(['value' => $validated['value'] ?? null]);
        }

        return back()->with('success', 'Contenus et informations légales mis à jour.');
    }

    public function rights()
    {
        return view('admin.data-rights', ['requests' => DataRightsRequest::with(['user', 'handler'])->latest()->paginate(30)]);
    }

    public function updateRight(Request $request, DataRightsRequest $dataRightsRequest)
    {
        $data = $request->validate(['status' => ['required', 'in:Nouvelle,En cours,Traitée,Refusée'], 'admin_response' => ['nullable', 'string', 'max:5000']]);
        $data += ['handled_by' => $request->user()->id, 'handled_at' => in_array($data['status'], ['Traitée', 'Refusée'], true) ? now() : null];
        $dataRightsRequest->update($data);
        return back()->with('success', 'Demande RGPD mise à jour.');
    }
}
