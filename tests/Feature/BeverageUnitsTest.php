<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Plan;
use App\Models\User;
use App\Services\Nutrition;
use Database\Seeders\NutritionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeverageUnitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_volume_conversion_preserves_legacy_values_and_scales_household_measures(): void
    {
        $this->seed(NutritionSeeder::class);
        $milk = Food::where('source_id', 3)->where('source_key', '458')->firstOrFail();
        $this->assertSame('mL', $milk->unit);
        $original = $milk->raw_values['volume_conversion']['original'];
        $this->assertEqualsWithDelta($original['calories'] * 1.03, $milk->calories, 0.000001);
        $foods = Food::all()->keyBy('id');
        $nutrition = app(Nutrition::class);
        $legacy = $nutrition->items([['food_id' => $milk->id, 'quantity' => 100]], $foods);
        $this->assertEqualsWithDelta($original['calories'], $legacy['totals']['calories'], 0.000001);
        foreach (['cup' => 200, 'coffee_cup' => 50, 'glass' => 240, 'goblet' => 180] as $measure => $ml) {
            $item = ['food_id' => $milk->id, 'quantity' => 1, 'multiplier' => 2, 'measure' => $measure, 'measure_ml' => 180];
            $result = $nutrition->items([$item], $foods);
            $this->assertEqualsWithDelta($milk->calories * $ml * 2 / 100, $result['totals']['calories'], 0.000001);
            $this->assertEqualsWithDelta($ml * 2 * 1.03, $result['quantity'], 0.000001);
        }
        $before = $milk->toArray();
        $this->seed(NutritionSeeder::class);
        $after = $milk->fresh()->toArray();
        foreach (['calories', 'unit', 'raw_values', 'notes'] as $key) {
            $this->assertSame($before[$key], $after[$key]);
        }
        $this->assertSame('g', Food::where('source_id', 3)->where('source_key', '511')->firstOrFail()->unit);
        $this->assertSame('g', Food::where('source_id', 12)->where('source_key', '8202602:99')->firstOrFail()->unit);
    }

    public function test_measures_are_persisted_and_recipe_mass_is_converted(): void
    {
        $this->seed(NutritionSeeder::class);
        $admin = User::where('role', 'admin')->firstOrFail();
        $admin->forceFill(['setup_token' => null])->save();
        $this->actingAs($admin);
        $milk = Food::where('source_id', 3)->where('source_key', '458')->firstOrFail();
        $plan = Plan::first()->toArray();
        $item = ['food_id' => $milk->id, 'quantity' => 1, 'measure' => 'glass'];
        $plan['data']['meals'][0]['items'] = [$item];
        $this->putJson('/api/plans/1', $plan)->assertOk()->assertJsonPath('data.meals.0.items.0.measure', 'glass');
        $recipe = $this->postJson('/api/recipes', ['name' => 'Leite medido', 'ingredients' => [$item]])->assertSuccessful()->json();
        $output = Food::findOrFail($recipe['food_id']);
        $this->assertEqualsWithDelta(247.2, $output->base_quantity, 0.000001);
        $this->assertEqualsWithDelta($milk->calories * 2.4, $output->calories, 0.000001);
        $plan['data']['meals'][0]['items'][0]['measure'] = 'invalid';
        $this->postJson('/api/calculate', $plan)->assertUnprocessable();
    }
}
