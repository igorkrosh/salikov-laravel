<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\WebinarController;
use App\Http\Controllers\ModuleController;

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

Route::middleware('auth:sanctum')->get('/profile', [UserController::class, 'GetUserProfile']);
Route::middleware('auth:sanctum')->get('/user/calendar', [UserController::class, 'GetCalendar']);


Route::middleware('auth:sanctum')->post('/user/edit', [UserController::class, 'EditUser']);
Route::middleware('auth:sanctum')->post('/user/{userId}/edit', [UserController::class, 'EditSpecificUser']);
Route::middleware('auth:sanctum')->post('/user/create', [UserController::class, 'CreateUser']);
Route::middleware('auth:sanctum')->post('/user/all', [UserController::class, 'GetAllUsers']);

/**********************/


/*** CourseController ***/
Route::middleware('auth:sanctum')->get('/course/get-by-user', [CourseController::class, 'GetCoursesByUser']);
Route::middleware('auth:sanctum')->get('/course/{courseId}/get', [CourseController::class, 'GetCourseById']);
Route::middleware('auth:sanctum')->get('/course/{courseId}/users', [CourseController::class, 'GetCourseUsers']);
Route::middleware('auth:sanctum')->get('/course/{courseId}/blocks', [CourseController::class, 'GetCourseBlocks']);
Route::middleware('auth:sanctum')->get('/course/{courseId}/user/{userId}/access', [CourseController::class, 'GetCourseUserAccess']);

Route::middleware('auth:sanctum')->post('/course/create', [CourseController::class, 'CreateCourse']);
Route::middleware('auth:sanctum')->post('/course/{courseId}/edit', [CourseController::class, 'EditCourse']);
Route::middleware('auth:sanctum')->post('/course/{courseId}/set-access', [CourseController::class, 'AddUserAccess']);
Route::middleware('auth:sanctum')->post('/course/{courseId}/user/{userId}/set-access', [CourseController::class, 'SetCourseUserAccess']);


Route::middleware('auth:sanctum')->delete('/course/{courseId}/delete', [CourseController::class, 'DeleteCourse']);

/************************/

/*** WebinarController ***/

Route::middleware('auth:sanctum')->get('/webinar/get-by-user', [WebinarController::class, 'GetWebinarsByUser']);
Route::middleware('auth:sanctum')->get('/webinar/{webinarId}/get', [WebinarController::class, 'GetWebinarById']);

Route::middleware('auth:sanctum')->post('/webinar/create', [WebinarController::class, 'CreateWebinar']);
Route::middleware('auth:sanctum')->post('/webinar/{webinarId}/edit', [WebinarController::class, 'EditWebinar']);

Route::middleware('auth:sanctum')->delete('/webinar/{webinarId}/delete', [WebinarController::class, 'DeleteWebinar']);

/*************************/

/*** ModuleController ***/

Route::middleware('auth:sanctum')->get('/module/{type}/{moduleId}', [ModuleController::class, 'GetModuleById']);
Route::middleware('auth:sanctum')->get('/module/test/{moduleId}/result', [ModuleController::class, 'GetTestResult']);
Route::middleware('auth:sanctum')->get('/module/{type}/{moduleId}/progress', [ModuleController::class, 'GetModuleProgress']);

Route::middleware('auth:sanctum')->post('/module/{type}/{moduleId}/status', [ModuleController::class, 'SetModuleProgress']);
Route::middleware('auth:sanctum')->post('/module/{type}/{moduleId}/task', [ModuleController::class, 'SetModuleTask']);
Route::middleware('auth:sanctum')->post('/module/test/{moduleId}/result', [ModuleController::class, 'SetTestResult']);


/************************/