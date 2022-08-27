<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SzpController extends Controller
{

    public function __construct()
    {
        $this->key_szp_allfood = sha1('ALL' . date('Ymd') . 'FOOD_2201');
    }

    public function getAllDrivers(Request $request)
    {
        $pass = $request->input('pass');
        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        $users = DB::table('users')
            ->select('id', 'name', 'surname', 'id_city', 'birthday', 'phone', 'type', 'status', 'rating', 'state', 'created_at')
            ->get();
        $result['users'] = $users;
        $result['success'] = true;
        return response()->json($result);
    }

    public function getOneDriverDetails(Request $request)
    {
        $pass = $request->input('pass');
        $id_driver = $request->input('id_driver');
        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        $user = DB::table('users')
            ->select('id', 'name', 'surname', 'id_city', 'photo', 'birthday', 'phone', 'type', 'status', 'rating', 'state', 'created_at')
            ->where('id', $id_driver)->first();

        $active_order = DB::table('orders')->select('id_allfood', 'from_address', 'to_address', 'cafe_phone', 'cafe_name', 'phone', 'name', 'type', 'status', 'created_at', 'distance', 'duration_sec', 'needed_sec', 'mode')->whereNotIn('status', ['7', '9'])->where('id_courier', $user->id)->orderByDesc('id')->get();

        $orders = DB::table('orders')->select('id_allfood', 'type', 'cafe_name', 'to_address', 'status', 'created_at', 'distance', 'duration_sec', 'needed_sec', 'mode')->where('id_courier', $user->id)->orderByDesc('id')->limit(10)->get();

        $result['user'] = $user;
        $result['active_order'] = $active_order;
        $result['orders'] = $orders;

        $result['success'] = true;

        return response()->json($result);
    }

    public function changeDriverStatusSzp(Request $request)
    {
        $pass = $request->input('pass');
        $id_driver = $request->input('id_driver');
        $status = $request->input('status');
        $result['success'] = false;

        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        $user_update = DB::table('users')->where('id', $id_driver)->update(['status' => $status]);

        if ($user_update) $result['success'] = true;

        return response()->json($result);

    }

    public function getDriversGeo(Request $request)
    {
        $pass = $request->input('pass');
        $id_city = $request->input('id_city');
        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        $users_geo = DB::table('users_geo')->join('users', 'users_geo.id_user', '=', 'users.id')
            ->select('users_geo.id', 'users_geo.id_user', 'users_geo.lan', 'users_geo.lon', 'users_geo.type', 'users_geo.updated_at', 'users.name', 'users.surname', 'users.state', 'users.rating')
            ->where('users.status', 3)
            ->orderByDesc('users_geo.id');

        if ($id_city != 0) {
            $users_geo = $users_geo->where('users.id_city', $id_city)->get()->unique('id_user');
        } else {
            $users_geo = $users_geo->get()->unique('id_user');
        }

        $result['users_geo'] = $users_geo;
        $result['success'] = true;

        return response()->json($result);
    }

    public function getOneDriverGeo(Request $request)
    {
        $pass = $request->input('pass');
        $id_driver = $request->input('id_driver');
        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        $data = DB::table('users_geo')->select('lan', 'lon', 'updated_at')->orderByDesc('id')->first();
        if ($data) {
            $result['geo'] = $data;
        }
        $result['success'] = true;
        return response()->json($result);
    }

    public function getDriversForNaznachenieZakaza(Request $request)
    {
        $pass = $request->input('pass');
        $id_allfood = $request->input('id_driver');
        $type = $request->input('type');
        $result['success'] = false;
        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }
        do {
            $order = DB::table('orders')->where('id_allfood', $id_allfood)->where('type',$type)->first();

            if (!$order){
                $result['message'] = 'Заказ не найден';
                break;
            }

            $from_lat = explode("\n", $order->from_geo)[0];
            $from_lon = explode("\n", $order->from_geo)[1];

            $geo_sql = "( 6371000 *
                    ACOS(
                        COS( RADIANS( {$from_lat} ) ) *
                        COS( RADIANS( lan ) ) *
                        COS( RADIANS( lon ) -
                        RADIANS( {$from_lon} ) ) +
                        SIN( RADIANS( {$from_lat} ) ) *
                        SIN( RADIANS( lan) )
                    )
                )
                AS distance";

            $distance = 10000;

            $drivers = DB::table("users_geo")
                ->selectRaw("users.id as id, id_user, users_geo.type, users.state, ".$geo_sql)
                ->join("users", "users_geo.id_user", "=","users.id")
                ->where("users.status",3)
                ->where("users_geo.updated_at",">", date("Y-m-d H:i:s",time()-3600))
                ->whereNotIn('users.state', [0,3,4])
                ->having("distance", "<",$distance)
                ->orderByDesc("users.rating")
                ->get();

            $result['drivers'] = $drivers;
            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }

    public function naznachitZakaz(Request $request)
    {
        $pass = $request->input('pass');
        $id_driver = $request->input('id_driver');
        $id_order = $request->input('id_order');

        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }


    }

    public function getCommentsForSzp(Request $request){

    }

}
