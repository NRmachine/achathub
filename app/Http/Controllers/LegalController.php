<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;

class LegalController extends Controller
{
    public function terms()
    {
        return $this->page('legal.terms', 'Conditions générales');
    }

    public function privacy()
    {
        return $this->page('legal.privacy', 'Politique de confidentialité');
    }

    public function cookies()
    {
        return $this->page('legal.cookies', 'Politique des cookies');
    }

    public function notice()
    {
        return $this->page('legal.notice', 'Mentions légales');
    }

    public function consent(Request $request)
    {
        $data = $request->validate(['choice' => ['required', 'in:accepted,refused']]);

        return back()->withCookie(cookie(
            'cookie_consent', $data['choice'], 60 * 24 * 180, '/', null,
            app()->environment('production'), true, false, 'Lax'
        ));
    }

    private function page(string $view, string $title)
    {
        $settings = SiteSetting::query()->pluck('value', 'key');
        $missingLegalSettings = $settings
            ->filter(fn (?string $value): bool => blank($value) || str_starts_with($value, 'À renseigner') || str_starts_with($value, 'À compléter'))
            ->keys();

        return view($view, compact('title', 'settings', 'missingLegalSettings'));
    }
}
