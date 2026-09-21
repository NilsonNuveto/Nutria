<?php

namespace App\Services;

use App\Models\Food;
use App\Models\Plan;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

class FoodDeduplicator
{
    public function aliases(array $foods): array
    {
        $official = [];
        foreach ($foods as $food) {
            if ($food['source_id'] === 3) {
                $official[$this->nameKey($food['name'])] = $food['id'];
            }
        }
        $aliases = [];
        foreach ($foods as $food) {
            $key = $this->nameKey($food['name']);
            if ($food['source_id'] === 11 && isset($official[$key])) {
                $aliases[$food['id']] = $official[$key];
            }
        }

        return $aliases;
    }

    private function nameKey(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim(\Normalizer::normalize($name))));
    }

    public function run(?array $aliases = null): int
    {
        return DB::transaction(function () use ($aliases): int {
            $aliases ??= $this->aliases(Food::all()->toArray());
            foreach ($aliases as $destination) {
                Food::findOrFail($destination);
            }
            foreach (Plan::all() as $plan) {
                $data = $plan->data;
                foreach ($data['meals'] as &$meal) {
                    foreach ($meal['items'] as &$item) {
                        $item['food_id'] = $aliases[$item['food_id']] ?? $item['food_id'];
                        if (isset($item['alternative'])) {
                            $item['alternative']['food_id'] = $aliases[$item['alternative']['food_id']] ?? $item['alternative']['food_id'];
                        }
                    }
                    unset($item);
                }
                unset($meal);
                if ($data !== $plan->data) {
                    $plan->update(['data' => $data, 'version' => $plan->version + 1]);
                }
            }
            $changedFoods = array_fill_keys(array_values($aliases), true);
            foreach (Recipe::orderBy('id')->get() as $recipe) {
                $ingredients = $recipe->ingredients;
                foreach ($ingredients as &$item) {
                    $item['food_id'] = $aliases[$item['food_id']] ?? $item['food_id'];
                }
                unset($item);
                if ($ingredients !== $recipe->ingredients) {
                    $recipe->update(['ingredients' => $ingredients]);
                }
                if (collect($ingredients)->contains(fn (array $item): bool => isset($changedFoods[$item['food_id']]))) {
                    $result = app(Nutrition::class)->items($ingredients, Food::all()->keyBy('id'));
                    $values = ['base_quantity' => $result['quantity']];
                    foreach (Nutrition::NUTRIENTS as $nutrient) {
                        $values[$nutrient] = $result['missing'][$nutrient] ? null : $result['totals'][$nutrient];
                    }
                    Food::findOrFail($recipe->food_id)->update($values);
                    $changedFoods[$recipe->food_id] = true;
                }
            }
            $deleted = Food::whereIn('id', array_keys($aliases))->delete();
            foreach (DB::table('sources')->get() as $source) {
                $data = json_decode($source->data, true);
                $data['original_imported_count'] ??= $data['imported_count'];
                $data['imported_count'] = Food::where('source_id', $source->id)->count();
                DB::table('sources')->where('id', $source->id)->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
            }

            return $deleted;
        });
    }
}
