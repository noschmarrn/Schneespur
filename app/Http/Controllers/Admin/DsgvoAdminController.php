<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DsgvoConfirmation;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DsgvoAdminController extends Controller
{
    public function index(): View
    {
        $markdown = Setting::get('dsgvo_template_markdown');
        $version = (int) Setting::get('dsgvo_template_version', 1);

        if ($markdown === null) {
            $markdown = view('dsgvo.default-template')->render();
        }

        $previewHtml = Str::markdown($this->replacePlaceholders($markdown), ['html_input' => 'strip']);
        $confirmationCount = DsgvoConfirmation::where('template_version', $version)->count();

        return view('admin.dsgvo.index', [
            'markdown' => $markdown,
            'previewHtml' => $previewHtml,
            'version' => $version,
            'confirmationCount' => $confirmationCount,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'markdown' => 'required|string|min:50|max:200000',
            'substantial_change' => 'nullable|boolean',
        ]);

        Setting::set('dsgvo_template_markdown', $validated['markdown']);

        if ($request->boolean('substantial_change')) {
            $currentVersion = (int) Setting::get('dsgvo_template_version', 1);
            Setting::set('dsgvo_template_version', $currentVersion + 1, 'int');

            return redirect()->back()
                ->with('success', __('dsgvo.flash_template_updated_substantial'));
        }

        return redirect()->back()
            ->with('success', __('dsgvo.flash_template_updated'));
    }

    public function preview(Request $request): Response
    {
        $request->validate([
            'markdown' => 'required|string',
        ]);

        $html = Str::markdown($this->replacePlaceholders($request->markdown), ['html_input' => 'strip']);

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    public function confirmations(Request $request): View
    {
        $query = DsgvoConfirmation::with('driver')
            ->orderByDesc('confirmed_at');

        if ($search = $request->input('search')) {
            $query->whereHas('driver', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $confirmations = $query->paginate(25)->withQueryString();

        return view('admin.dsgvo.confirmations', [
            'confirmations' => $confirmations,
        ]);
    }

    private function replacePlaceholders(string $text): string
    {
        $companyName = Setting::get('company_name', '');
        $street = Setting::get('company_street', '');
        $zip = Setting::get('company_zip', '');
        $city = Setting::get('company_city', '');
        $email = Setting::get('company_email', '');
        $dpo = Setting::get('dpo_contact', '');
        $dpoEmail = Setting::get('dpo_email', '');

        $address = trim("$street, $zip $city", ', ');

        $replacements = [
            '[Firmenname eintragen]' => $companyName ?: '[Firmenname eintragen]',
            '[Adresse eintragen]' => $address ?: '[Adresse eintragen]',
            '[E-Mail-Adresse eintragen]' => $email ?: '[E-Mail-Adresse eintragen]',
            '[DPO-E-Mail-Adresse eintragen]' => $dpoEmail ?: '[DPO-E-Mail-Adresse eintragen]',
            '[Datenschutzbeauftragter / Ansprechpartner eintragen]' => $dpo ?: '[Datenschutzbeauftragter / Ansprechpartner eintragen]',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    public function showConfirmation(int $id): View
    {
        $confirmation = DsgvoConfirmation::findOrFail($id);
        $confirmation->load('driver');

        $snapshotHtml = Str::markdown($confirmation->notice_text_snapshot, ['html_input' => 'strip']);

        return view('admin.dsgvo.confirmation-show', [
            'confirmation' => $confirmation,
            'snapshotHtml' => $snapshotHtml,
        ]);
    }
}
