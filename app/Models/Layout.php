<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Layout extends Model {
    protected $fillable = ['race_id', 'drummer_id', 'helm_id', 'left_seats', 'right_seats', 'reserves'];
    protected $casts = ['left_seats' => 'array', 'right_seats' => 'array', 'reserves' => 'array'];
    public function race() { return $this->belongsTo(Race::class); }
}
