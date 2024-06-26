<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancyEkranController extends Controller
{
    public function getFinancyFirstEkranData(Request $request){
        $password = $request->input("password");
        $result['success'] = false;

        $result['nalichnie'] = 0;
        $result['ojidanie'] = 0;
        $result['vyplaty'] = array();

        do{
            $user = UserController::getUser($password);
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $balance = DB::table("balance")->where("id_user", $user->id)
                ->pluck("amount")->first();

            $cash_on_hand = MoneyController::calculateCashOnHand($user->id);

            $result['nalichnie'] = $cash_on_hand;
            $result['ojidanie'] = $balance ?? 0;

            $vyplaty = DB::table("vyplaty")->select('id', 'date_from', 'date_to', 'summa', 'nalogi')
                ->where("id_user", $user->id)->limit(5)->orderByDesc('id')->get();

            if (count($vyplaty) > 0) {
                foreach ($vyplaty as $v) {
                    $v->period = Carbon::createFromFormat('Y-m-d', $v->date_from)->format('d.m');
                    $v->period .= ' - '.Carbon::createFromFormat('Y-m-d', $v->date_to)->format('d.m.Y');
                    $result['vyplaty'][] = $v;
                }
            }

//            $result['vyplaty'] = array(
//              array("id"=>3, "period"=>'01.05 - 15.05.2024', "summa"=>230000),
//              array("id"=>2, "period"=>'16.04 - 30.04.2024', "summa"=>57450),
//              array("id"=>1, "period"=>'01.04 - 15.04.2024', "summa"=>198000)
//            );
            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    public function getVyplatyDataById(Request $request)
    {
        $password   = $request->input("password");
        $id_vyplaty = $request->input("id_vyplaty");
        $result['success'] = false;

        do{
            $user = UserController::getUser($password);
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $vyplata = DB::table("vyplaty")->where("id", $id_vyplaty)->first();

            if (!$vyplata){
                $result['message'] = 'Выплата не найдено';
                break;
            }

//            $result['vyplata'] = $vyplata;

            $result['vyplata']['kol_orders'] = $vyplata->kol_orders;
            $result['vyplata']['summa'] = $vyplata->summa;
            $result['vyplata']['nalogi'] = $vyplata->nalogi;
            $result['vyplata']['itogo_nachisleno'] = $vyplata->summa - $vyplata->nalogi;

            $result['vyplata']['period'] = Carbon::createFromFormat('Y-m-d', $vyplata->date_from)->format('d.m');
            $result['vyplata']['period'] .= ' - '.Carbon::createFromFormat('Y-m-d', $vyplata->date_to)->format('d.m.Y');



            $balance = DB::table("balance_history")
                ->leftJoin('orders', 'balance_history.id_order', '=', 'orders.id')
                ->selectRaw('balance_history.*, orders.cafe_name')
                ->where("amount",'>', 0)
                ->where("balance_history.created_at", '>=', $vyplata->date_from)
                ->where("balance_history.created_at", '<=', $vyplata->date_to)
                ->where('id_user', $user->id)
                ->orderByDesc('id')
                ->orderBy('type')
                ->get();

            if ($balance){
                $result['svodka_zakazov'] = self::groupBalanceByIdOrder($balance);
            }else{
                $result['svodka_zakazov'] = array();
            }
            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    public static function groupBalanceByIdOrder($data = null)
    {
        $types = array(
          1 => 'Базовая оплата',
          2 => 'Бонус до кафе',
          3 => 'Доплата за слот',
          4 => 'Оператор корректировка',
          5 => 'Кэф слота',
          6 => 'Кэф от ALLFOOD',
        );

        $result = array();

        $i = -1;
        $current_id_order = 0;

        foreach ($data as $d){
            if($current_id_order != $d->id_order || $d->id_order == 0)
            {
                $i++; $j = 0;
                $current_id_order = $d->id_order;
                $result[$i]['itogo'] = 0;
            }

            $result[$i]['id_order'] = $d->id_order;
            $result[$i]['cafe_name'] = $d->cafe_name ?? $types[$d->type];
            $result[$i]['date']     = Carbon::createFromFormat('Y-m-d H:i:s', $d->created_at)
                                            ->locale("ru_RU")->isoFormat('LL');
            $result[$i]['tran'][$j]['title'] = $types[$d->type];
            $result[$i]['tran'][$j]['amount'] = $d->amount;

            $result[$i]['itogo'] +=$d->amount;

            $j++;
        }

//        return response()->json($result);
        return $result;
    }

    public function getCashDetailsEkran(Request $request)
    {
        $password   = $request->input("password");
        $result['success'] = false;

        do {
            $user = UserController::getUser($password);
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }
            $cash_on_hand = $user->cash_on_hand;

            $last_cash_vozvrat_id_zapis = DB::table("cash_driver_history")->select('id')
                ->where("id_driver", $user->id)
                ->where('summa', '<', 0)
                ->orderByDesc('id')
                ->pluck('id')
                ->first();


            $svodka = DB::table("cash_driver_history")
                ->leftJoin('orders', 'cash_driver_history.id_order', '=', 'orders.id')
                ->selectRaw('cash_driver_history.*, orders.cafe_name')
                ->where('cash_driver_history.id_driver', $user->id)
                ->where('cash_driver_history.id', '>', $last_cash_vozvrat_id_zapis)
                ->orderByDesc('cash_driver_history.id')
                ->get();

            if ($svodka){
                foreach ($svodka as $key => $s) {
                    $result['svodka'][$key]['id'] = $s->id;
                    $result['svodka'][$key]['date'] =  Carbon::createFromFormat('Y-m-d H:i:s', $s->created_at)->locale("ru_RU")->isoFormat('LL');;
                    $result['svodka'][$key]['cafe_name'] = $s->cafe_name;
                    $result['svodka'][$key]['id_order'] = $s->id_order;
                    $result['svodka'][$key]['summa'] = $s->summa;
                }
            }else{
                $result['svodka'] = array();
            }

            $result['cash_on_hand'] = $cash_on_hand;

            $result['success'] = true;

        } while (false);

        return response()->json($result);
    }

    public function getAllVyplaty(Request $request){
        $password = $request->input("password");
        $count_req = $request->input("count_req");
        $result['success'] = false;
        $result['vyplaty'] = array();

        do{
            $user = UserController::getUser($password);
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }
            $take = 15;
            $skip = $count_req * $take;
            $vyplaty = DB::table("vyplaty")
                ->select('id', 'date_from', 'date_to', 'summa')
                ->where("id_user", $user->id)
                ->orderByDesc('id')
                ->skip($skip)->take($take)
                ->get();

            if (count($vyplaty) > 0) {
                foreach ($vyplaty as $v) {
                    $v->period = Carbon::createFromFormat('Y-m-d', $v->date_from)->format('d.m');
                    $v->period .= ' - '.Carbon::createFromFormat('Y-m-d', $v->date_to)->format('d.m.Y');
                    $result['vyplaty'][] = $v;
                }
            }

            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }
}
