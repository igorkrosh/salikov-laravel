<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class UserController extends Controller
{
    public function Register(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:6', 'confirmed']
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

        $result = [
            'user' => [
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
                'avatar' => ''
            ],
            'progress' => [
                [
                    'image' => 'https://www.iserbia.rs/files//2018/06/tajna-oruzja-uverljivih-ljudi.jpg',
                    'title' => 'Охрана исключительных прав IT-компаний: программное обеспечение и товарные знаки',
                    'lectors' => 'Иванов А.А.',
                    'type' => 'Курс',
                    'progress' => 50
                ],
                [
                    'image' => 'https://www.iserbia.rs/files//2018/06/tajna-oruzja-uverljivih-ljudi.jpg',
                    'title' => 'Охрана исключительных прав IT-компаний: программное обеспечение и товарные знаки',
                    'lectors' => 'Иванов А.А.',
                    'type' => 'Курс',
                    'progress' => 99
                ]
            ],
            'webinar' => [
                [
                    'type' => 'course',
                    'title' => 'Обращение взыскания на интеллектуальную собственность: тренды',
                    'date' => '20.04.2022 16:00',
                    'lectors' => 'Саликов И.А.',
                    'webinarType' => 'Вебинар в рамках курса'
                ],
                [
                    'type' => 'free',
                    'title' => 'Обращение взыскания на интеллектуальную собственность: тренды',
                    'date' => '20.04.2022 16:00',
                    'lectors' => 'Саликов И.А.',
                    'webinarType' => 'Вебинар в рамках курса'
                ]
            ],
            'journal' => [
                [
                    'type' => '',
                    'title' => 'Задание по курсу: Обращение взыскания на интеллектуальную собственность',
                    'text' => 'Вычислите квадратный корень...',
                    'date' => '20.04.2022'
                ],
                [
                    'type' => '',
                    'title' => 'Задание по курсу: Обращение взыскания на интеллектуальную собственность',
                    'text' => 'Вычислите квадратный корень...',
                    'date' => '20.04.2022'
                ]
            ],
            'results' => [
                [
                    'type' => 'one',
                    'title' => 'Обращение взыскания на интеллектуальную собственность: тренды',
                    'lectors' => 'Иванов И.И',
                    'date' => '20.04.2022'
                ],
                [
                    'type' => 'two',
                    'title' => 'Обращение взыскания на интеллектуальную собственность: тренды',
                    'lectors' => 'Иванов И.И',
                    'date' => '20.04.2022'
                ],
                [
                    'type' => 'three',
                    'title' => 'Обращение взыскания на интеллектуальную собственность: тренды',
                    'lectors' => 'Иванов И.И',
                    'date' => '20.04.2022'
                ],
                [
                    'type' => 'four',
                    'title' => 'Обращение взыскания на интеллектуальную собственность: тренды',
                    'lectors' => 'Иванов И.И',
                    'date' => '20.04.2022'
                ],
                [
                    'type' => 'five',
                    'title' => 'Обращение взыскания на интеллектуальную собственность: тренды',
                    'lectors' => 'Иванов И.И',
                    'date' => '20.04.2022'
                ]
            ],
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
            ],
            'done' => [
                [
                    'image' => 'https://www.iserbia.rs/files//2018/06/tajna-oruzja-uverljivih-ljudi.jpg',
                    'title' => 'Охрана исключительных прав IT-компаний: программное обеспечение и товарные знаки',
                    'lectors' => 'Иванов А.А.',
                    'type' => 'Курс',
                    'progress' => 100
                ],
                [
                    'image' => 'https://www.iserbia.rs/files//2018/06/tajna-oruzja-uverljivih-ljudi.jpg',
                    'title' => 'Охрана исключительных прав IT-компаний: программное обеспечение и товарные знаки 2',
                    'lectors' => 'Иванов А.А.',
                    'type' => 'Курс',
                    'progress' => 100
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

    public function CreateUser(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:6', 'confirmed']
        ]);

        /*

        User::create([
            'name' => $request->name,
            'last_name' => !empty($request->lastName) ? $request->lastName : '',
            'birthday' => !empty($request->birthday) ? $request->birthday : '',
            'city' => !empty($request->city) ? $request->city : '',
            'phone' => !empty($request->phone) ? $request->phone : '',
            'role' => !empty($request->role) ? $request->role : 'user',
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        */

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
}
