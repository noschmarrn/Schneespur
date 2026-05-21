<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmDsgvoRequest;
use App\Models\DsgvoConfirmation;
use App\Models\Setting;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DsgvoOnboardingController extends Controller
{
    public function show(): View
    {
        [$text, $version] = $this->currentTemplate();

        $text = $this->replacePlaceholders($text);

        $dsgvoHtml = Str::markdown($text, ['html_input' => 'strip']);

        $companyDataMissing = empty(Setting::get('company_name'));

        return view('onboarding.dsgvo', [
            'dsgvoHtml' => $dsgvoHtml,
            'templateVersion' => $version,
            'companyDataMissing' => $companyDataMissing,
        ]);
    }

    public function confirm(ConfirmDsgvoRequest $request): Response
    {
        if (empty(Setting::get('company_name'))) {
            return redirect()->route('onboarding.dsgvo')
                ->with('error', __('dsgvo.company_data_missing_title'));
        }

        [$text, $version] = $this->currentTemplate();

        DsgvoConfirmation::create([
            'driver_id' => $request->user()->id,
            'confirmed_at' => now(),
            'signed_by' => $request->validated('signed_by'),
            'notice_text_snapshot' => $text,
            'notice_language' => 'de',
            'template_version' => $version,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $user = $request->user();
        $user->dsgvo_informed_at = now();
        $user->confirmed_version = $version;
        $user->save();

        return redirect()->route('dashboard')
            ->with('success', __('dsgvo.flash_confirmed'));
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function currentTemplate(): array
    {
        $text = Setting::get('dsgvo_template_markdown');
        $version = (int) Setting::get('dsgvo_template_version', 1);

        if ($text === null) {
            $text = view(self::resolveDefaultTemplateView())->render();
        }

        return [$text, $version];
    }

    public static function resolveDefaultTemplateView(): string
    {
        $locale = app()->getLocale();
        $localized = "dsgvo.default-template-{$locale}";

        return view()->exists($localized) ? $localized : 'dsgvo.default-template';
    }

    private function replacePlaceholders(string $text): string
    {
        return dsgvo_apply_company_placeholders($text);
    }
}
