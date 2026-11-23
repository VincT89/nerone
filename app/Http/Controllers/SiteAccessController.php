<?php

namespace App\Http\Controllers;

use App\Services\SiteAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiteAccessController extends Controller
{
    public function enter(Request $request, SiteAccess $access)
    {
        $data = $request->validate([
            'password'=>['required', 'string', 'max:256'],
        ], [
            'password.required'=>'Inserisci la password.',
            'password.max'=>'Inserisci una password valida.',
        ]);

        if (!$access->unlock($request, $data['password'])) {
            throw ValidationException::withMessages(['password'=>'Password non corretta.']);
        }

        return redirect()->route('home')->header('Cache-Control', 'no-store');
    }
}
