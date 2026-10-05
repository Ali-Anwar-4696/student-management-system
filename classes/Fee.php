<?php

declare(strict_types=1);

class Fee
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO fees (student_id, fee_type, amount, due_date, status)
            VALUES (:student_id, :fee_type, :amount, :due_date, :status)
        ");

        return $stmt->execute([
            ':student_id' => $data['student_id'],
            ':fee_type' => $data['fee_type'],
            ':amount' => $data['amount'],
            ':due_date' => $data['due_date'],
            ':status' => $data['status'] ?? 'pending',
        ]);
    }

    public function duplicateExists(int $studentId, string $feeType, string $dueDate, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM fees WHERE student_id = :sid AND fee_type = :type AND due_date = :due';
        $params = [':sid' => $studentId, ':type' => $feeType, ':due' => $dueDate];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, s.name AS student_name, s.student_id AS student_code
            FROM fees f
            INNER JOIN students s ON f.student_id = s.id
            WHERE f.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Build the shared filter fragment used by list()/count() so the
     * paged list and its row count can never drift apart.
     *
     * @return array{0: string, 1: array<string,mixed>}
     */
    private function buildFilters(string $search, string $status, string $studentId): array
    {
        $where = '';
        $params = [];

        if ($search !== '') {
            $where .= ' AND (s.name LIKE :q OR s.student_id LIKE :q2 OR f.fee_type LIKE :q3)';
            $like = '%' . $search . '%';
            $params[':q'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }
        if (in_array($status, ['pending', 'partial', 'paid', 'overdue'], true)) {
            $where .= ' AND f.status = :status';
            $params[':status'] = $status;
        }
        if ($studentId !== '' && ctype_digit($studentId)) {
            $where .= ' AND f.student_id = :student_id';
            $params[':student_id'] = (int) $studentId;
        }

        return [$where, $params];
    }

    /**
     * Total number of fee rows matching the active filters.
     */
    public function countFiltered(string $search = '', string $status = '', string $studentId = ''): int
    {
        [$where, $params] = $this->buildFilters($search, $status, $studentId);

        $sql = "
            SELECT COUNT(*)
            FROM fees f
            INNER JOIN students s ON f.student_id = s.id
            WHERE 1=1
            {$where}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * One page of fee rows.
     *
     * The paid total comes from a single grouped LEFT JOIN instead of a
     * correlated sub-select per row, which previously made the page render
     * cost grow with the size of the whole fee table.
     *
     * @return array<int,array<string,mixed>>
     */
    public function list(
        string $search = '',
        string $status = '',
        string $studentId = '',
        int $limit = 0,
        int $offset = 0
    ): array {
        [$where, $params] = $this->buildFilters($search, $status, $studentId);

        $sql = "
            SELECT f.*,
                   s.name AS student_name,
                   s.student_id AS student_code,
                   COALESCE(p.paid_total, 0) AS paid_amount
            FROM fees f
            INNER JOIN students s ON f.student_id = s.id
            LEFT JOIN (
                SELECT fee_id, SUM(amount) AS paid_total
                FROM fee_payments
                GROUP BY fee_id
            ) p ON p.fee_id = f.id
            WHERE 1=1
            {$where}
            ORDER BY f.due_date DESC, f.id DESC
        ";

        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Students for the fee forms.
     *
     * The picker used to embed every active student, which made the page
     * grow without bound as the school got larger. It now supports a
     * server-side search term and an explicit cap so the control stays
     * usable at any student count.
     *
     * @return array<int,array<string,mixed>>
     */
    public function studentsForPicker(string $search = '', int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $sql = '
            SELECT id, student_id, name
            FROM students
            WHERE status = \'active\'
        ';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (name LIKE :a OR student_id LIKE :b)';
            $params[':a'] = '%' . $search . '%';
            $params[':b'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY name ASC LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function addPayment(int $feeId, int $studentId, float $amount, string $date, string $method, ?string $reference): bool
    {
        if ($amount <= 0) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $fee = $this->getById($feeId);
            if (!$fee || (int) $fee['student_id'] !== $studentId) {
                $this->pdo->rollBack();
                return false;
            }

            // -------------------------------------------------
            // MODEL-LEVEL OVERPAYMENT GUARD
            // -------------------------------------------------
            // The controller validates too; this protects the
            // canonical table from any other caller.
            $paidStmt = $this->pdo->prepare(
                'SELECT COALESCE(SUM(amount), 0) FROM fee_payments WHERE fee_id = :id'
            );
            $paidStmt->execute([':id' => $feeId]);
            $alreadyPaid = (float) $paidStmt->fetchColumn();
            $remaining = (float) $fee['amount'] - $alreadyPaid;

            if ($amount > $remaining) {
                $this->pdo->rollBack();
                return false;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO fee_payments (fee_id, student_id, amount, payment_date, payment_method, transaction_reference)
                VALUES (:fee_id, :student_id, :amount, :payment_date, :payment_method, :ref)
            ");
            $stmt->execute([
                ':fee_id' => $feeId,
                ':student_id' => $studentId,
                ':amount' => $amount,
                ':payment_date' => $date,
                ':payment_method' => $method,
                ':ref' => $reference,
            ]);

            $this->refreshStatus($feeId);
            $this->pdo->commit();

            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function refreshStatus(int $feeId): void
    {
        $stmt = $this->pdo->prepare('SELECT amount, due_date FROM fees WHERE id = :id');
        $stmt->execute([':id' => $feeId]);
        $fee = $stmt->fetch();
        if (!$fee) {
            return;
        }

        $paidStmt = $this->pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM fee_payments WHERE fee_id = :id');
        $paidStmt->execute([':id' => $feeId]);
        $paid = (float) $paidStmt->fetchColumn();
        $amount = (float) $fee['amount'];

        if ($paid >= $amount) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        } elseif ($fee['due_date'] < date('Y-m-d')) {
            $status = 'overdue';
        } else {
            $status = 'pending';
        }

        $upd = $this->pdo->prepare('UPDATE fees SET status = :status WHERE id = :id');
        $upd->execute([':status' => $status, ':id' => $feeId]);
    }

    /**
     * Keep fee statuses aligned with the due date.
     *
     * - unpaid + past due        -> overdue
     * - overdue + due date moved -> pending
     * - partially paid           -> stays "partial"
     *   (more informative than "overdue"; refreshStatus()
     *    agrees with this rule)
     *
     * @param int|null $studentId scope the refresh to one student
     */
    public function refreshOverdue(?int $studentId = null): void
    {
        $scope = '';

        $params = [];

        if ($studentId !== null) {
            $scope = ' AND f.student_id = :student_id';
            $params[':student_id'] = $studentId;
        }

        // Only rows whose status can actually change are visited, and the
        // paid total comes from a single grouped join. The previous form
        // ran a correlated sub-select per candidate row on every request,
        // which made each page load scale with the whole fee table.
        $stmt = $this->pdo->prepare("
            UPDATE fees f
            LEFT JOIN (
                SELECT fee_id, SUM(amount) AS paid_total
                FROM fee_payments
                GROUP BY fee_id
            ) p ON p.fee_id = f.id
            SET f.status = 'overdue'
            WHERE f.status = 'pending'
              AND f.due_date < CURDATE()
              AND COALESCE(p.paid_total, 0) < f.amount
              {$scope}
        ");
        $stmt->execute($params);

        $revert = $this->pdo->prepare("
            UPDATE fees f
            SET f.status = 'pending'
            WHERE f.status = 'overdue'
              AND f.due_date >= CURDATE()
              {$scope}
        ");
        $revert->execute($params);
    }

    public function summary(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COALESCE(SUM(amount), 0) AS billed,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS collected_full
            FROM fees
        ");
        $row = $stmt->fetch() ?: ['billed' => 0, 'collected_full' => 0];
        $paid = (float) $this->pdo->query('SELECT COALESCE(SUM(amount), 0) FROM fee_payments')->fetchColumn();

        return [
            'billed' => (float) $row['billed'],
            'collected' => $paid,
            'outstanding' => max((float) $row['billed'] - $paid, 0),
        ];
    }

    public function paymentsForFee(int $feeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM fee_payments WHERE fee_id = :id ORDER BY payment_date DESC, id DESC'
        );
        $stmt->execute([':id' => $feeId]);

        return $stmt->fetchAll();
    }
}
