<?php

namespace FinnWiel\ShazzooMedia\Policies;

use App\Models\User;

class BasePolicy
{
    protected function isTenantMatch(User $user, $resource): bool
    {
        return $user->tenant_id === $resource->tenant_id;
    }
}
