<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DailyPoemService;

class IndexController extends Controller
{
    public function index(DailyPoemService $dailyPoemService)
    {
        $daily = $dailyPoemService->today();

        return view('web.index', compact('daily'));
    }
}
