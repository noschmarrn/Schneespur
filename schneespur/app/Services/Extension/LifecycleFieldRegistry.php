<?php

namespace App\Services\Extension;

use App\Contracts\LifecycleFieldHandler;
use App\Enums\LifecyclePoint;
use App\Models\User;
use App\Services\Diagnostic\DiagnosticManager;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * Registry for module-contributed lifecycle fields.
 *
 * Module authors — three things to know:
 *
 * 1. Permission gating applies to persist too.
 *    If your contribution carries a `permission` key, the `persist` handler
 *    runs only for users who pass that Gate check — consistent with
 *    render() and rules().  Omit `permission` for fields visible to all.
 *
 * 2. Namespace your field keys.
 *    Contribution rules are merged via array_merge (last-wins) and extracted
 *    back out via fieldKeys().  Use module-prefixed keys (e.g. `lager_salt_used`)
 *    to avoid collisions with core fields such as `notes` or `type`.
 *
 * 3. Manual jobs bypass lifecycle hooks.
 *    createManualJob does not render @lifecycleFields or call persist();
 *    lifecycle fields are only captured on the driver shift/job-start/job-end flows.
 */
class LifecycleFieldRegistry extends ExtensionRegistry
{
    public function registerField(LifecyclePoint $point, string $slug, array $contribution): void
    {
        $this->register($slug, array_merge([
            'slug' => $slug,
            'point' => $point,
            'view' => null,
            'rules' => [],
            'persist' => null,
            'order' => 100,
            'permission' => null,
        ], $contribution, ['slug' => $slug, 'point' => $point]));
    }

    /**
     * Ordered, permission-filtered contributions for a point.
     *
     * @return array<int, array<string, mixed>>
     */
    public function contributions(LifecyclePoint $point, ?Authenticatable $user = null): array
    {
        $entries = array_filter(
            $this->items,
            fn (array $e) => $e['point'] === $point
                && ($e['permission'] === null || $user === null || Gate::forUser($user)->allows($e['permission'])),
        );

        $entries = array_values($entries);
        usort($entries, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $entries;
    }

    /**
     * Merged validation rules for a point.
     *
     * @return array<string, mixed>
     */
    public function rules(LifecyclePoint $point, ?Authenticatable $user = null): array
    {
        $rules = [];

        foreach ($this->contributions($point, $user) as $entry) {
            $rules = array_merge($rules, $entry['rules']);
        }

        return $rules;
    }

    /** @return string[] */
    public function fieldKeys(LifecyclePoint $point): array
    {
        return array_keys($this->rules($point));
    }

    public function render(LifecyclePoint $point, ?Authenticatable $user = null): string
    {
        $html = '';

        foreach ($this->contributions($point, $user) as $entry) {
            if ($entry['view'] === null) {
                continue;
            }

            $html .= View::make($entry['view'], ['user' => $user])->render();
        }

        return $html;
    }

    public function persist(LifecyclePoint $point, Model $entity, array $validated, User $user): void
    {
        foreach ($this->contributions($point, $user) as $entry) {
            $handler = $entry['persist'];

            if ($handler === null) {
                continue;
            }

            try {
                DB::transaction(function () use ($handler, $entity, $validated, $user) {
                    if ($handler instanceof Closure) {
                        $handler($entity, $validated, $user);

                        return;
                    }

                    $instance = is_string($handler) ? app($handler) : $handler;

                    if ($instance instanceof LifecycleFieldHandler) {
                        $instance->handle($entity, $validated, $user);

                        return;
                    }

                    $instance->handle($entity, $validated, $user);
                });
            } catch (\Throwable $e) {
                try {
                    app(DiagnosticManager::class)->report('lifecycle_field_persist_failed', [
                        'error' => $e->getMessage(),
                        'exception_class' => get_class($e),
                    ], [
                        'source' => 'LifecycleFieldRegistry',
                        'slug' => $entry['slug'],
                        'point' => $point->value,
                    ]);
                } catch (\Throwable) {
                    // Never let diagnostic reporting break the original flow
                }

                Log::warning("LifecycleFieldRegistry: persist handler '{$entry['slug']}' failed at {$point->value}: {$e->getMessage()}");
            }
        }
    }
}
