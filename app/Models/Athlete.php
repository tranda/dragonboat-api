<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Athlete extends Model {
    protected $fillable = ['name', 'weight', 'gender', 'year_of_birth', 'is_bcp', 'preferred_side', 'is_helm', 'is_drummer', 'edbf_id', 'notes', 'is_removed', 'team_id'];
    protected $casts = ['weight' => 'float', 'is_bcp' => 'boolean', 'is_helm' => 'boolean', 'is_drummer' => 'boolean', 'is_removed' => 'boolean'];
}
