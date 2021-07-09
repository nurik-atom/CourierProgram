<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $name = $request->input('name');
        $surname = $request->input('surname');
        $phone = $request->input('phone');
        $type = $request->input('type');
        $id_cafe = $request->input('id_cafe');
        $password = $request->input('password');
        $result['success'] = false;

        do {
            if (!$name) {
                $result['message'] = 'Не передан имя';
                break;
            }
            if (!$surname) {
                $result['message'] = 'Не передан фамилия';
                break;
            }
            if (!$phone) {
                $result['message'] = 'Не передан Телефон';
                break;
            }
            if (!$type) {
                $result['message'] = 'Не передан тип';
                break;
            }
            if (!$password) {
                $result['message'] = 'Не передан пароль';
                break;
            }

            $user = DB::table('users')->select('id')->where('phone', $phone)->first();
            if (!is_null($user)) {
                $result['message'] = 'Этот пользователь уже зарегистрован';
                break;
            }
            DB::beginTransaction();
            $userID = DB::table('users')->insertGetId([
                'name' => $name,
                'surname' => $surname,
                'phone' => $phone,
                'status' => 1,
                'type' => $type,
                'id_cafe' => $id_cafe,
                'password' => bcrypt($password),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            if (!$userID) {
                DB::rollBack();
                $result['message'] = 'Попробуйте позже';
                break;
            }

            DB::commit();
            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }
}
