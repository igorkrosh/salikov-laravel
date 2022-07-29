<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

use DateTime;
use DateTimeZone;

use App\Models\User;
use App\Models\JuricticUser;
use App\Models\Session;

class AuthController extends Controller
{
    public function Token(Request $request)
    {
        $request->session()->token();
    }

    public function Login(Request $request)
    {
        $request->validate([
            'email' => ['required'],
            'password' => ['required'],
        ]);

        $sessionId = $request->session()->getId();
        $session = Session::where('id', $sessionId)->first();

        if (Auth::attempt($request->only('email', 'password')))
        {
            if (!empty($session->validation_code))
            {
                $session->validation_code = '';
                $session->save();
            }

            return [
                'token' => Auth::user()->createToken('authentication')->plainTextToken
            ];
        }

        throw ValidationException::withMessages([
            'email' => ['Почта или пароль не верны'],
        ]);
    }

    public function LoginByEmail(Request $request)
    {
        $request->validate([
            'email' => ['required'],
            'code' => ['required'],
        ]);

        $sessionId = $request->session()->getId();
        $session = Session::where('id', $sessionId)->first();

        if ($session->validation_code != $request->code)
        {
            throw ValidationException::withMessages([
                'code' => ['Не верный код подтверждения'],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (empty($user))
        {
            throw ValidationException::withMessages([
                'email' => ['Пользователь с таким email не найден'],
            ]);
        }

        $session->validation_code = '';
        $session->save();

        Auth::loginUsingId($user->id, true);

        return ['token' => Auth::user()->createToken('authentication')->plainTextToken];
    }

    public function LoginBySms(Request $request)
    {
        $request->validate([
            'phone' => ['required'],
            'code' => ['required'],
        ]);

        $sessionId = $request->session()->getId();
        $session = Session::where('id', $sessionId)->first();

        if ($session->validation_code != $request->code)
        {
            throw ValidationException::withMessages([
                'code' => ['Не верный код подтверждения'],
            ]);
        }

        $user = User::where('phone', preg_replace('/[^0-9]/', '', $request->phone),)->first();

        if (empty($user))
        {
            throw ValidationException::withMessages([
                'phone' => ['Пользователь с таким телефоном не найден'],
            ]);
        }

        $session->validation_code = '';
        $session->save();

        Auth::loginUsingId($user->id, true);

        return ['token' => Auth::user()->createToken('authentication')->plainTextToken];
    }

    public function Logout()
    {
        Auth::logout();
    }

    public function Register(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        if ($request->jurictic)
        {
            $request->validate([
                'company_name' => ['required'],
                'inn' => ['required'],
            ]);
        }

        $user = new User();

        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->jurictic = empty($request->jurictic) ? false : $request->jurictic;

        $user->save();

        if ($request->jurictic)
        {
            $juricticUser = new JuricticUser();

            $juricticUser->user_id = $user->id;
            $juricticUser->company_name = $request->company_name;
            $juricticUser->inn = $request->inn;
            $juricticUser->ogrn = empty($request->ogrn) ? '' : $request->ogrn;
            $juricticUser->account = empty($request->account) ? '' : $request->account;
            $juricticUser->address = empty($request->address) ? '' : $request->address;

            $juricticUser->save();
        }

        Auth::attempt($request->only('email', 'password'));
    }

    public function RegisterJuristic(Request $request)
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

        Auth::attempt($request->only('email', 'password'));
    }

    public function SendEmailCode(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (empty($user))
        {
            throw ValidationException::withMessages([
                'email' => ['Пользователь с таким email не найден'],
            ]);
        }

        $code = rand(1000, 9999);
        $sessionId = $request->session()->getId();
        $session = Session::where('id', $sessionId)->first();

        $session->validation_code = $code;
        $session->save();

        $email = [
            'from_email' => 'info@kathedra.ru',
            'from_name' => 'Образовательная платформа',
            'to' => $request->email,
            'subject' => 'Уведовление системы безопастности',
            'text' => 'Код подтверждения',
            'html' => "<h1>$code</h1>",
            'payment' => "subscriber_priority",
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('notisend.key')
        ])->withOptions([
            'verify' => false,
        ])->post('https://api.notisend.ru/v1/email/messages', $email);



        return $response;
    }

    public function SendSmsCode(Request $request)
    {
        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        $user = User::where('phone', $phone)->first();

        if (empty($user))
        {
            throw ValidationException::withMessages([
                'phone' => ['Пользователь с таким номером не найден'],
            ]);
        }

        $code = rand(1000, 9999);

        $sessionId = $request->session()->getId();
        $session = Session::where('id', $sessionId)->first();

        $session->validation_code = $code;
        $session->save();

        $sms = "Код подтверждения: $code";

        $data = [
            'project' => config('notisend.sms_project'),
            'sender' => config('notisend.sms_sender'),
            'recipients' => $phone,
            'message' => $sms,
            'apikey' => config('notisend.sms_key')
        ];

        $response = Http::withOptions([
            'verify' => false,
        ])->post('https://sms.notisend.ru/api/message/send', $data);

        return $response;
    }

    public function VerificateMail(Request $request)
    {
        $sessionId = $request->session()->getId();
        $session = Session::where('id', $sessionId)->first();

        $user = User::where('id', $session->user_id)->first();

        if ($session->validation_code == $request->code)
        {
            $dt = new DateTime();
            $dt->setTimezone(new DateTimeZone('Europe/Moscow'));
            $dt->setTimestamp(time());

            $user->email_verified_at = $dt->format('Y-m-d H:i:s');

            $user->save();

            $session->validation_code = '';
            $session->save();
        }
    }
}
