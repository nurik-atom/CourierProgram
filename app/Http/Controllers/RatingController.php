<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PushController;

class RatingController extends Controller
{
    public function addComment(Request $request)
    {
        $id_allfood = $request->input("id_allfood");
        $star = $request->input("star");
        $comment = $request->input("comment");
        $user_tel = $request->input("user_tel");
        $result['success'] = false;
        do {
            $order = DB::table("orders")->where("id_allfood", $id_allfood)->first();
            if (!$order) {
                $result['message'] = 'Заказ не найден';
                break;
            }

            $id_courier = $order->id_courier;

            $add = DB::table("rating")->insert([
                "id_allfood" => $id_allfood,
                "star" => $star,
                "comment" => $comment,
                "user_tel" => $user_tel,
                "id_courier" => $id_courier,
                "created_at" => Carbon::now(),
                "updated_at" => Carbon::now(),
            ]);

            if (!$add) {
                $result['message'] = 'Ошибка при добавление';
                break;
            }
            $result['success'] = true;
            self::calculateRating($id_courier);
            return response()->json($result);
        } while (false);
    }

    public function getRatingUser(Request $request)
    {
        $password = $request->input("password");
        $message = 'Не передан параметр';
        $array = [];

        do{
            $array[] = $password;
            if (!$this->returnMessage($array, $message,1)){
                $result['message'] = $message;
                break;
            }
            $user = DB::table("users")->where("password", $password)->first();
            if (!$user){
                $result['message'] = $message;
                break;
            }
            $result['success'] = true;
            $result['rating_info'] = self::calculateRating($user->id);
        }while(false);
        /*if (!$user) {
            $result['message'] = 'Пользователь не найден';
            $result['success'] = false;
        } else {
            $result['success'] = true;
            $result['calculate_Rate'] = self::calculateRating($user->id);
        }*/
        return response()->json($result);
    }

    public static function calculateRating($id_courier)
    {
        $avg_star = DB::table("rating")
            ->where("id_courier", $id_courier)
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->avg("star");


        $count_all_offer = DB::table("order_user")
            ->where("id_user", $id_courier)
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->groupBy("id_order")
            ->count();

        $count_propusk = DB::table("order_user")
            ->where("id_user", $id_courier)
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->whereIn("status", [11,12])
            ->count();

        $count_orders = DB::table("orders")
            ->where("id_courier", $id_courier)
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->count();

        $count_during_orders = DB::table("orders")
            ->where("id_courier", $id_courier)
            ->where("needed_sec", ">=", "duration_sec")
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->count();

        $count_today = DB::table("order_user")
            ->where("id_user", $id_courier)
            ->where("created_at", ">", Carbon::now()->subHour(5))
            ->where("status", "=", 7)->count();


        $percent_success = 1 - $count_propusk/ ($count_all_offer ?: 1);
        $percent_during  = $count_during_orders / ($count_orders ?: 1);

        $rating = $avg_star * $percent_success * $percent_during;

        $sort_rating = ($rating * 10) - $count_today;

        if ($sort_rating == 0) $sort_rating = 50;


        $result['rating'] = round($rating, 1);
        $result['percent_success'] = (int) 100 * $percent_success;
        $result['percent_during_orders'] = (int) 100 * $percent_during;
        $result['all_orders'] =  $count_orders;
        $result['days'] =  '15 дней';

        DB::table("users")
            ->where("id", $id_courier)
            ->update(["rating"=>round($rating,1), "sort_rating"=>$sort_rating]);

        return $result;

    }

    public static function returnMessage($array,$message,$count){
        return !(count($array) !== $count);
    }


}
