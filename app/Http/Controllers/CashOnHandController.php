<?php

namespace App\Http\Controllers;

use App\Http\Resources\CashOnHandHistoryResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashOnHandController extends Controller
{
    public function plusSumma($id_driver, $summa, $id_order, $comment = ''){

        $insert  = DB::table("cash_driver_history")->insert([
            'id_driver' => $id_driver,
            'id_order'  => $id_order,
            'summa'     => $summa,
            'comment'   => $comment,
            "created_at" => Carbon::now(),
            "updated_at" => Carbon::now()]);
        if ($insert){
            $this->updateSumma($id_driver,$summa);
        }

        return (bool)$insert;
    }

    public function minusSumma($id_driver, $summa, $id_order, $comment = ''){
        $summa = -1 * $summa;
        $insert = DB::table("cash_driver_history")->insert([
            'id_driver' => $id_driver,
            'id_order'  => $id_order,
            'summa'     => $summa,
            'comment'   => $comment,
            "created_at" => Carbon::now(),
            "updated_at" => Carbon::now()]);

        if ($insert){
            $this->updateSumma($id_driver,$summa);
        }
        return (bool)$insert;
    }

    public function updateSumma($id_driver, $summa){
        if ($summa != 0){
            $old_summa = DB::table('users')->where('id', $id_driver)->pluck('cash_on_hand')->first();
            $new_summa = (int) $old_summa + $summa;
            $update_users = DB::table('users')
                ->where('id', $id_driver)
                ->update(['cash_on_hand' => $new_summa]);

        }
    }

    public function updateAllSummaDriver($id_driver){
        $summa = DB::table('cash_driver_history')->where('id_driver', $id_driver)->sum('summa');
        $update_users = DB::table('users')
            ->where('id', $id_driver)
            ->update(['cash_on_hand' => $summa]);
        return $summa;
    }

    public function getCashHistory(Request $request){
        $password = $request->input('password');
        $result['success'] = false;
        do{
            $current = DB::table('users')->where('password',$password)->select('cash_on_hand', 'id')->first();

            if (!$current){
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $result['current'] = $current->cash_on_hand;

            $result['history'] = array();
            $history = DB::table('cash_driver_history', 'h')
                ->leftJoin('orders as o', 'h.id_order', '=', 'o.id')
                ->select('h.id', 'h.summa', 'h.created_at', 'o.cafe_name')
                ->where('id_driver', $current->id)->get();
            if($history) {
                $result['history'] = CashOnHandHistoryResource::collection($history);
            }
            $result['success'] = true;
        }while(false);
        return response()->json($result);
    }

    public function getCurrentCash(Request $request){
        $password = $request->input('password');
        $result['success'] = false;
        do{
            $current = DB::table('users')->where('password',$password)->select('cash_on_hand', 'id')->first();

            if (!$current){
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $result['current'] = $current->cash_on_hand;

            $result['success'] = true;
        }while(false);
        return response()->json($result);
    }

    public function driverReturnCash(Request $request){
        $key = $request->input('key');

    }
}
