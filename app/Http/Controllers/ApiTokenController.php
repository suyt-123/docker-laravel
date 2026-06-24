<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Security\ApiTokenAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function __construct(private readonly ApiTokenAuditLogger $auditLogger) {}

    /**
     * @var array<string, string>
     */
    public const ABILITIES = [
        'read:quotations' => '讀取報價單 API',
        'write:quotations' => '寫入報價單流程 API',
        'read:project-change-orders' => '讀取工程變更追加單 API',
        'write:project-change-orders' => '寫入工程變更追加單流程 API',
        'read:materials' => '讀取材料品項 API',
        'write:materials' => '寫入材料品項 API',
        'read:purchase-orders' => '讀取採購單 API',
        'write:purchase-orders' => '寫入採購單 API',
        'write:inventory-transactions' => '寫入庫存異動 API',
    ];

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', Rule::in(array_keys(self::ABILITIES))],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $expiresAt = filled($data['expires_at'] ?? null)
            ? Carbon::parse($data['expires_at'])->endOfDay()
            : null;

        $token = $request->user()->createToken($data['name'], $data['abilities'], $expiresAt);

        $this->auditLogger->created($token->accessToken, $request->user());

        return Redirect::route('profile.edit')->with([
            'success' => 'API token 已建立，請立即複製保存。',
            'api_token' => [
                'name' => $data['name'],
                'plain_text_token' => $token->plainTextToken,
            ],
        ]);
    }

    public function destroy(Request $request, PersonalAccessToken $token): RedirectResponse
    {
        abort_unless(
            $token->tokenable_type === get_class($request->user())
            && (int) $token->tokenable_id === (int) $request->user()->id,
            404,
        );

        $this->auditLogger->revoked($token, $request->user(), $request->user());

        $token->delete();

        return Redirect::route('profile.edit')->with('success', 'API token 已撤銷。');
    }

    public function destroyForUser(Request $request, User $user, PersonalAccessToken $token): RedirectResponse
    {
        abort_unless($this->tokenBelongsToUser($token, $user), 404);

        $this->auditLogger->revoked($token, $user, $request->user());

        $token->delete();

        return Redirect::route('users.show', $user)->with('success', 'API token 已由管理員撤銷。');
    }

    private function tokenBelongsToUser(PersonalAccessToken $token, User $user): bool
    {
        return $token->tokenable_type === $user::class
            && (int) $token->tokenable_id === (int) $user->id;
    }
}
