<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TorrentContributionException;
use PDO;
use PDOException;
use Throwable;

/** Source ownership is durable evidence; a newly attached pivot is not ownership. */
final class TorrentContributionLedger
{
    private readonly string $driver;

    public function __construct(private readonly PDO $pdo)
    {
        $this->driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->require(\in_array($this->driver, ['mysql', 'sqlite'], true), 'UNAVAILABLE', 503);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($this->driver === 'sqlite') {
            $pdo->exec('PRAGMA busy_timeout = 15000');
        }
    }

    /** @return array<string, mixed> */
    public function capabilities(int $callerId): array
    {
        $this->require($callerId > 0, 'UNAUTHORIZED', 403);

        return [
            'version'             => 'source-contributions-v1', 'caller_id' => $callerId,
            'ledger_instance_id'  => $this->instance(), 'generationFencing' => true,
            'selectiveWithdrawal' => true, 'completeReadBack' => true,
        ];
    }

    /**
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function apply(int $callerId, string $contributionId, array $input, string $expectedInstance): array
    {
        $this->require($callerId > 0, 'UNAUTHORIZED', 403);
        $this->identifier($contributionId, 128);
        $this->require(array_diff(array_keys($input), ['generation', 'desired', 'username', 'email', 'torrent_ids', 'idempotency_key']) === [], 'INVALID_REQUEST', 422);
        $this->require(\is_int($input['generation'] ?? null) && $input['generation'] > 0 && $input['generation'] <= 9007199254740991, 'INVALID_REQUEST', 422);
        $this->require(\in_array($input['desired'] ?? null, ['active', 'withdrawn'], true), 'INVALID_REQUEST', 422);
        $username = $input['username'] ?? null;
        $email = $input['email'] ?? null;
        $this->require(\is_string($username) && $username !== '' && \strlen($username) <= 255 && trim($username) === $username && !preg_match('/[\x00-\x1f\x7f]/', $username), 'INVALID_REQUEST', 422);
        $this->require(\is_string($email) && \strlen($email) <= 254 && strtolower($email) === $email && filter_var($email, FILTER_VALIDATE_EMAIL) !== false, 'INVALID_REQUEST', 422);
        $ids = $this->torrentIds($input['torrent_ids'] ?? null);
        $this->identifier($input['idempotency_key'] ?? null, 200);
        $key = hash('sha256', $contributionId);
        $operationKey = hash('sha256', $input['idempotency_key']);
        $requestHash = hash('sha256', json_encode([$contributionId, $username, $email, $ids, $input['generation'], $input['desired']], JSON_THROW_ON_ERROR));

        return $this->transaction(function () use ($callerId, $contributionId, $input, $username, $email, $ids, $key, $operationKey, $requestHash, $expectedInstance): array {
            $this->require(hash_equals($this->instance(), $expectedInstance), 'SCOPE_CONFLICT');
            $user = $this->one('SELECT id, username, email, active, can_download, deleted_at FROM users WHERE username = ?'.$this->lock(), [$username]);
            $this->require($user !== null && $user['username'] === $username && strtolower((string) $user['email']) === $email, 'RECIPIENT_UNAVAILABLE');
            $userId = (int) $user['id'];
            $row = $this->one('SELECT * FROM torrent_source_contributions WHERE caller_id = ? AND source_key = ?'.$this->lock(), [$callerId, $key]);

            if ($row !== null) {
                $this->require($row['contribution_id'] === $contributionId && (int) $row['user_id'] === $userId && $row['username'] === $username && $row['email'] === $email && $this->storedIds($row) === $ids, 'BINDING_CONFLICT');
            }
            $operation = $this->one('SELECT request_hash FROM torrent_source_operations WHERE caller_id = ? AND idempotency_key_hash = ?', [$callerId, $operationKey]);

            if ($operation !== null) {
                $this->require(hash_equals($operation['request_hash'], $requestHash), 'IDEMPOTENCY_CONFLICT');
                $this->require($row !== null, 'UNAVAILABLE', 503);

                if ((int) $row['generation'] === $input['generation'] && $row['desired'] === $input['desired']) {
                    if ($row['desired'] === 'active') {
                        $this->require($user['deleted_at'] === null && (bool) $user['active'] && (bool) $user['can_download'], 'RECIPIENT_UNAVAILABLE');
                        $this->require($this->existingTorrents($ids) === $ids, 'RESOURCE_UNAVAILABLE');
                    }

                    foreach ($ids as $torrentId) {
                        $this->reconcile($userId, $torrentId);
                    }
                }

                return $this->project($row, $user);
            }

            if ($row !== null) {
                $this->require($input['generation'] >= (int) $row['generation'], 'STALE_GENERATION');
                $this->require($input['generation'] !== (int) $row['generation'] || $input['desired'] === $row['desired'], 'GENERATION_CONFLICT');
            }

            if ($input['desired'] === 'active') {
                $this->require($user['deleted_at'] === null && (bool) $user['active'] && (bool) $user['can_download'], 'RECIPIENT_UNAVAILABLE');
                $this->require($this->existingTorrents($ids) === $ids, 'RESOURCE_UNAVAILABLE');
            }

            if ($row === null) {
                // Capture every pre-existing attachment before recording this
                // contribution. Later legacy attachments update this support,
                // even when the effective pivot was already present.
                foreach ($ids as $torrentId) {
                    $this->ensureIndependent($userId, $torrentId);
                }
                $now = gmdate('Y-m-d H:i:s');
                $this->execute(
                    'INSERT INTO torrent_source_contributions (caller_id, source_key, contribution_id, user_id, username, email, torrent_ids, generation, desired, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$callerId, $key, $contributionId, $userId, $username, $email, json_encode($ids, JSON_THROW_ON_ERROR), $input['generation'], $input['desired'], $now, $now]
                );
                $pk = (int) $this->pdo->lastInsertId();

                foreach ($ids as $torrentId) {
                    $this->execute('INSERT INTO torrent_source_resources (contribution_pk, user_id, torrent_id) VALUES (?, ?, ?)', [$pk, $userId, $torrentId]);
                }
            } else {
                $pk = (int) $row['id'];
                $this->execute('UPDATE torrent_source_contributions SET generation = ?, desired = ?, updated_at = ? WHERE id = ?', [$input['generation'], $input['desired'], gmdate('Y-m-d H:i:s'), $pk]);
            }

            foreach ($ids as $torrentId) {
                $this->reconcile($userId, $torrentId);
            }
            $this->execute('INSERT INTO torrent_source_operations (caller_id, idempotency_key_hash, request_hash, contribution_pk, created_at) VALUES (?, ?, ?, ?, ?)', [$callerId, $operationKey, $requestHash, $pk, gmdate('Y-m-d H:i:s')]);
            $row = $this->one('SELECT * FROM torrent_source_contributions WHERE id = ?', [$pk]);

            return $this->project($row, $user);
        });
    }

    /** @return array<string, mixed> */
    public function read(int $callerId, string $contributionId, string $expectedInstance): array
    {
        $this->require($callerId > 0, 'UNAUTHORIZED', 403);
        $this->identifier($contributionId, 128);

        return $this->transaction(function () use ($callerId, $contributionId, $expectedInstance): array {
            $this->require(hash_equals($this->instance(), $expectedInstance), 'SCOPE_CONFLICT');
            $row = $this->one('SELECT * FROM torrent_source_contributions WHERE caller_id = ? AND source_key = ?', [$callerId, hash('sha256', $contributionId)]);
            $this->require($row !== null && $row['contribution_id'] === $contributionId, 'NOT_FOUND', 404);
            // Match the write/legacy lock order: recipient before contribution.
            $user = $this->lockUser((int) $row['user_id']);
            $row = $this->one('SELECT * FROM torrent_source_contributions WHERE id = ?'.$this->lock(), [(int) $row['id']]);

            return $this->project($row, $user);
        });
    }

