<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegionalHelpController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        return view('regional.help', [
            'text' => (string) $request->query('text', ''),
        ]);
    }

    private function isAuthorized(Request $request): bool
    {
        return (string) ($request->session()->get('user_code') ?? '') !== '';
    }
}

