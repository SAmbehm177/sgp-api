<?php

namespace App\Http\Controllers;

use App\Models\Fonction;

class FonctionController extends Controller
{
    public function index()
    {
        return Fonction::orderBy('libelle')->get();
    }
}