    /**
     * Used by every existing API and web attachment entry point.
     * @param  list<int|string>     $torrentIds
     * @return array<string, mixed>
     */
    public function attachIndependent(int $userId, array $torrentIds): array
    {
        $ids = $this->torrentIds(array_values(array_unique(array_map('intval', $torrentIds))));

        return $this->transaction(function () use ($userId, $ids): array {
            $this->instance();
            $this->lockUser($userId);
            $this->require($this->existingTorrents($ids) === $ids, 'RESOURCE_UNAVAILABLE');
            $before = $this->attached($userId);

            foreach ($ids as $torrentId) {
                $this->ensureIndependent($userId, $torrentId);
                $this->execute('UPDATE torrent_source_independent SET independent_support = 1 WHERE user_id = ? AND torrent_id = ?', [$userId, $torrentId]);
                $this->reconcile($userId, $torrentId);
            }
            $after = $this->attached($userId);

            return ['previous_count' => \count($before), 'attached_torrents' => $after, 'newly_attached' => array_values(array_diff($after, $before))];
        });
    }

    /**
     * A broad legacy removal withdraws independent support, not other sources.
     * @param  list<int|string>|null $torrentIds
     * @return array<string, mixed>
     */
    public function removeIndependent(int $userId, ?array $torrentIds = null): array
    {
        $ids = $torrentIds === null ? null : $this->torrentIds(array_values(array_unique(array_map('intval', $torrentIds))));

        return $this->transaction(function () use ($userId, $ids): array {
            $this->instance();
            $this->lockUser($userId);
            $before = $this->attached($userId);

            if ($ids === null) {
                $known = $this->rows('SELECT torrent_id FROM torrent_source_independent WHERE user_id = ? UNION SELECT torrent_id FROM torrent_source_resources WHERE user_id = ?', [$userId, $userId]);
                $ids = array_values(array_unique(array_merge($before, array_map(static fn (array $row): int => (int) $row['torrent_id'], $known))));
                $this->require(\count($ids) <= 10000, 'UNAVAILABLE', 503);
                sort($ids, SORT_NUMERIC);
            }

            foreach ($ids as $torrentId) {
                $this->ensureIndependent($userId, $torrentId);
                $this->execute('UPDATE torrent_source_independent SET independent_support = 0 WHERE user_id = ? AND torrent_id = ?', [$userId, $torrentId]);
                $this->reconcile($userId, $torrentId);
            }
            $after = $this->attached($userId);

            $removed = array_values(array_diff($before, $after));

            return ['removed_count' => \count($removed), 'removed_torrent_ids' => $removed, 'retained_source_count' => \count(array_intersect($ids, $after)), 'attached_torrents' => $after];
        });
    }

