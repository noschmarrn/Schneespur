<?php

namespace App\Services\Translation;

use Illuminate\Translation\Translator;

class BrandedTranslator extends Translator
{
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        if (! array_key_exists('app_name', $replace)) {
            $replace['app_name'] = $this->resolveAppName();
        }

        return parent::get($key, $replace, $locale, $fallback);
    }

    private function resolveAppName(): string
    {
        try {
            return brand();
        } catch (\Throwable) {
            return (string) config('app.name', 'Schneespur');
        }
    }
}
