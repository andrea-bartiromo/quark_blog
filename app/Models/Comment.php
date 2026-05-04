<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = ['article_id', 'name', 'email', 'body', 'status'];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }
}
