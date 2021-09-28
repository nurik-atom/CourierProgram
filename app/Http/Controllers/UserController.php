<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function authTel(Request $request)
    {
        $phone = $request->input('phone');
        $data['success'] = false;
        do {
            if (strlen($phone) != 11) {
                $data['message'] = 'Номер телефона неправильно введен!';
                break;
            }

            $user_sms = DB::table('users_sms')->where('phone', $phone)->orderByDesc('id')->first();

            if (isset($user_sms) && (time() < (strtotime($user_sms->created_at) + 3600))) {
                $data['message'] = 'Вам уже отправлено смс';
                break;
            }

            $code = rand(1234, 9998);

            DB::beginTransaction();

            $users_sms_id = DB::table('users_sms')->insertGetId([
                'phone' => $phone,
                'code' => $code,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            if (!$users_sms_id) {
                DB::rollBack();
                $data['message'] = 'Попробуйте позже';
                break;
            }
            DB::commit();
            $mess = "Ваш пароль: $code \n С уважением, ALLFOOD Courier";
            $sender = env('SMS_SENDER');
            $login = env('SMS_LOGIN');
            $psw = env('SMS_PASSWORD');

            $url = "https://smsc.ru/sys/send.php?sender=$sender&login=$login&psw=$psw&phones=$phone&mes=$mess";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) {
                $data['message'] = 'Попробуйте позже';
                break;
            }

            $data['success'] = true;
        } while (false);
        return response()->json($data);
    }

    public function inputSmsCode(Request $request)
    {
        $phone = $request->input('phone');
        $sms = $request->input('code');
        $data['success'] = false;

        do {
            if (strlen($sms) != 4) {
                $data['message'] = 'Введонный код неверный';
                break;
            }

            $user_sms = DB::table('users_sms')->where('phone', $phone)->orderByDesc('id')->first();

            if (!empty($user_sms) && $user_sms->code != $sms) {
                $data['message'] = 'Код неверный';
                break;
            }

            $user = DB::table('users')->where('phone', $phone)->orderByDesc('id')->first();
            $password = "AllFood-" . rand(123456, 999999) . time();

            if (!$user) {
                $users_sms_id = DB::table('users')->insertGetId([
                    'phone' => $phone,
                    'status' => 1,
                    'password' => bcrypt($password),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
                $data['id'] = $users_sms_id;
                $data['status'] = 1;
                $data['success'] = true;
                break;
            }
            $data['status'] = $user->status;
            $data['id'] = $user->id;
            $data['success'] = true;
        } while (false);

        return response()->json($data);
    }

    public function register(Request $request)
    {
        $name = $request->input('name');
        $surname = $request->input('surname');
        $phone = $request->input('phone');
        $type = $request->input('type');
        $id_cafe = $request->input('id_cafe');
        $password = $request->input('password');
        $result['success'] = false;

        do {
            if (!$name) {
                $result['message'] = 'Не передан имя';
                break;
            }
            if (!$surname) {
                $result['message'] = 'Не передан фамилия';
                break;
            }
            if (!$phone) {
                $result['message'] = 'Не передан Телефон';
                break;
            }
            if (!$type) {
                $result['message'] = 'Не передан тип';
                break;
            }
            if (!$password) {
                $result['message'] = 'Не передан пароль';
                break;
            }

            $user = DB::table('users')->select('id')->where('phone', $phone)->first();
            if (!is_null($user)) {
                $result['message'] = 'Этот пользователь уже зарегистрован';
                break;
            }
            DB::beginTransaction();
            $userID = DB::table('users')->insertGetId([
                'name' => $name,
                'surname' => $surname,
                'phone' => $phone,
                'status' => 1,
                'type' => $type,
                'id_cafe' => $id_cafe,
                'password' => bcrypt($password),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if (!$userID) {
                DB::rollBack();
                $result['message'] = 'Попробуйте позже';
                break;
            }

            DB::commit();
            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }

    public function signIn(Request $request)
    {
        $phone = $request->input('phone');
        $password = $request->input('password');
        $result['success'] = false;
        do {
            if (!$phone) {
                $result['message'] = 'Не передан телефон';
                break;
            }
            if (!$password) {
                $result['message'] = 'Не передан пароль';
                break;
            }
            $token = Str::random(60);
            $token = sha1(time() . $token);
            $userID = DB::table('users')->select('id', 'password')->where('phone', $phone)->first();
            if (is_null($userID)) {
                $result['message'] = 'Не найден пользователь';
                break;
            }
            if (!Hash::check($password, $userID->password)) {
                $result['message'] = 'Пароль или логин не совпадает';
                break;
            }
            DB::table('users')->where('id', $userID->id)->update([
                'token' => $token,
            ]);
            $result['success'] = true;
            $result['token'] = $token;
        } while (false);

        return response()->json($result);
    }

    public function profile(Request $request)
    {
        $token = $request->input('token');
        $result['success'] = false;
        do {
            if (!$token) {
                $result['message'] = 'Не передан токен';
                break;
            }
            $user = $this->checkUser($token);
            if (!$this->checkUser($token)) {
                $result['message'] = 'Не найден пользователь';
                break;
            }
            $result['success'] = true;
            $result['id'] = $user->id;
            $result['name'] = $user->name;
            $result['surname'] = $user->surname;
            $result['phone'] = $user->phone;
            $result['type'] = $user->type;
            ksort($result);
        } while (false);
        return response()->json($result);
    }

    public function checkUser($token)
    {
        $user = DB::table('users')
            ->where('token', $token)
            ->select('id', 'name', 'surname', 'phone', 'type')
            ->first();
        if (!$user) {
            return false;
        } else {
            return $user;
        }
    }

    public function test()
    {
        echo "something";
    }
}
