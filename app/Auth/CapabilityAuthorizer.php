<?php

namespace App\Auth;

use App\Models\User;

class CapabilityAuthorizer
{
    public function allows(User $user, string $capability, ?int $tenantId = null): bool
    {
        return $this->capabilityCodes($user, $tenantId)->contains($capability);
    }

    public function denies(User $user, string $capability, ?int $tenantId = null): bool
    {
        return ! $this->allows($user, $capability, $tenantId);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function capabilityCodes(User $user, ?int $tenantId = null)
    {
        $user->loadMissing('roles.capabilities');

        return $user->roles
            ->filter(fn ($role) => is_null($tenantId) || is_null($role->pivot?->tenant_id) || (int) $role->pivot->tenant_id === $tenantId)
            ->flatMap(fn ($role) => $role->capabilities)
            ->filter(fn ($capability) => is_null($tenantId) || is_null($capability->tenant_id) || (int) $capability->tenant_id === $tenantId)
            ->pluck('code')
            ->unique()
            ->values();
    }
}
