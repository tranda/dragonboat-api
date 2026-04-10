<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable {
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = ['name', 'email', 'password', 'role_id', 'athlete_id'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime'];

    public function role() { return $this->belongsTo(Role::class); }
    public function athlete() { return $this->belongsTo(Athlete::class); }
    public function hasRole(string $role): bool { return $this->role?->name === $role; }
    public function isAdmin(): bool { return $this->hasRole('admin'); }
    public function isCoach(): bool { return $this->hasRole('coach'); }
    public function isAthlete(): bool { return $this->hasRole('athlete'); }
}
