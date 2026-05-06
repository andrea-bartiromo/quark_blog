<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;
    protected $table   = 'activity_log';
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'subject_title', 'ip', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, string $subjectType = null, int $subjectId = null, string $subjectTitle = null): void
    {
        static::create([
            'user_id'       => auth()->id(),
            'action'        => $action,
            'subject_type'  => $subjectType,
            'subject_id'    => $subjectId,
            'subject_title' => $subjectTitle,
            'ip'            => request()->ip(),
            'created_at'    => now(),
        ]);
    }
}