<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Plan;
use App\Models\User;
use App\Services\Nutrition;
use Database\Seeders\NutritionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NutritionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NutritionSeeder::class);
        $admin = User::where('role', 'admin')->firstOrFail();
        $admin->forceFill(['setup_token' => null])->save();
        $this->actingAs($admin);
    }

    public function test_import_is_complete_and_repeatable(): void
    {
        $this->assertDatabaseCount('foods', 799);
        $this->assertDatabaseCount('plans', 1);
        $this->assertDatabaseCount('sources', 12);
        $this->assertDatabaseMissing('foods', ['category' => 'Número_do']);
        $this->assertEquals(63, Food::where('source_id', 3)->where('category', 'Cereais_e_derivados')->count());
        $this->seed(NutritionSeeder::class);
        $this->assertDatabaseCount('foods', 799);
        $this->assertCount(9, Plan::first()->data['meals']);
        $this->assertSame(15, collect(Plan::first()->data['meals'])->sum(fn ($m) => count($m['items'])));
    }

    public function test_profile_matches_workbook_and_handles_blank_and_zero_adjustment(): void
    {
        $s = app(Nutrition::class);
        $p = Plan::first()->data['profile'];
        $r = $s->profile($p);
        $this->assertEqualsWithDelta(1371.7, $r['bmr'], .000001);
        $this->assertEqualsWithDelta(2126.135, $r['target'], .000001);
        $this->assertEqualsWithDelta(29.0486565, $r['bmi'], .000001);
        $this->assertSame('Obesidade Moderada', $r['bmi_label']);
        $p['adjustment'] = 0;
        $this->assertEqualsWithDelta($r['energy'], $s->profile($p)['target'], .000001);
        $p['adjustment'] = 500;
        $this->assertEqualsWithDelta(1626.135, $s->profile($p)['target'], .000001);
        $p['goal'] = 'Ganhar Peso';
        $this->assertEqualsWithDelta(2626.135, $s->profile($p)['target'], .000001);
        $p['goal'] = 'Manter o Peso';
        $this->assertEqualsWithDelta(2126.135, $s->profile($p)['target'], .000001);
        $p['sex'] = 'Masculino';
        $this->assertEqualsWithDelta(1456.6, $s->profile($p)['bmr'], .000001);
    }

    public function test_bmi_boundaries_follow_original_gender_specific_formulas(): void
    {
        $p = Plan::first()->data['profile'];
        $p['height'] = 100;
        $s = app(Nutrition::class);
        foreach (['Feminino' => [19, 24, 29, 39], 'Masculino' => [20, 25, 30, 40]] as $sex => $limits) {
            $p['sex'] = $sex;
            foreach ($limits as $i => $limit) {
                $p['weight'] = $limit;
                $this->assertSame(['Normal', 'Obesidade Leve', 'Obesidade Moderada', 'Obesidade Mórbida'][$i], $s->profile($p)['bmi_label']);
            }
        }
    }

    public function test_proportions_and_missing_nutrients_are_not_zeroed(): void
    {
        $foods = Food::all()->keyBy('id');
        $s = app(Nutrition::class);
        $f = Food::where('source_id', 3)->where('name', 'Aveia, flocos, crua')->first();
        $r = $s->items([['food_id' => $f->id, 'quantity' => 30]], $foods);
        $this->assertEqualsWithDelta($f->calories * .3, $r['totals']['calories'], .000001);
        $unknown = Food::where('source_id', 3)->whereNull('sodium')->first();
        $r = $s->items([['food_id' => $unknown->id, 'quantity' => 100]], $foods);
        $this->assertSame(1, $r['missing']['sodium']);
        $r = $s->items([['food_id' => $unknown->id, 'quantity' => 0]], $foods);
        $this->assertSame(0, $r['missing']['sodium']);
    }

    public function test_plan_save_recalculates_and_prevents_lost_updates(): void
    {
        $p = Plan::first()->toArray();
        $p['data']['meals'][0]['items'][0]['quantity'] = 150;
        $this->putJson('/api/plans/1', $p)->assertOk()->assertJsonPath('version', 2);
        $this->putJson('/api/plans/1', $p)->assertStatus(409);
        $this->postJson('/api/calculate', $p)->assertOk()->assertJsonPath('meals.0.quantity', 380);
        $this->assertEquals(150, Plan::first()->data['meals'][0]['items'][0]['quantity']);
        $p['data']['meals'][0]['items'][0]['quantity'] = -10;
        $this->putJson('/api/plans/1', $p)->assertUnprocessable();
    }

    public function test_recipe_is_persisted_as_a_reusable_food_with_scaled_nutrients(): void
    {
        $f = Food::where('source_id', 11)->first();
        $r = $this->postJson('/api/recipes', ['name' => 'Receita de teste', 'ingredients' => [['food_id' => $f->id, 'quantity' => 200]], 'instructions' => 'Misturar.'])->assertSuccessful()->json();
        $food = Food::findOrFail($r['food_id']);
        $this->assertEquals(200, $food->base_quantity);
        $this->assertEqualsWithDelta(2 * $f->protein, $food->protein, .000001);
        $this->postJson('/api/recipes', ['name' => 'Inválida', 'ingredients' => [['food_id' => 999999, 'quantity' => 100]]])->assertUnprocessable();
    }

    public function test_source_foods_are_protected_and_custom_foods_have_crud(): void
    {
        $f = Food::where('source_id', 11)->first();
        $this->deleteJson('/api/foods/'.$f->id)->assertForbidden();
        $data = ['name' => 'Novo alimento', 'category' => 'Outros', 'unit' => 'g', 'base_quantity' => 50, 'calories' => 100, 'protein' => 0, 'carbs' => null, 'fat' => 2, 'fiber' => null, 'sodium' => 0];
        $r = $this->postJson('/api/foods', $data)->assertSuccessful()->json();
        $this->assertNull($r['carbs']);
        $data['calories'] = 200;
        $this->putJson('/api/foods/'.$r['id'], $data)->assertOk()->assertJsonPath('calories', 200);
        $this->deleteJson('/api/foods/'.$r['id'])->assertNoContent();
    }

    public function test_page_and_bootstrap_are_available(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk();
        $this->getJson('/api/bootstrap')->assertOk()->assertJsonCount(799, 'foods')->assertJsonCount(9, 'meal_types');
    }

    public function test_meal_units_are_saved_and_scale_calculations(): void
    {
        $plan = Plan::first()->toArray();
        $plan['data']['meals'] = [
            ['name' => 'Café da Manhã', 'time' => '07:45', 'items' => [
                ['food_id' => $plan['data']['meals'][0]['items'][0]['food_id'], 'quantity' => 50, 'multiplier' => 2],
            ]],
        ];
        $food = Food::findOrFail($plan['data']['meals'][0]['items'][0]['food_id']);
        $this->putJson('/api/plans/1', $plan)->assertOk()->assertJsonPath('data.meals.0.items.0.multiplier', 2);
        $this->assertEquals(2, Plan::first()->data['meals'][0]['items'][0]['multiplier']);
        $result = $this->postJson('/api/calculate', $plan)->assertOk()->json();
        $this->assertEquals(100, $result['quantity']);
        foreach (Nutrition::NUTRIENTS as $nutrient) {
            $this->assertEqualsWithDelta($food[$nutrient], $result['totals'][$nutrient], .000001);
        }
        $plan['data']['meals'][0]['items'][0]['multiplier'] = 0.5;
        $this->postJson('/api/calculate', $plan)->assertOk()->assertJsonPath('quantity', 25);
        foreach ([-1, null, 'invalid', 10001] as $invalidMultiplier) {
            $plan['data']['meals'][0]['items'][0]['multiplier'] = $invalidMultiplier;
            $this->postJson('/api/calculate', $plan)->assertUnprocessable();
        }
    }
}
