<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

use App\Models\User;
use App\Models\JuricticUser;
use App\Models\Course;
use App\Models\CourseAccess;
use App\Models\CourseBlock;
use App\Models\Webinar;
use App\Models\BlockAccess;
use App\Models\ModuleStream;
use App\Models\ModuleVideo;
use App\Models\ModuleJob;
use App\Models\ModuleTest;
use App\Models\Task;
use App\Models\Notification;
use App\Models\Progress;
use App\Models\WebinarAccess;

class UserController extends Controller
{
    public function GetUser(Request $request)
    {
        return [
            'user' => Auth::user()
        ];
    }
    public function GetUserProfile(Request $request)
    {
        $user = Auth::user();
        $access = BlockAccess::where('user_id', $user->id)->get()->unique('course_id');

        $webinars = [];
        $journal = [];

        foreach ($access as $item)
        {
            $course = Course::where('id', $item->course_id)->first();

            foreach ($this->GetModules($item->course_id)['stream'] as $stream)
            {
                if (Carbon::parse($stream->date_start)->addDays(1)->isPast())
                {
                    continue;
                }

                $webinars[] = [
                    'id' => $stream->id,
                    'course_id' => $stream->id,
                    'type' => 'stream',
                    'title' => $stream->title,
                    'date' => Carbon::parse($stream->date_start)->translatedFormat('d.m.Y H:i'),
                    'lectors' => $stream->authors,
                    'status' => $this->GetKinescopeVideoStatus($stream->link)
                ];
            }

            foreach ($this->GetModules($item->course_id)['job'] as $job)
            {
                if (empty(Task::where('module_id', $job->id)->first()))
                {
                    $journal[] = [
                        'type' => '',
                        'course' => $course->name,
                        'course_id' => $course->id,
                        'module' => $job->title, 
                        'date' => Carbon::parse($job->deadline)->translatedFormat('d.m.Y H:i'),
                        'task_id' => $job->id,
                    ];
                }
                
            }
        }

        foreach (WebinarAccess::where('user_id', $user->id)->get() as $webinarAccess)
        {
            $webinar = Webinar::where('id', $webinarAccess->webinar_id)->first();

            if (empty($webinar))
            {
                continue;
            }

            if (Carbon::parse($webinar->date_start)->addDays(1)->isPast())
            {
                continue;
            }

            $webinars[] = [
                'id' => $webinar->id,
                'type' => 'webinar',
                'title' => $webinar->name,
                'date' => Carbon::parse($webinar->date_start)->translatedFormat('d.m.Y H:i'),
                'lectors' => $webinar->authors,
                'image' => url('/').'/'.$webinar->image_path,
                'status' => $this->GetKinescopeVideoStatus($webinar->link)
            ];
        }

        usort($webinars, function($a, $b){
            return $a['date'] <=> $b['date'];
        });

        $results = [];

        foreach (Task::where('user_id', $user->id)->get() as $task)
        {
            $module = $this->GetModuleByType($task->module_id, $task->type);
            if (empty($module))
            {
                continue;
            }
            if (!empty($task->score))
            {
                $results[] = [
                    'type' => $task->score,
                    'title' => $module->title,
                    'lectors' => $module->authors,
                    'date' => $module->date_check,
                    'comment' => $task->comment,
                    'job' => $module->text,
                    'answer' => $task->task,
                    'id' => $module->id,
                ];
            }
            
        }

        if ($user->jurictic)
        {
            $juricticUser = JuricticUser::where('user_id', $user->id)->first();
        }

        $result = [
            'user' => [
                'id' => $user->id,
                'role' => $user->role,
                'points' => $user->active_points,
                'allPoints' => $user->points,
                'invites' => $user->invites,
                'name' => $user->name,
                'lastName' => $user->last_name,
                'birthday' => $user->birthday,
                'city' => $user->city,
                'phone' => $user->phone,
                'email' => $user->email,
                'avatar' => empty($user->img_path) ? '' : url('/').'/'.$user->img_path,
                'jurictic' => $user->jurictic,
                'jurictic_data' => $user->jurictic ? [
                    'company_name' => $juricticUser->company_name,
                    'inn' => $juricticUser->inn,
                    'ogrn' => $juricticUser->ogrn,
                    'account' => $juricticUser->account,
                    'address' => $juricticUser->address,
                ] : []
            ],
            'webinar' => $webinars,
            'results' => $results,
            'journal' => $journal,
            'achievements' => [
                [
                    'image' => 'https://salikov-law-practice-layout.vercel.app/assets/images/icons/achievements/2.png',
                    'title' => 'Тяга к знаниям',
                    'text' => 'Начать первый курс обучения'
                ],
                [
                    'image' => 'https://salikov-law-practice-layout.vercel.app/assets/images/icons/achievements/3.png',
                    'title' => 'Человек паук',
                    'text' => 'Прохождение нескольких курсов одновременно'
                ]
            ]
        ];

        return $result;
    }

