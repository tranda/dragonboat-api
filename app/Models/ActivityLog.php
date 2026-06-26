<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model {
    public $timestamps = false;
    protected $table = 'activity_log';
    protected $fillable = ['user_id', 'user_name', 'team_id', 'competition_id', 'action', 'entity_type', 'entity_name', 'entity_id', 'details', 'before_state', 'after_state', 'is_undone'];
    protected $casts = ['created_at' => 'datetime', 'before_state' => 'array', 'after_state' => 'array', 'is_undone' => 'boolean'];

    public static function log(string $action, string $entityType, ?string $entityName = null, ?string $details = null, ?string $entityId = null, ?array $beforeState = null, ?array $afterState = null, ?int $competitionId = null, ?int $teamId = null): self {
        $user = auth()->user();
        // Hybrid scoping: prefer the explicit scope of the entity being changed,
        // otherwise fall back to the current request context (actor's team + the
        // active competition header).
        $teamId = $teamId ?? $user?->team_id;
        $competitionId = $competitionId ?? (request()->header('X-Competition-Id') ?: null);
        return self::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'system',
            'team_id' => $teamId,
            'competition_id' => $competitionId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_name' => $entityName,
            'entity_id' => $entityId,
            'details' => $details,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'is_undone' => false,
        ]);
    }
}
