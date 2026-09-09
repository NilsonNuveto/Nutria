<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Plan;
use App\Models\User;
use App\Services\FoodDeduplicator;
use Database\Seeders\NutritionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_preserves_official_data_and_existing_references(): void
    {
        $this->seed(NutritionSeeder::class);
        $admin = User::where('role', 'admin')->firstOrFail();
        $admin->forceFill(['setup_token' => null])->save();
        $this->actingAs($admin);
        $seed = json_decode(file_get_contents(database_path('imports/seed.json')), true);
        $duplicate = Food::create(collect($seed['foods'])->firstWhere('name', 'Arroz, integral, cozido'));
        $official = Food::where('source_id', 3)->where('name', $duplicate->name)->firstOrFail();
        $plan = Plan::first();
        $data = $plan->data;
        $data['meals'][0]['items'] = [['food_id' => $duplicate->id, 'quantity' => 50, 'multiplier' => 2]];
        $plan->update(['data' => $data]);
        $recipe = $this->postJson('/api/recipes', ['name' => 'Arroz teste', 'ingredients' => [['food_id' => $duplicate->id, 'quantity' => 200]]])->assertSuccessful()->json();
        $this->artisan('foods:deduplicate')->assertSuccessful();
        $this->assertDatabaseMissing('foods', ['id' => $duplicate->id]);
        $this->assertEquals($official->id, $plan->fresh()->data['meals'][0]['items'][0]['food_id']);
        $this->assertEquals(2, $plan->fresh()->data['meals'][0]['items'][0]['multiplier']);
        $this->assertEqualsWithDelta($official->calories * 2, Food::findOrFail($recipe['food_id'])->calories, .000001);
        $this->assertEquals(0, app(FoodDeduplicator::class)->run());
        $this->seed(NutritionSeeder::class);
        $this->assertDatabaseMissing('foods', ['id' => $duplicate->id]);
    }

    public function test_distinct_preparations_and_custom_foods_are_not_merged(): void
    {
        $aliases = app(FoodDeduplicator::class)->aliases([
            ['id' => 1, 'name' => 'Arroz, cru', 'source_id' => 11],
            ['id' => 2, 'name' => 'Arroz, cozido', 'source_id' => 3],
            ['id' => 3, 'name' => 'Arroz, cozido', 'source_id' => null],
            ['id' => 4, 'name' => 'Arroz, cozido', 'source_id' => 11],
        ]);
        $this->assertSame([4 => 2], $aliases);
    }
}
