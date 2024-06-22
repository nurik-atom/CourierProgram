<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MoneyController extends Controller
{
    const MIN_AMOUNT = array(
        1 => 400, // Walking Courier
        2 => 400, // Velo Courier
        3 => 450, // Moto Courier
        4 => 600  // Car  Courier
    );

    const POLTORA_KM = 500;

    public function test_money(Request $request){
//        $t = self::minusAmount(10,5,600,"Test Amount");
//        if ($t) return true;

        $distance = $request->input("distance");
        $type = $request->input("type");
        return $this->costDeliveryAll($distance, $type);
    }

    public static function costDeliveryTekBaza($distance, $type)
    {

        // Вернем стоимость для Курьера
        // Должен быть минимальные ставки для курьеров разного типа,
        // Если авто минимум 600, если мото 450, пешком 300
        // Можем взять Данные чтобы уменьшить запрос а не ID. надо подумать

        $summa = 0;

        if ($distance < 3000) $metr_500 = 50;
        if ($distance > 3000) $metr_500 = 45;

        $count_500 = ($distance - 1500)/500;
        if ($count_500 < 0)
            $summa = self::POLTORA_KM;
        else
            $summa = self::POLTORA_KM + ($count_500 * $metr_500);

        if ($summa < self::MIN_AMOUNT[$type])
            $summa = self::MIN_AMOUNT[$type];

        return $summa;

    }

    public static function costDeliveryAll($distance, $type, $allfood_kef = 1, $slot_kef = 1){

        $summa = self::costDeliveryTekBaza($distance, $type);
//        $res['$count_500'] = $count_500;
//        $res['$metr_500'] = $metr_500;
//        $res['summa'] = ;

        return ceil($summa * $allfood_kef * $slot_kef);
    }

    /** TYPE
     *  1 За заказ
     *  2 Бонус до кафе
     *  3 Доплата за часы
     *  4 Оператор корректировка
    */
    public static function addAmount($id_user, $id_order, $amount, $description, $type){
        $add = DB::table("balance_history")->insert([
            "id_user"=>$id_user,
            "id_order"=>$id_order,
            "amount"=>$amount,
            "type"=>$type,
            "description"=>$description,
            "created_at"=>Carbon::now(),
            "updated_at"=>Carbon::now()
        ]);

        if ($add){
            self::calculateBalance($id_user);
            return true;
        }
    }

    public static function minusAmount($id_user, $id_order, $amount, $description,$type=1){
        $add = DB::table("balance_history")->insert([
            "id_user"=>$id_user,
            "id_order"=>$id_order,
            "amount"=>-1*$amount,
            "type"=>$type,
            "description"=>$description,
            "created_at"=>Carbon::now(),
            "updated_at"=>Carbon::now()
        ]);

        if ($add){
            self::calculateBalance($id_user);
            return true;
        }
    }

    public static function calculateBalance($id_user){
        $summ = DB::table("balance_history")->where("id_user", $id_user)->sum("amount");
        $balance = DB::table("balance")->where("id_user", $id_user)->first();

        if ($balance){
            DB::table("balance")->where("id_user",$id_user)
                ->update([
                "amount"=>$summ,
                "updated_at"=>Carbon::now()
            ]);
        }else{
            DB::table("balance")->insert([
                "id_user"=>$id_user,
                "amount"=>$summ,
                "created_at"=>Carbon::now(),
                "updated_at"=>Carbon::now()
            ]);
        }
    }

    public static function calculateCashOnHand($id_user)
    {
        $cash_on_hand = DB::table("cash_driver_history")->where("id_driver", $id_user)->sum("summa");
        if ($cash_on_hand){
            DB::table("users")->where("id",$id_user)
                ->update([
                    "cash_on_hand"=>$cash_on_hand,
                    "updated_at"=>Carbon::now()
                ]);
        }else{
            $cash_on_hand = 0;
        }

        return $cash_on_hand;
    }

    public static function oplatitZakasPosleFinish($order, $user)
    {

//      1 => 'Базовая оплата',
//      2 => 'Бонус до кафе',
//      3 => 'Доплата за слот',
//      4 => 'Оператор корректировка',
//      5 => 'Кэф слота',
//      6 => 'Кэф от ALLFOOD',

//TODO bitpedi ali
        $baza = self::costDeliveryTekBaza($order->distance, $order->type);
        $description = "Заказ №" . $order->id;
        MoneyController::addAmount($user->id, $order->id, $baza, $description, 1);

        //! Доплата Дистанция до кафе
        $summa_to_cafe = self::getSummaToCafe($order->distance_to_cafe);

        if ($summa_to_cafe > 0){
            MoneyController::addAmount($user->id, $order->id, $summa_to_cafe, 'Расстояние до заведения '.round($order->distance_to_cafe/1000, 2).' км', 2);
        }

        if ($order->kef > 1){
            $summa = (int) $baza * $order->kef;
            $description = 'Кэф от allfood';
            MoneyController::addAmount($user->id, $order->id, $summa, $description, 5);
        }

        if ($order->kef_slota != 1){
            $summa = (int) $baza * $order->kef_slota;
            $description = 'Кэф слота';
            MoneyController::addAmount($user->id, $order->id, $summa, $description, 6);
        }

    }

    public static function getSummaToCafe($distance){
        if ($distance < 2000){
            $res = 0;
        }else{
            $res = (int) (50 * ($distance / 1000));
        }
        return $res;
    }
}
