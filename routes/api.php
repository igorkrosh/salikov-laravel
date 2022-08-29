<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\WebinarController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\TinkoffController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StatisticController;
use App\Http\Controllers\PromocodeController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\SettingsController;

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

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return Auth::user();
});

/*** AuthController ***/

Route::post('/register', [AuthController::class, 'Register']);
Route::post('/login', [AuthController::class, 'Login'])->name('login');
Route::post('/logout', [AuthController::class, 'Logout']);

Route::get('/auth/token', [AuthController::class, 'Token']);

Route::post('/auth/login', [AuthController::class, 'Login']);
Route::post('/auth/logout', [AuthController::class, 'Logout']);

Route::post('/auth/code/email/send', [AuthController::class, 'SendEmailCode']);
Route::post('/auth/code/sms/send', [AuthController::class, 'SendSmsCode']);
Route::post('/auth/verificate/email', [AuthController::class, 'VerificateMail']);
Route::post('/auth/login/email', [AuthController::class, 'LoginByEmail']);
Route::post('/auth/login/sms', [AuthController::class, 'LoginBySms']);

/**********************/

/*** UserController ***/

Route::middleware('auth:sanctum')->get('/user', [UserController::class, 'GetUser']);

Route::middleware('auth:sanctum')->get('/profile', [UserController::class, 'GetUserProfile']);
Route::middleware('auth:sanctum')->get('/user/calendar', [UserController::class, 'GetCalendar']);
Route::middleware('auth:sanctum')->get('/user/notifications', [UserController::class, 'GetUserNotifications']);
Route::middleware('auth:sanctum')->get('/user/progress', [UserController::class, 'GetUserProgress']);
Route::middleware('auth:sanctum')->get('/user/{userId}/profile', [UserController::class, 'GetProfileByUserId']);
Route::middleware('auth:sanctum')->get('/user/referral', [UserController::class, 'ReferralData']);


Route::middleware('auth:sanctum')->post('/user/edit', [UserController::class, 'EditUser']);
Route::middleware('auth:sanctum')->post('/user/{userId}/edit', [UserController::class, 'EditSpecificUser']);
Route::middleware('auth:sanctum')->post('/user/create', [UserController::class, 'CreateUser']);
Route::middleware('auth:sanctum')->post('/user/all', [UserController::class, 'GetAllUsers']);

/**********************/


/*** CourseController ***/

Route::middleware('auth:sanctum')->get('/course/get-by-user', [CourseController::class, 'GetCoursesByUser']);
Route::middleware('auth:sanctum')->get('/course/{courseId}/get', [CourseController::class, 'GetCourseById']);
Route::middleware('auth:sanctum')->get('/course/{courseId}/get/all', [CourseController::class, 'GetCourse']);
Route::middleware('auth:sanctum')->get('/course/{courseId}/users', [CourseController::class, 'GetCourseUsers']);
Route::middleware('auth:sanctum')->get('/course/{courseId}/blocks', [CourseController::class, 'GetCourseBlocks']);
Route::middleware('auth:sanctum')->get('/course/{courseId}/user/{userId}/access', [CourseController::class, 'GetCourseUserAccess']);
Route::middleware('auth:sanctum')->get('/course/all', [CourseController::class, 'GetCourseAll']);
Route::middleware('auth:sanctum')->get('/course/status/{status}', [CourseController::class, 'GetCoursesByStatus']);
Route::middleware('auth:sanctum')->get('/course/recomendations', [CourseController::class, 'GetRecomendations']);
Route::middleware('auth:sanctum')->get('/course/categories', [CourseController::class, 'CourseCaterogies']);
Route::middleware('auth:sanctum')->get('/course/filter', [CourseController::class, 'CourseFilter']);

Route::middleware('auth:sanctum')->post('/course/create', [CourseController::class, 'CreateCourse']);
Route::middleware('auth:sanctum')->post('/course/{courseId}/edit', [CourseController::class, 'EditCourse']);
Route::middleware('auth:sanctum')->post('/course/{courseId}/set-access', [CourseController::class, 'AddUserAccess']);
Route::middleware('auth:sanctum')->post('/course/{courseId}/user/{userId}/set-access', [CourseController::class, 'SetCourseUserAccess']);


Route::middleware('auth:sanctum')->delete('/course/{courseId}/delete', [CourseController::class, 'DeleteCourse']);

