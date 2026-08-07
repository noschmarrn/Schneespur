<?php

namespace App\Services\Installer;

class EnvFileWriter
{
    public function isWritable(): bool
    {
        return is_writable(base_path('.env'));
    }

    public function ensureEnvExists(): void
    {
        if (! file_exists(base_path('.env'))) {
            copy(base_path('.env.example'), base_path('.env'));
        }
    }

    public function get(string $key): ?string
    {
        $content = $this->getFullContent();
        if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $content, $matches)) {
            return trim($matches[1], '"\'');
        }

        return null;
    }

    public function set(string $key, string $value): bool
    {
        $this->ensureEnvExists();

        // ensureEnvExists() can fail quietly — a missing .env.example, or a
        // read-only project directory, which is an ordinary situation on shared
        // hosting. Check before reading rather than letting file_get_contents
        // raise a warning on the way to the same `return false`.
        if (! file_exists(base_path('.env'))) {
            return false;
        }

        $content = file_get_contents(base_path('.env'));
        if ($content === false) {
            return false;
        }

        $escaped = $this->formatValue($value);
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $key . '=' . $escaped, $content);
        } else {
            $content = rtrim($content, "\n") . "\n" . $key . '=' . $escaped . "\n";
        }

        return file_put_contents(base_path('.env'), $content, LOCK_EX) !== false;
    }

    public function setMany(array $keyValues): bool
    {
        foreach ($keyValues as $key => $value) {
            if (! $this->set($key, $value)) {
                return false;
            }
        }

        return true;
    }

    public function getFullContent(): string
    {
        if (! file_exists(base_path('.env'))) {
            return '';
        }

        return file_get_contents(base_path('.env')) ?: '';
    }

    private function formatValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s#"\'\\\\]/', $value) || str_contains($value, '${')) {
            return '"' . addcslashes($value, '"\\') . '"';
        }

        return $value;
    }
}
