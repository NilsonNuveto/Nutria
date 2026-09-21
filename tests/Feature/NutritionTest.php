<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Plan;
use App\Models\User;
use App\Services\Nutrition;
use Database\Seeders\NutritionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_profile_uses_current_energy_equations_and_goal_based_macros(): void
    {
        $nutrition = app(Nutrition::class);
        $profile = Plan::first()->data['profile'];
        $result = $nutrition->profile($profile);

        $this->assertEqualsWithDelta(2234.54, $result['energy'], .000001);
        $this->assertEqualsWithDelta(1734.54, $result['target'], .000001);
        $this->assertEqualsWithDelta(29.0486565, $result['bmi'], .000001);
        $this->assertSame('Sobrepeso', $result['bmi_label']);
        $this->assertTrue($result['automatic_adjustment']);
        $this->assertEqualsWithDelta(88.4, $result['macros']['protein']['grams'], .000001);
        $this->assertEqualsWithDelta(215.1445, $result['macros']['carbs']['grams'], .000001);
        $this->assertEqualsWithDelta(57.818, $result['macros']['fat']['grams'], .000001);
        $this->assertEqualsWithDelta(24.28356, $result['macros']['fiber']['grams'], .000001);

        $profile['adjustment'] = 0;
        $this->assertEqualsWithDelta($result['energy'], $nutrition->profile($profile)['target'], .000001);
        $profile['adjustment'] = null;
        $profile['goal'] = 'Ganhar Peso';
        $this->assertEqualsWithDelta($result['energy'] * 1.05, $nutrition->profile($profile)['target'], .000001);
        $profile['goal'] = 'Manter o Peso';
        $this->assertEqualsWithDelta($result['energy'], $nutrition->profile($profile)['target'], .000001);
    }

    public function test_profile_calculates_body_composition_and_current_adult_bmi_ranges(): void
    {
        $nutrition = app(Nutrition::class);
        $profile = Plan::first()->data['profile'];
        $profile['body_fat'] = 30;
        $profile['waist'] = 90;
        $profile['target_weight'] = 60;
        $result = $nutrition->profile($profile);

        $this->assertEqualsWithDelta(20.4, $result['fat_mass'], .000001);
        $this->assertEqualsWithDelta(47.6, $result['lean_mass'], .000001);
        $this->assertEqualsWithDelta(90 / 153, $result['waist_height_ratio'], .000001);
        $this->assertSame('Adiposidade central aumentada', $result['waist_height_label']);
        $this->assertEqualsWithDelta(-8, $result['weight_delta'], .000001);
        $this->assertEqualsWithDelta(78, $result['macros']['protein']['grams'], .000001);

        $profile['height'] = 100;
        foreach ([[18.49, 'Abaixo do peso'], [18.5, 'Eutrofia'], [25, 'Sobrepeso'], [30, 'Obesidade grau I'], [35, 'Obesidade grau II'], [40, 'Obesidade grau III']] as [$weight, $label]) {
            $profile['weight'] = $weight;
            $this->assertSame($label, $nutrition->profile($profile)['bmi_label']);
        }
        $profile['age'] = 18;
        $this->assertSame('Avaliar por curva de crescimento', $nutrition->profile($profile)['bmi_label']);
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
        $this->getJson('/api/bootstrap?catalog=0')->assertOk()->assertJsonCount(0, 'foods')->assertJsonCount(0, 'categories');
    }

    public function test_bootstrap_maps_shared_catalog_by_source_key_when_database_ids_differ(): void
    {
        $food = Food::where('source_id', 12)->firstOrFail();
        $newId = 50000;
        DB::table('foods')->where('id', $food->id)->update(['id' => $newId]);

        $foods = $this->getJson('/api/bootstrap')->assertOk()->json('foods');
        $mapped = collect($foods)->first(fn (array $item): bool => $item['source_id'] === 12 && $item['source_key'] === $food->source_key);

        $this->assertNotNull($mapped);
        $this->assertSame($newId, $mapped['id']);
    }

    public function test_meal_alternatives_are_saved_and_use_the_average_nutrients(): void
    {
        $foods = Food::where('unit', 'g')->whereNotNull('calories')->whereNotNull('protein')->whereNotNull('carbs')->whereNotNull('fat')->take(2)->get();
        $primary = $foods->firstOrFail();
        $alternative = $foods->last();
        $plan = Plan::first()->toArray();
        $plan['data']['meals'] = [[
            'name' => 'Café da Manhã',
            'time' => '07:45',
            'items' => [[
                'food_id' => $primary->id,
                'quantity' => $primary->base_quantity,
                'multiplier' => 1,
                'alternative' => [
                    'food_id' => $alternative->id,
                    'quantity' => $alternative->base_quantity,
                    'multiplier' => 1,
                ],
            ]],
        ]];

        $this->putJson('/api/plans/1', $plan)
            ->assertOk()
            ->assertJsonPath('data.meals.0.items.0.alternative.food_id', $alternative->id);
        $result = $this->postJson('/api/calculate', $plan)->assertOk()->json();
        foreach (Nutrition::NUTRIENTS as $nutrient) {
            $this->assertEqualsWithDelta(($primary[$nutrient] + $alternative[$nutrient]) / 2, $result['totals'][$nutrient], .000001);
        }

        $plan['version'] = 2;
        $plan['data']['meals'][0]['items'][0]['alternative']['food_id'] = 999999;
        $this->putJson('/api/plans/1', $plan)->assertUnprocessable();
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
