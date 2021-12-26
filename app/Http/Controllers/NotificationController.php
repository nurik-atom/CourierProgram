<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotifResource;
use App\Http\Resources\NotifShortResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{

    public function getCountNewNotifs(Request $request)
    {
        $password = $request->input("password");
        $result['success'] = false;

        do {
            $user = UserController::getUser($password);
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }
            $notifications_id = DB::table("notifications")
                ->where("actual_time", ">", Carbon::now())
                ->where(function ($query) use ($user) {
                    $query->where("id_user", $user->id)
                        ->orWhereNull("id_user");
                })
                ->where(function ($query) use ($user) {
                    $query->where("id_city", $user->id_city)
                        ->orWhereNull("id_city");
                })->pluck("id");

            $opens_count = DB::table("notif_open")
                ->where("id_user", $user->id)
                ->whereIn("id_notif", $notifications_id)
                ->count(DB::raw('DISTINCT id_notif'));

            $result['count_new_notifs'] = count($notifications_id) - $opens_count;
            $result['success'] = true;
        } while (false);
        return response()->json($result);
    }

    public function getNotifications(Request $request)
    {
        $password = $request->input("password");
        $result['success'] = false;
        do {
            $result['notifs'] = array();
            $user = UserController::getUser($password);
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $notifications = DB::table("notifications")
                ->select("id", "created_at", "name", "short_text")
                ->where("actual_time", ">", Carbon::now())
                ->where(function ($query) use ($user) {
                    $query->where("id_user", $user->id)
                        ->orWhereNull("id_user");
                })
                ->where(function ($query) use ($user) {
                    $query->where("id_city", $user->id_city)
                        ->orWhereNull("id_city");
                })->get();

            if ($notifications) {
                $result['notifs'] = NotifShortResource::collection($notifications);
            }
            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }

    public function openNotification(Request $request)
    {
        $password = $request->input("password");
        $id_notif = $request->input("id_notif");
        $result['success'] = false;

        do {
            $user = UserController::getUser($password);
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }
            $notif = DB::table("notifications")->find($id_notif);

            if (!$notif){
                $result['message'] = 'Уведомление не найдено';
                break;
            }

            $new_count = $notif->howmany_open+1;
            DB::table("notifications")->where("id", $id_notif)->update(["howmany_open"=>$new_count]);

            DB::table("notif_open")->insert([
                "id_user"=>$user->id,
                "id_notif"=>$notif->id,
                "created_at"=>Carbon::now(),
                "updated_at"=>Carbon::now()
            ]);


            $result['notif'] = NotifResource::collection([$notif])[0];
            $result['success'] = true;
        } while (false);
        return response()->json($result);
    }
}
