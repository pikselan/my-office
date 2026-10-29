<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database\Database;
use App\Core\Logger;
use App\Core\Request;

/**
 * Pencatat perubahan penting.
 *
 * Tidak pernah menyimpan kata sandi, token mentah, atau isi dokumen.
 * Kegagalan pencatatan tidak boleh menggagalkan proses bisnis, karena itu
 * seluruh penulisan dibungkus try/catch.
 */
final class AuditService
{
    /**
     * @param array<string, mixed> $metadata
     */
    public static function log(
        ?int $tenantId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $metadata = [],
        ?int $actorId = null
    ): void {
        try {
            Database::insertRow('audit_logs', [
                'tenant_id'     => $tenantId,
                'actor_user_id' => $actorId ?? Auth::id(),
                'action'        => $action,
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'request_id'    => Request::id(),
                'ip_address'    => self::ip(),
                'metadata_json' => $metadata === [] ? null : json_encode(
                    Logger::scrub($metadata),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                'created_at'    => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Audit log gagal dicatat: ' . $action, ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forTenant(?int $tenantId, int $limit = 25, int $offset = 0): array
    {
        if ($tenantId === null) {
            return Database::select(
                'SELECT a.*, u.name AS actor_name FROM audit_logs a'
                . ' LEFT JOIN users u ON u.id = a.actor_user_id'
                . ' WHERE a.tenant_id IS NULL'
                . ' ORDER BY a.id DESC LIMIT ? OFFSET ?',
                [$limit, $offset]
            );
        }

        return Database::select(
            'SELECT a.*, u.name AS actor_name FROM audit_logs a'
            . ' LEFT JOIN users u ON u.id = a.actor_user_id'
            . ' WHERE a.tenant_id = ?'
            . ' ORDER BY a.id DESC LIMIT ? OFFSET ?',
            [$tenantId, $limit, $offset]
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function decode(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $metadata = $row['metadata_json'] ?? null;
            $rows[$index]['metadata'] = is_string($metadata) ? (json_decode($metadata, true) ?: []) : [];
        }

        return $rows;
    }

    /**
     * Bersihkan log yang lebih tua dari kebijakan retensi.
     */
    public static function prune(): int
    {
        $days = Config::int('security.log_retention_days', 365);
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * 86400));

        return Database::statement('DELETE FROM audit_logs WHERE created_at < ?', [$cutoff]);
    }

    private static function ip(): ?string
    {
        $request = \App\Core\App::isBooted() ? \App\Core\App::instance()->currentRequest() : null;
        return $request !== null ? $request->ip() : null;
    }
}