/************************/

/*** WebinarController ***/

Route::middleware('auth:sanctum')->get('/webinar/get-by-user', [WebinarController::class, 'GetWebinarsByUser']);
Route::middleware('auth:sanctum')->get('/stream/get', [WebinarController::class, 'GetStreams']);
Route::middleware('auth:sanctum')->get('/webinar/{webinarId}/get', [WebinarController::class, 'GetWebinarById']);
Route::middleware('auth:sanctum')->get('/webinar/{webinarId}/config', [WebinarController::class, 'GetWebinar']);
Route::middleware('auth:sanctum')->get('/webinar/all', [WebinarController::class, 'GetWebinarAll']);

Route::middleware('auth:sanctum')->post('/webinar/create', [WebinarController::class, 'CreateWebinar']);
Route::middleware('auth:sanctum')->post('/webinar/{webinarId}/edit', [WebinarController::class, 'EditWebinar']);

Route::middleware('auth:sanctum')->delete('/webinar/{webinarId}/delete', [WebinarController::class, 'DeleteWebinar']);

/*************************/

/*** ModuleController ***/

Route::middleware('auth:sanctum')->get('/module/{type}/{moduleId}', [ModuleController::class, 'GetModuleById']);
Route::middleware('auth:sanctum')->get('/module/test/{moduleId}/result', [ModuleController::class, 'GetTestResult']);
Route::middleware('auth:sanctum')->get('/module/{type}/{moduleId}/progress', [ModuleController::class, 'GetModuleProgress']);
Route::middleware('auth:sanctum')->get('/task/check/get', [ModuleController::class, 'GetCheckTaskList']);
Route::middleware('auth:sanctum')->get('/task/{taskId}/get', [ModuleController::class, 'GetTask']);

Route::middleware('auth:sanctum')->post('/module/{type}/{moduleId}/status', [ModuleController::class, 'SetModuleProgress']);
Route::middleware('auth:sanctum')->post('/module/{type}/{moduleId}/task', [ModuleController::class, 'SetModuleTask']);
Route::middleware('auth:sanctum')->post('/module/test/{moduleId}/result', [ModuleController::class, 'SetTestResult']);
Route::middleware('auth:sanctum')->post('/task/{taskId}/check', [ModuleController::class, 'SetCheckTask']);


/************************/

/*** FileController ***/

Route::middleware('auth:sanctum')->post('/file/user/avatar', [FileController::class, 'StoreUserAvatar']);
Route::middleware('auth:sanctum')->post('/file/course/{courseId}/cover', [FileController::class, 'StoreCourseCover']);
Route::middleware('auth:sanctum')->post('/file/webinar/{webinarId}/cover', [FileController::class, 'StoreWebinarCover']);

/**********************/

/*** NotificationController ***/

Route::middleware('auth:sanctum')->get('/notification/get', [NotificationController::class, 'NotificationList']);

Route::middleware('auth:sanctum')->post('/notification/test', [NotificationController::class, 'NotificationTest']);

Route::middleware('auth:sanctum')->delete('/notification/{notificationId}/delete', [NotificationController::class, 'DeleteNotification']);
Route::middleware('auth:sanctum')->delete('/notification/delete/all', [NotificationController::class, 'DeleteAllNotification']);

/******************************/

/*** TicketController ***/

Route::middleware('auth:sanctum')->get('/ticket/get', [TicketController::class, 'GetUserTickets']);
Route::middleware('auth:sanctum')->get('/ticket/all', [TicketController::class, 'GetTicketsList']);
Route::middleware('auth:sanctum')->get('/ticket/{ticketId}/chat', [TicketController::class, 'GetTicketChat']);

Route::middleware('auth:sanctum')->post('/ticket/create', [TicketController::class, 'CreateTicket']);
Route::middleware('auth:sanctum')->post('/ticket/{ticketId}/chat/message', [TicketController::class, 'AddMessageToChat']);
Route::middleware('auth:sanctum')->post('/ticket/{ticketId}/status', [TicketController::class, 'TicketStatus']);

Route::middleware('auth:sanctum')->delete('/ticket/message/{messageId}', [TicketController::class, 'DeleteMessage']);

/************************/

/*** ChatController ***/

