<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\NutritionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientPlanNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_name_is_derived_from_patient_on_creation_and_update(): void
    {
        $this->seed(NutritionSeeder::class);
        $this->actingAs(User::factory()->create(['status' => 'approved']));
        $payload = $this->getJson('/api/bootstrap')->assertOk()->json('template');
        $this->assertCount(6, $payload['data']['meals']);
        $this->assertEmpty(array_intersect(['Pré-Treino', 'Pós-Treino', 'Lanche - Noite'], array_column($payload['data']['meals'], 'name')));
        unset($payload['name']);
        $payload['data']['profile']['name'] = 'Ana Silva';
        $created = $this->postJson('/api/plans', $payload)->assertSuccessful()->assertJsonPath('name', 'Ana Silva')->json();
        $created['name'] = 'Título enviado manualmente';
        $created['data']['profile']['name'] = 'Ana Souza';
        $this->putJson('/api/plans/'.$created['id'], $created)->assertOk()->assertJsonPath('name', 'Ana Souza');
        $this->assertDatabaseHas('plans', ['id' => $created['id'], 'name' => 'Ana Souza']);
    }

    public function test_new_patient_is_available_in_refreshed_patient_list(): void
    {
        $this->seed(NutritionSeeder::class);
        $this->actingAs(User::factory()->create(['status' => 'approved']));
        $patient = $this->postJson('/api/patients', ['name' => 'Novo paciente'])->assertSuccessful()->json();
        $this->getJson('/api/bootstrap')->assertOk()->assertJsonCount(1, 'patients')->assertJsonPath('patients.0.id', $patient['id']);
    }
}
