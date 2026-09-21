<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Recipe;
use App\Services\Nutrition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class NutritionController extends Controller
{
    public function bootstrap(Request $request)
    {
        $seed = json_decode(file_get_contents(database_path('imports/seed.json')), true, 512, JSON_THROW_ON_ERROR);
        $includeCatalog = $request->boolean('catalog', true);
        $excludedDefaultMeals = ['Pré-Treino', 'Pós-Treino', 'Lanche - Noite'];
        $defaultMeals = array_values(array_filter($seed['meals'], fn (array $meal): bool => ! in_array($meal['name'], $excludedDefaultMeals, true)));
        $sharedFoods = json_decode(file_get_contents(database_path('imports/catalog.json')), true, 512, JSON_THROW_ON_ERROR);
        $foods = collect();
        if ($includeCatalog) {
            $catalogBySource = collect($sharedFoods)->keyBy(fn (array $food): string => $food['source_id'].'|'.$food['source_key']);
            $sharedFoodRecords = Food::whereNotNull('source_id')->get(['id', 'source_id', 'source_key'])->map(function (Food $food) use ($catalogBySource): ?array {
                $catalogFood = $catalogBySource->get($food->source_id.'|'.$food->source_key);

                return $catalogFood ? [...$catalogFood, 'id' => $food->id] : null;
            })->filter();
            $customFoods = Food::whereNull('source_id')->orderBy('name')->get();
            $foods = $sharedFoodRecords->concat($customFoods)->sortBy('name')->values();
        }

        return ['user' => auth()->user(), 'patients' => Patient::orderBy('name')->get(), 'template' => ['name' => 'Novo plano', 'version' => 1, 'patient_id' => null, 'data' => ['profile' => [...$seed['profile'], 'name' => '', 'weight' => 70, 'height' => 170, 'age' => 30, 'body_fat' => null, 'waist' => null, 'target_weight' => null], 'meals' => array_map(fn ($meal) => [...$meal, 'items' => []], $defaultMeals), 'notes' => '']], 'foods' => $foods, 'plans' => Plan::orderByDesc('updated_at')->get(), 'recipes' => Recipe::all(),
            'sources' => DB::table('sources')->get()->map(fn ($s) => json_decode($s->data, true)),
            'activities' => $seed['activities'], 'meal_types' => $seed['meal_types'], 'categories' => $includeCatalog ? collect($seed['categories'])->merge($foods->pluck('category'))->unique()->values() : []];
    }

    private function visibleFoodRule(): Exists
    {
        return Rule::exists('foods', 'id')->where(fn ($query) => $query->where(fn ($query) => $query->whereNotNull('source_id')->orWhere('user_id', auth()->id())));
    }

    private function rules(): array
    {
        $seed = json_decode(file_get_contents(database_path('imports/seed.json')), true);

        return ['patient_id' => ['nullable', 'integer', Rule::exists('patients', 'id')->where('user_id', auth()->id())], 'name' => 'sometimes|nullable|string|max:160', 'data' => 'required|array:profile,meals,notes',
            'data.profile' => 'required|array:name,sex,age,weight,height,body_fat,waist,target_weight,activity,goal,adjustment',
            'data.profile.name' => 'required|string|max:160', 'data.profile.sex' => ['required', Rule::in(['Masculino', 'Feminino'])],
            'data.profile.age' => 'required|integer|min:1|max:120', 'data.profile.weight' => 'required|numeric|gt:0|max:500',
            'data.profile.height' => 'required|numeric|gt:0|max:300', 'data.profile.body_fat' => 'nullable|numeric|min:2|max:70',
            'data.profile.waist' => 'nullable|numeric|gt:0|max:300', 'data.profile.target_weight' => 'nullable|numeric|gt:0|max:500', 'data.profile.activity' => ['required', Rule::in(array_column($seed['activities'], 'name'))],
            'data.profile.goal' => ['required', Rule::in(['Perder Peso', 'Manter o Peso', 'Ganhar Peso'])],
            'data.profile.adjustment' => 'nullable|numeric|min:0|max:10000', 'data.notes' => 'nullable|string|max:10000',
            'data.meals' => 'required|array|min:1|max:30', 'data.meals.*' => 'required|array:name,time,items',
            'data.meals.*.name' => ['required', Rule::in($seed['meal_types'])], 'data.meals.*.time' => 'required|date_format:H:i',
            'data.meals.*.items' => 'present|array|max:100', 'data.meals.*.items.*' => 'required|array:food_id,quantity,multiplier,measure,measure_ml,alternative',
            'data.meals.*.items.*.measure' => 'sometimes|required|in:g,mL,cup,coffee_cup,glass,goblet',
            'data.meals.*.items.*.measure_ml' => 'sometimes|required|numeric|gt:0|max:2000',
            'data.meals.*.items.*.multiplier' => 'sometimes|required|numeric|min:0|max:10000',
            'data.meals.*.items.*.food_id' => ['required', 'integer', $this->visibleFoodRule()], 'data.meals.*.items.*.quantity' => 'required|numeric|min:0|max:100000',
            'data.meals.*.items.*.alternative' => 'sometimes|required|array:food_id,quantity,multiplier,measure,measure_ml',
            'data.meals.*.items.*.alternative.measure' => 'sometimes|required|in:g,mL,cup,coffee_cup,glass,goblet',
            'data.meals.*.items.*.alternative.measure_ml' => 'sometimes|required|numeric|gt:0|max:2000',
            'data.meals.*.items.*.alternative.multiplier' => 'sometimes|required|numeric|min:0|max:10000',
            'data.meals.*.items.*.alternative.food_id' => ['required_with:data.meals.*.items.*.alternative', 'integer', $this->visibleFoodRule()],
            'data.meals.*.items.*.alternative.quantity' => 'required_with:data.meals.*.items.*.alternative|numeric|min:0|max:100000'];
    }

    public function calculate(Request $request, Nutrition $nutrition)
    {
        $v = $request->validate($this->rules());

        return $nutrition->plan($v['data']);
    }

    public function savePlan(Request $request, Nutrition $nutrition, ?Plan $plan = null)
    {
        $v = $request->validate([...$this->rules(), 'version' => 'sometimes|integer|min:1']);
        $v['name'] = $v['data']['profile']['name'];
        abort_if($nutrition->profile($v['data']['profile'])['target'] <= 0, 422, 'O ajuste deve resultar em uma meta calórica positiva.');
        if (! $plan) {
            return Plan::create(['name' => $v['name'], 'data' => $v['data'], 'user_id' => $request->user()->id, 'patient_id' => $v['patient_id'] ?? null])->fresh();
        }
        $request->validate(['version' => 'required|integer|min:1']);
        $changed = Plan::whereKey($plan->id)->where('version', $v['version'])->update(['name' => $v['name'], 'data' => $v['data'], 'version' => $v['version'] + 1, 'patient_id' => $v['patient_id'] ?? null]);
        abort_unless($changed, 409, 'Este plano foi alterado em outra janela. Recarregue antes de salvar.');

        return $plan->fresh();
    }

    public function deletePlan(Plan $plan)
    {
        $plan->delete();

        return response()->noContent();
    }

    public function saveFood(Request $request, ?Food $food = null)
    {
        $rules = ['name' => 'required|string|max:200', 'category' => 'required|string|max:100', 'base_quantity' => 'required|numeric|gt:0|max:100000', 'unit' => ['required', Rule::in(['g', 'mL', 'g/mL'])], 'notes' => 'nullable|string|max:10000'];
        foreach (Nutrition::NUTRIENTS as $n) {
            $rules[$n] = 'nullable|numeric|min:0|max:1000000';
        }
        $v = $request->validate($rules);
        if ($food) {
            abort_if($food->source_id !== null, 403, 'Para preservar a fonte, duplique o alimento antes de editar.');
            $food->update($v);

            return $food;
        }

        return Food::create([...$v, 'user_id' => $request->user()->id]);
    }

    public function deleteFood(Food $food)
    {
        abort_if($food->source_id !== null, 403, 'Alimentos de fontes importadas são preservados.');
        foreach (Plan::all() as $p) {
            foreach ($p->data['meals'] as $m) {
                foreach ($m['items'] as $i) {
                    abort_if($i['food_id'] === $food->id || ($i['alternative']['food_id'] ?? null) === $food->id, 409, 'Alimento utilizado em um plano.');
                }
            }
        }
        foreach (Recipe::all() as $r) {
            abort_if($r->food_id === $food->id, 409, 'Alimento vinculado a uma receita.');
            foreach ($r->ingredients as $i) {
                abort_if($i['food_id'] === $food->id, 409, 'Alimento utilizado em uma receita.');
            }
        }
        $food->delete();

        return response()->noContent();
    }

    public function saveRecipe(Request $request, Nutrition $nutrition)
    {
        $v = $request->validate(['name' => 'required|string|max:200', 'instructions' => 'nullable|string|max:10000', 'ingredients' => 'required|array|min:1|max:100',
            'ingredients.*' => 'required|array:food_id,quantity,measure,measure_ml', 'ingredients.*.measure' => 'sometimes|required|in:g,mL,cup,coffee_cup,glass,goblet', 'ingredients.*.measure_ml' => 'sometimes|required|numeric|gt:0|max:2000', 'ingredients.*.food_id' => ['required', 'integer', $this->visibleFoodRule()], 'ingredients.*.quantity' => 'required|numeric|gt:0|max:100000']);

        return DB::transaction(function () use ($v, $nutrition) {
            $foodIds = collect($v['ingredients'])->pluck('food_id')->unique();
            $result = $nutrition->items($v['ingredients'], Food::whereKey($foodIds)->get()->keyBy('id'));
            $values = [];
            foreach (Nutrition::NUTRIENTS as $n) {
                $values[$n] = $result['missing'][$n] ? null : $result['totals'][$n];
            }
            $food = Food::create([...$values, 'name' => $v['name'], 'category' => 'Receitas', 'user_id' => auth()->id(), 'base_quantity' => $result['quantity'], 'unit' => 'g', 'notes' => 'Receita calculada pela soma dos ingredientes.']);

            $recipe = Recipe::create([...$v, 'food_id' => $food->id, 'user_id' => auth()->id()]);

            return $recipe->setRelation('food', $food);
        });
    }
}