Route::middleware('auth:sanctum')->get('/chat/{type}/{streamId}/message/get', [ChatController::class, 'GetChatMessages']);

Route::middleware('auth:sanctum')->post('/chat/{type}/{streamId}/message/send', [ChatController::class, 'SendMessage']);

Route::middleware('auth:sanctum')->delete('/chat/{type}/{streamId}/message/{messageId}', [ChatController::class, 'DeleteMessage']);

/**********************/

/*** TinkoffController ***/

Route::middleware('auth:sanctum')->post('/buy/course/{courseId}', [TinkoffController::class, 'BuyCourse']);
Route::middleware('auth:sanctum')->post('/buy/course/{courseId}/order/free', [TinkoffController::class, 'TakeFreeCourse']);
Route::middleware('auth:sanctum')->post('/buy/course/{courseId}/order/create', [TinkoffController::class, 'CourseOrderCreate']);
Route::middleware('auth:sanctum')->post('/buy/course/{courseId}/order/jurictic', [TinkoffController::class, 'SendJuricticNotification']);
Route::middleware('auth:sanctum')->post('/buy/webinar/{webinarId}/order/jurictic', [TinkoffController::class, 'SendWebinarJuricticNotification']);

Route::middleware('auth:sanctum')->post('/buy/webinar/{webinarId}', [TinkoffController::class, 'BuyWebinar']);
Route::middleware('auth:sanctum')->post('/buy/webinar/{webinarId}/order/free', [TinkoffController::class, 'TakeFreeWebinar']);

Route::post('/buy/order/notification', [TinkoffController::class, 'PaymentNotification']);
Route::post('/buy/order/notification', [TinkoffController::class, 'PaymentNotification']);
Route::post('/buy/credit/notification', [TinkoffController::class, 'CreditNotification']);

/*************************/

/*** StatisticController ***/

Route::get('/statistic/today', [StatisticController::class, 'StatisticToday']);
Route::get('/statistic/days/{days}', [StatisticController::class, 'StatisticDays']);
Route::get('/statistic/year', [StatisticController::class, 'StatisticYear']);
Route::get('/statistic/courses', [StatisticController::class, 'StatisticCourses']);

Route::middleware('auth:sanctum')->post('/statistic/courses', [StatisticController::class, 'StatisticCourses']);
Route::middleware('auth:sanctum')->post('/statistic/numbers', [StatisticController::class, 'StatisticNumbers']);

Route::middleware('auth:sanctum')->post('/statistic/today', [StatisticController::class, 'StatisticToday']);
Route::middleware('auth:sanctum')->post('/statistic/days/{days}', [StatisticController::class, 'StatisticDays']);
Route::middleware('auth:sanctum')->post('/statistic/year', [StatisticController::class, 'StatisticYear']);

Route::post('/statistic/course/{courseId}/enter', [StatisticController::class, 'CourseEnter']);

/***************************/

/*** PromocodeController ***/

Route::middleware('auth:sanctum')->get('/promocode/all', [PromocodeController::class, 'GetAllPromocodes']);

Route::middleware('auth:sanctum')->post('/promocode/all', [PromocodeController::class, 'GetAllPromocodes']);

Route::middleware('auth:sanctum')->post('/promocode/add', [PromocodeController::class, 'CreatePromocode']);
Route::middleware('auth:sanctum')->post('/promocode/get', [PromocodeController::class, 'GetPromocode']);

Route::middleware('auth:sanctum')->delete('/promocode/delete/{id}', [PromocodeController::class, 'DeletePromocode']);

/***************************/

/*** ReferralController ***/

Route::middleware('auth:sanctum')->post('/referral/create', [ReferralController::class, 'CreateReferralLink']);

Route::middleware('auth:sanctum')->get('/referral/{type}/get', [ReferralController::class, 'GetReferralLinksByType']);
Route::middleware('auth:sanctum')->post('/referral/{type}/get', [ReferralController::class, 'GetReferralLinksByType']);

Route::middleware('auth:sanctum')->delete('/referral/delete/{ref}', [ReferralController::class, 'DeleteReferralLink']);

/**************************/

/*** SettingsController ***/

Route::middleware('auth:sanctum')->get('/settings/all', [SettingsController::class, 'GetSettings']);

Route::middleware('auth:sanctum')->post('/settings/update', [SettingsController::class, 'UpdateSettings']);

/**************************/