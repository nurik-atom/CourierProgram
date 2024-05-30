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
                })->orderByDesc("id")->get();

            $ids_notif = $notifications->pluck("id")->toArray();

            $notif_opens_ids = DB::table("notif_open")->where("id_user",$user->id)->whereIn("id_notif",$ids_notif)->groupBy('id_notif')->pluck("id_notif")->toArray();

            foreach ($notifications as $key => $n){
                $id = $n->id;
                $notifications[$key]->new = !in_array($id, $notif_opens_ids);
            }

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

            if (!$notif) {
                $result['message'] = 'Уведомление не найдено';
                break;
            }

            $new_count = $notif->howmany_open + 1;
            DB::table("notifications")->where("id", $id_notif)->update(["howmany_open" => $new_count]);

            DB::table("notif_open")->insert([
                "id_user" => $user->id,
                "id_notif" => $notif->id,
                "created_at" => Carbon::now(),
                "updated_at" => Carbon::now()
            ]);


            $result['notif'] = NotifResource::collection([$notif])[0];
            $result['success'] = true;
        } while (false);
        return response()->json($result);
    }

    public function sendNotification(Request $request)
    {
        $key = $request->input("key");
        $id_city = $request->input("id_city");
        $id_user = $request->input("id_user");
        $name = $request->input("name");
        $short_text = $request->input("short_text");
        $full_text = $request->input("full_text");
        $actual_day = $request->input("actual_day");

        $result['success'] = false;
        do {
            if ($key != env("ALLFOOD_KEY")) {
                $result['message'] = 'Ключ не правильный';
                break;
            }
            if (!$name || !$short_text || !$full_text || !$actual_day) {
                $result['message'] = 'Данные не полные';
                break;
            }

            DB::table("notifications")->insert([
                "id_user" => $id_user,
                "id_city" => $id_city,
                "name" => $name,
                "short_text" => $short_text,
                "full_text" => $full_text,
                "actual_time" => Carbon::now()->addDay($actual_day),
                "created_at"=>Carbon::now(),
                "updated_at"=>Carbon::now()
            ]);

            if (!$id_city && !$id_user){
                $type = 2;
                $to = "/topics/all";
            }elseif ($id_user == null){
                $type = 1;
                $to = DB::table("users")->where("id_city", $id_city)->pluck("token");
            }else{
                $type = 1;
                $to = DB::table("users")->where("id", $id_user)->pluck("token");
            }

            $result['pusk4h'] = PushController::sendNotification($type, $to, $name, $short_text);
            $result['success'] = true;
        } while (false);
        return response()->json($result);
    }

    public function getNotifMessage(Request $request){
        $password = $request->input("password");
        $count_req = $request->input("count_req");
        $result['success'] = false;
        $take = 10;
        $skip = $count_req * $take;
        do {
            $user = UserController::getUser($password);
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $notifications = DB::table('notifications')
                ->select('id', 'name', 'short_text', 'created_at')
                ->where(function ($query) {
                    $query->where('id_user', 1)
                        ->orWhereNull('id_user');
                })
                ->where(function ($query) {
                    $query->where('id_city', 1)
                        ->orWhereNull('id_city');
                })
                ->take($take)->skip($skip)
                ->orderByDesc("id")
                ->get();

            if ($notifications) {
                foreach ($notifications as $n) {
                    $n->date = Carbon::createFromFormat('Y-m-d H:i:s', $n->created_at)
                        ->locale("ru_RU")->isoFormat('LLL');
                    $result['notifs'][] = $n;
                }
            }

            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }
}
