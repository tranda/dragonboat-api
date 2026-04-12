<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Competition extends Model {
    protected $fillable = ['name', 'year', 'location', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function teams() { return $this->belongsToMany(Team::class, 'competition_team'); }
    public function races() { return $this->hasMany(Race::class); }
}
