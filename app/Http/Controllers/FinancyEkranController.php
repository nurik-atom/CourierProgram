<?php

namespace App\Http\Controllers;

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

            $result['nalichnie'] = $user->cash_on_hand;
            $result['ojidanie'] = $balance ?? 0;

            $vyplaty = DB::table("vyplaty")->where("id_user", $user->id);

            $result['vyplaty'] = array(
              array("id"=>3, "period"=>'01.05 - 15.05.2024', "summa"=>230000),
              array("id"=>2, "period"=>'16.04 - 30.04.2024', "summa"=>57450),
              array("id"=>1, "period"=>'01.04 - 15.04.2024', "summa"=>198000)
            );

            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }
}