    /**
     * @param  array<string, mixed> $row
     * @param  array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function project(array $row, array $user): array
    {
        $this->require((int) $row['user_id'] === (int) $user['id'] && $row['username'] === $user['username'] && $row['email'] === strtolower((string) $user['email']), 'BINDING_CONFLICT');
        $ids = $this->storedIds($row);
        $recorded = array_map(static fn (array $item): int => (int) $item['torrent_id'], $this->rows('SELECT torrent_id FROM torrent_source_resources WHERE contribution_pk = ? ORDER BY torrent_id', [(int) $row['id']]));
        $this->require($recorded === $ids, 'UNAVAILABLE', 503);
        $existing = $this->existingTorrents($ids);
        $resources = [];
        $complete = true;

        foreach ($ids as $torrentId) {
            $support = $this->one('SELECT independent_support FROM torrent_source_independent WHERE user_id = ? AND torrent_id = ?', [(int) $user['id'], $torrentId]);
            $this->require($support !== null, 'UNAVAILABLE', 503);
            $attached = $this->pivotExists((int) $user['id'], $torrentId);
            $independent = (bool) $support['independent_support'];
            $sourceActive = $row['desired'] === 'active';
            $expected = $independent || $this->activeSource((int) $user['id'], $torrentId);
            $available = \in_array($torrentId, $existing, true) && $user['deleted_at'] === null && (bool) $user['active'] && (bool) $user['can_download'];
            $complete = $complete && ($attached === $expected) && (!$sourceActive || $available);
            $resources[] = ['torrent_id' => $torrentId, 'contribution_active' => $sourceActive, 'effective_access' => $attached && $available, 'independent_support' => $independent];
        }

        return ['contribution_id' => $row['contribution_id'], 'caller_id' => (int) $row['caller_id'], 'ledger_instance_id' => $this->instance(),
            'generation'          => (int) $row['generation'], 'desired' => $row['desired'], 'username' => $row['username'], 'email' => $row['email'],
            'torrent_ids'         => $ids, 'complete' => $complete, 'resources' => $resources];
    }

    private function ensureIndependent(int $userId, int $torrentId): void
    {
        if ($this->one('SELECT independent_support FROM torrent_source_independent WHERE user_id = ? AND torrent_id = ?', [$userId, $torrentId]) !== null) {
            return;
        }
        $this->require($this->one('SELECT contribution_pk FROM torrent_source_resources WHERE user_id = ? AND torrent_id = ? LIMIT 1', [$userId, $torrentId]) === null, 'UNAVAILABLE', 503);
        $this->execute('INSERT INTO torrent_source_independent (user_id, torrent_id, independent_support) VALUES (?, ?, ?)', [$userId, $torrentId, $this->pivotExists($userId, $torrentId) ? 1 : 0]);
    }

    private function reconcile(int $userId, int $torrentId): void
    {
        $support = $this->one('SELECT independent_support FROM torrent_source_independent WHERE user_id = ? AND torrent_id = ?', [$userId, $torrentId]);
        $this->require($support !== null, 'UNAVAILABLE', 503);

        if ((bool) $support['independent_support'] || $this->activeSource($userId, $torrentId)) {
            if (!$this->pivotExists($userId, $torrentId)) {
                $now = gmdate('Y-m-d H:i:s');
                $this->execute('INSERT INTO attached_torrent_user (user_id, torrent_id, created_at, updated_at) VALUES (?, ?, ?, ?)', [$userId, $torrentId, $now, $now]);
            }
        } else {
            // Existing installations may have duplicate pivots. Remove all
            // only after proving that no source or independent support remains.
            $this->execute('DELETE FROM attached_torrent_user WHERE user_id = ? AND torrent_id = ?', [$userId, $torrentId]);
        }
    }

    private function activeSource(int $userId, int $torrentId): bool
    {
        return $this->one("SELECT r.contribution_pk FROM torrent_source_resources r JOIN torrent_source_contributions c ON c.id = r.contribution_pk WHERE r.user_id = ? AND r.torrent_id = ? AND c.user_id = r.user_id AND c.desired = 'active' LIMIT 1", [$userId, $torrentId]) !== null;
    }

    private function pivotExists(int $userId, int $torrentId): bool
    {
        return $this->one('SELECT id FROM attached_torrent_user WHERE user_id = ? AND torrent_id = ? LIMIT 1', [$userId, $torrentId]) !== null;
    }

    /** @return list<int> */
    private function attached(int $userId): array
    {
        return array_map(static fn (array $row): int => (int) $row['torrent_id'], $this->rows('SELECT DISTINCT torrent_id FROM attached_torrent_user WHERE user_id = ? ORDER BY torrent_id', [$userId]));
    }

