<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'bio', 'photo', 'role', 'twitter', 'linkedin',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function isEditor(): bool
    {
        return in_array($this->role, ['editor', 'admin']);
    }

    public function isAuthor(): bool
    {
        return $this->role === 'author';
    }

    public function canAccessAdmin(): bool
    {
        return $this->isEditor();
    }

    public function canAccessRedazione(): bool
    {
        return in_array($this->role, ['editor', 'admin', 'author']);
    }
}
