<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HelpController extends Controller
{
    public function getAllHelpPages(Request $request){
        $result['whatsapp'] = '77089222820';
        $result['zvonok'] = '77081139347';
        $result['pages'] = DB::table("help_pages")->orderBy("sort")->get();
        return response()->json($result);
    }

    public function getHelpBalancePage(Request $request){
        $id = $request->input("id");

        $result = DB::table("help_balance_pages")
            ->select("id", "name", "icon", "type", "big_text")
            ->where("id", $id)
            ->first();
        return response()->json($result);
    }
}
