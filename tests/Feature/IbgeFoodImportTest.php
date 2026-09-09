<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Plan;
use Database\Seeders\NutritionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IbgeFoodImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_expansion_exceeds_thirty_percent_and_preserves_saved_plans(): void
    {
        $this->seed(NutritionSeeder::class);
        $this->assertGreaterThanOrEqual(783, Food::count());
        $this->assertSame(197, Food::where('source_id', 12)->count());
        $plan = Plan::first();
        $data = $plan->data;
        $data['meals'][0]['items'][0]['multiplier'] = 2.5;
        $plan->update(['data' => $data]);
        $before = $plan->fresh()->toArray();
        $ids = Food::pluck('id', 'name')->all();
        $this->seed(NutritionSeeder::class);
        $this->assertSame($before, $plan->fresh()->toArray());
        $this->assertSame($ids, Food::pluck('id', 'name')->all());
        $this->assertDatabaseCount('foods', 799);
        $manifest = json_decode(file_get_contents(database_path('imports/ibge-breakfast.json')), true);
        foreach ($manifest['foods'] as $entry) {
            $food = Food::where('source_id', 12)->where('source_key', $entry['source_key'])->firstOrFail();
            $this->assertSame($entry['source_row'], $food->source_row);
            $this->assertSame($entry['raw_values']['reference_code'], $food->raw_values['reference_code']);
        }
    }
}
