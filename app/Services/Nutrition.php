<?php

namespace App\Services;

use App\Models\Food;

class Nutrition
{
    public const NUTRIENTS = ['calories', 'protein', 'carbs', 'fat', 'fiber', 'sodium'];

    public function profile(array $profile): array
    {
        $male = $profile['sex'] === 'Masculino';
        $age = (int) $profile['age'];
        $weight = (float) $profile['weight'];
        $height = (float) $profile['height'];
        $bmi = $weight / ($height / 100) ** 2;
        $activityLevel = $this->activityLevel($profile['activity']);
        $energy = $this->estimatedEnergy($age, $height, $weight, $male, $activityLevel);
        $hasAdjustment = array_key_exists('adjustment', $profile) && $profile['adjustment'] !== null && $profile['adjustment'] !== '';
        $adjustment = $hasAdjustment ? (float) $profile['adjustment'] : match ($profile['goal']) {
            'Perder Peso' => 500.0,
            'Ganhar Peso' => $energy * 0.05,
            default => 0.0,
        };
        $energyAdjustment = match ($profile['goal']) {
            'Perder Peso' => -$adjustment,
            'Ganhar Peso' => $adjustment,
            default => 0.0,
        };
        $target = $energy + $energyAdjustment;
        $result = [
            'bmi' => $bmi,
            'bmi_label' => $this->bmiLabel($bmi, $age),
            'energy' => $energy,
            'target' => $target,
            'energy_adjustment' => $energyAdjustment,
            'automatic_adjustment' => ! $hasAdjustment,
            'method' => 'NASEM EER 2023',
            'macros' => $target > 0 ? $this->macroTargets($profile, $target, $activityLevel) : null,
        ];
        $bodyFat = (float) ($profile['body_fat'] ?? 0);
        if ($bodyFat > 0) {
            $result['fat_mass'] = $weight * $bodyFat / 100;
            $result['lean_mass'] = $weight - $result['fat_mass'];
        }
        $waist = (float) ($profile['waist'] ?? 0);
        if ($waist > 0) {
            $result['waist_height_ratio'] = $waist / $height;
            $result['waist_height_label'] = $age < 5 || $bmi >= 35
                ? 'Interpretar clinicamente'
                : match (true) {
                    $result['waist_height_ratio'] < 0.4 => 'Abaixo da faixa de referência',
                    $result['waist_height_ratio'] < 0.5 => 'Faixa saudável',
                    $result['waist_height_ratio'] < 0.6 => 'Adiposidade central aumentada',
                    default => 'Adiposidade central alta',
                };
        }
        $targetWeight = (float) ($profile['target_weight'] ?? 0);
        if ($targetWeight > 0) {
            $result['weight_delta'] = $targetWeight - $weight;
        }

        return $result;
    }

    private function activityLevel(string $activity): string
    {
        return match ($activity) {
            'Levemente Ativo' => 'low',
            'Moderadamente Ativo' => 'active',
            'Bastante Ativo', 'Muito Ativo' => 'very',
            default => 'inactive',
        };
    }

    private function estimatedEnergy(int $age, float $height, float $weight, bool $male, string $activityLevel): float
    {
        if ($age < 3) {
            return $male
                ? -716.45 - $age + 17.82 * $height + 15.06 * $weight + 20
                : -69.15 + 80 * $age + 2.65 * $height + 54.15 * $weight + 15;
        }
        $equations = $age < 19 ? [
            'male' => ['inactive' => [-447.51, 3.68, 13.01, 13.15], 'low' => [19.12, 3.68, 8.62, 20.28], 'active' => [-388.19, 3.68, 12.66, 20.46], 'very' => [-671.75, 3.68, 15.38, 23.25]],
            'female' => ['inactive' => [55.59, -22.25, 8.43, 17.07], 'low' => [-297.54, -22.25, 12.77, 14.73], 'active' => [-189.55, -22.25, 11.74, 18.34], 'very' => [-709.59, -22.25, 18.22, 14.25]],
        ] : [
            'male' => ['inactive' => [753.07, -10.83, 6.50, 14.10], 'low' => [581.47, -10.83, 8.30, 14.94], 'active' => [1004.82, -10.83, 6.52, 15.91], 'very' => [-517.88, -10.83, 15.61, 19.11]],
            'female' => ['inactive' => [584.90, -7.01, 5.72, 11.71], 'low' => [575.77, -7.01, 6.60, 12.14], 'active' => [710.25, -7.01, 6.54, 12.34], 'very' => [511.83, -7.01, 9.07, 12.56]],
        ];
        [$base, $ageCoefficient, $heightCoefficient, $weightCoefficient] = $equations[$male ? 'male' : 'female'][$activityLevel];
        $growth = $age >= 19 ? 0 : match (true) {
            $age === 3 => $male ? 20 : 15,
            $age <= 8 => 15,
            $age <= 13 => $male ? 25 : 30,
            default => 20,
        };

        return $base + $ageCoefficient * $age + $heightCoefficient * $height + $weightCoefficient * $weight + $growth;
    }

    private function bmiLabel(float $bmi, int $age): string
    {
        if ($age < 19) {
            return 'Avaliar por curva de crescimento';
        }

        return match (true) {
            $bmi < 18.5 => 'Abaixo do peso',
            $bmi < 25 => 'Eutrofia',
            $bmi < 30 => 'Sobrepeso',
            $bmi < 35 => 'Obesidade grau I',
            $bmi < 40 => 'Obesidade grau II',
            default => 'Obesidade grau III',
        };
    }

