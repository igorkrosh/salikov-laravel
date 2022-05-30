<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return Auth::user();
});

/*** UserController ***/

Route::post('/register', [UserController::class, 'Register']);
Route::post('/login', [UserController::class, 'Login'])->name('login');
Route::post('/logout', [UserController::class, 'Logout']);
Route::post('/user/edit', [UserController::class, 'EditUser']);
Route::post('/user/create', [UserController::class, 'CreateUser']);

Route::middleware('auth:sanctum')->get('/profile', [UserController::class, 'GetUserProfile']);

/**********************/
