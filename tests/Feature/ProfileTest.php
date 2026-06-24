<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_page_lists_api_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('外部報表', ['read:quotations']);

        $this
            ->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Edit')
                ->where('apiTokens.0.name', '外部報表')
                ->where('apiTokens.0.abilities.0', 'read:quotations')
                ->where('apiTokenAbilities.read:quotations', '讀取報價單 API')
                ->where('apiTokenAbilities.write:quotations', '寫入報價單流程 API')
                ->where('apiTokenAbilities.write:project-change-orders', '寫入工程變更追加單流程 API')
                ->where('apiTokenAbilities.read:materials', '讀取材料品項 API')
                ->where('apiTokenAbilities.write:materials', '寫入材料品項 API')
                ->where('apiTokenAbilities.read:purchase-orders', '讀取採購單 API')
                ->where('apiTokenAbilities.write:purchase-orders', '寫入採購單 API')
                ->where('apiTokenAbilities.write:inventory-transactions', '寫入庫存異動 API')
            );
    }

    public function test_user_can_create_api_token(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('profile.api-tokens.store'), [
                'name' => '報表串接',
                'abilities' => ['read:quotations'],
                'expires_at' => now()->addMonth()->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('api_token.name', '報表串接')
            ->assertSessionHas('api_token.plain_text_token')
            ->assertRedirect(route('profile.edit'));

        $token = PersonalAccessToken::firstOrFail();

        $this->assertSame($user->id, $token->tokenable_id);
        $this->assertSame(['read:quotations'], $token->abilities);
        $this->assertNotNull($token->expires_at);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'create',
            'event' => 'api_token.created',
            'subject_type' => PersonalAccessToken::class,
            'subject_id' => $token->id,
            'module' => 'security.api_tokens',
        ]);
    }

    public function test_user_can_revoke_their_api_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('待撤銷', ['read:quotations'])->accessToken;

        $this
            ->actingAs($user)
            ->delete(route('profile.api-tokens.destroy', $token))
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'revoke',
            'event' => 'api_token.revoked',
            'subject_type' => PersonalAccessToken::class,
            'subject_id' => $token->id,
            'module' => 'security.api_tokens',
        ]);
    }

    public function test_user_cannot_revoke_another_users_api_token(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $owner->createToken('不可撤銷', ['read:quotations'])->accessToken;

        $this
            ->actingAs($other)
            ->delete(route('profile.api-tokens.destroy', $token))
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->id,
        ]);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
