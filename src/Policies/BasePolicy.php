<?php

namespace FinnWiel\ShazzooMedia\Policies;

use App\Models\User;

class BasePolicy
{
    protected function isTenantMatch(User $user, $resource): bool
    {
        if (!config('shazzoo_media.enable_tenant_scope')) {
            return true;
        }
        return $user->tenant_id === $resource->tenant_id;
    }
}
