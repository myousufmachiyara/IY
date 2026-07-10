<?php

namespace App\Models\Concerns;

use App\Models\Scopes\AgentScope;

trait ScopedToAgent
{
    public static function bootScopedToAgent(): void
    {
        static::addGlobalScope(new AgentScope);
    }

    /** Escape hatch when you deliberately need to bypass the scope. */
    public function scopeAllAgents($query)
    {
        return $query->withoutGlobalScope(AgentScope::class);
    }
}