<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Team extends Model {
    protected $fillable = ['name', 'country', 'type'];

    public function competitions() { return $this->belongsToMany(Competition::class, 'competition_team'); }
    public function users() { return $this->hasMany(User::class); }
    public function athletes() { return $this->hasMany(Athlete::class); }
}
