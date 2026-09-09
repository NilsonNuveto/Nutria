<?php

namespace Database\Seeders;

use App\Models\Food;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IbgeFoodSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('imports/ibge-breakfast.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($data): void {
            $source = $data['source'];
            DB::table('sources')->updateOrInsert(['id' => $source['id']], [
                'name' => $source['name'], 'data' => json_encode($source, JSON_UNESCAPED_UNICODE),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($data['foods'] as $food) {
                Food::firstOrCreate(['source_id' => $source['id'], 'source_key' => $food['source_key']], $food);
            }
            $source['imported_count'] = Food::where('source_id', $source['id'])->count();
            DB::table('sources')->where('id', $source['id'])->update(['data' => json_encode($source, JSON_UNESCAPED_UNICODE)]);
        });
    }
}
