<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DsgvoOnboardingController;
use App\Models\DsgvoConfirmation;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DsgvoAdminController extends Controller
{
    public function index(): View
    {
        Gate::authorize('dsgvo.view');

        $markdown = Setting::get('dsgvo_template_markdown');
        $version = (int) Setting::get('dsgvo_template_version', 1);

        if ($markdown === null) {
            $markdown = view(DsgvoOnboardingController::resolveDefaultTemplateView())->render();
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
        Gate::authorize('dsgvo.edit');

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
        Gate::authorize('dsgvo.edit');

        $request->validate([
            'markdown' => 'required|string',
        ]);

        $html = Str::markdown($this->replacePlaceholders($request->markdown), ['html_input' => 'strip']);

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    public function confirmations(Request $request): View
    {
        Gate::authorize('dsgvo.view');

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
        return dsgvo_apply_company_placeholders($text);
    }

    public function showConfirmation(int $id): View
    {
        Gate::authorize('dsgvo.view');

        $confirmation = DsgvoConfirmation::findOrFail($id);
        $confirmation->load('driver');

        $snapshotHtml = Str::markdown($confirmation->notice_text_snapshot, ['html_input' => 'strip']);

        return view('admin.dsgvo.confirmation-show', [
            'confirmation' => $confirmation,
            'snapshotHtml' => $snapshotHtml,
        ]);
    }
}
