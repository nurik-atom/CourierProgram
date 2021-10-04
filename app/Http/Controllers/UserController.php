<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function signStepOne(Request $request)
    {
        $phone = $request->input('phone');
        $signatureCode = $request->input('signatureCode');
        $data['success'] = false;
        do {

            //Тестовые номера пароль по умолчанию 4321
            if ($phone == '77089222820') {
                $data['success'] = true;
                break;
            }

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

            $mess = "Ваш пароль: $code \n С уважением, ALLFOOD Courier\n".$signatureCode." ";
            $array = array(
                'login'    => 'allfood',
                'psw' => 'ceb183606831afdd536973f8523e51d3',
                'phones' => $phone,
                'mes' => $mess
            );
            $ch = curl_init('https://smsc.ru/sys/send.php');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $array);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) {
                $data['message'] = 'Ошибка отправки смс';
                break;
            }else{
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
            }

            $data['success'] = true;
        } while (false);
        return response()->json($data);
    }

    public function signStepTwo(Request $request)
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

            if (!$user_sms || ($user_sms->code != $sms)) {
                //Если тестовые аккаунты продолжаем
                if ($phone == '77089222820' && $sms == '4321')
                    $data['success'] = true;
                else {
                    $data['message'] = 'Код неверный';
                    break;
                }
            }

            $user = DB::table('users')->where('phone', $phone)->orderByDesc('id')->first();
            $password = bcrypt("AllFood-" . rand(123456, 999999) . time());

            if (!$user) {
                $users_sms_id = DB::table('users')->insertGetId([
                    'phone' => $phone,
                    'status' => 1,
                    'password' => $password,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
                $data['id'] = $users_sms_id;
                $data['status'] = 1;
                $data['success'] = true;
                $data['password'] = $password;
                break;
            }
            $user_pass_update = DB::table("users")->where("phone", $phone)->update(['password' => $password]);

            $data['status'] = $user->status;
            $data['id'] = $user->id;
            $data['success'] = true;
            $data['password'] = $password;
        } while (false);

        return response()->json($data);
    }

    public function editDataUser(Request $request)
    {
        $password = $request->input('password');
        $name = $request->input('name');
        $surname = $request->input('surname');
        $id_city = $request->input('id_city');
        $photo = $request->input('photo');
        $type_transport = $request->input('type_transport');

        $data['success'] = false;
        do {
            if (!$password) {
                $data['message'] = 'Пользователь не найден';
                break;
            }
            $user = DB::table('users')->where('password', $password)->orderByDesc('id')->first();

            if (!$user) {
                $data['message'] = 'Пользователь не найден';
                break;
            }

            $user_data_update = DB::table("users")->where("password", $password)->update(['name' => $name, 'surname' => $surname, 'id_city' => $id_city, 'photo' => $photo, 'type_transport' => $type_transport]);
            $data['success'] = true;

        } while (false);
        return response()->json($data);

    }

    public function editTokenUser(Request $request)
    {
        $password = $request->input('password');
        $token = $request->input('token');
        $data['success'] = false;
        do {


        } while (false);
        return response()->json($data);
    }

    public function profileUser(Request $request)
    {
        $phone = $request->input('phone');
        $password = $request->input('password');

    }


    public function test()
    {
        echo "something";
    }
}
