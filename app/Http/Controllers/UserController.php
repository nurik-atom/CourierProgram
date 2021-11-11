<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

//use Illuminate\Support\Facades\Hash;
//use Illuminate\Support\Str;

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

            $mess = "Ваш пароль: $code \n С уважением, ALLFOOD Courier\n" . $signatureCode . " ";
            $array = array(
                'login' => 'allfood',
                'psw' => 'ceb183606831afdd536973f8523e51d3',
                'phones' => $phone,
                'mes' => $mess
            );
            $ch = curl_init('https://smsc.ru/sys/send.php');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $array);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) {
                $data['message'] = 'Ошибка отправки смс';
                break;
            } else {
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
            $password = sha1("AllFood-" . rand(123456, 999999) . time(), true);

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
        $phone = $request->input('phone');
        $password = $request->input('password');
        $name = $request->input('name');
        $surname = $request->input('surname');
        $birthday = $request->input('birthday');
        $id_city = $request->input('id_city');
        $type_transport = $request->input('type_transport');

        $data['success'] = false;
        do {

            if (!$password) {
                $data['message'] = 'Пользователь не найден';
                break;
            }
            $user = DB::table('users')->where([['password', '=', $password], ['phone', '=', $phone]])->orderByDesc('id')->first();

            if (!$user) {
                $data['message'] = 'Пользователь не найден';
                break;
            }

            if ($request->hasFile('photo')) {
                $image = $request->photo->store('images', "public");
            } else {
                $data['message'] = 'Ошибка загрузки Фото';
                break;
            }

            $user_data_update = DB::table("users")
                ->where([["password", '=', $password], ['phone', '=', $phone]])
                ->update(['name' => $name, 'surname' => $surname, 'id_city' => $id_city, 'birthday' => $birthday, 'type' => $type_transport, 'status' => '2', 'photo' => $image]);

            if ($user_data_update) {
                $data['success'] = true;
                $data['image'] = 'https://courier.qala.kz/storage/app/' . $image;
            }

        } while (false);
        return response()->json($data);

    }

    function getStatusUser(Request $request)
    {
        $phone = $request->input('phone');
        $user = DB::table('users')->where('phone', $phone)->orderByDesc('id')->first();
        if ($user) {
            $data['success'] = true;
            $data['status'] = $user->status;
        } else {
            $data['success'] = false;
            $data['message'] = 'Номер телефона не найден';
        }
        return response()->json($data);
    }

    function deleteUser(Request $request)
    {
        $phone = $request->input('phone');
        $user = DB::table('users')->where('phone', $phone)->delete();
        if ($user) {
            $data['success'] = true;
        } else {
            $data['success'] = false;
            $data['message'] = 'Номер телефона не найден';
        }
        return response()->json($data);
    }

    function setStatusUser(Request $request)
    {
        $phone = $request->input('phone');
        $new_status = $request->input("new_status");
        $user = DB::table('users')->where('phone', $phone)->update(["status" => $new_status]);
        if ($user) {
            $data['success'] = true;
        } else {
            $data['success'] = false;
            $data['message'] = 'Уже изменен или номер не найден';
        }
        return response()->json($data);
    }


    public function setUserGeoPosition(Request $request)
    {
        $password = $request->input("password");
        $lan = $request->input("lan");
        $lon = $request->input("lon");
        $type = $request->input("type");

        do {
            $user = DB::table("users")->where("password", $password)->pluck("id")->first();
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $select_geo = DB::table("users_geo")
                ->where("created_at", ">", date("Y-m-d H:i:s", time() - 600))
                ->pluck("id")->get();

            if ($select_geo) {
                $update_geo = DB::table("users_geo")
                    ->where("id", $select_geo)
                    ->update(["id_user" => $user, "lan" => $lan, "lon" => $lon, "type" => $type, "updated_at" => Carbon::now()]);
            } else {
                $add_geo = DB::table("users_geo")
                    ->insert(["id_user" => $user, "lan" => $lan, "lon" => $lon, "type" => $type,
                        "created_at" => Carbon::now(), "updated_at" => Carbon::now()]);
            }

            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }

    public function insertStateUser(Request $request)
    {
        $password = $request->input("password");
        $state = $request->input("state");
        $result['success'] = false;

        do {
            $user = DB::table("users")->where("password", $password)->pluck("id")->first();
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $add_state = DB::table("users_state")->insert(["id_user" => $user, "state" => $state,
                "created_at" => Carbon::now(), "updated_at" => Carbon::now()]);
            $update_state = DB::table("users")->where("id", $user)->update(["state" => $state]);
            if (!$add_state)
                $result['message'] = 'Ошибка при добавление';
            else
                $result['success'] = true;

        } while (false);
        return response()->json($result);
    }

    public static function insertStateUserFunc($id_user, $state){
        $add_state = DB::table("users_state")->insert(["id_user" => $id_user, "state" => $state,
            "created_at" => Carbon::now(), "updated_at" => Carbon::now()]);
        $update_state = DB::table("users")->where("id", $id_user)->update(["state" => $state]);

        if($add_state && $update_state)
            return true;
        else
            return false;
    }

    public function getStateUser(Request $request)
    {
        $password = $request->input("password");
        $result['success'] = false;

        do {
            $user = DB::table("users")->select(["id", "state"])->where("password", $password)->first();
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }
            $result['state'] = $user->state;

            if ($user->state == 2 || $user->state == 3 || $user->state == 4){
                $id_order = DB::table("order_user")->where("id_user", $user->id)->orderByDesc("id")->pluck("id_order")->first();

                if ($id_order){
                    $order = DB::table("orders")->where("id",$id_order)->get();
                    $result['order'] = OrderResource::collection($order)[0];
                }
            }
            $result['success'] = true;

        } while (false);
        return response()->json($result);
    }

    public function getDataUser(Request $request)
    {
        $password = $request->input("password");
        if ($user = User::where("password", $password)->first()) {
            $result = new UserResource($user);
            $result["success"] = true;
        } else {
            $result['success'] = false;
            $result['message'] = 'Пользователь не найден';
        }
        return response()->json($result);

    }

    public function editTokenUser(Request $request)
    {
        $password = $request->input('password');
        $token = $request->input('token');
        $result['success'] = false;

        if ($user = DB::table("users")->where("password", $password)->first()) {
            $result["success"] = true;
            $update = DB::table("users")->where("id",$user->id)->update(["token"=>$token]);
        } else {
            $result['success'] = false;
            $result['message'] = 'Пользователь не найден';
        }

        return response()->json($result);
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
