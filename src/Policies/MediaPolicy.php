<?php

namespace FinnWiel\ShazzooMedia\Policies;

use FinnWiel\ShazzooMedia\Models\MediaExtended as Media;
use App\Models\User;

class MediaPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['superadmin', 'admin', 'editor', 'viewer']);
    }

    public function view(User $user, Media $media): bool
    {
        if ($user->hasRole('superadmin')) return true;

        return $this->isTenantMatch($user, $media) &&
               $user->hasRole(['admin', 'editor', 'viewer']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['superadmin', 'admin', 'editor']);
    }

    public function update(User $user, Media $media): bool
    {
        if ($user->hasRole('superadmin')) return true;

        return $this->isTenantMatch($user, $media) &&
               $user->hasRole(['admin', 'editor']);
    }

    public function delete(User $user, Media $media): bool
    {
        if ($user->hasRole('superadmin')) return true;

        return $this->isTenantMatch($user, $media) &&
               $user->hasRole(['admin']);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(['superadmin', 'admin']);
    }

    public function download(User $user, Media $media): bool
    {
        if ($user->hasRole('superadmin')) return true;

        return $this->isTenantMatch($user, $media) &&
               $user->hasRole(['admin', 'editor', 'viewer']);
    }
}