    public function ReferralData(Request $request)
    {
        return [
            'invite_link' => config('app.ref')."/invite/".Auth::user()->id,
            'count' => count(User::where('invite_user', Auth::user()->id)->get()),
            'all_points' => Auth::user()->points,
            'points' => Auth::user()->active_points,
        ];
    }

    public function EditUser(Request $request)
    {
        $user = Auth::user();
        
        $user->name = !empty($request->name) ? $request->name : $user->name;
        $user->last_name = !empty($request->lastName) ? $request->lastName : $user->last_name;
        $user->birthday = !empty($request->birthday) ? $request->birthday : $user->birthday;
        $user->city = !empty($request->city) ? $request->city : $user->city;
        $user->phone = !empty($request->phone) ? preg_replace('/[^0-9]/', '', $request->phone) : $user->phone;
        $user->email = !empty($request->email) ? $request->email : $user->email;
        $user->jurictic = $request->jurictic;

        $user->save();

        if ($user->jurictic)
        {
            JuricticUser::where('user_id', $user->id)->delete();
            
            $juricticUser = new JuricticUser();

            $juricticUser->user_id = $user->id;
            $juricticUser->company_name = $request->jurictic_data['company_name'];
            $juricticUser->inn = $request->jurictic_data['inn'];
            $juricticUser->ogrn = empty($request->jurictic_data['ogrn']) ? '' : $request->jurictic_data['ogrn'];
            $juricticUser->account = empty($request->jurictic_data['account']) ? '' : $request->jurictic_data['account'];
            $juricticUser->address = empty($request->jurictic_data['address']) ? '' : $request->jurictic_data['address'];

            $juricticUser->save();
        }

        return $user;
    }

    public function EditSpecificUser(Request $request, $userId)
    {
        $user = User::where('id', $userId)->first();

        if (!empty($request->password) && $request->password != $request->password_confirmation)
        {
            return response()->json([
                'message' => 'Пароли не совпадает'
            ] , 422);
        }

        $checkEmail = User::where([['id', '!=', $user->id], ['email', $request->email]])->first();

        if (!empty($checkEmail))
        {
            return response()->json([
                'message' => 'Пользователь с таким email уже существует'
            ] , 422);
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->last_name = !empty($request->last_name) ? $request->last_name : '';
        $user->birthday = !empty($request->birthday) ? $request->birthday : '';
        $user->city = !empty($request->city) ? $request->city : '';
        $user->phone = !empty($request->phone) ? $request->phone : '';
        $user->role = !empty($request->role) ? $request->role : 'user';
        $user->password = Hash::make($request->password);

        $user->save();

        return response()->json($user , 200);
    }

    public function CreateUser(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:6', 'confirmed']
        ]);

        $user = new User();

        $user->name = $request->name;
        $user->email = $request->email;
        $user->last_name = !empty($request->lastName) ? $request->lastName : '';
        $user->birthday = !empty($request->birthday) ? $request->birthday : '';
        $user->city = !empty($request->city) ? $request->city : '';
        $user->phone = !empty($request->phone) ? $request->phone : '';
        $user->role = !empty($request->role) ? $request->role : 'user';
        $user->password = Hash::make($request->password);

