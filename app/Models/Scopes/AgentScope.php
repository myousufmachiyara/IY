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

        // No user (console/seeds/queues) or privileged roles => see everything.
        if (! $user || $user->isSuperAdmin() || $user->isAccountant()) {
            return;
        }

        // Sales agents are restricted to their own rows.
        if ($user->isSalesAgent()) {
            $builder->where($model->getTable() . '.agent_id', $user->id);
        }

        // Vendor agents only see rows tied to them (vehicles carry vendor_id).
        if ($user->isVendorAgent() && in_array('vendor_id', $model->getFillable(), true)) {
            $builder->where($model->getTable() . '.vendor_id', $user->id);
        }
    }
}