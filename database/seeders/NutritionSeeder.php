<?php

namespace Database\Seeders;

use App\Models\Food;
use App\Models\Plan;
use App\Services\BeverageUnits;
use App\Services\FoodDeduplicator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NutritionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminSeeder::class);
        $data = json_decode(file_get_contents(database_path('imports/seed.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($data) {
            $deduplicator = app(FoodDeduplicator::class);
            $aliases = $deduplicator->aliases($data['foods']);
            foreach ($data['meals'] as &$meal) {
                foreach ($meal['items'] as &$item) {
                    $item['food_id'] = $aliases[$item['food_id']] ?? $item['food_id'];
                }
                unset($item);
            }
            unset($meal);
            foreach ($data['sources'] as $s) {
                DB::table('sources')->updateOrInsert(['id' => $s['id']], ['name' => $s['name'], 'data' => json_encode($s, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($data['foods'] as $f) {
                if (isset($aliases[$f['id']])) {
                    continue;
                }
                Food::updateOrCreate(['source_id' => $f['source_id'], 'source_key' => $f['source_key']], $f);
            }
            Plan::firstOrCreate(['id' => 1], ['name' => $data['profile']['name'], 'data' => ['profile' => $data['profile'], 'meals' => $data['meals'], 'notes' => '']]);
            $deduplicator->run($aliases);
            $this->call(IbgeFoodSeeder::class);
            app(BeverageUnits::class)->run();
            $this->call(AdminSeeder::class);
        });
    }
}
