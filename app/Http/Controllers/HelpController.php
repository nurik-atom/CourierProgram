<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HelpController extends Controller
{
    public function getAllHelpPages(Request $request){
        $result['whatsapp'] = 'https://wa.me/77089222820';
        $result['zvonok'] = '+77089222820';
        $result['pages'] = DB::table("help_pages")->orderBy("sort")->get();
        return response()->json($result);
    }
}
