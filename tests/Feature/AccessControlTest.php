<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\NutritionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_admin_approval_and_cannot_elevate_role(): void
    {
        $this->seed(NutritionSeeder::class);
        $this->getJson('/api/bootstrap')->assertUnauthorized();
        $this->get('/')->assertRedirect('/login');
        $credentials = ['email' => 'nutri@example.com', 'password' => 'long-password-123'];
        $this->postJson('/api/auth/register', [...$credentials, 'name' => 'Nutri', 'password_confirmation' => $credentials['password'], 'role' => 'admin', 'status' => 'approved'])->assertCreated();
        $nutritionist = User::where('email', $credentials['email'])->firstOrFail();
        $this->assertSame('nutritionist', $nutritionist->role);
        $this->assertSame('pending', $nutritionist->status);
        $this->postJson('/api/auth/login', $credentials)->assertForbidden();
        $admin = User::where('role', 'admin')->firstOrFail();
        $admin->forceFill(['setup_token' => null])->save();
        $this->actingAs($admin)->patchJson('/api/admin/users/'.$nutritionist->id, ['status' => 'approved'])->assertOk();
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/login', $credentials)->assertOk();
        $this->getJson('/api/bootstrap')->assertOk()->assertJsonCount(0, 'plans')->assertJsonCount(0, 'patients')->assertJsonCount(799, 'foods');
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->get('/admin')->assertForbidden();
    }

    public function test_registration_requires_at_least_eight_password_characters(): void
    {
        $shortPassword = '1234567';
        $this->postJson('/api/auth/register', [
            'name' => 'Senha curta',
            'email' => 'short-password@example.com',
            'password' => $shortPassword,
            'password_confirmation' => $shortPassword,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $validPassword = '12345678';
        $this->postJson('/api/auth/register', [
            'name' => 'Senha válida',
            'email' => 'valid-password@example.com',
            'password' => $validPassword,
            'password_confirmation' => $validPassword,
        ])->assertCreated();
    }

    public function test_admin_first_password_requires_single_use_local_code(): void
    {
        $this->seed(NutritionSeeder::class);
        $admin = User::where('role', 'admin')->firstOrFail();
        $admin->forceFill(['setup_token' => hash('sha256', 'local-secret')])->save();
        $data = ['email' => $admin->email, 'password' => 'a-long-new-password', 'password_confirmation' => 'a-long-new-password'];
        $this->postJson('/api/auth/setup', [...$data, 'token' => 'invalid'])->assertUnprocessable();
        $this->postJson('/api/auth/setup', [...$data, 'token' => 'local-secret'])->assertOk();
        $this->assertNull($admin->fresh()->setup_token);
        $this->getJson('/api/admin/users')->assertOk();
        $this->postJson('/api/auth/setup', [...$data, 'token' => 'local-secret'])->assertUnprocessable();
    }

    public function test_private_data_is_isolated_even_when_ids_are_submitted_directly(): void
    {
        $this->seed(NutritionSeeder::class);
        $owner = User::factory()->create(['status' => 'approved']);
        $other = User::factory()->create(['status' => 'approved']);
        $patient = Patient::factory()->create(['user_id' => $owner->id]);
        $privateFood = Food::create(['name' => 'Exclusivo', 'category' => 'Outros', 'unit' => 'g', 'base_quantity' => 100, 'calories' => 123, 'user_id' => $owner->id]);
        $privatePlan = Plan::create(['name' => 'Privado', 'data' => Plan::first()->data, 'user_id' => $owner->id, 'patient_id' => $patient->id]);
        Recipe::create(['name' => 'Receita privada', 'food_id' => $privateFood->id, 'ingredients' => [], 'user_id' => $owner->id]);
        $this->actingAs($other);
        $bootstrap = $this->getJson('/api/bootstrap')->assertOk()->assertJsonCount(0, 'plans')->assertJsonCount(0, 'patients')->assertJsonCount(0, 'recipes')->json();
        $this->assertNotContains($privateFood->id, array_column($bootstrap['foods'], 'id'));
        $this->deleteJson('/api/foods/'.$privateFood->id)->assertNotFound();
        $this->putJson('/api/foods/'.$privateFood->id, [])->assertNotFound();
        $this->deleteJson('/api/plans/'.$privatePlan->id)->assertNotFound();
        $this->putJson('/api/plans/'.$privatePlan->id, [])->assertNotFound();
        $this->putJson('/api/patients/'.$patient->id, [])->assertNotFound();
        $payload = $bootstrap['template'];
        $payload['data']['profile']['name'] = 'Meu paciente';
        $payload['data']['meals'][0]['items'] = [['food_id' => $privateFood->id, 'quantity' => 100]];
        $this->postJson('/api/calculate', $payload)->assertUnprocessable();
        $this->postJson('/api/recipes', ['name' => 'Tentativa', 'ingredients' => $payload['data']['meals'][0]['items']])->assertUnprocessable();
        $payload['data']['meals'][0]['items'] = [];
        $payload['patient_id'] = $patient->id;
        $this->postJson('/api/plans', $payload)->assertUnprocessable();
        $food = $this->postJson('/api/foods', ['name' => 'Meu alimento', 'category' => 'Outros', 'unit' => 'g', 'base_quantity' => 100, 'calories' => 50, 'user_id' => $owner->id])->assertSuccessful()->json();
        $this->assertSame($other->id, $food['user_id']);
        $this->actingAs($owner)->getJson('/api/bootstrap')->assertOk()->assertJsonCount(1, 'patients')->assertJsonCount(1, 'plans')->assertJsonCount(1, 'recipes');
    }

    public function test_blocked_user_cannot_keep_using_existing_session(): void
    {
        $user = User::factory()->create(['status' => 'rejected']);
        $this->actingAs($user)->getJson('/api/bootstrap')->assertForbidden();
        $this->postJson('/api/patients', ['name' => 'Paciente'])->assertForbidden();
    }
}
