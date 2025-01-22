<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function index()
    {
        return view('web.quote.index');
    }

    public function show($quote_id)
    {
        $quote = Quote::where('quote_id', $quote_id)->first();
        return view('web.quote.show', compact('quote'));
    }
}
