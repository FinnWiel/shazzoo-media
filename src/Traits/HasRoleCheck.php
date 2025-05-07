<?php
namespace FinnWiel\ShazzooMedia\Traits;

trait HasRoleCheck
{
    /**
     * Check if the user has the given role(s).
     *
     * @param string|array $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;
        return in_array($this->role, $roles);
    }
}