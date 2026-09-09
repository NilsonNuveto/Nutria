<?php

namespace App\Services;

use App\Models\Food;
use Illuminate\Support\Str;

class BeverageUnits
{
    public const MEASURES = ['mL' => 1, 'cup' => 200, 'coffee_cup' => 50, 'glass' => 240, 'goblet' => 150];

    public function run(): void
    {
        foreach (Food::whereIn('source_id', [3, 11, 12])->get() as $food) {
            if (isset($food->raw_values['volume_conversion'])) {
                continue;
            }
            $name = Str::ascii(Str::lower($food->name));
            $reference = Str::lower($food->raw_values['reference_description'] ?? '');
            if (preg_match('/\bpo\b|condensado|farinha|unprepared|dry instant/', $name.' '.$reference)) {
                continue;
            }
            if (! preg_match('/\bsuco\b|^cafe|^cha[ ,]|^mate |^agua de coco|^leite|^bebida|^vitamina|^ades|^yakult|^refrigerante|^cerveja|aguardente/', $name)) {
                continue;
            }
            [$density, $description, $estimated] = $this->density($name);
            $original = ['base_quantity' => $food->base_quantity, 'unit' => $food->unit];
            foreach (Nutrition::NUTRIENTS as $nutrient) {
                $original[$nutrient] = $food->$nutrient;
                $food->$nutrient = $food->$nutrient === null ? null : $food->$nutrient * 100 * $density / $food->base_quantity;
            }
            $food->raw_values = [...($food->raw_values ?? []), 'volume_conversion' => [
                'density_g_ml' => $density, 'description' => $description, 'estimated' => $estimated,
                'source_url' => $estimated ? 'https://www.ibge.gov.br/biblioteca/visualizacao/livros/liv50000.pdf' : 'https://www.fao.org/4/ap815e/ap815e.pdf',
                'original' => $original,
            ]];
            $food->base_quantity = 100;
            $food->unit = 'mL';
            $food->notes = trim(($food->notes ?? '')."\nValores exibidos convertidos para 100 mL usando densidade de {$density} g/mL. {$description}. ".($estimated ? 'Conversão aproximada pela convenção de líquidos do IBGE; não é densidade medida deste produto.' : 'FAO/INFOODS, Density Database v2 (2012); densidade de referência para esta classe de bebida.'));
            $food->save();
        }
    }

    /** @return array{float, string, bool} */
    private function density(string $name): array
    {
        return match (true) {
            str_contains($name, 'concentrado'), str_contains($name, 'coco'), str_contains($name, 'capuccino'), str_contains($name, 'com leite'), str_contains($name, 'yakult'), str_contains($name, 'achocolatado'), str_contains($name, 'lactea') => [1.0, 'Convenção IBGE para líquidos sem densidade específica', true],
            str_contains($name, 'soja'), str_starts_with($name, 'ades') => [1.08, 'Soy drink', false],
            str_contains($name, 'cabra') => [1.08, 'Milk, goat, whole', false],
            str_contains($name, 'fermentado') => [1.01, 'Milk, acidophilus cultured', false],
            str_starts_with($name, 'leite') => [1.03, 'Milk, liquid (whole, semi-skimmed, skimmed)', false],
            str_contains($name, 'laranja') && str_contains($name, 'suco') && ! str_contains($name, 'banana') => [1.038, 'Orange juice', false],
            str_contains($name, 'suco') => [1.06, 'Fruit juice (valor genérico aproximado para sucos)', false],
            str_starts_with($name, 'vitamina') => [1.0, 'Milk shake, fruit and other', false],
            str_starts_with($name, 'cafe') => [1.0, 'Coffee, brewed / expresso', false],
            str_starts_with($name, 'cha'), str_starts_with($name, 'mate') => [1.0, 'Tea, liquid', false],
            str_contains($name, 'isotonica') => [1.03, 'Sports drink, ready-to-drink', false],
            str_contains($name, 'cerveja') => [1.007, 'Beer, pilsner', false],
            str_contains($name, 'aguardente') => [0.95, 'Spirits, 40% alcohol (aproximação)', false],
            str_contains($name, 'cola') => [1.04, 'Soft drinks, cola type', false],
            str_contains($name, 'tonica'), str_contains($name, 'limao') => [1.02, 'Carbonated beverage / lemonade type', false],
            str_contains($name, 'refrigerante'), str_contains($name, 'laranja') => [1.029, 'Orange soda (aproximação para refrigerante açucarado)', false],
            default => [1.0, 'Convenção IBGE para líquidos sem densidade específica', true],
        };
    }

    public static function quantity(array $item, Food $food): float
    {
        $quantity = (float) $item['quantity'] * ($item['multiplier'] ?? 1);
        if ($food->unit !== 'mL') {
            return $quantity;
        }
        $density = $food->raw_values['volume_conversion']['density_g_ml'] ?? 1;
        $measure = $item['measure'] ?? (isset($food->raw_values['volume_conversion']) ? 'g' : 'mL');

        return $measure === 'g' ? $quantity / $density : $quantity * ($measure === 'goblet' ? ($item['measure_ml'] ?? 150) : (self::MEASURES[$measure] ?? 1));
    }
}
