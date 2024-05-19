<?php

namespace App\Http\Controllers\Slots;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UserController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlotsAppController extends Controller
{

    function getAllSlotsFromApp(Request $request){
        $result['success'] = false;
        do{
            $user = UserController::getUser($request->input('password'));

            if (!$user){
                $result['auth'] = false;
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $slotsDB = SlotsBackController::getArraySlotsPluckDayFromToday($user->id_city) ?? array();

            $slotsUserDB = SlotsBackController::getUserSubSlotsPluckDayFromToday($user->id);

            $slotsStatuss = SlotsBackController::getStatussSlots($slotsDB, $slotsUserDB);

//            $result['$slotsUserDB'] = $slotsUserDB;
//            $result['$slotsDB'] = $slotsDB;
//            $result['$slotsStatuss'] = $slotsStatuss;
            $end = 7;
            $current_day = Carbon::now();
            $all_slots = array();
            $key = 0;
            for ($i = 0; $i<$end; $i++){
                if($i != 0){
                    $current_day->add(1, 'day');
                }

                $all_slots[$key]['name_day'] = $current_day->isoFormat('dddd');
                $all_slots[$key]['date_day'] = $current_day->isoFormat('DD.MM');
                $all_slots[$key]['status'] = $i<3 ? 1: 0;
                $key2 = 0;
                $ds = array();
                for ($j = 0; $j < 24; $j++){

                    $ds[$key2]['name_day_slot'] = $j.':00 - '.($j+1).':00';

                    $sm = false;
                    $s_key = 'd_'.$current_day->isoFormat('YYYY-MM-DD').'_'.$j;
                    if (!empty($slotsDB[$s_key])){
                        $sm = $slotsDB[$s_key];
                    }

                    $ds[$key2]['id_slot'] = $sm->id ?? 0;
                    $ds[$key2]['type']    = $sm->type ?? 0;
                    $ds[$key2]['kol']     = $sm->kol ?? 0;
                    $ds[$key2]['kef']     = $sm->kef ?? 0;
                    $ds[$key2]['status']  = 0;

                    if($ds[$key2]['id_slot']){
                        $ds[$key2]['status'] = $slotsStatuss[$ds[$key2]['id_slot']];
                    }


                    $key2++;
                }

                $all_slots[$key]['slots'] = $ds;
                $key++;
            }

            $result['days'] = $all_slots;
            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    function subsToSlotFromApp(Request $request){
        $id_slot = $request->input('id_slot');
        $result['success'] = false;
        do{
            $user = UserController::getUser($request->input('password'));

            if (!$user){
                $result['auth'] = false;
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $slot = DB::table('slots')->where('id', $id_slot)->first();

            if (!$slot){
                $result['message'] = 'Слот не найден';
                break;
            }

            $slot_followers = DB::table('slots_users')->where('id_slot', $slot->id)
                ->where('status', 1)->count();

            if ($slot->kol <= $slot_followers){
                $result['message'] = 'Слот переполнен';
                break;
            }
            $updateOrInsert = DB::table('slots_users')->updateOrInsert(
                [   'id_slot'  => $id_slot,
                    'id_user'  => $user->id
                ],[
                    'status'   => 2
                ]
            );

            if ($updateOrInsert){
                $result['success'] = true;
            }
        }while(false);

        return response()->json($result);
    }

    function unSubsFromSlotFromApp(Request $request){
        $id_slot = $request->input('id_slot');
        $prichina = $request->input('prichina');

        $result['success'] = false;
        do{
            $user = UserController::getUser($request->input('password'));

            if (!$user){
                $result['auth'] = false;
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $slots_zapis = DB::table('slots_users')
                ->where('id_slot', $id_slot)
                ->where('id_user', $user->id)
                ->first();

            if (!$slots_zapis){
                $result['message'] = 'Запись не найден';
                break;
            }

            $update = DB::table('slots_users')
                ->where('id', $slots_zapis->id)
                ->update(['status'   => 4, 'prichina_otmena'=> $prichina]);

            if ($update){
                $result['success'] = true;
            }
        }while(false);

        return response()->json($result);
    }
}
