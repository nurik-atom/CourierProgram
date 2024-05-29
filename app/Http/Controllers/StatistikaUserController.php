<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatistikaUserController extends Controller
{
    public function getStatistikaEkranFirstReq(Request $request){
        $password = $request->input("password");
        $result['success'] = false;

        do {
            $user = UserController::getUser($password);
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $result['dates'][] = array(
                'name'=>'Сегодня',
                'title' => Carbon::now()->locale("ru_RU")->isoFormat('D MMMM'),
                'date_from' => Carbon::now()->format("Y-m-d"),
                'date_to' => Carbon::now()->format("Y-m-d"),
            );

            $result['dates'][] = array(
                'name'=>'Вчера',
                'title' => Carbon::now()->addDay(-1)->locale("ru_RU")->isoFormat('D MMMM'),
                'date_from' => Carbon::now()->addDay(-1)->format("Y-m-d"),
                'date_to' => Carbon::now()->addDay(-1)->format("Y-m-d"),
            );

            $result['dates'][] = array(
                'name'=>'С начала недели',
                'title' => Carbon::now()->startOfWeek()->locale("ru_RU")->isoFormat('D MMMM').' - '.Carbon::now()->locale("ru_RU")->isoFormat('D MMMM'),
                'date_from' => Carbon::now()->startOfWeek()->format("Y-m-d"),
                'date_to' => Carbon::now()->format("Y-m-d"),
            );


            $result['dates'][] = array(
                'name'=>'С начала месяца',
                'title' => Carbon::now()->startOfMonth()->locale("ru_RU")->isoFormat('D MMMM').' - '.Carbon::now()->locale("ru_RU")->isoFormat('D MMMM'),
                'date_from' => Carbon::now()->startOfMonth()->format("Y-m-d"),
                'date_to' => Carbon::now()->format("Y-m-d"),
            );

            $result['data'] = self::getStatistikaData($user->id, Carbon::now()->format("Y-m-d"), Carbon::now()->format("Y-m-d"));
        }while(false);

        return response()->json($result);

    }

    public function getStatistikaData($id_user, $date_from, $date_to){

        $orders =  DB::table('orders')
            ->selectRaw('COUNT(*) as count, SUM(distance + distance_to_cafe) as distance')
            ->where('status', 7)
            ->where('id_courier', $id_user)
            ->whereBetween('created_at', [$date_from.' 00:00:00', $date_to.' 23:59:59'])
            ->first();

        $result['kol_orders'] = $orders->count;
        $result['distance']   = (int) $orders->distance;

        $result['kol_otmena'] = DB::table('order_user')
            ->where('status', 9)
            ->where('id_user', $id_user)
            ->whereBetween('created_at', [$date_from.' 00:00:00', $date_to.' 23:59:59'])
            ->count();

        $result['zarabotok'] = DB::table('balance_history')
            ->where('amount', '>', 0)
            ->where('id_user', $id_user)
            ->whereBetween('created_at', [$date_from.' 00:00:00', $date_to.' 23:59:59'])
            ->sum('amount');


        return $result;
    }
}
