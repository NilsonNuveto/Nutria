<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('data');
            $t->timestamps();
        });
        Schema::create('foods', function (Blueprint $t) {
            $t->id();
            $t->string('name')->index();
            $t->string('category')->index();
            $t->double('base_quantity');
            $t->string('unit')->default('g/mL');
            foreach (['calories', 'protein', 'carbs', 'fat', 'fiber', 'sodium'] as $key) {
                $t->double($key)->nullable();
            }
            $t->text('notes')->nullable();
            $t->foreignId('source_id')->nullable()->constrained('sources');
            $t->string('source_key')->nullable();
            $t->unsignedInteger('source_row')->nullable();
            $t->json('raw_values')->nullable();
            $t->unique(['source_id', 'source_key']);
            $t->timestamps();
        });
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('data');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('recipes', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('ingredients');
            $t->foreignId('food_id')->constrained('foods');
            $t->text('instructions')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['recipes', 'plans', 'foods', 'sources'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
