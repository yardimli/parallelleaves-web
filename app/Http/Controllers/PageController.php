<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class PageController extends Controller
{
    public function show(string $page = 'index')
    {
        if (Auth::check() && in_array($page, ['login', 'register'], true)) {
            return redirect('/dashboard');
        }

        return view("pages.$page");
    }
}
