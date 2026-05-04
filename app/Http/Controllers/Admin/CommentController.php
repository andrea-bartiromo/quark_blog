<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;

class CommentController extends Controller
{
    public function index()
    {
        return view('admin.comments', [
            'comments' => Comment::latest()->with('article')->get(),
        ]);
    }

    public function approve(Comment $comment)
    {
        $comment->update(['status' => 'approved']);

        return back()->with('success', 'Commento approvato.');
    }

    public function destroy(Comment $comment)
    {
        $comment->delete();

        return back()->with('success', 'Commento eliminato.');
    }
}
