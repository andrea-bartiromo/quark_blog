<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;

class VerificationController extends Controller
{
    public function index()
    {
        return view('admin.verification', [
            'articles' => Article::latest('updated_at')->with('author')->get(),
        ]);
    }
}
