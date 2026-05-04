<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        // Honeypot anti-spam
        if ($request->input('website') !== '') {
            return response()->json(['ok' => true]);
        }

        $validated = $request->validate([
            'article_id' => 'required|exists:articles,id',
            'name'       => 'required|max:80',
            'email'      => 'required|email',
            'body'       => 'required|min:10|max:1500',
        ]);

        Comment::create($validated + ['status' => 'pending']);

        return response()->json([
            'ok'      => true,
            'message' => 'Commento inviato. Sarà pubblicato dopo la moderazione.',
        ]);
    }
}
