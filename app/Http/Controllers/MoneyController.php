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

    const POLTORA_KM = 300;

    public function test_money(Request $request){
//        $t = self::minusAmount(10,5,600,"Test Amount");
//        if ($t) return true;

        $distance = $request->input("distance");
        $type = $request->input("type");
        return $this->costDelivery($distance, $type);
    }

    public static function costDelivery($distance, $type){
        // Вернем стоимость для Курьера
        // Должен быть минимальные ставки для курьеров разного типа,
        // Если авто минимум 500, если мото 400, пешком 300
        // Можем взять Данные чтобы уменьшить запрос а не ID. надо подумать

        $summa = 0;

        if ($distance < 3000) $metr_500 = 50;
        if ($distance > 3000) $metr_500 = 45;
        if ($distance > 5000) $metr_500 = 40;
        if ($distance > 6000) $metr_500 = 35;
        if ($distance > 7000) $metr_500 = 30;

        $count_500 = ($distance - 1500)/500;
        if ($count_500 < 0)
            $summa = self::POLTORA_KM;
        else
            $summa = self::POLTORA_KM + ($count_500 * $metr_500);

        if ($summa < self::MIN_AMOUNT[$type])
            $summa = self::MIN_AMOUNT[$type];

//        $res['$count_500'] = $count_500;
//        $res['$metr_500'] = $metr_500;
//        $res['summa'] = ;

        return ceil($summa);
    }

    public static function addAmount($id_user, $id_order, $amount, $description){
        $add = DB::table("balance_history")->insert([
            "id_user"=>$id_user,
            "id_order"=>$id_order,
            "amount"=>$amount,
            "description"=>$description,
            "created_at"=>Carbon::now(),
            "updated_at"=>Carbon::now()
        ]);

        if ($add){
            self::calculateBalance($id_user);
            return true;
        }
    }

    public static function minusAmount($id_user, $id_order, $amount, $description){
        $add = DB::table("balance_history")->insert([
            "id_user"=>$id_user,
            "id_order"=>$id_order,
            "amount"=>-1*$amount,
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
}
