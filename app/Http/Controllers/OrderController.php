<?php

namespace App\Http\Controllers;

use App\Http\Requests\checkOrderUserRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\CalculateRatingUser;
use App\Jobs\RequestFinishOrderToAllfood;
use App\Jobs\RequestPoluchilOrderToAllfood;
use App\Jobs\RequestStartDeliveryOrderToAllfood;
use App\Jobs\RequestTakedOrderToAllfood;
use Carbon\Carbon;
use http\Client\Curl\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use function Laravel\Prompts\select;

//use App\Http\Controllers\UserController;

class OrderController extends Controller
{

//Статусы заказа
//1. новый
//2. назначается курьер
//3. назначен курьер
//4. Курьер в Кафе
//5. на доставке
//6. Возле клиента
//7. Доставлен Успешно
//8 отмена курьер
//9 отмена

//Статусы предложение к курьера
//1. Новый
//2. Курьер принял
//3. Курьер не принял
//9. Курьер отменил


    public static function getTextStatus($key)
    {
        $status[1] = 'Новый заказ';
        $status[2] = 'Ищем курьера';
        $status[3] = 'Курьер назначен';
        $status[4] = 'Курьер в кафе ждет заказа';
        $status[5] = 'Заказ доставляется';
        $status[6] = 'Курьер у клиента';
        $status[7] = 'Заказ успешно доставлен';
        $status[9] = 'Заказ отменен';

        return $status[$key];
    }

    public function getStatusTimeOrder(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input("id_order");
        $result['success'] = false;
        do {
            $user = UserController::getUser($password);

            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $order = DB::table("orders")->find($id_order);
            if (!$order) {
                $result['message'] = 'Заказ не найден';
                break;
            }

            $result['status'] = $order->status;
            if ($order->status === 2) {
                $time_offer = DB::table("order_user")
                    ->where("id_order", $id_order)
                    ->where("status", 2)
                    ->pluck("created_at")
                    ->last();

                $result['time_to_success'] = time() - strtotime($time_offer);
            }

            if ($order->status === 3) {
                $result['time_to_cafe'] = strtotime($order->arrive_time) - time();
            }

            if ($order->status === 5) {
                $time_status = DB::table("order_user")
                    ->where("id_user", $user->id)
                    ->where("id_order", $id_order)
                    ->where("status", 5)->pluck("created_at")->last();
                $result['time_to_client'] = strtotime($time_status) + $order->needed_sec - time();
            }
            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }

    public function checkOrderUser(checkOrderUserRequest $request)
    {
        $password = $request->input("password");
        $user = UserController::getUser($password);
        $result['success'] = false;
        $result['have_order'] = false;
        do {
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }
            $result['user_state'] = $user->state;
            $order_user = DB::table("order_user")->where("id_user", $user->id)->orderByDesc("id")->first();
            if (!$order_user) {
                break;
            }
            if ($order_user->status > 7) {
                break;
            }
            $order = DB::table("orders")
                ->where("id", $order_user->id_order)
                ->orderByDesc("id")
                ->get();

            if ($order[0]->status > 6) {
                break;
            }

            if ($order) {
                $result['have_order'] = true;

                if ($order[0]->status == 2){
                    $result['seconds'] = time() - strtotime($order_user->created_at);
                }

                if ($order[0]->status <= 3) {
                    $result['seconds'] = strtotime($order[0]->arrive_time) - time();
                }

                if ($order[0]->status == 4) {
                    $result['seconds'] = $order[0]->needed_sec;
                }

                if ($order[0]->status == 5) {
                    $start_time = DB::table("order_user")->where("id_order", $order[0]->id)->where("status", 5)->select("created_at")->first();

                    $result['seconds'] = $order[0]->needed_sec + strtotime($start_time->created_at) - time();
                }

                $result['order'] = OrderResource::collection($order)[0];
            }
            $result['success'] = true;
        } while (false);
        return response()->json($result);

    }

