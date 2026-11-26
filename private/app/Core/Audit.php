<?php
declare(strict_types=1);

namespace BBA\Core;

final class Audit
{
    public static function log(
        string $action,
        ?int $actorId = null,
        ?int $projectId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        array $meta = []
    ): void {
        try {
            Db::insert('audit_log', [
                'actor_id' => $actorId,
                'project_id' => $projectId,
                'action' => mb_substr($action, 0, 60),
                'target_type' => $targetType,
                'target_id' => $targetId,
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'meta_json' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            error_log('audit failed: ' . $e->getMessage());
        }
    }
}
