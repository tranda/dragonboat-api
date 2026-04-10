<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BenchFactor extends Model {
    public $timestamps = false;
    protected $fillable = ['boat_type', 'factors'];
    protected $casts = ['factors' => 'array'];
}
