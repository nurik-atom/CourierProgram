<?php

namespace App\Http\Controllers\Slots;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlotsBackController extends Controller
{
    public function canSubsToSlotByID($id_slot){
        $result['success'] = false;
        $result['message'] = '';

        do{
            $slot = DB::table('slots')->find($id_slot);
            if (!$slot){
                $result['message'] = 'Слот не найден';
                break;
            }
            if ($slot->status == 0){
                $result['message'] = 'Слот не активен';
                break;
            }

            $count_slot_drivers = DB::table('slots_users')->where('id_slot', $id_slot)->count();

            if ($slot->kol <= $count_slot_drivers){
                $result['message'] = 'Слот переполнен';
                break;
            }

            $result['success'] = true;
        }while(false);

        return $result;
    }

    public static function getArraySlotsPluckDayFromToday($id_city){
        $slots_sql = DB::table('slots')
            ->where('date_day', '>=', date('Y-m-d'))
            ->where('id_city', $id_city)
            ->get();

        $slots_sql_count = DB::table('slots_users')
                            ->selectRaw('COUNT(*) as kol, id_slot')
                            ->whereIn('id_slot', $slots_sql->pluck('id'))
                            ->groupBy('id_slot')
                            ->pluck('kol', 'id_slot');

        $slots_sql_2 = array();

        foreach ($slots_sql as $k=>$s){
            $slots_sql_2['d_'.$s->date_day.'_'.$s->hour] = $s;
            $slots_sql_2['d_'.$s->date_day.'_'.$s->hour]->sub_users_kol = $slots_sql_count[$s->id] ?? 0;
        }
        return $slots_sql_2;
    }

    public static function getUserSubSlotsPluckDayFromToday($id_user){
        $slots_sql = DB::table('slots_users', 'su')
            ->leftJoin('slots', 'su.id_slot', '=', 'slots.id')
            ->select('slots.date_day','slots.hour', 'slots.kef', 'su.status', 'su.prichina_otmena')
            ->where('slots.date_day', '>=', date('Y-m-d'))
            ->where('su.id_user', $id_user)
            ->get();

        $slots_sql_2 = array();
        foreach ($slots_sql as $k=>$s){
            $slots_sql_2['user_'.$s->date_day.'_'.$s->hour] = $s;
        }

        return $slots_sql_2;
    }

    public static function getStatussSlots($sql_slots, $user_slots){
        /* Статусы
         * 0: Не активен
         * 1: Активен и доступно для брони
         * 2: Активен но заполнен, не доступно для брони
         * 3: Юзер уже забронировал этот слот активен
         * 4: Юзер забронировал но отменил слот
         * 5: Юзер успешно выполнил этот слот
         * 6: Юзер опаздал на этот слот, пропустил
         * */

        $time_proverka = 0;
        $slots_statuss = array();
        if ($sql_slots){
            foreach ($sql_slots as $key => $ss){
                $key1 = 'user_'.$ss->date_day.'_'.$ss->hour;

                $time_proverka = strtotime($ss->date_day.' 00:00:01') + $ss->hour*3600;
                $slots_statuss[$ss->id] = 999;

                if (empty($user_slots[$key1])){
                    //!Юзер не подписан 0, 1 или 2

                    $slots_statuss[$ss->id] = ($time_proverka < time() ? 0 : $ss->status);

                    if ($slots_statuss[$ss->id] == 1 && $ss->kol <= $ss->sub_users_kol) {
                        $slots_statuss[$ss->id] = 2;
                    }
                }else{
                    $slots_statuss[$ss->id] = $user_slots[$key1]->status;
                }
            }
        }

        return $slots_statuss;
    }


}