    private function macroTargets(array $profile, float $target, string $activityLevel): array
    {
        $age = (int) $profile['age'];
        $weight = (float) $profile['weight'];
        $adult = $age >= 19;
        $proteinPercentage = 15.0;
        $protein = $target * 0.15 / 4;
        $fatPercentage = $age <= 3 ? 35.0 : 30.0;
        if ($adult) {
            $targetWeight = (float) ($profile['target_weight'] ?? 0);
            $referenceWeight = $profile['goal'] === 'Perder Peso' && $targetWeight > 0 && $targetWeight < $weight ? $targetWeight : $weight;
            $gramsPerKilogram = match ($profile['goal']) {
                'Perder Peso' => 1.3,
                'Ganhar Peso' => 1.6,
                default => $activityLevel === 'inactive' ? 0.8 : 1.2,
            };
            $protein = max($referenceWeight * $gramsPerKilogram, $target * 0.10 / 4);
            $protein = min($protein, $target * 0.35 / 4);
            $proteinPercentage = $protein * 4 / $target * 100;
            $fatPercentage = max(20, min(30, 55 - $proteinPercentage));
        }
        $fat = $target * ($fatPercentage / 100) / 9;
        $carbs = max(0, ($target - $protein * 4 - $fat * 9) / 4);
        $carbsPercentage = $carbs * 4 / $target * 100;
        $ranges = $adult
            ? ['protein' => [10, 35], 'carbs' => [45, 65], 'fat' => [20, 35]]
            : ($age <= 3 ? ['protein' => [5, 20], 'carbs' => [45, 65], 'fat' => [30, 40]] : ['protein' => [10, 30], 'carbs' => [45, 65], 'fat' => [25, 35]]);

        return [
            'protein' => ['grams' => $protein, 'percentage' => $proteinPercentage, 'grams_per_kg' => $protein / $weight, 'range_grams' => [$target * $ranges['protein'][0] / 400, $target * $ranges['protein'][1] / 400]],
            'carbs' => ['grams' => $carbs, 'percentage' => $carbsPercentage, 'range_grams' => [$target * $ranges['carbs'][0] / 400, $target * $ranges['carbs'][1] / 400]],
            'fat' => ['grams' => $fat, 'percentage' => $fatPercentage, 'range_grams' => [$target * $ranges['fat'][0] / 900, $target * $ranges['fat'][1] / 900]],
            'fiber' => ['grams' => $target * 14 / 1000],
        ];
    }

    public function items(array $items, $foods): array
    {
        $result = $this->emptyTotal();
        foreach ($items as $item) {
            $itemResult = $this->item($item, $foods);
            $result['quantity'] += $itemResult['quantity'];
            foreach (self::NUTRIENTS as $nutrient) {
                $result['totals'][$nutrient] += $itemResult['totals'][$nutrient];
                $result['missing'][$nutrient] += $itemResult['missing'][$nutrient];
            }
        }

        return $result;
    }

    private function item(array $item, $foods): array
    {
        $primary = $this->option($item, $foods[$item['food_id']]);
        if (! isset($item['alternative'])) {
            return $primary;
        }

        $alternative = $this->option($item['alternative'], $foods[$item['alternative']['food_id']]);
        $result = $this->emptyTotal();
        $result['quantity'] = ($primary['quantity'] + $alternative['quantity']) / 2;
        foreach (self::NUTRIENTS as $nutrient) {
            $result['totals'][$nutrient] = ($primary['totals'][$nutrient] + $alternative['totals'][$nutrient]) / 2;
            $result['missing'][$nutrient] = $primary['missing'][$nutrient] + $alternative['missing'][$nutrient];
        }

        return $result;
    }

    private function option(array $option, Food $food): array
    {
        $result = $this->emptyTotal();
        $consumedQuantity = BeverageUnits::quantity($option, $food);
        $result['quantity'] = $consumedQuantity * ($food->raw_values['volume_conversion']['density_g_ml'] ?? 1);
        foreach (self::NUTRIENTS as $nutrient) {
            if ($food[$nutrient] === null && $consumedQuantity > 0) {
                $result['missing'][$nutrient] = 1;
            } else {
                $result['totals'][$nutrient] = ($food[$nutrient] ?? 0) * $consumedQuantity / $food['base_quantity'];
            }
        }

        return $result;
    }

    private function emptyTotal(): array
    {
        return ['totals' => array_fill_keys(self::NUTRIENTS, 0.0), 'missing' => array_fill_keys(self::NUTRIENTS, 0), 'quantity' => 0.0];
    }

    public function plan(array $data): array
    {
        $all = collect($data['meals'])->flatMap(fn (array $meal) => $meal['items'])->all();
        $foodIds = collect($all)->flatMap(fn (array $item): array => array_filter([$item['food_id'], $item['alternative']['food_id'] ?? null]))->unique();
        $foods = Food::whereKey($foodIds)->get()->keyBy('id');
        $meals = [];
        $groups = [];
        foreach ($data['meals'] as $meal) {
            $meals[] = $this->items($meal['items'], $foods);
            foreach ($meal['items'] as $item) {
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
