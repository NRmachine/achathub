<?php

namespace App\Http\Controllers;

use App\Models\DataRightsRequest;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['settings' => SiteSetting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['settings' => ['required', 'array'], 'settings.*' => ['nullable', 'string', 'max:5000']]);
        foreach ($data['settings'] as $key => $value) {
            SiteSetting::where('key', $key)->update(['value' => $value]);
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
