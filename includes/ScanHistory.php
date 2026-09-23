<?php

declare(strict_types=1);

require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/ProductResult.php';

/**
 * Optional MySQL scan history.
 */
final class ScanHistory
{
    private static ?PDO $pdo = null;

    public static function enabled(): bool
    {
        return (bool) AppConfig::get('db_enabled', false);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            AppConfig::get('db_host', '127.0.0.1'),
            AppConfig::get('db_name', 'ai_ar_scanner'),
            AppConfig::get('db_charset', 'utf8mb4')
        );

        self::$pdo = new PDO(
            $dsn,
            (string) AppConfig::get('db_user', 'root'),
            (string) AppConfig::get('db_pass', ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        return self::$pdo;
    }

    public static function insert(ProductResult $result): void
    {
        if (!self::enabled()) {
            return;
        }

        $sql = 'INSERT INTO scan_history
            (object_label, product_name, manufacturer, specification, description, confidence, provider, created_at)
            VALUES
            (:object_label, :product_name, :manufacturer, :specification, :description, :confidence, :provider, NOW())';

        $stmt = self::pdo()->prepare($sql);
        $stmt->execute([
            ':object_label' => $result->objectLabel,
            ':product_name' => $result->productName,
            ':manufacturer' => $result->manufacturer,
            ':specification' => $result->specification,
            ':description' => $result->description,
            ':confidence' => $result->confidence,
            ':provider' => $result->provider,
        ]);
    }

    /**
     * @return array{items:array,total:int,page:int,perPage:int}
     */
    public static function list(string $search = '', int $page = 1, int $perPage = 20): array
    {
        if (!self::enabled()) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage];
        }

        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE product_name LIKE :q OR manufacturer LIKE :q OR object_label LIKE :q';
            $params[':q'] = '%' . $search . '%';
        }

        $countStmt = self::pdo()->prepare("SELECT COUNT(*) FROM scan_history {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT id, object_label, product_name, manufacturer, specification, description,
                       confidence, provider, created_at
                FROM scan_history {$where}
                ORDER BY id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public static function clear(): void
    {
        if (!self::enabled()) {
            return;
        }
        self::pdo()->exec('DELETE FROM scan_history');
    }

    public static function find(int $id): ?array
    {
        if (!self::enabled()) {
            return null;
        }
        $stmt = self::pdo()->prepare('SELECT * FROM scan_history WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
