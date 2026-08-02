<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Attributi assegnabili tramite mass assignment.
     *
     * NOTA:
     * Il campo "role" è stato volutamente rimosso per evitare
     * escalation di privilegi accidentali.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'bio',
        'photo',
        'twitter',
        'linkedin',
    ];

    /**
     * Attributi nascosti nelle serializzazioni.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Cast automatici.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Articoli dell'utente.
     */
    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Editor o amministratore.
     */
    public function isEditor(): bool
    {
        return in_array($this->role, ['editor', 'admin'], true);
    }

    /**
     * Collaboratore.
     */
    public function isAuthor(): bool
    {
        return $this->role === 'author';
    }

    /**
     * Accesso al pannello amministrativo.
     */
    public function canAccessAdmin(): bool
    {
        return $this->isEditor();
    }

    /**
     * Accesso all'area redazione.
     */
    public function canAccessRedazione(): bool
    {
        return in_array($this->role, ['editor', 'admin', 'author'], true);
    }
}