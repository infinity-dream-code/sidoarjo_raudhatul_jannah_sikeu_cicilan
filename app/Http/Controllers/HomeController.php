<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.index');
    }
}
