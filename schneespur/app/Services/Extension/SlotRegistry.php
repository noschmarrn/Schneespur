<?php

namespace App\Services\Extension;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class SlotRegistry extends ExtensionRegistry
{
    protected array $appends = [];
    protected array $replaces = [];

    public function append(string $slotName, string $viewPath, array $data = [], int $order = 100, ?string $permission = null): void
    {
        $this->appends[$slotName][] = [
            'view' => $viewPath,
            'data' => $data,
            'order' => $order,
            'permission' => $permission,
        ];
    }

    public function replace(string $slotName, string $viewPath, array $data = [], ?string $permission = null): void
    {
        if (isset($this->replaces[$slotName])) {
            Log::warning("SlotRegistry: slot '{$slotName}' already has a replace entry from view '{$this->replaces[$slotName]['view']}', overwriting with '{$viewPath}' (last-wins)");
        }

        $this->replaces[$slotName] = [
            'view' => $viewPath,
            'data' => $data,
            'permission' => $permission,
        ];
    }

    public function render(string $slotName, ?User $user = null): string
    {
        if (isset($this->replaces[$slotName])) {
            $replace = $this->replaces[$slotName];

            if ($replace['permission'] !== null && $user !== null && ! Gate::forUser($user)->allows($replace['permission'])) {
                return '';
            }

            return View::make($replace['view'], $replace['data'])->render();
        }

        if (! isset($this->appends[$slotName])) {
            return '';
        }

        $entries = $this->appends[$slotName];
        usort($entries, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        $html = '';
        foreach ($entries as $entry) {
            if ($entry['permission'] !== null && $user !== null && ! Gate::forUser($user)->allows($entry['permission'])) {
                continue;
            }

            $html .= View::make($entry['view'], $entry['data'])->render();
        }

        return $html;
    }

    public function getSlotNames(): array
    {
        return array_unique(array_merge(
            array_keys($this->appends),
            array_keys($this->replaces),
        ));
    }
}
