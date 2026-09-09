<?php

namespace App\Services;

use App\Models\Food;

class Nutrition
{
    public const NUTRIENTS = ['calories', 'protein', 'carbs', 'fat', 'fiber', 'sodium'];

    public function profile(array $p): array
    {
        $male = $p['sex'] === 'Masculino';
        $bmi = $p['weight'] / ($p['height'] / 100) ** 2;
        $bmr = $male
            ? 66 + 13.7 * $p['weight'] + 5 * $p['height'] - 6.8 * $p['age']
            : 655 + 9.6 * $p['weight'] + 1.8 * $p['height'] - 4.7 * $p['age'];
        $activities = collect(json_decode(file_get_contents(database_path('imports/seed.json')), true)['activities']);
        $energy = $bmr * $activities->firstWhere('name', $p['activity'])['factor'];
        $delta = $p['adjustment'] ?? 0;
        $target = $energy + ($p['goal'] === 'Ganhar Peso' ? $delta : ($p['goal'] === 'Perder Peso' ? -$delta : 0));
        $limits = $male ? [20, 25, 30, 40] : [19, 24, 29, 39];
        $labels = ['Abaixo do Normal', 'Normal', 'Obesidade Leve', 'Obesidade Moderada', 'Obesidade Mórbida'];
        $index = 0;
        foreach ($limits as $limit) {
            if ($bmi >= $limit) {
                $index++;
            }
        }

        return ['bmi' => $bmi, 'bmi_label' => $labels[$index], 'bmr' => $bmr, 'energy' => $energy, 'target' => $target];
    }

    public function items(array $items, $foods): array
    {
        $totals = array_fill_keys(self::NUTRIENTS, 0.0);
        $missing = array_fill_keys(self::NUTRIENTS, 0);
        $quantity = 0;
        foreach ($items as $item) {
            $food = $foods[$item['food_id']];
            $consumedQuantity = BeverageUnits::quantity($item, $food);
            $quantity += $consumedQuantity * ($food->raw_values['volume_conversion']['density_g_ml'] ?? 1);
            foreach (self::NUTRIENTS as $key) {
                if ($food[$key] === null && $consumedQuantity > 0) {
                    $missing[$key]++;
                } else {
                    $totals[$key] += ($food[$key] ?? 0) * $consumedQuantity / $food['base_quantity'];
                }
            }
        }

        return ['totals' => $totals, 'missing' => $missing, 'quantity' => $quantity];
    }

    public function plan(array $data): array
    {
        $foods = Food::all()->keyBy('id');
        $all = [];
        $meals = [];
        $groups = [];
        foreach ($data['meals'] as $meal) {
            $meals[] = $this->items($meal['items'], $foods);
            foreach ($meal['items'] as $item) {
                $all[] = $item;
                $groups[$foods[$item['food_id']]['category']][] = $item;
            }
        }
        $categories = [];
        foreach ($groups as $name => $items) {
            $categories[] = ['name' => $name, 'count' => count($items), ...$this->items($items, $foods)];
        }
        $profile = $this->profile($data['profile']);
        $summary = $this->items($all, $foods);

        return [...$summary, 'profile' => $profile, 'meals' => $meals, 'categories' => $categories,
            'percentage' => $profile['target'] > 0 && ! $summary['missing']['calories'] ? $summary['totals']['calories'] / $profile['target'] * 100 : null];
    }
}
