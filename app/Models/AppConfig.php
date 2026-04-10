<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AppConfig extends Model {
    protected $table = 'app_config';
    protected $fillable = ['competition_year', 'gender_policy', 'age_rules'];
    protected $casts = ['gender_policy' => 'array', 'age_rules' => 'array'];
}