    public static function getOrderTitleAndButtonState($orders)
    {
        $texts = [
            2 => [
                'title' => 'Новая доставка из',
                'button' => 'Принят заказ',
                'active' => true,
            ],
            3 => [
                'title' => 'Заберите заказ в',
                'button' => 'Подтвердить получение заказа',
                'active' => true,
            ],
            4 => [
                'title' => 'Заберите следующий заказ',
                'button' => 'Заберите следующий заказ',
                'active' => false,
            ],
            5 => [
                'title' => 'Доставка',
                'button' => 'Завершить доставку',
                'active' => true,
            ],
            330 => [
                'title' => 'Заберите заказ в',
                'button' => 'Подтвердить получение заказа',
                'active' => true,
            ],
            331 => [
                'title' => 'Заберите предыдущий заказ',
                'button' => 'Заберите предыдущий заказ',
                'active' => false,
            ],
            430 => [
                'title' => 'Заберите следующий заказ',
                'button' => 'Заберите следующий заказ',
                'active' => false,
            ],
            431 => [
                'title' => 'Заберите заказ в',
                'button' => 'Подтвердить получение заказа',
                'active' => true,
            ],
            540 => [
                'title' => 'Доставка',
                'button' => 'Завершить доставку',
                'active' => true,
            ],
            541 => [
                'title' => 'Доставьте предыдущий заказ',
                'button' => 'Доставьте предыдущий заказ',
                'active' => false,
            ],
            530 => [
                'title' => 'Доставка',
                'button' => 'Завершить доставку',
                'active' => true,
            ],
            531 => [
                'title' => 'Доставьте предыдущий заказ',
                'button' => 'Доставьте предыдущий заказ',
                'active' => false,
            ],
        ];

        $res = array();
        $kol_order = count($orders);

        if ($kol_order > 1) {
            $ss0 = (int) $orders[0]->status.$orders[1]->status.'0';
            $ss1 = (int) $orders[0]->status.$orders[1]->status.'1';

            $orders[0]->title_text = $texts[$ss0]['title'];
            $orders[0]->button_text = $texts[$ss0]['button'];
            $orders[0]->button_active = $texts[$ss0]['active'];

            $orders[1]->title_text = $texts[$ss1]['title'];
            $orders[1]->button_text = $texts[$ss1]['button'];
            $orders[1]->button_active = $texts[$ss1]['active'];

        }else{
            $orders[0]->title_text = $texts[$orders[0]->status]['title'];
            $orders[0]->button_text = $texts[$orders[0]->status]['button'];
            $orders[0]->button_active = $texts[$orders[0]->status]['active'];
        }

        return $orders;

    }

    public static function getActiveOrderTabIndex($orders)
    {
        return (int) ($orders[0]->status === 4 && $orders[1]->status === 3);
    }

