<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    // ---------- GET /api/tasks/{id}: поля, кэш, 404 ----------

    public function test_show_returns_task_with_deadline_status(): void
    {
        $task = Task::factory()->create(['deadline' => now()->addHours(5)]);

        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.deadline_status', 'due_soon')
            ->assertJsonPath('cached', false);
    }

    public function test_show_computes_overdue_and_on_track(): void
    {
        $overdue = Task::factory()->create(['deadline' => now()->subDay()]);
        $onTrack = Task::factory()->create(['deadline' => now()->addDays(3)]);

        $this->getJson("/api/tasks/{$overdue->id}")->assertJsonPath('data.deadline_status', 'overdue');
        $this->getJson("/api/tasks/{$onTrack->id}")->assertJsonPath('data.deadline_status', 'on_track');
    }

    public function test_second_request_is_served_from_cache(): void
    {
        $task = Task::factory()->create();

        $this->getJson("/api/tasks/{$task->id}")->assertJsonPath('cached', false);
        $this->getJson("/api/tasks/{$task->id}")->assertJsonPath('cached', true);
    }

    public function test_cache_expires_after_60_seconds(): void
    {
        $task = Task::factory()->create();

        $this->getJson("/api/tasks/{$task->id}");
        $this->travel(61)->seconds();

        $this->getJson("/api/tasks/{$task->id}")->assertJsonPath('cached', false);
    }

    public function test_status_change_clears_cache(): void
    {
        $this->authenticate();
        $task = Task::factory()->create();

        $this->getJson("/api/tasks/{$task->id}"); // прогреваем кэш
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress'])->assertOk();

        $this->getJson("/api/tasks/{$task->id}")
            ->assertJsonPath('cached', false)
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_show_returns_404_for_missing_task(): void
    {
        $this->getJson('/api/tasks/99999')
            ->assertNotFound()
            ->assertJson(['message' => 'Task not found']);
    }

    // ---------- PATCH /api/tasks/{id}/status: доступ и переходы ----------

    public function test_patch_without_token_returns_401(): void
    {
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertUnauthorized();
    }

    public function test_allowed_transition_returns_200(): void
    {
        $this->authenticate();
        $task = Task::factory()->create(['status' => 'in_progress']);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done');
    }

    public function test_done_to_todo_is_forbidden(): void
    {
        $this->authenticate();
        $task = Task::factory()->create(['status' => 'done']);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'todo'])
            ->assertStatus(422)
            ->assertJsonPath('allowed', ['in_progress']);
    }

    public function test_todo_to_done_is_forbidden(): void
    {
        $this->authenticate();
        $task = Task::factory()->create(['status' => 'todo']);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'done'])
            ->assertStatus(422);
    }

    public function test_same_status_is_forbidden(): void
    {
        $this->authenticate();
        $task = Task::factory()->create(['status' => 'todo']);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'todo'])
            ->assertStatus(422);
    }

    public function test_invalid_status_value_returns_422(): void
    {
        $this->authenticate();
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'foo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_missing_status_returns_422(): void
    {
        $this->authenticate();
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}/status", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_patch_returns_404_for_missing_task(): void
    {
        $this->authenticate();

        $this->patchJson('/api/tasks/99999/status', ['status' => 'in_progress'])
            ->assertNotFound();
    }

    // ---------- POST /api/tasks: создание и валидация ----------

    public function test_store_creates_task(): void
    {
        $this->postJson('/api/tasks', [
            'title' => 'New task',
            'deadline' => now()->addDay()->toIso8601String(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'New task')
            ->assertJsonPath('data.status', 'todo');

        $this->assertDatabaseHas('tasks', ['title' => 'New task']);
    }

    public function test_store_requires_title(): void
    {
        $this->postJson('/api/tasks', ['deadline' => now()->addDay()->toIso8601String()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_store_rejects_past_deadline(): void
    {
        $this->postJson('/api/tasks', ['title' => 'Old', 'deadline' => now()->subDay()->toIso8601String()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('deadline');
    }

    public function test_store_ignores_status_from_client(): void
    {
        $this->postJson('/api/tasks', [
            'title' => 'Sneaky',
            'deadline' => now()->addDay()->toIso8601String(),
            'status' => 'done',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'todo');
    }

    // ---------- GET /api/tasks: фильтры и пагинация ----------

    public function test_index_filters_by_status(): void
    {
        Task::factory()->count(2)->create(['status' => 'todo']);
        Task::factory()->create(['status' => 'done']);

        $this->getJson('/api/tasks?status=done')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_deadline_status(): void
    {
        Task::factory()->create(['deadline' => now()->subDay()]);
        Task::factory()->count(2)->create(['deadline' => now()->addDays(5)]);

        $this->getJson('/api/tasks?deadline_status=overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_paginates(): void
    {
        Task::factory()->count(5)->create();

        $this->getJson('/api/tasks?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('total', 5);
    }

    public function test_index_rejects_invalid_filter(): void
    {
        $this->getJson('/api/tasks?status=foo')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    // ---------- POST /api/login ----------

    public function test_login_returns_token_that_unlocks_patch(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token'])
            ->json('token');

        $this->withToken($token)
            ->patchJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertOk();
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized();
    }

    public function test_login_without_password_returns_422(): void
    {
        $this->postJson('/api/login', ['email' => 'admin@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }
}
