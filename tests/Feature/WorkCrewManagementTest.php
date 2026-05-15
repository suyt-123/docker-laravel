<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkCrew;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkCrewManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_work_crew_pages(): void
    {
        $this->get(route('work-crews.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_create_work_crew(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)->post(route('work-crews.store'), [
            'name' => '北區鋼構班',
            'leader_name' => '陳班長',
            'phone' => '0912-000-000',
            'specialties_text' => "H 鋼\n浪板",
            'daily_rate' => 2800,
        ])->assertRedirect();

        $crew = WorkCrew::where('name', '北區鋼構班')->firstOrFail();
        $this->assertSame(['H 鋼', '浪板'], $crew->specialties);
    }

    public function test_work_crew_requires_name(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->from(route('work-crews.create'))
            ->post(route('work-crews.store'), ['name' => ''])
            ->assertRedirect(route('work-crews.create'))
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_update_and_delete_work_crew(): void
    {
        $user = $this->authorizedUser();
        $crew = WorkCrew::create(['name' => '舊工班']);

        $this->actingAs($user)->patch(route('work-crews.update', $crew), [
            'name' => '更新工班',
            'specialties_text' => '採光罩,雨遮',
        ])->assertRedirect(route('work-crews.show', $crew));

        $crew->refresh();
        $this->assertSame('更新工班', $crew->name);
        $this->assertSame(['採光罩', '雨遮'], $crew->specialties);

        $this->actingAs($user)
            ->delete(route('work-crews.destroy', $crew))
            ->assertRedirect(route('work-crews.index'));

        $this->assertDatabaseMissing('work_crews', ['id' => $crew->id]);
    }
}
