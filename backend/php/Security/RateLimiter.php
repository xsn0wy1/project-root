<?php
declare(strict_types=1);
namespace Hospital\Security;
use Hospital\Http\ApiException;

final class RateLimiter
{
    private \PDO $db;
    private string $key;
    private array $charges = [];
    private bool $refundPrepared = false;
    public function __construct(\PDO $db, string $key)
    {
        $this->db = $db;
        $this->key = $key;
    }

    public function consume(string $name, int $limit, int $seconds): void
    {
        $this->take([[$name, $limit, $seconds]]);
    }

    /** Reserve before credential verification; no database lock survives this call. */
    public function reserve(array $budgets): void
    {
        if ($this->charges)
            throw new \LogicException('A rate-limit reservation is already active.');
        $this->charges = $this->take($budgets);
    }

    /** Refund once, optionally resetting only the successful user's source bucket.
     * Inside a caller's transaction, confirmRefund must follow its successful commit.
     * After rollback, calling refund again releases the original provisional charge.
     */
    public function refund(?string $reset = null): void
    {
        if (!$this->charges)
        return;
        $own = !$this->db->inTransaction();
        if (!$own && $this->refundPrepared)
        return;
        if ($own)
            $this->db->beginTransaction();
        try {
            foreach ($this->charges as $bucket => $charge) {
                $sql = $charge['name'] === $reset
                    ? 'DELETE FROM auth_rate_limits WHERE bucket_key = ? AND resets_at = ?'
                    : 'UPDATE auth_rate_limits SET attempts = attempts - 1 WHERE bucket_key = ? AND resets_at = ? AND attempts > 0';
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$bucket, $charge['window']]);
            }
            if ($own) {
                $this->db->commit();
                $this->confirmRefund();
            } else {
                $this->refundPrepared = true;
            }
        } catch (\Throwable $error) {
            if ($own && $this->db->inTransaction())
                $this->db->rollBack();
            throw $error;
        }
    }

    /** Memory only: successful application commits must perform no fallible cleanup. */
    public function confirmRefund(): void
    {
        $this->charges = [];
        $this->refundPrepared = false;
    }

    private function take(array $budgets): array
    {
        $ordered = [];
        foreach ($budgets as [$name, $limit, $seconds]) {
            $bucket = hash_hmac('sha256', $name, $this->key);
            if (isset($ordered[$bucket]) || $limit < 1 || $seconds < 1)
                throw new \LogicException('Invalid rate-limit budget.');
            $ordered[$bucket] = [$name, $limit, $seconds];
        }
        ksort($ordered);
        $this->db->beginTransaction();
        try {
            $charges = [];
            foreach ($ordered as $bucket => [$name, $limit, $seconds]) {
                // Take write locks in one stable order, avoiding duplicate shared locks.
                $stmt = $this->db->prepare('INSERT INTO auth_rate_limits (bucket_key, attempts, resets_at) VALUES (?, 0, ?) ON DUPLICATE KEY UPDATE bucket_key = VALUES(bucket_key)');
                $stmt->execute([$bucket, gmdate('Y-m-d H:i:s', time() + $seconds)]);
                $stmt = $this->db->prepare('SELECT attempts, resets_at FROM auth_rate_limits WHERE bucket_key = ? FOR UPDATE');
                $stmt->execute([$bucket]);
                $row = $stmt->fetch();
                $expired = strtotime($row['resets_at'] . ' UTC') <= time();
                $attempts = $expired ? 0 : (int) $row['attempts'];
                $resets = $expired ? gmdate('Y-m-d H:i:s', time() + $seconds) : $row['resets_at'];
                if ($attempts >= $limit) {
                    header('Retry-After: ' . max(1, strtotime($resets . ' UTC') - time()));
                    throw new ApiException(429, 'RATE_LIMITED', \Hospital\I18n::t("errors.demasiados_intentos_espera_unos_minutos"));
                }
                $stmt = $this->db->prepare('UPDATE auth_rate_limits SET attempts = ?, resets_at = ? WHERE bucket_key = ?');
                $stmt->execute([$attempts + 1, $resets, $bucket]);
                $charges[$bucket] = ['name' => $name, 'window' => $resets];
            }
            $this->db->commit();
            return $charges;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction())
                $this->db->rollBack();
            throw $e;
        }
    }
}
