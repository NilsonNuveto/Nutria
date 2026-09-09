<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (DB::table('plans')->get() as $plan) {
                $data = json_decode($plan->data, true);
                $name = $data['profile']['name'] ?? '';
                if ($name !== '' && $plan->name !== $name) {
                    DB::table('plans')->where('id', $plan->id)->update(['name' => $name, 'version' => $plan->version + 1]);
                }
            }
        });
    }

    public function down(): void {}
};
