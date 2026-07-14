<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Competition extends Model {
    protected $fillable = ['name', 'year', 'location', 'is_active', 'is_locked', 'gender_policy', 'reserves'];
    protected $casts = ['is_active' => 'boolean', 'is_locked' => 'boolean', 'gender_policy' => 'array', 'reserves' => 'array'];

    public function teams() { return $this->belongsToMany(Team::class, 'competition_team'); }
    public function athletes() { return $this->belongsToMany(Athlete::class, 'competition_athlete'); }
    public function races() { return $this->hasMany(Race::class); }

    /** True when the given competition id exists and is locked (read-only). */
    public static function isLockedById($id): bool {
        return $id ? (bool) static::where('id', $id)->value('is_locked') : false;
    }

    /** Abort the request with 423 Locked when the given competition is locked. */
    public static function guardLocked($id): void {
        if (static::isLockedById($id)) {
            abort(423, 'Competition is locked and cannot be changed. Unlock it first.');
        }
    }
}
