<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Athlete extends Model {
    protected $fillable = ['name', 'weight', 'gender', 'year_of_birth', 'is_bcp', 'is_removed'];
    protected $casts = ['weight' => 'float', 'is_bcp' => 'boolean', 'is_removed' => 'boolean'];
}
