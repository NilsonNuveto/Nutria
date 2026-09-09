<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = mb_strtolower(trim((string) config('nutria.admin_email')));
        $name = trim((string) config('nutria.admin_name'));
        $token = (string) config('nutria.admin_setup_token');

        if ($email === '') {
            throw new \RuntimeException('Defina NUTRIA_ADMIN_EMAIL antes de executar o seeder de produção.');
        }

        $admin = User::where('email', $email)->first();

        if (! $admin) {
            if ($token === '') {
                throw new \RuntimeException('Defina NUTRIA_ADMIN_SETUP_TOKEN antes de criar o administrador inicial.');
            }

            $admin = new User;
            $admin->forceFill([
                'name' => $name !== '' ? $name : 'Administrador Nutria',
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'role' => 'admin',
                'status' => 'approved',
                'approved_at' => now(),
                'setup_token' => hash('sha256', $token),
            ])->save();
        }

        if ($admin->role !== 'admin') {
            throw new \RuntimeException('O e-mail reservado ao administrador já pertence a outra conta.');
        }

        $legacyPlanIds = DB::table('plans')->whereNull('user_id')->pluck('id');

        foreach (['plans', 'recipes'] as $table) {
            DB::table($table)->whereNull('user_id')->update(['user_id' => $admin->id]);
        }

        DB::table('foods')->whereNull('source_id')->whereNull('user_id')->update(['user_id' => $admin->id]);

        foreach (DB::table('plans')->whereIn('id', $legacyPlanIds)->whereNull('patient_id')->get() as $plan) {
            $data = json_decode($plan->data, true);
            $patientName = trim($data['profile']['name'] ?? '') ?: 'Paciente importado';
            $patient = DB::table('patients')->where('user_id', $admin->id)->where('name', $patientName)->first();
            $id = $patient?->id ?? DB::table('patients')->insertGetId([
                'user_id' => $admin->id,
                'name' => $patientName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('plans')->where('id', $plan->id)->update(['patient_id' => $id]);
        }
    }

}
