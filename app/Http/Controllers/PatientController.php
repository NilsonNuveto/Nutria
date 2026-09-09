<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Plan;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PatientController extends Controller
{
    public function save(Request $request, ?Patient $patient = null): Patient
    {
        $data = $request->validate(['name' => 'required|string|max:160', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:40', 'birth_date' => 'nullable|date|before_or_equal:today', 'notes' => 'nullable|string|max:10000']);
        if ($patient) {
            $patient->update($data);

            return $patient;
        }

        return Patient::create([...$data, 'user_id' => $request->user()->id]);
    }

    public function delete(Patient $patient): Response
    {
        abort_if(Plan::where('patient_id', $patient->id)->exists(), 409, 'Remova ou desvincule os planos deste paciente primeiro.');
        $patient->delete();

        return response()->noContent();
    }
}
