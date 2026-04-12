<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model {
    public $timestamps = false;
    protected $table = 'activity_log';
    protected $fillable = ['user_id', 'user_name', 'action', 'entity_type', 'entity_name', 'details'];
    protected $casts = ['created_at' => 'datetime'];

    public static function log(string $action, string $entityType, ?string $entityName = null, ?string $details = null): void {
        $user = auth()->user();
        self::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'system',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_name' => $entityName,
            'details' => $details,
        ]);
    }
}