    public function checkOrderUser_2(Request $request){
        $password = $request->input("password");
        $user = UserController::getUser($password);
        $result['success'] = false;
//        $result['have_order'] = false;

        do {
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }
            $result['user_state'] = $user->state;
            $result['user_type'] = $user->type;
            $result['user_online'] = $user->state != 0 ? 1 : 0;

            $orders = DB::table("orders")
                ->where("id_courier", $user->id)
                ->whereNotIn('status', [7,9])
                ->orderBy("id")
                ->get();
            if (count($orders) > 0){
                $result['kol_order'] = count($orders);
                $orders = self::getOrderTitleAndButtonState($orders);
                $result['active_order_tab_index'] = ($result['kol_order'] > 1)
                    ? self::getActiveOrderTabIndex($orders)
                    : 0;
                $result['orders'] = OrderResource::collection($orders);

            }else{
                $result['kol_order'] = 0;
                $result['orders'] = array();
            }

            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    public static function changeOrderCourierStatus($id_order, $id_courier, $status)
    {
        //Update "ORDERS" table
        DB::table("orders")->where("id", $id_order)
            ->update(['status' => $status, 'id_courier' => $id_courier]);


        if ($status == 7){
            $oneMoreOrder = DB::table('orders')
                ->select('status', 'id')
                ->where('id_courier', $id_courier)
                ->whereNotIn('status', [1,7,9])
                ->where('id', '!=', $id_order)
                ->orderBy('id')
                ->first();

            if ($oneMoreOrder){
                $user_state = $oneMoreOrder->status;
                if ($oneMoreOrder->status == 4){
                    self::autoStartDelivery($oneMoreOrder->id, $id_courier);
                }
            }else{
                $user_state = 1;
            }
        }
        else{
            $user_state = $status;
        }

        //Update User State
        UserController::insertStateUserFunc($id_courier, $user_state);

        //ADD to ORDER_USER table
        DB::table("order_user")
            ->insert([
                "id_user" => $id_courier,
                "id_order" => $id_order,
                "status" => $status,
                "created_at" => Carbon::now(),
                "updated_at" => Carbon::now()]);

    }

    public function takeOrder(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input('id_order');

        $result['success'] = false;

        do {
            $checkedDataResult = $this->checkUserAndOrder($password, $id_order, 2);

            if (!$checkedDataResult['success']) {
                $result['message'] = $checkedDataResult['message'];
                break;
            }
            $user = $checkedDataResult['user'];
            $order = $checkedDataResult['order'];

            self::changeOrderCourierStatus($order->id, $user->id, 3);

            //Curl to allfood kz
            RequestTakedOrderToAllfood::dispatch($order, $user, 5)->delay(15);

//            $result['allfood'] = PushController::takedOrderAllfood($order, $user, "5");
            $result['success'] = true;

        } while (false);

        return response()->json($result);
    }

    public function poluchilZakaz(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input("id_order");
        $lat = $request->input("lat");
        $lon = $request->input("lon");
        $result['success'] = false;
        do {
            $checkedDataResult = $this->checkUserAndOrder($password, $id_order, 3);

            if (!$checkedDataResult['success']) {
                $result['message'] = $checkedDataResult['message'];
                break;
            }
            $user = $checkedDataResult['user'];
            $order = $checkedDataResult['order'];

            $distance_to_cafe = SearchController::getDistance($order->from_geo, $lat . "\n" . $lon);

            if ($distance_to_cafe > 300) {
                $result['message'] = 'Вы слишком далеко находитесь от кафе';
                break;
            }

            if (!self::autoStartDelivery($order->id, $user->id)) {
                self::changeOrderCourierStatus($order->id, $user->id, 4);
                RequestPoluchilOrderToAllfood::dispatch($order, $user)->delay(15);

            }else{
                RequestStartDeliveryOrderToAllfood::dispatch($order, $user,15)->delay(15);
            }


            //Curl to allfood kz
//            $result['allfood'] = PushController::courierInCafe($order, $user);
            $result['success'] = true;
        } while (false);
        return response()->json($result);
    }

    public static function autoStartDelivery($id_order, $id_user)
    {
        $result = false;
        $other_order = DB::table("orders")->select('id', 'status')
            ->where("id_courier", $id_user)
            ->whereNot('id', $id_order)
            ->whereIn('status', [3,4,5])
            ->first();

        if (!$other_order){
            self::changeOrderCourierStatus($id_order, $id_user, 5);
            $result = true;
        }

        if ($other_order && $other_order->status == 4){
            self::changeOrderCourierStatus($other_order->id, $id_user, 5);
        }

        return $result;
    }

    public function finishDeliveryOrder(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input("id_order");
        $lat = $request->input("lat");
        $lon = $request->input("lon");

        $result['success'] = false;
        do {
            $checkedDataResult = $this->checkUserAndOrder($password, $id_order, 5);

            if (!$checkedDataResult['success']) {
                $result['message'] = $checkedDataResult['message'];
                break;
            }
            $user = $checkedDataResult['user'];
            $order = $checkedDataResult['order'];

            $distance_to_cafe = SearchController::getDistance($order->to_geo, $lat . "\n" . $lon);

            if ($distance_to_cafe > 300) {
                $result['message'] = 'Вы слишком далеко находитесь от адреса клиента';
                break;
            }

            MoneyController::oplatitZakasPosleFinish($order, $user);

            if ($order->sposob_oplaty == 1){
                (new CashOnHandController)->plusSumma($order->id_courier, $order->summ_order, $order->id);
            }

            self::changeOrderCourierStatus($order->id, $user->id, 7);

            if ($request->hasFile('photo_check')) {
                $file = $request->file('photo_check');
                $filename = time() . '_' . rand(11111,99999).'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('photo_checks', $filename, 'public'); // Сохраняем файл в папке 'photo_checks' на диске 'public'
                $fullUrl = Storage::url($path);
                $update_photo_check = DB::table("orders")
                    ->where('id', $order->id)
                    ->update(['photo_check' => $fullUrl]);
                $result['photo_check'] = true;
            }

            $result['success'] = true;

            CalculateRatingUser::dispatch($order->id_courier)->delay(30);
            RequestFinishOrderToAllfood::dispatch($order, $user)->delay(15);

        } while (false);

        return response()->json($result);

    }

    public function refusingOrderReq(Request $request)
    {
        $pass = $request['password'];
        $id_order = $request['id_order'];
        $cause = $request["prichina"];
        $result['success'] = false;

        $user = UserController::getUser($pass);

        do {
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }
            $order = DB::table("orders")->where("id", $id_order)->select("status", "id_courier")->first();

            if (!$order->status) {
                $result['message'] = 'Заказ не найден';
                break;
            }
            DB::table("orders")->where('id', $id_order)->update(['status' => 1]);
            self::refusingOrder($user->id, $id_order, 12, $cause);
            UserController::insertStateUserFunc($user->id, 1);
            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }

    public static function refusingOrder($id_user, $id_order, $status, $cause){
        $prev_time = DB::table("order_user")
            ->where("id_user", $id_user)
            ->where("id_order", $id_order)
            ->where("status", "!=", $status)
            ->pluck("created_at")->last();

        if (!$prev_time) $prev_time = Carbon::now();

        DB::table("order_user")->updateOrInsert([
            "id_user" => $id_user,
            "id_order" => $id_order,
            "status" => $status,
            "refuse_text" => $cause
        ],["seconds" => Carbon::now()->diffInSeconds($prev_time),
            "created_at" => Carbon::now(),
            "updated_at" => Carbon::now()]);
    }

    public function cancelOrder(Request $request)
    {
        $pass = $request['password'];
        $id_order = $request['id_order'];
        $cause = $request["prichina"];
        $result['success'] = false;
        do {
            $user = UserController::getUser($pass);

            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $order = DB::table("orders")->where("id", $id_order)->select("status", "id_courier")->first();


            if (!$order->status) {
                $result['message'] = 'Заказ не найден';
                break;
            }

            if ($user->id != $order->id_courier) {
                $result['message'] = 'Заказ не принадлежит Вам';
                break;
            }

            if ($order->status == 9) {
                $result['message'] = 'Заказ уже отменен';
                break;
            }

            $cancelSql = DB::table("orders")->where('id', $id_order)
                ->update(['status' => 1]);
            self::addCauseToCancelled($id_order, $user->id, 1, $cause);

            UserController::defineStateAndUpdate($user->id);

            if (!$cancelSql) {
                $result['message'] = 'Произошло ошибка';
            } else
                $result['success'] = true;

        } while (false);
        return response()->json($result);
    }

    public static function addCauseToCancelled($id_order, $id_who, $who, $cause)
    {
        // WHO
//        1. Курьер
//        2. Кафе
//        3. Клиент
//        4. Оператор
//        5. Программа


        $add = DB::table("orders_cancelled")->insert([
            "id_order" => $id_order,
            "id_who" => $id_who,
            "who" => $who,
            "cause" => $cause
        ]);

        if ($add)
            return true;
        else
            return false;

    }

    public function checkUserAndOrder($password, $id_order, $status)
    {
        do {
            $result['success'] = false;

            if (!$password) {
                $result['message'] = 'Пароль нет';
                break;
            }

            if (!$id_order) {
                $result['message'] = 'id заказа нет';
                break;
            }

            $user = DB::table("users")->where("password", $password)->first();
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $order = DB::table("orders")->where("id", $id_order)->first();

            if (!$order) {
                $result['message'] = 'Заказ не найден';
                break;
            }

            if ($status != $order->status) {
                $result['message'] = 'Неправильный статус';
                break;
            }

            $result['order'] = $order;
            $result['user'] = $user;

            $result['success'] = true;
        } while (false);

        return $result;
    }

    public function prichinyOtmeny()
    {
        $result = array();
        $result[] = 'Расстояние слишком большое';
        $result[] = 'Магазин не в моей отправной точке';
        $result[] = 'Я не хочу делать заказ';
        $result[] = 'Я не хочу идти в этот магазин';
        $result[] = 'У меня слишком много заказов';
        $result[] = 'У меня проблемы с телефоном или приложением';
        $result[] = 'Моя смена скоро закончится';
        $result[] = 'Мне нужен перерыв';
        $result[] = 'У меня чрезвычайная ситуация';
        $result[] = 'Магазин закрыт';

        return response()->json($result);
    }

    public function managerNotFoundDriver(Request $request){

        $id_order = $request['id_order'];
        $type     = $request['type'];

        $result['success'] = false;
        $result['driver'] = array();
        do {


            $order = DB::table("orders")
                ->where("id_allfood", $id_order)
                ->where('type', $type)
                ->select("status", "id_courier")
                ->first();

            if (!$order) {
                $result['message'] = 'Заказ не найден';
                break;
            }

            if ($order->id_courier){
                $driver = DB::table('users')
                    ->select('id','name','photo', 'phone', 'type')
                    ->where('id', $order->id_courier)
                    ->first();
                if ($driver) {
                    $result['driver'] = $driver;
                }
            }

            $result['success'] = true;

        } while (false);
        return response()->json($result);
    }

    public function getQROplatyOrder(Request $request)
    {
        $id_order = $request['id_order'];

        $result['success'] = false;
        do {


            $order = DB::table('orders')
                ->where('id', $id_order)
                ->select('status', 'id_courier','type', 'id_cafe')
                ->first();

            if (!$order) {
                $result['message'] = 'Заказ не найден';
                break;
            }

            if ($order->type == 1){
                $qr = array(
                    array(
                        'name'=>'ALLFOOD',
                        'bank'=>'Kaspi',
                        'qr' => 'https://allfood.kz/upload/file_1723635093_346245619.jpg'
                    ),array(
                        'name'=>'ALLFOOD',
                        'bank'=>'Halyk Bank',
                        'qr' => 'https://allfood.kz/upload/file_1723635093_346245619.jpg'
                    )

                );
            }else{
                $qr = PushController::sendReqToAllfoodGetResult('getQROplatyCafe', array('id_cafe' => $order->id_cafe));
            }



            $result['oplaty'] = $qr;

            $result['success'] = true;

        } while (false);
        return response()->json($result);
    }
}
