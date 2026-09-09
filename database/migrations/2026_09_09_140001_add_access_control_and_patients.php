<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('nutritionist');
            $table->string('status')->default('pending')->index();
            $table->string('crn')->nullable();
            $table->string('setup_token')->nullable();
            $table->timestamp('approved_at')->nullable();
        });
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('birth_date')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        foreach (['foods', 'plans', 'recipes'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            });
        }
        Schema::table('plans', function (Blueprint $table): void {
            $table->foreignId('patient_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plans', fn (Blueprint $table) => $table->dropConstrainedForeignId('patient_id'));
        foreach (['foods', 'plans', 'recipes'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        }
        Schema::dropIfExists('patients');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'status', 'crn', 'setup_token', 'approved_at']));
    }
};