    /**
     * @param  list<int> $ids
     * @return list<int>
     */
    private function existingTorrents(array $ids): array
    {
        $placeholders = implode(',', array_fill(0, \count($ids), '?'));

        return array_map(static fn (array $row): int => (int) $row['id'], $this->rows('SELECT id FROM torrents WHERE id IN ('.$placeholders.') AND deleted_at IS NULL ORDER BY id', $ids));
    }

    /** @return array<string, mixed> */
    private function lockUser(int $userId): array
    {
        $user = $this->one('SELECT id, username, email, active, can_download, deleted_at FROM users WHERE id = ?'.$this->lock(), [$userId]);
        $this->require($user !== null, 'RECIPIENT_UNAVAILABLE');

        return $user;
    }

    private function instance(): string
    {
        $row = $this->one('SELECT instance_id FROM torrent_source_ledger WHERE id = 1', []);
        $this->require($row !== null && \is_string($row['instance_id']) && preg_match('/^[a-f0-9]{64}$/D', $row['instance_id']) === 1, 'UNAVAILABLE', 503);

        return $row['instance_id'];
    }

    /**
     * @param  array<string, mixed> $row
     * @return list<int>
     */
    private function storedIds(array $row): array
    {
        return $this->torrentIds(json_decode($row['torrent_ids'], true, 16, JSON_THROW_ON_ERROR));
    }

    /** @return list<int> */
    private function torrentIds(mixed $ids): array
    {
        $this->require(\is_array($ids) && array_is_list($ids) && \count($ids) > 0 && \count($ids) <= 1000, 'INVALID_REQUEST', 422);

        foreach ($ids as $id) {
            $this->require(\is_int($id) && $id > 0 && $id <= 4294967295, 'INVALID_REQUEST', 422);
        }
        $this->require(\count(array_unique($ids)) === \count($ids), 'INVALID_REQUEST', 422);
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    private function identifier(mixed $value, int $maximum): void
    {
        $this->require(\is_string($value) && \strlen($value) <= $maximum && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]*$/D', $value) === 1, 'INVALID_REQUEST', 422);
    }

    private function lock(): string
    {
        return $this->driver === 'mysql' ? ' FOR UPDATE' : '';
    }

    private function transaction(callable $callback): mixed
    {
        $this->require(!$this->pdo->inTransaction(), 'UNAVAILABLE', 503);

        if ($this->driver === 'sqlite') {
            $this->pdo->exec('BEGIN IMMEDIATE');
        } else {
            // The recipient lock serializes writes. Use fresh statement
            // snapshots after acquiring it, including a GET that first looked
            // up the source to discover its immutable recipient.
            $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            $this->pdo->beginTransaction();
        }

        try {
            $result = $callback();
            $this->driver === 'sqlite' ? $this->pdo->exec('COMMIT') : $this->pdo->commit();

            return $result;
        } catch (Throwable $error) {
            try {
                $this->driver === 'sqlite' ? $this->pdo->exec('ROLLBACK') : $this->pdo->rollBack();
            } catch (Throwable) {
            }

            if ($error instanceof PDOException && (string) $error->getCode() === '23000') {
                throw new TorrentContributionException('BINDING_CONFLICT');
            }

            throw $error;
        }
    }

    /**
     * @param  list<mixed>                $parameters
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $parameters): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param  list<mixed>               $parameters
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $parameters): ?array
    {
        $rows = $this->rows($sql, $parameters);
        $this->require(\count($rows) <= 1, 'UNAVAILABLE', 503);

        return $rows[0] ?? null;
    }

    /** @param list<mixed> $parameters */
    private function execute(string $sql, array $parameters): void
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
    }

    private function require(bool $condition, string $reason, int $status = 409): void
    {
        if (!$condition) {
            throw new TorrentContributionException($reason, $status);
        }
    }
}
