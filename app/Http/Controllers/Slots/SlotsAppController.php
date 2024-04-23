<?php

namespace App\Http\Controllers\Slots;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UserController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlotsAppController extends Controller
{
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
                ]);

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
                ->update(['status'   => 0, 'prichina_otmena'=> $prichina]);

            if ($update){
                $result['success'] = true;
            }
        }while(false);

        return response()->json($result);
    }
}
