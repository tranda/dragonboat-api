<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Race extends Model {
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'name', 'boat_type', 'num_rows', 'distance', 'gender_category', 'age_category', 'category', 'scheduled_at', 'stage', 'display_order', 'competition_id', 'team_id'];
    protected $casts = ['scheduled_at' => 'datetime'];
    public function layout() { return $this->hasOne(Layout::class); }
}