        $user->save();
    }

    public function GetAllUsers(Request $request)
    {
        $users = User::all();
        
        if (empty($request->filter))
        {
            return [
                'users' => $users,
                'count' => count($users)
            ];
        }

        $users = DB::table('users');

        $useAdmin = $request->filter['admin'];
        $useEducator = $request->filter['educator'];
        $useUser = $request->filter['user'];
        $useAuthor = $request->filter['author'];
        $useName = !empty($request->filter['name']);
        $useCategory = !empty($request->filter['category']);
        $useCourse = !empty($request->filter['course']);
        $useSort = !empty($request->filter['sort']);

        $users = $users->where(function ($query) use ($useAdmin, $useEducator, $useUser, $useAuthor) {
            if ($useAdmin)
            {
                $query->orWhere('role', 'admin');
            }

            if ($useEducator)
            {
                $query->orWhere('role', 'educator');
            }

            if ($useUser)
            {
                $query->orWhere('role', 'user');
            }

            if ($useAuthor)
            {
                $query->orWhere('role', 'author');
            }
        });

        if ($useName)
        {
            $name = $request->filter['name'];
            $users->where(function ($query) use ($name) {
                $query->where('name', 'like', "%$name%")->orWhere('last_name', 'like', "%$name%");
            });
        }

        if ($useCategory)
        {
            $category = $request->filter['category'];

            $courses = Course::where('category', $category)->get();
            $ids = [];
            
            foreach ($courses as $course)
            {
                $ids[] = $course->id;
            }

            $blockAccess = BlockAccess::whereIn('course_id', $ids)->get()->unique('user_id');
            $ids = [];
            
            foreach ($blockAccess as $access)
            {
                $ids[] = $access->user_id;
            }

            $users->where(function ($query) use ($ids) {
                $query->whereIn('id', $ids);
            });
        }

        if ($useCourse)
        {
            $courseId = $request->filter['course'];

            $course = Course::where('id', $courseId)->first();

            $blockAccess = BlockAccess::where('course_id', $course->id)->get()->unique('user_id');
            $ids = [];
            
            foreach ($blockAccess as $access)
            {
                $ids[] = $access->user_id;
            }

            $users->where(function ($query) use ($ids) {
                $query->whereIn('id', $ids);
            });
        }

        $users = $users->get();
        $table = [];

        foreach ($users as $user)
        {
            $table[] = [
                'id' => $user->id,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'birthday' => $user->birthday,
                'city' => $user->city,
                'phone' => $user->phone,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => Carbon::parse($user->created_at)->translatedFormat('d.m.Y H:i'),
                'avatar' => empty($user->img_path) ? '' : url('/').'/'.$user->img_path,

            ];
        }

        if ($useSort)
        {
            if ($request->filter['sort'] == 'asc')
            {
                usort($table, function($a, $b){
                    return Carbon::parse($a['created_at'])->getTimestamp() >= Carbon::parse($b['created_at'])->getTimestamp();
                });
            }
            else 
            {
                usort($table, function($a, $b){
                    return Carbon::parse($a['created_at'])->getTimestamp() <= Carbon::parse($b['created_at'])->getTimestamp();
                });
            }
        }

        return [
            'users' => $table,
            'count' => count($users)
        ];
    }

    public function GetCalendar(Request $request)
    {
        $userId = Auth::user()->id;
        $courses = Course::where('creator', $userId)->get();
        $webinars = Webinar::where('creator', $userId)->get();
        $access = BlockAccess::where('user_id', $userId)->get()->unique('course_id');

        foreach ($access as $course)
        {
            $course = Course::where('id', $course->course_id)->get();

            $courses = $courses->concat($course);
        }

        $dates = [];

        foreach ($webinars as $webinar)
        {
            $dates[] = [
                'date' => $webinar->date_start,
                'text' => "Вебинар: \"$webinar->name\"",
                'color' => 'yellow'
            ];
        }

        foreach ($courses as $course)
        {
            $dates[] = [
                'date' => $course->date_start,
                'text' => "Начало курса: $course->name",
                'color' => 'blue'
            ];

            $blocks = CourseBlock::where('course_id', $course->id)->get();

            foreach ($blocks as $block)
            {
                $dates[] = [
                    'date' => $block->date_start,
                    'text' => "Начало блока: \"$block->title\"",
                    'color' => "purple"
                ];
            }

            $modules = $this->GetModules($course->id);

            foreach($modules['stream'] as $module)
            {
                $dates[] = [
                    'date' => $module->date_start,
                    'text' => "Стрим: \"$module->title\"",
                    'color' => 'orange'
                ];
            }

            foreach($modules['job'] as $module)
            {
                $dates[] = [
                    'date' => $module->deadline,
                    'text' => "Срок сдачи задания \"$module->title\"",
                    'color' => 'red'
                ];

                $dates[] = [
                    'date' => $module->check_date,
                    'text' => "Проверка задания \"$module->title\"",
                    'color' => 'green'
                ];
            }

            foreach($modules['test'] as $module)
            {
                $dates[] = [
                    'date' => $module->deadline,
                    'text' => "Срок сдачи задания \"$module->title\"",
                    'color' => 'red'
                ];

                $dates[] = [
                    'date' => $module->check_date,
                    'text' => "Проверка задания \"$module->title\"",
                    'color' => 'green'
                ];
            }

            return $dates;
        }
    }

    public function GetUserNotifications(Request $request)
    {
        return Notification::where('user_id', Auth::user()->id)->get();
    }

    public function GetUserProgress(Request $request)
    {
        $user = Auth::user();

        $access = BlockAccess::where('user_id', $user->id)->get()->unique('course_id');

        $response = [
            'done' => [],
            'progress' => [],
            'all' => [],
        ];

        foreach ($access as $item)
        {
            $course = Course::where('id', $item->course_id)->first();

            if (empty($course))
            {
                continue;
            }

            $progress = app(CourseController::class)->CalcCourseProgress($item->course_id, $user->id);

            $data = [
                'id' => $item->course_id,
                'date' => $course->created_at,
                'duration' => $course->duration,
                'image' => url('/').'/'.$course->image_path,
                'lectors' => $course->authors,
                'title' => $course->name,
                'type' => 'Курс',
                'progress' => $progress,
            ];

            if ($progress == 100)
            {
                $response['done'][] = $data;
            }
            else 
            {
                $response['progress'][] = $data;
            }
        }

        return $response;
    }

    public function GetProfileByUserId(Request $request, $userId)
    {
        $user = User::where('id', $userId)->first();

        return [
            'id' => $user->id,
            'role' => $user->role,
            'points' => $user->points,
            'allPoints' => $user->active_points,
            'invites' => $user->invites,
            'name' => $user->name,
            'lastName' => $user->last_name,
            'birthday' => $user->birthday,
            'city' => $user->city,
            'phone' => $user->phone,
            'email' => $user->email,
            'avatar' => empty($user->img_path) ? '' : url('/').'/'.$user->img_path
        ];
    }
}
