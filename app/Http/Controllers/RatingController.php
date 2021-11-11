<?php

namespace App\Http\Controllers;

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
        $user = DB::table("users")->where("password", $password)->first();

        if (!$user) {
            $result['message'] = 'Пользователь не найден';
            $result['success'] = false;
        } else {
            $result['success'] = true;
            $result['calculate_Rate'] = self::calculateRating($user->id);
        }
        return response()->json($result);
    }

    public static function calculateRating($id_courier)
    {
        $avg_star = DB::table("rating")
            ->where("id_courier", $id_courier)
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->avg("star");

        $count_agree = DB::table("order_user")
            ->where("id_user", $id_courier)
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->where("status", 2)
            ->count();

        $count_propusk = DB::table("order_user")
            ->where("id_user", $id_courier)
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->where("status", "!=", 2)->count();

        $count_today = DB::table("order_user")
            ->where("id_user", $id_courier)
            ->where("created_at", ">", Carbon::now()->subHour(5))
            ->where("status", "=", 2)->count();


        if($count_agree)
            $percent_propusk = $count_propusk / $count_agree;
        else
            $percent_propusk = 0;

        $sort_rating = ($avg_star*10)-$percent_propusk-$count_today;

        if ($sort_rating == 0) $sort_rating = 50;


        $result['percent_propusk'] = $percent_propusk;
        $result['$count_propusk'] = $count_propusk;
        $result['$count_agree'] = $count_agree;
        $result['$avg_star'] = round($avg_star,1);
        $result['$sort_rating'] = $sort_rating;
        $result['env'] = env("FIREBASE_AUTH");

        $mes['title'] = 'Nursik Push Barsa ait';
        $mes['body'] = "body";
//        $resposce = PushController::sendDataPush($id_courier, '22', $mes);

//        $result['responce'] = $resposce;
        $update = DB::table("users")
            ->where("id", $id_courier)
            ->update(["rating"=>round($avg_star,1), "sort_rating"=>$sort_rating]);

        return $result;

    }


}
