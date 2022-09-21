<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashOnHandController extends Controller
{
    public function plusSumma($id_driver, $summa){

        $insert  = DB::table("order_user")->insert([
                'id_driver' => $id_driver,
                'summa'     => $summa,
                "created_at" => Carbon::now(),
                "updated_at" => Carbon::now()]);
        if ($insert){
            $this->updateSumma($id_driver);
        }

        return (bool)$insert;
    }

    public function minusSumma($id_driver, $summa){
        $insert  = DB::table("order_user")->insert([
            'id_driver' => $id_driver,
            'summa'     => $summa,
            "created_at" => Carbon::now(),
            "updated_at" => Carbon::now()]);

        if ($insert){
            $this->updateSumma($id_driver);
        }
        return (bool)$insert;
    }

    public function updateSumma($id_driver){
        $update_summa = DB::table('cash_driver_history')
            ->select(DB::raw('SUM(summa) as summa'))
            ->where('id_driver', $id_driver)
            ->where('calculated', false)->first();

        if ($update_summa){
            $update_summa = $update_summa->summa;
            $old_summa = DB::table('users')->where('id', $id_driver)->pluck('cash_on_hand')->first();
            if ($update_summa != 0){
                $new_summa = (int) $update_summa + $old_summa;
                $update_users = DB::table('users')
                    ->where('id', $id_driver)
                    ->update(['cash_on_hand' => $new_summa]);
            }
        }
    }


}
