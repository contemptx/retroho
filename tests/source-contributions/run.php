<?php

declare(strict_types=1);

// Isolated real-database regression suite. No production API, mail or credentials.
require getenv('CONTRIBUTION_TEST_AUTOLOAD') ?: __DIR__.'/../../vendor/autoload.php';

require __DIR__.'/../../app/Exceptions/TorrentContributionException.php';

require __DIR__.'/../../app/Services/TorrentContributionLedger.php';

use App\Exceptions\TorrentContributionException;
use App\Services\TorrentContributionLedger;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;

function check(bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
}
function expectReason(callable $callback, string $reason): void
{
    try {
        $callback();
    } catch (TorrentContributionException $error) {
        check($error->reason === $reason, 'Expected '.$reason.', got '.$error->reason);

        return;
    }

    throw new RuntimeException('Expected '.$reason);
}
function connection(): PDO
{
    return new PDO(getenv('CONTRIBUTION_TEST_DSN'), getenv('CONTRIBUTION_TEST_USER') ?: null, getenv('CONTRIBUTION_TEST_PASSWORD') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
function payload(array $ids, int $generation = 1, string $desired = 'active', string $key = 'operation'): array
{
    return ['generation' => $generation, 'desired' => $desired, 'username' => 'synthetic_customer',
        'email'          => 'synthetic@example.test', 'torrent_ids' => $ids, 'idempotency_key' => $key];
}
function attached(PDO $pdo, int $id): bool
{
    $query = $pdo->prepare('SELECT id FROM attached_torrent_user WHERE user_id=1 AND torrent_id=?');
    $query->execute([$id]);

    return $query->fetch() !== false;
}
function concurrent(array $operations, string $instance): void
{
    check(\function_exists('pcntl_fork'), 'pcntl is required for the concurrency checks');
    $children = [];

    foreach ($operations as $operation) {
        $pid = pcntl_fork();
        check($pid !== -1, 'Unable to create isolated test process');

        if ($pid === 0) {
            try {
                $ledger = new TorrentContributionLedger(connection());

                if ($operation['kind'] === 'independent') {
                    $ledger->attachIndependent(1, $operation['ids']);
                } else {
                    try {
                        $ledger->apply(99, $operation['id'], $operation['input'], $instance);
                    } catch (TorrentContributionException $error) {
                        if ($error->reason !== ($operation['allow'] ?? '')) {
                            throw $error;
                        }
                    }
                }

                exit(0);
            } catch (Throwable $error) {
                fwrite(STDERR, 'Concurrent fixture failed: '.\get_class($error).'\n');

                exit(1);
            }
        }
        $children[] = $pid;
    }

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        check(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'Concurrent operation failed');
    }
}

if (!getenv('CONTRIBUTION_TEST_DSN')) {
    $file = tempnam(sys_get_temp_dir(), 'retroho-contribution-');
    putenv('CONTRIBUTION_TEST_DSN=sqlite:'.$file);
    $parentProcess = getmypid();
    register_shutdown_function(static function () use ($file, $parentProcess): void {
        if (getmypid() === $parentProcess && file_exists($file)) {
            unlink($file);
        }
    });
}
$dsn = getenv('CONTRIBUTION_TEST_DSN');
$sqlite = str_starts_with($dsn, 'sqlite:');

if (!$sqlite) {
    check(getenv('CONTRIBUTION_TEST_DATABASE') === 'retroho_contribution_test', 'Refuse any non-isolated database');
}
$capsule = new Manager(new Container());
$capsule->addConnection($sqlite ? ['driver' => 'sqlite', 'database' => substr($dsn, 7), 'prefix' => ''] : [
    'driver'   => 'mysql', 'host' => getenv('CONTRIBUTION_TEST_HOST') ?: '127.0.0.1',
    'port'     => getenv('CONTRIBUTION_TEST_PORT') ?: 3306, 'database' => 'retroho_contribution_test',
    'username' => getenv('CONTRIBUTION_TEST_USER'), 'password' => getenv('CONTRIBUTION_TEST_PASSWORD') ?: '',
    'charset'  => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
]);
$capsule->setAsGlobal();
$container = $capsule->getContainer();
$container->instance('db', $capsule->getDatabaseManager());
$container->bind('db.schema', static fn () => $capsule->getConnection()->getSchemaBuilder());
Facade::setFacadeApplication($container);
$schema = $capsule->schema();

foreach (['torrent_source_operations', 'torrent_source_independent', 'torrent_source_resources', 'torrent_source_contributions', 'torrent_source_ledger', 'attached_torrent_user', 'torrents', 'users'] as $table) {
    $schema->dropIfExists($table);
}
$schema->create('users', static function (Blueprint $table): void {
    $table->increments('id');
    $table->string('username')->unique();
    $table->string('email');
    $table->boolean('active');
    $table->boolean('can_download');
    $table->timestamp('deleted_at')->nullable();
});
$schema->create('torrents', static function (Blueprint $table): void {
    $table->increments('id');
    $table->timestamp('deleted_at')->nullable();
});
$pivotMigration = require __DIR__.'/../../database/migrations/2024_09_26_135500_create_attached_torrent_user_table.php';
$pivotMigration->up();
$migration = require __DIR__.'/../../database/migrations/2026_10_29_000001_create_torrent_source_contributions.php';
$migration->up();
$pdo = $capsule->getConnection()->getPdo();
$pdo->exec("INSERT INTO users (id,username,email,active,can_download) VALUES (1,'synthetic_customer','synthetic@example.test',1,1),(2,'other_customer','other@example.test',1,1)");

for ($id = 10; $id <= 40; $id++) {
    $pdo->exec('INSERT INTO torrents (id) VALUES ('.$id.')');
}
$ledger = new TorrentContributionLedger($pdo);
$instance = $ledger->capabilities(99)['ledger_instance_id'];
check(\strlen($instance) === 64 && $ledger->capabilities(98)['caller_id'] === 98, 'Durable capability scope');
$checks = 1;

$pdo->exec('INSERT INTO attached_torrent_user (user_id,torrent_id) VALUES (1,10),(1,10)');
$receipt = $ledger->apply(99, 'baseline', payload([10, 11], 1, 'active', 'baseline-1'), $instance);
check($receipt['complete'] && $receipt['resources'][0]['independent_support'] && !$receipt['resources'][1]['independent_support'], 'Baseline evidence captured');
$receipt = $ledger->apply(99, 'baseline', payload([10, 11], 2, 'withdrawn', 'baseline-2'), $instance);
check($receipt['complete'] && attached($pdo, 10) && !attached($pdo, 11), 'Withdrawal preserves existing independent pivot duplicates');
$checks++;

$ledger->apply(99, 'later-independent', payload([12], 1, 'active', 'later-1'), $instance);
$independent = $ledger->attachIndependent(1, [12]);
check($independent['newly_attached'] === [], 'Existing pivot is not falsely newly attached');
$ledger->apply(99, 'later-independent', payload([12], 2, 'withdrawn', 'later-2'), $instance);
check(attached($pdo, 12), 'Independent support added after source must survive');
$checks++;

$ledger->apply(99, 'shared-a', payload([13, 14], 1, 'active', 'shared-a-1'), $instance);
$ledger->apply(98, 'shared-a', payload([13], 1, 'active', 'shared-a-1'), $instance);
$ledger->apply(99, 'shared-a', payload([13, 14], 2, 'withdrawn', 'shared-a-2'), $instance);
check(attached($pdo, 13) && !attached($pdo, 14), 'Other caller source is preserved');
expectReason(static fn () => $ledger->read(97, 'shared-a', $instance), 'NOT_FOUND');
$checks++;

$removed = $ledger->removeIndependent(1);
check($removed['retained_source_count'] === 1 && attached($pdo, 13) && !attached($pdo, 10) && !attached($pdo, 12), 'Broad remove clears only independent support');
check($removed['removed_count'] === 2 && $removed['removed_torrent_ids'] === [10, 12], 'Removal receipt excludes retained source access and duplicate baseline pivots');
$ledger->apply(98, 'shared-a', payload([13], 2, 'withdrawn', 'shared-a-2'), $instance);
check(!attached($pdo, 13), 'Final source withdraw removes unsupported pivot');
$checks++;

$ledger->apply(99, 'tombstone', payload([15], 2, 'withdrawn', 'tombstone-2'), $instance);
expectReason(static fn () => $ledger->apply(99, 'tombstone', payload([15], 1, 'active', 'tombstone-1'), $instance), 'STALE_GENERATION');
check(!attached($pdo, 15), 'Revoke-before-grant cannot resurrect access');
$checks++;

$replay = $ledger->apply(99, 'baseline', payload([10, 11], 1, 'active', 'baseline-1'), $instance);
check($replay['generation'] === 2 && $replay['desired'] === 'withdrawn' && !attached($pdo, 11), 'Old successful replay returns current state without reviving access');
expectReason(static fn () => $ledger->apply(99, 'other-operation', payload([16], 1, 'active', 'baseline-1'), $instance), 'IDEMPOTENCY_CONFLICT');
expectReason(static fn () => $ledger->apply(99, 'baseline', payload([10], 3, 'active', 'changed-binding'), $instance), 'BINDING_CONFLICT');
expectReason(static fn () => $ledger->apply(99, 'tombstone', payload([15], 2, 'active', 'generation-conflict'), $instance), 'GENERATION_CONFLICT');
expectReason(static fn () => $ledger->apply(99, 'scope', payload([16], 2, 'withdrawn', 'scope-2'), str_repeat('0', 64)), 'SCOPE_CONFLICT');
expectReason(static fn () => $ledger->read(99, 'baseline', str_repeat('0', 64)), 'SCOPE_CONFLICT');
$checks++;

$ledger->apply(99, 'drift', payload([17], 1, 'active', 'drift-1'), $instance);
$pdo->exec('DELETE FROM attached_torrent_user WHERE user_id=1 AND torrent_id=17');
check(!$ledger->read(99, 'drift', $instance)['complete'], 'Read-back must expose missing effective pivot');
check($ledger->apply(99, 'drift', payload([17], 1, 'active', 'drift-1'), $instance)['complete'], 'Current exact retry reconciles drift safely');
$checks++;

foreach ([true, 0, -1, 1.5, '2'] as $generation) {
    $bad = payload([18]);
    $bad['generation'] = $generation;
    expectReason(static fn () => $ledger->apply(99, 'invalid', $bad, $instance), 'INVALID_REQUEST');
}
expectReason(static fn () => $ledger->apply(99, 'duplicates', payload([18, 18]), $instance), 'INVALID_REQUEST');
$bad = payload([18]);
$bad['username'] = 'other_customer';
$bad['email'] = 'other@example.test';
expectReason(static fn () => $ledger->apply(99, 'drift', $bad, $instance), 'BINDING_CONFLICT');
check(!attached($pdo, 18), 'Invalid requests cannot change attachment state');
$checks++;

$trigger = $sqlite ? "CREATE TRIGGER fail_contribution_operation BEFORE INSERT ON torrent_source_operations BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END"
    : "CREATE TRIGGER fail_contribution_operation BEFORE INSERT ON torrent_source_operations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'";
$pdo->exec($trigger);

try {
    $ledger->apply(99, 'rollback', payload([19], 1, 'active', 'rollback-1'), $instance);

    throw new RuntimeException('Failure injection did not run');
} catch (PDOException|TorrentContributionException) {
}
$pdo->exec('DROP TRIGGER fail_contribution_operation');
check(!attached($pdo, 19), 'Transaction rolls back effective attachment');
expectReason(static fn () => $ledger->read(99, 'rollback', $instance), 'NOT_FOUND');
check((int) $pdo->query('SELECT COUNT(*) FROM torrent_source_independent WHERE torrent_id=19')->fetchColumn() === 0, 'Transaction rolls back ownership evidence');
$checks++;

concurrent([
    ['kind' => 'source', 'id' => 'concurrent-a', 'input' => payload([20], 1, 'active', 'concurrent-a-1')],
    ['kind' => 'source', 'id' => 'concurrent-b', 'input' => payload([20], 1, 'active', 'concurrent-b-1')],
], $instance);
$pdo = connection();
$ledger = new TorrentContributionLedger($pdo);
$ledger->apply(99, 'concurrent-a', payload([20], 2, 'withdrawn', 'concurrent-a-2'), $instance);
check(attached($pdo, 20), 'Concurrent source baseline is not misattributed');
$ledger->apply(99, 'concurrent-b', payload([20], 2, 'withdrawn', 'concurrent-b-2'), $instance);
check(!attached($pdo, 20), 'No false independent baseline from concurrent source');
$checks++;

$ledger->apply(99, 'concurrent-independent', payload([21], 1, 'active', 'concurrent-independent-1'), $instance);
concurrent([
    ['kind' => 'source', 'id' => 'concurrent-independent', 'input' => payload([21], 2, 'withdrawn', 'concurrent-independent-2')],
    ['kind' => 'independent', 'ids' => [21]],
], $instance);
$pdo = connection();
$ledger = new TorrentContributionLedger($pdo);
check(attached($pdo, 21) && $ledger->read(99, 'concurrent-independent', $instance)['resources'][0]['independent_support'], 'Concurrent independent grant survives withdrawal');
$checks++;

concurrent([
    ['kind' => 'source', 'id' => 'generation-race', 'input' => payload([22], 1, 'active', 'race-1'), 'allow' => 'STALE_GENERATION'],
    ['kind' => 'source', 'id' => 'generation-race', 'input' => payload([22], 2, 'withdrawn', 'race-2')],
], $instance);
$pdo = connection();
$ledger = new TorrentContributionLedger($pdo);
$final = $ledger->read(99, 'generation-race', $instance);
check($final['complete'] && $final['generation'] === 2 && !$final['resources'][0]['contribution_active'] && !attached($pdo, 22), 'Newer concurrent withdrawal wins');
$checks++;

// Exercise the actual controller with Laravel Request/JsonResponse. The
// inherited base controller is irrelevant to these self-contained endpoints.
$capsule->getConnection()->reconnect();
eval('namespace App\\Http\\Controllers\\API; class BaseController {
    public function sendResponse($data, $message) { return new \\Illuminate\\Http\\JsonResponse(["success" => true, "data" => $data, "message" => $message]); }
    public function sendError($message, $errors = [], $status = 404) { return new \\Illuminate\\Http\\JsonResponse(["success" => false, "message" => $message, "errors" => $errors], $status); }
}');
function response(): object
{
    return new class () {
        public function json(array $data, int $status = 200): Illuminate\Http\JsonResponse
        {
            return new Illuminate\Http\JsonResponse($data, $status);
        }
    };
}

require __DIR__.'/../../app/Http/Controllers/API/TorrentContributionController.php';
$controller = new App\Http\Controllers\API\TorrentContributionController();
function requestFixture(?object $user, string $method = 'GET', ?array $body = null, array $headers = []): Illuminate\Http\Request
{
    $request = Illuminate\Http\Request::create('/api/user/torrent-contributions/test', $method, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body === null ? null : json_encode($body));
    $request->setUserResolver(static fn () => $user);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}
$owner = (object) ['id' => 99, 'group' => (object) ['is_owner' => true]];
check($controller->capabilities(requestFixture(null))->getStatusCode() === 403, 'Controller rejects missing authenticated owner');
check($controller->capabilities(requestFixture((object) ['id' => 99, 'group' => (object) ['is_owner' => false]]))->getStatusCode() === 403, 'Controller rejects non-owner API user');
check($controller->capabilities(requestFixture($owner))->getData(true)['data']['caller_id'] === 99, 'Controller scope comes from authenticated caller');
check($controller->update(requestFixture($owner, 'PUT', payload([23], 1, 'active', 'controller-1')), 'controller')->getStatusCode() === 409, 'Scope headers are required');
$wrong = $controller->update(requestFixture($owner, 'PUT', payload([23], 1, 'active', 'controller-1'), ['X-Torrent-Caller' => '98', 'X-Torrent-Ledger-Instance' => $instance]), 'controller');
check($wrong->getStatusCode() === 409 && !attached($pdo, 23), 'Forged caller scope has no effect');
$valid = $controller->update(requestFixture($owner, 'PUT', payload([23], 1, 'active', 'controller-1'), ['X-Torrent-Caller' => '99', 'X-Torrent-Ledger-Instance' => $instance]), 'controller');
check($valid->getStatusCode() === 200 && $valid->getData(true)['data']['complete'], 'Actual controller applies exact-scoped request');
check(str_contains((string) $valid->headers->get('Cache-Control'), 'no-store'), 'Responses cannot be cached');
$checks++;

// Execute the merged production attachment handlers against the isolated
// database. The fixture supplies only the unchanged auth/response shell and
// User model table binding; request-validation rules are outside this check.
$capsule->bootEloquent();
eval('namespace App\\Models; class User extends \\Illuminate\\Database\\Eloquent\\Model { protected $table = "users"; public $timestamps = false; }');
$legacyOwner = $owner;
function auth(): object
{
    return new class () {
        public function user(): ?object
        {
            return $GLOBALS['legacyOwner'];
        }
    };
}
Illuminate\Http\Request::macro('validate', fn (array $rules): array => $this->all());

require __DIR__.'/../../app/Http/Controllers/API/UserController.php';
$legacy = new App\Http\Controllers\API\UserController();
$legacyInput = ['email' => 'OTHER@EXAMPLE.TEST', 'reason' => 'Portal unlink'];
$legacyRequest = Illuminate\Http\Request::create('/api/user/remove-all-torrents', 'POST', $legacyInput);
$other = payload([24], 1, 'active', 'legacy-source-1');
$other['username'] = 'other_customer';
$other['email'] = 'other@example.test';
$ledger->apply(99, 'legacy-source', $other, $instance);
$ledger->attachIndependent(2, [25]);
$legacyOwner = (object) ['id' => 98, 'group' => (object) ['is_owner' => false]];
check($legacy->removeAllTorrents($legacyRequest)->getStatusCode() === 403, 'Legacy removal still requires owner');
$legacyOwner = $owner;
$legacyResult = $legacy->removeAllTorrents($legacyRequest);
$legacyData = $legacyResult->getData(true)['data'];
check($legacyResult->getStatusCode() === 200 && $legacyData['torrents_removed'] === 1 && $legacyData['removed_count'] === 1 && $legacyData['torrent_ids'] === [25], 'Live legacy removal response reports only actually removed torrents');
check($legacyData['reason'] === 'Portal unlink' && $legacyData['email'] === 'other@example.test' && $legacyData['retained_source_count'] === 1 && $legacyData['source_contributions_preserved'], 'Production reason and case-insensitive lookup are retained');
check($ledger->read(99, 'legacy-source', $instance)['resources'][0]['effective_access'], 'Legacy unlink preserves the active source attachment');
$attachRequest = Illuminate\Http\Request::create('/api/user/torrents', 'POST', ['email' => 'OTHER@EXAMPLE.TEST', 'torrent_sku' => [24, 26]]);
$attachedResult = $legacy->attachTorrents($attachRequest)->getData(true)['data'];
check($attachedResult['previous_count'] === 1 && $attachedResult['attached_torrents'] === [24, 26] && $attachedResult['newly_attached'] === [26] && $attachedResult['all_verified'], 'Merged attach handler retains live lookup and reports exact new attachments');
$other['generation'] = 2;
$other['desired'] = 'withdrawn';
$other['idempotency_key'] = 'legacy-source-2';
check($ledger->apply(99, 'legacy-source', $other, $instance)['resources'][0]['effective_access'], 'Independent support added through actual legacy controller survives source withdrawal');
$checks++;

echo 'PASS '.$checks.' contribution regression groups on '.($sqlite ? 'SQLite' : 'MySQL')."; real migration, concurrent transactions, scope and controller boundaries.\n";
