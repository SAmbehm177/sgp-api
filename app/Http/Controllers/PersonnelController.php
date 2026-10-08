<?php

namespace App\Http\Controllers;

use App\Models\Personnel;

class PersonnelController extends Controller
{
    public function index()
    {
        return Personnel::select('id', 'nom', 'prenom', 'poste')
            ->orderBy('nom')
            ->paginate(30);
    }
}
