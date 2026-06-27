<?php

namespace App\Services\Extension;

/**
 * Lets a module take over the public root URL ("/") to serve a public homepage
 * instead of the default login redirect. A module registers a handler during
 * its ServiceProvider boot(); the core "/" route consults this registry at
 * request time.
 */
class PublicHomepageRegistry
{
    /** @var (callable(): mixed)|null */
    protected $handler = null;

    /**
     * Register the handler that produces the homepage response.
     * The handler may return a Response, a View, or a string.
     *
     * @param  callable(): mixed  $handler
     */
    public function register(callable $handler): void
    {
        $this->handler = $handler;
    }

    public function has(): bool
    {
        return $this->handler !== null;
    }

    /**
     * Invoke the registered handler to produce the homepage response.
     */
    public function render(): mixed
    {
        return ($this->handler)();
    }
}
