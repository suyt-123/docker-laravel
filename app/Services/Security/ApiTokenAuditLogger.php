<?php

namespace App\Services\Security;

use App\Models\User;
use App\Support\ActivityLogger;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenAuditLogger
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function created(PersonalAccessToken $token, User $owner): void
    {
        $this->logger->log(
            'create',
            'api_token.created',
            $token,
            null,
            $this->tokenValues($token, $owner),
            "API token「{$token->name}」已建立",
            'security.api_tokens',
        );
    }

    public function revoked(PersonalAccessToken $token, User $owner, User $actor): void
    {
        $this->logger->log(
            'revoke',
            'api_token.revoked',
            $token,
            $this->tokenValues($token, $owner),
            [
                'revoked_by' => [
                    'id' => $actor->id,
                    'name' => $actor->name,
                    'email' => $actor->email,
                ],
            ],
            $actor->is($owner)
                ? "API token「{$token->name}」已由擁有者撤銷"
                : "API token「{$token->name}」已由管理員撤銷",
            'security.api_tokens',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenValues(PersonalAccessToken $token, User $owner): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
            ],
            'last_used_at' => $token->last_used_at?->toDateTimeString(),
            'expires_at' => $token->expires_at?->toDateTimeString(),
            'created_at' => $token->created_at?->toDateTimeString(),
        ];
    }
}
