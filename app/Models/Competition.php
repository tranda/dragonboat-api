<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Competition extends Model {
    protected $fillable = ['name', 'year', 'location', 'is_active', 'gender_policy', 'reserves'];
    protected $casts = ['is_active' => 'boolean', 'gender_policy' => 'array', 'reserves' => 'array'];

    public function teams() { return $this->belongsToMany(Team::class, 'competition_team'); }
    public function athletes() { return $this->belongsToMany(Athlete::class, 'competition_athlete'); }
    public function races() { return $this->hasMany(Race::class); }
}
