<?php

namespace App\Http\Controllers\Slots;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlotsSzpController extends Controller
{
    public function updateOrCreateSlotsFromSzp(Request $request){
        $key        = $request->input("key");
        $date_day   = $request->input("date_day");
        $hour       = $request->input("hour");
        $kol        = $request->input("kol");
        $id_admin   = $request->input("id_admin");
        $id_city    = $request->input("id_city");
        $kef        = $request->input("kef");
        $result['success'] = false;

        if (!$key || $key != env("ALLFOOD_KEY")) exit("Error key");

        do{

            $updateOrInsert = DB::table('slots')->updateOrInsert(
                [   'date_day'  => $date_day,
                    'hour'      => $hour
                ],
                [
                'status'    => 1,
                'kol'       => $kol,
                'id_admin'  => $id_admin,
                'id_city'   => $id_city,
                'kef'       => $kef,
                'updated_at' => Carbon::now(),
            ]);

            if (!$updateOrInsert) break;

            $id_new_slot = DB::table('slots')->select('id')
                ->where('date_day',$date_day)
                ->where('hour', $hour)
                ->first();

            $result['success'] = true;
            $result['id_new_slot'] = $id_new_slot->id;

        }while(false);

        return response()->json($result);
    }

    public function getSlotsFromSzp(Request $request){
        $key  = $request->input("key");
        $from = $request->input("from");
        $to   = $request->input("to");
        $id_city   = $request->input("id_city");
        $result['success'] = false;

        do{

            $slots = DB::table('slots')
                ->where('date_day', '>=', $from)
                ->where('date_day', '<=', $to)
                ->where('id_city', $id_city)
                ->get();

            $result['slots_ids'] = $slots->pluck('id');
//            $slots_user =

            if (!$slots) break;

            $result['success'] = true;
            $result['slots'] = $slots;
        }while(false);

        return response()->json($result);
    }

    public function unSubSlotsFromSzp(Request $request){

    }
}
