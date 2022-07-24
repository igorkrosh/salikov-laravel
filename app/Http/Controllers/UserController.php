<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Course;
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

class UserController extends Controller
{
    public function Register(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        
    }

    public function Login(Request $request)
    {
        $request->validate([
            'email' => ['required'],
            'password' => ['required']
        ]);

        if (Auth::attempt($request->only('email', 'password')))
        {
            return response()->json(Auth::user(), 200);
        }

        throw ValidationException::withMessages([
            'email' => ['Почта или пароль не верны'],
        ]);
    }

    public function Logout()
    {
        Auth::logout();
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
                $webinars[] = [
                    'id' => $stream->id,
                    'course_id' => $stream->id,
                    'type' => 'stream',
                    'title' => $stream->title,
                    'date' => $stream->date_start,
                    'lectors' => $stream->authors,
                ];
            }

            foreach ($this->GetModules($item->course_id)['job'] as $job)
            {
                $journal[] = [
                    'type' => '',
                    'course' => $course->name,
                    'course_id' => $course->id,
                    'module' => $job->title, 
                    'date' => $job->deadline,
                    'task_id' => $job->id,
                ];
            }
        }

        foreach (Webinar::get() as $webinar)
        {
            $webinars[] = [
                'id' => $webinar->id,
                'type' => 'webinar',
                'title' => $webinar->name,
                'date' => $webinar->date_start,
                'lectors' => $webinar->authors,
                'image' => url('/').'/'.$webinar->image_path,
            ];
        }

        usort($webinars, function($a, $b){
            return $a['date'] <=> $b['date'];
        });

        $results = [];

        foreach (Task::where('user_id', $user->id)->get() as $task)
        {
            $module = $this->GetModuleByType($task->module_id, $task->type);
            $results[] = [
                'type' => $task->score,
                'title' => $module->title,
                'lectors' => $module->authors,
                'date' => $module->date_check
            ];
        }

        $result = [
            'user' => [
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

    public function EditUser(Request $request)
    {
        $user = Auth::user();
        
        $user->name = !empty($request->name) ? $request->name : $user->name;
        $user->last_name = !empty($request->lastName) ? $request->lastName : $user->lastName;
        $user->birthday = !empty($request->birthday) ? $request->birthday : $user->birthday;
        $user->city = !empty($request->city) ? $request->city : $user->city;
        $user->phone = !empty($request->phone) ? $request->phone : $user->phone;
        $user->email = !empty($request->email) ? $request->email : $user->email;

        $user->save();

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
            return $users;
        }

        $users = DB::table('users');

        $useAdmin = $request->filter['admin'];
        $useEducator = $request->filter['educator'];
        $useUser = $request->filter['user'];
        $useName = !empty($request->filter['name']);

        $users = $users->where(function ($query) use ($useAdmin, $useEducator, $useUser) {
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
        });

        if ($useName)
        {
            $name = $request->filter['name'];
            $users->where(function ($query) use ($name) {
                $query->where('name', 'like', "%$name%")->orWhere('last_name', 'like', "%$name%");
            });
        }
        return $users->get();
    }

    public function GetCalendar(Request $request)
    {
        $userId = Auth::user()->id;
        $courses = Course::where('creator', $userId)->get();
        $webinars = Webinar::where('creator', $userId)->get();
        $access = BlockAccess::where('user_id', $userId)->get()->unique('course_id');

        foreach ($access as $course)
        {
            $course = Course::where('id', $course->course_id)->first();

            $courses[] = $course;
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
            $modules = $this->GetModules($course->id);

            $count = count($modules['stream']) + count($modules['video']) + count($modules['job']) + count($modules['test']);
            $done = 0;

            $moduleTypes = ['stream', 'video', 'job', 'test'];

            foreach ($moduleTypes as $key)
            {
                foreach ($modules[$key] as $module)
                {
                    $progress = Progress::where([['module_id', $module->id], ['type', $key]])->first();

                    if (empty($progress))
                    {
                        continue;
                    }

                    if ($progress->status == 'done')
                    {
                        $done++;
                    }
                }
            }

            $progress = round(($done / $count) * 100);

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
