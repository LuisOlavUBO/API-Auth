<?php

namespace App\Console\Commands\Concerns;

use App\Models\Role;
use Illuminate\Support\Collection;

trait ResolvesRoles
{
    /**
     * Find roles by their "<application>/<role>" reference, reporting errors to the console.
     * A role name is only unique inside its application, so a bare name is not a reference.
     *
     * @param  string[]  $references
     * @return Collection<int, Role>|null
     */
    protected function resolveRoles(array $references): ?Collection
    {
        $roles = collect();
        $unknown = [];

        foreach ($references as $reference) {
            [$application, $name] = array_pad(explode('/', $reference, 2), 2, null);

            $role = $name === null ? null : Role::where('name', $name)
                ->whereHas('application', fn ($applications) => $applications->where('name', $application))
                ->first();

            $role ? $roles->push($role) : $unknown[] = $reference;
        }

        if ($unknown !== []) {
            $this->error('Unknown role(s), use <application>/<role>: '.implode(', ', $unknown));

            return null;
        }

        return $roles;
    }
}
