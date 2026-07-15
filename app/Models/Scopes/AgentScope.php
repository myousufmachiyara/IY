<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class AgentScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        if ($user->can('data.view_all')) {
            return;
        }

        if ($user->can('scope.by_agent') && in_array('agent_id', $model->getFillable(), true)) {
            $builder->where($model->getTable() . '.agent_id', $user->id);
        }
    }
}