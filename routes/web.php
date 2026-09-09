<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NutritionController;
use App\Http\Controllers\PatientController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureApproved;
use Illuminate\Support\Facades\Route;

Route::view('/login', 'app')->name('login');
Route::view('/register', 'app')->name('register');
Route::get('/admin', function () {
    if (auth()->check()) {
        abort_unless(auth()->user()->role === 'admin', 403);
    }

    return view('app');
})->name('admin');
Route::post('/api/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/api/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/api/auth/setup', [AuthController::class, 'setup'])->middleware('throttle:5,1');
Route::post('/api/auth/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::middleware(['auth', EnsureApproved::class])->group(function () {
    Route::view('/', 'app')->name('home');
    Route::prefix('api')->group(function () {
        Route::get('bootstrap', [NutritionController::class, 'bootstrap']);
        Route::post('calculate', [NutritionController::class, 'calculate']);
        Route::post('plans', [NutritionController::class, 'savePlan']);
        Route::put('plans/{plan}', [NutritionController::class, 'savePlan']);
        Route::delete('plans/{plan}', [NutritionController::class, 'deletePlan']);
        Route::post('foods', [NutritionController::class, 'saveFood']);
        Route::put('foods/{food}', [NutritionController::class, 'saveFood']);
        Route::delete('foods/{food}', [NutritionController::class, 'deleteFood']);
        Route::post('recipes', [NutritionController::class, 'saveRecipe']);
        Route::post('patients', [PatientController::class, 'save']);
        Route::put('patients/{patient}', [PatientController::class, 'save']);
        Route::delete('patients/{patient}', [PatientController::class, 'delete']);
        Route::middleware(EnsureAdmin::class)->prefix('admin')->group(function () {
            Route::get('users', [AdminController::class, 'index']);
            Route::patch('users/{user}', [AdminController::class, 'update']);
        });
    });
});
