<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderMiniResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

//use Illuminate\Support\Facades\Hash;
//use Illuminate\Support\Str;

class UserController extends Controller
{
    public $active_state = [
      2,3,4,5,6
    ];

    public function imReady(Request $request){
        $password = $request->input("password");
        $state = $request->input("state");
        $result['success'] = false;

        if ($user = self::getUser($password)){
            $add_state = DB::table("users_state")->insert(["id_user" => $user->id, "state" => $state,
                "created_at" => Carbon::now(), "updated_at" => Carbon::now()]);
            $update_state = DB::table("users")->where("id", $user->id)->update(["state" => $state ]);
            $result['success'] = true;
        }

        return response()->json($result);
    }

    public static function getUser($password)
    {
        if (!$password) {
            return false;
        }
        $user = DB::table("users")->where("password", $password)->first();

        if ($user) {
            return $user;
        }

        return false;
    }

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
                'psw' => env("SMS_PASSWORD"),
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
            $password = sha1("AllFood-" . rand(123456, 999999) . time());

            if (!$user) {
                $users_sms_id = DB::table('users')->insertGetId([
                    'phone' => $phone,
                    'status' => 1,
                    'password' => $password,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
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

    public function checkUser(Request $request){
        $password = $request->input("password");
        $result['success'] = false;
        $user = self::getUser($password);
        if ($user !== false){
            $result['success'] = true;
            $result['status'] = $user->status;
        }

        return response()->json($result);
    }

    public function citiesForReg(){
        $cities = array(
            array('id'=>1, 'name'=>'Жанаозен'),
            array('id'=>2, 'name'=>'Актау'),
            array('id'=>6, 'name'=>'Семей')
        );
        return response()->json($cities);
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

    public function successRegistration(Request $request){
        $id_user = $request->input("id_user");
        $key = $request->input("key");
        $data['success'] = false;
        if ($key !== env("ALLFOOD_KEY")){
            exit("Error Key");
        }
        if (DB::table("users")->find($id_user)->update(['status' => 4])){
            PushController::successRegistrationPush($id_user);
            $data['success'] = true;
        }
        return response()->json($data);
    }

    function getStatusUser(Request $request)
    {
        $phone = $request->input('phone');
        $user = DB::table('users')->where('phone', $phone)->orderByDesc('id')->first();
        if ($user) {
            $data['success'] = true;
            $data['status'] = $user->status;
            $data['password'] = $user->password;
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
        $lat = $request->input("lat");
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
                ->pluck("id")->first();

            if ($select_geo) {
                $update_geo = DB::table("users_geo")
                    ->where("id", $select_geo)
                    ->update(["id_user" => $user, "lan" => $lat, "lon" => $lon, "type" => $type, "updated_at" => Carbon::now()]);
            } else {
                $add_geo = DB::table("users_geo")
                    ->insert(["id_user" => $user, "lan" => $lat, "lon" => $lon, "type" => $type,
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

    public static function insertStateUserFunc($id_user, $state)
    {
        $add_state = DB::table("users_state")->insert(["id_user" => $id_user, "state" => $state,
            "created_at" => Carbon::now(), "updated_at" => Carbon::now()]);
        $update_state = DB::table("users")->where("id", $id_user)->update(["state" => $state]);

        if ($add_state && $update_state)
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

            if (in_array($user->state,$this->active_state,false)) {
                $id_order = DB::table("order_user")->where("id_user", $user->id)->orderByDesc("id")->pluck("id_order")->first();

                if ($id_order) {
                    $order = DB::table("orders")->where("id", $id_order)->get();
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
            $update = DB::table("users")->where("id", $user->id)->update(["token" => $token]);
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

    public function changePhone(Request $request)
    {
        $password = $request->input("password");
        $new_number = $request->input("new_number");
        $data['success'] = false;
        do {

            if (strlen($new_number) != 11) {
                $data['message'] = "Номер не правильно\n" . $new_number;
                break;
            }

            $user = DB::table("users")->where("password", $password)->first();
            if (!$user) {
                $data['message'] = "Пользователь не найден";
                break;
            }
            $new_number_user = DB::table("users")->where("phone", $new_number)->first();
            if ($new_number_user) {
                $data['message'] = "Пользователь с таким номером уже существует.";
                break;
            }
//TODO ОИШБКА БЛЕАТЬ
//            $update = DB::table("users")->where("password", $password)->update(["phone" => $new_number, "password" => "inUpdateState"]);

//            if (!$update) {
//                $data['message'] = "Ошибка при изменение";
//                break;
//            }

            $user_sms = DB::table('users_sms')->where('phone', $new_number)->orderByDesc('id')->first();

            if (isset($user_sms) && (time() < (strtotime($user_sms->created_at) + 3600))) {
                $data['message'] = 'Вам уже отправлено смс';
                break;
            }

            $code = rand(1234, 9998);

            $mess = "Ваш пароль: $code \n С уважением, ALLFOOD Courier";
            $array = array(
                'login' => 'allfood',
                'psw' => env("SMS_PASSWORD"),
                'phones' => $new_number,
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
                    'phone' => $new_number,
                    'code' => $code,
                    'update_user' => $user->id,
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

    public function changePhoneStepTwo(Request $request){
        $password   = $request->input("password");
        $new_number = $request->input("new_number");
        $sms_code   = $request->input("sms_code");

        $result['success'] = false;

        do{
            $user = DB::table("users")->where("password", $password)->first();
            if (!$user) {
                $result['message'] = "Пользователь не найден";
                $result['success'] = false;
                break;
            }

            $sms_check = DB::table("users_sms")->where('phone', $new_number)->where('update_user', $user->id)->where('code', $sms_code)->first();
            if (!$sms_check){
                $result['message'] = "Смс код неправильно";
                $result['success'] = false;
                break;
            }

            $password = sha1("AllFood-" . rand(123456, 999999) . time());
            $update = DB::table('users')->where('id',$user->id)->update(['phone'=>$new_number, 'password'=>$password])
                ;

            $result['status']       = $user->status;
            $result['id']           = $user->id;
            $result['new_number']   = $new_number;
            $result['success']      = true;
            $result['password']     = $password;

        }while(false);
        return response()->json($result);
    }

    public function changeType(Request $request)
    {
        $password = $request->input("password");
        $new_type = $request->input("new_type");

        $user = DB::table("users")->where("password", $password)->first();

        if (!$user) {
            $data['message'] = "Пользователь не найден";
            $data['success'] = false;
        } else {
            DB::table("users")->where("password", $password)->update(["type" => $new_type]);
            $data['success'] = true;
        }

        return response()->json($data);
    }

    public function changeNames(Request $request)
    {
        $password = $request->input("password");
        $name = $request->input("name");
        $surname = $request->input("surname");
        $birthday = $request->input("birthday");
        $result['success'] = false;
        do{
            $user = self::getUser($password);
            if (!$user){
                $result['message'] = 'Пользователь не найден';
                break;
            }

            DB::table("users")->where("password", $password)->update([
                "name" => $name,
                "surname" => $surname,
                "birthday" => $birthday
            ]);

            if ($request->hasFile('photo')) {
                $image = $request->photo->store('images', "public");
                if ($image){
                    DB::table("users")->where("password", $password)->update([
                        "photo" => $image
                    ]);
                }
            }

            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    public function getMoneyAndOrdersUser(Request $request)
    {
        $password = $request->input("password");
        $result['success'] = false;
        do {
            $user = self::getUser($password);
            if(!$user){
                $request['message'] = "Пользователь не найден";
                break;
            }
            //WEEK
            $weekStartDate = Carbon::now()->startOfMonth()->format('Y-m-d H:i:s');
            $this_week = DB::table("balance_history")
                ->where("id_user", $user->id)
                ->where("id_order", "!=", 0)
                ->where("created_at", ">=", $weekStartDate)
                ->sum("amount");

            if (!$this_week)
                $result['this_week'] = 0;
            else
                $result['this_week'] = $this_week;

            //TODAY
            $startToday = date("Y-m-d")." 00:00:00";
            $today =  DB::table("balance_history")
                ->where("id_user", $user->id)
                ->where("id_order", "!=", 0)
                ->where("created_at", ">=", $startToday)
                ->sum("amount");

            if (!$today)
                $result['today'] = 0;
            else
                $result['today'] = $today;

            //BALANCE
            $balance = DB::table("balance")->where("id_user", $user->id)
                ->pluck("amount")->first();

            if (!$balance)
                $result['balance'] = 0;
            else
                $result['balance'] = $balance;

            $result['help_balance_pages'] = array();

            $help_balance_pages = DB::table("help_balance_pages")
                ->select("id", "name", "icon", "type")
                ->orderBy("sort")
                ->get();

            if ($help_balance_pages) {
                $result['help_balance_pages'] = $help_balance_pages;
            }

//            $result['orders'] = array();
//
//            $orders = DB::table("orders")
//                ->select("id", "created_at", "price_delivery")
//                ->where("id_courier", $user->id)
//                ->orderByDesc("id")
//                ->limit(20)->get();
//            $result['orders'] = OrderMiniResource::collection($orders);

            $result['date'] = Carbon::now()->startOfMonth()->diffForHumans();
            $result['success'] = true;
        } while (false);
        return response()->json($result);
    }

    public function getDateRangeOrders(Request $request){
        $password = $request->input("password");
        $from = $request->input("from");
        $to = $request->input("to");

        $result['success'] = false;

        do{
            $user = self::getUser($password);
            if (!$user){
                $result['message'] = 'Пользователь не найден';
                break;
            }
            $result['orders']= array();
            $sumCount = DB::table("orders")
                ->selectRaw("IFNULL(SUM(price_delivery),0) as summa, COUNT(id) as kol")
                ->where("id_courier", $user->id)
                ->where("created_at",">=" ,$from)
                ->where("created_at","<=" ,$to)
                ->where("status",7)
                ->first();

            $result['summ'] = (int) $sumCount->summa;
            $result['count'] = $sumCount->kol;

            $orders = DB::table("orders")
                ->select("id","cafe_name", "created_at","price_delivery", "status")
                ->where("id_courier", $user->id)
                ->where("created_at",">=" ,$from)
                ->where("created_at","<=" ,$to)
                ->orderByDesc("id")
                ->get();

            if ($orders) {
                $result['orders'] = OrderMiniResource::collection($orders);
            }
            $result['success'] = true;

        }while(false);

        return response()->json($result);
    }

    public function getFullDetailsOrder(Request $request){
        $password = $request->input("password");
        $id_order = $request->input("id_order");

        $result['success'] = false;

        do{
            $user = self::getUser($password);
            if (!$user){
                $result['message'] = 'Пользователь не найден';
                break;
            }
            $result['order']= array();

            $order = DB::table("orders")
                ->where("id_courier", $user->id)
                ->where("id", $id_order)
                ->get();

            if ($order) {
                $result['order'] = OrderResource::collection($order)[0];
            }else{
                $result['message'] = 'Заказ не найден';
            }
            $result['success'] = true;

        }while(false);

        return response()->json($result);
    }

    public function test()
    {
        echo "something";
    }
}
