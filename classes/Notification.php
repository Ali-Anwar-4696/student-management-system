<?php

declare(strict_types=1);

class Notification
{
    private PDO $pdo;

    /**
     * Constructor
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * =========================================================
     * CREATE NOTIFICATION
     * =========================================================
     *
     * General notification create karne ke liye.
     *
     * Supported fields:
     * - user_id
     * - target_role
     * - title
     * - message
     * - type
     * - priority
     * - action_url
     * - created_by
     */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO notifications
            (
                user_id,
                target_role,
                title,
                message,
                type,
                priority,
                action_url,
                created_by
            )
            VALUES
            (
                :user_id,
                :target_role,
                :title,
                :message,
                :type,
                :priority,
                :action_url,
                :created_by
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':user_id' => !empty($data['user_id'])
                ? (int) $data['user_id']
                : null,

            ':target_role' => $data['target_role'] ?? 'all',

            ':title' => trim((string) ($data['title'] ?? '')),

            ':message' => trim((string) ($data['message'] ?? '')),

            ':type' => $data['type'] ?? 'general',

            ':priority' => $data['priority'] ?? 'normal',

            ':action_url' => !empty($data['action_url'])
                ? trim((string) $data['action_url'])
                : null,

            ':created_by' => !empty($data['created_by'])
                ? (int) $data['created_by']
                : null,
        ]);
    }

    /**
     * =========================================================
     * SEND TO ONE USER
     * =========================================================
     */
    public function sendToUser(
        int $userId,
        string $title,
        string $message,
        string $type = 'general',
        string $priority = 'normal',
        ?string $actionUrl = null,
        ?int $createdBy = null
    ): bool {
        return $this->create([
            'user_id' => $userId,
            'target_role' => 'all',
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'priority' => $priority,
            'action_url' => $actionUrl,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * =========================================================
     * SEND TO ROLE
     * =========================================================
     *
     * Example:
     *
     * sendToRole(
     *     'teacher',
     *     'New Assignment',
     *     'A new assignment has been created.'
     * );
     */
    public function sendToRole(
        string $role,
        string $title,
        string $message,
        string $type = 'general',
        string $priority = 'normal',
        ?string $actionUrl = null,
        ?int $createdBy = null
    ): bool {
        return $this->create([
            'user_id' => null,
            'target_role' => $role,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'priority' => $priority,
            'action_url' => $actionUrl,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * =========================================================
     * BROADCAST TO EVERYONE
     * =========================================================
     */
    public function broadcast(
        string $title,
        string $message,
        string $type = 'announcement',
        string $priority = 'normal',
        ?string $actionUrl = null,
        ?int $createdBy = null
    ): bool {
        return $this->create([
            'user_id' => null,
            'target_role' => 'all',
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'priority' => $priority,
            'action_url' => $actionUrl,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * =========================================================
     * GET USER NOTIFICATIONS
     * =========================================================
     */
    public function forUser(
        int $userId,
        string $role,
        string $status = 'all',
        string $search = ''
    ): array {
        $conditions = [
            "(n.user_id = :user_id OR n.target_role = 'all' OR n.target_role = :role)"
        ];

        $params = [
            ':user_id' => $userId,
            ':role' => $role,
        ];

        /**
         * Read / unread filter
         */
        if ($status === 'read') {
            $conditions[] = 'r.id IS NOT NULL';
        } elseif ($status === 'unread') {
            $conditions[] = 'r.id IS NULL';
        }

        /**
         * Search
         */
        if ($search !== '') {
            $conditions[] = "
                (
                    n.title LIKE :search
                    OR n.message LIKE :search
                )
            ";

            $params[':search'] = '%' . $search . '%';
        }

        $where = implode(' AND ', $conditions);

        $sql = "
            SELECT
                n.*,

                CASE
                    WHEN r.id IS NULL THEN 0
                    ELSE 1
                END AS is_read

            FROM notifications n

            LEFT JOIN notification_reads r
                ON r.notification_id = n.id
                AND r.user_id = :read_user_id

            WHERE {$where}

            ORDER BY n.created_at DESC, n.id DESC

            LIMIT 100
        ";

        $params[':read_user_id'] = $userId;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * =========================================================
     * FIND ONE NOTIFICATION FOR USER
     * =========================================================
     */
    public function findForUser(
        int $notificationId,
        int $userId,
        string $role
    ): ?array {
        $stmt = $this->pdo->prepare("
            SELECT
                n.*,

                CASE
                    WHEN r.id IS NULL THEN 0
                    ELSE 1
                END AS is_read

            FROM notifications n

            LEFT JOIN notification_reads r
                ON r.notification_id = n.id
                AND r.user_id = :read_user_id

            WHERE n.id = :notification_id

              AND (
                    n.user_id = :user_id
                    OR n.target_role = 'all'
                    OR n.target_role = :role
                  )

            LIMIT 1
        ");

        $stmt->execute([
            ':notification_id' => $notificationId,
            ':read_user_id' => $userId,
            ':user_id' => $userId,
            ':role' => $role,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * =========================================================
     * CHECK ACCESS
     * =========================================================
     */
    public function canAccess(
        int $notificationId,
        int $userId,
        string $role
    ): bool {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM notifications
            WHERE id = :notification_id

              AND (
                    user_id = :user_id
                    OR target_role = 'all'
                    OR target_role = :role
                  )

            LIMIT 1
        ");

        $stmt->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId,
            ':role' => $role,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * =========================================================
     * MARK AS READ
     * =========================================================
     */
    public function markRead(
        int $notificationId,
        int $userId
    ): bool {
        $check = $this->pdo->prepare("
            SELECT id
            FROM notification_reads
            WHERE notification_id = :notification_id
              AND user_id = :user_id
            LIMIT 1
        ");

        $check->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId,
        ]);

        if ($check->fetchColumn()) {
            return true;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO notification_reads
            (
                notification_id,
                user_id,
                read_at
            )
            VALUES
            (
                :notification_id,
                :user_id,
                NOW()
            )
        ");

        return $stmt->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId,
        ]);
    }

    /**
     * =========================================================
     * MARK AS UNREAD
     * =========================================================
     */
    public function markUnread(
        int $notificationId,
        int $userId
    ): bool {
        $stmt = $this->pdo->prepare("
            DELETE FROM notification_reads
            WHERE notification_id = :notification_id
              AND user_id = :user_id
        ");

        return $stmt->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId,
        ]);
    }

    /**
     * =========================================================
     * MARK ALL AS READ
     * =========================================================
     */
    public function markAllRead(
        int $userId,
        string $role
    ): bool {
        $stmt = $this->pdo->prepare("
            SELECT n.id
            FROM notifications n
            LEFT JOIN notification_reads r
                ON r.notification_id = n.id
                AND r.user_id = :read_user_id

            WHERE r.id IS NULL

              AND (
                    n.user_id = :user_id
                    OR n.target_role = 'all'
                    OR n.target_role = :role
                  )
        ");

        $stmt->execute([
            ':read_user_id' => $userId,
            ':user_id' => $userId,
            ':role' => $role,
        ]);

        $notifications = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!$notifications) {
            return true;
        }

        $insert = $this->pdo->prepare("
            INSERT INTO notification_reads
            (
                notification_id,
                user_id,
                read_at
            )
            VALUES
            (
                :notification_id,
                :user_id,
                NOW()
            )
        ");

        try {
            $this->pdo->beginTransaction();

            foreach ($notifications as $notificationId) {
                $insert->execute([
                    ':notification_id' => (int) $notificationId,
                    ':user_id' => $userId,
                ]);
            }

            $this->pdo->commit();

            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return false;
        }
    }

    /**
     * =========================================================
     * UNREAD COUNT
     * =========================================================
     */
    public function unreadCount(
        int $userId,
        string $role
    ): int {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)

            FROM notifications n

            LEFT JOIN notification_reads r
                ON r.notification_id = n.id
                AND r.user_id = :read_user_id

            WHERE r.id IS NULL

              AND (
                    n.user_id = :user_id
                    OR n.target_role = 'all'
                    OR n.target_role = :role
                  )
        ");

        $stmt->execute([
            ':read_user_id' => $userId,
            ':user_id' => $userId,
            ':role' => $role,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * =========================================================
     * TOTAL COUNT
     * =========================================================
     */
    public function totalCount(
        int $userId,
        string $role
    ): int {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)

            FROM notifications n

            WHERE
                n.user_id = :user_id
                OR n.target_role = 'all'
                OR n.target_role = :role
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':role' => $role,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * =========================================================
     * READ COUNT
     * =========================================================
     */
    public function readCount(
        int $userId,
        string $role
    ): int {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)

            FROM notifications n

            INNER JOIN notification_reads r
                ON r.notification_id = n.id
                AND r.user_id = :read_user_id

            WHERE
                n.user_id = :user_id
                OR n.target_role = 'all'
                OR n.target_role = :role
        ");

        $stmt->execute([
            ':read_user_id' => $userId,
            ':user_id' => $userId,
            ':role' => $role,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * =========================================================
     * DELETE NOTIFICATION
     * =========================================================
     *
     * Admin kisi bhi notification ko delete kar sakta hai.
     *
     * Normal user sirf apni direct notification delete kar
     * sakta hai.
     */
    public function delete(
        int $notificationId,
        int $userId,
        string $role
    ): bool {
        if ($role === 'admin') {
            $stmt = $this->pdo->prepare("
                DELETE FROM notifications
                WHERE id = :notification_id
            ");

            return $stmt->execute([
                ':notification_id' => $notificationId,
            ]);
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM notifications
            WHERE id = :notification_id
              AND user_id = :user_id
        ");

        return $stmt->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId,
        ]);
    }
}