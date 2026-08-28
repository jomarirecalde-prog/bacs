<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;

class CeoResolver
{
    private ?User $resolved = null;

    private bool $resolvedFlag = false;

    public function user(): ?User
    {
        if ($this->resolvedFlag) {
            return $this->resolved;
        }

        $this->resolvedFlag = true;

        $configured = Setting::get('ceo_user_id');
        if ($configured) {
            $this->resolved = User::query()
                ->whereKey($configured)
                ->where('status', 'active')
                ->first();

            if ($this->resolved) {
                return $this->resolved;
            }
        }

        $this->resolved = Employee::query()
            ->where(function ($query) {
                $query->where('position', 'like', '%CEO%')
                    ->orWhere('position', 'like', '%President%');
            })
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->with('user')
            ->orderBy('id')
            ->first()
            ?->user;

        return $this->resolved;
    }

    public function label(): string
    {
        $user = $this->user();

        if (! $user) {
            return 'Not designated';
        }

        $name = $user->employee?->fullName() ?: $user->name;
        $position = $user->employee?->position;

        return $position ? "{$name} — {$position}" : $name;
    }

    public function isAuthorized(User $user): bool
    {
        $ceo = $this->user();

        return $ceo !== null && $ceo->id === $user->id;
    }
}
