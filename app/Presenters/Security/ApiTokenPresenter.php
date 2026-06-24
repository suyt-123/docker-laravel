<?php

namespace App\Presenters\Security;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function token(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'status' => $this->status($token),
            'last_used_at' => $this->dateTime($token->last_used_at),
            'last_used_label' => $this->lastUsedLabel($token),
            'expires_at' => $token->expires_at?->toDateString(),
            'expires_label' => $this->expiresLabel($token),
            'created_at' => $this->dateTime($token->created_at),
        ];
    }

    private function status(PersonalAccessToken $token): string
    {
        if ($token->expires_at && $token->expires_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    private function lastUsedLabel(PersonalAccessToken $token): string
    {
        if (! $token->last_used_at) {
            return '尚未使用';
        }

        return $token->last_used_at
            ->timezone(config('app.timezone'))
            ->diffForHumans();
    }

    private function expiresLabel(PersonalAccessToken $token): string
    {
        if (! $token->expires_at) {
            return '未設定';
        }

        $expiresAt = $token->expires_at->timezone(config('app.timezone'));

        if ($expiresAt->isPast()) {
            return '已到期';
        }

        return '剩餘 '.$expiresAt->diffForHumans(Carbon::now(), true);
    }

    private function dateTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (method_exists($value, 'timezone')) {
            return $value->timezone(config('app.timezone'))->format('Y-m-d H:i');
        }

        return (string) $value;
    }
}
