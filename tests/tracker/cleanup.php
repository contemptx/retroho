<?php

declare(strict_types=1);

// Isolated real-database cleanup regression suite. Never bootstrap the live app.

namespace {
    require getenv('TRACKER_TEST_AUTOLOAD') ?: __DIR__.'/../../vendor/autoload.php';
}

namespace App\Models {
    class Peer extends \Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];
    }

    class History extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'history';

        protected $guarded = [];
    }
}

namespace {
    use App\Console\Commands\AutoFlushPeers;
    use App\Models\Peer;
    use Illuminate\Container\Container;
    use Illuminate\Database\Capsule\Manager;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Events\Dispatcher;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Facade;

    require getenv('TRACKER_TEST_CLEANUP_COMMAND') ?: __DIR__.'/../../app/Console/Commands/AutoFlushPeers.php';

    function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    class TestCache
    {
        public array $decrements = [];

        public function decrement(string $key): int
        {
            $this->decrements[] = $key;

            return 0;
        }
    }

    if (!\function_exists('cache')) {
        function cache(): TestCache
        {
            return Container::getInstance()->make('cache');
        }
    }

    function peerRow(int $id, string $updatedAt, bool $active = true, int $userId = 1, int $torrentId = 10): void
    {
        DB::table('peers')->insert([
            'id'         => $id, 'user_id' => $userId, 'torrent_id' => $torrentId,
            'active'     => $active, 'seeder' => true,
            'created_at' => $updatedAt, 'updated_at' => $updatedAt,
        ]);
    }

    function historyRow(string $updatedAt, int $userId = 1, int $torrentId = 10): void
    {
        DB::table('history')->insert([
            'user_id'    => $userId, 'torrent_id' => $torrentId, 'active' => true,
            'created_at' => $updatedAt, 'updated_at' => $updatedAt,
        ]);
    }

    function activePeer(int $id): bool
    {
        return (bool) DB::table('peers')->where('id', $id)->value('active');
    }

    function activeHistory(int $userId = 1, int $torrentId = 10): bool
    {
        return (bool) DB::table('history')->where('user_id', $userId)->where('torrent_id', $torrentId)->value('active');
    }

    function cleanup(): void
    {
        $command = new class () extends AutoFlushPeers {
            public function comment($string, $verbosity = null): void
            {
            }
        };
        $command->handle();
    }

    $dsn = getenv('TRACKER_TEST_DSN') ?: 'sqlite::memory:';
    $driver = strstr($dsn, ':', true);
    check(\in_array($driver, ['sqlite', 'mysql'], true), 'Only isolated SQLite or MySQL tests are permitted');
    $configuration = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];

    if ($driver === 'mysql') {
        check(getenv('TRACKER_TEST_DATABASE') === 'retroho_tracker_test', 'Refuse any non-isolated database');
        $configuration = [
            'driver'   => 'mysql', 'database' => 'retroho_tracker_test',
            'host'     => getenv('TRACKER_TEST_HOST') ?: '127.0.0.1',
            'port'     => getenv('TRACKER_TEST_PORT') ?: 3306,
            'username' => getenv('TRACKER_TEST_USER'),
            'password' => getenv('TRACKER_TEST_PASSWORD') ?: '',
            'charset'  => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
        ];
    }

    $container = new Container();
    Container::setInstance($container);
    $capsule = new Manager($container);
    $capsule->addConnection($configuration);
    $capsule->setEventDispatcher(new Dispatcher($container));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $container->instance('db', $capsule->getDatabaseManager());
    $container->instance('cache', new TestCache());
    Facade::setFacadeApplication($container);
    $schema = $capsule->schema();
    $schema->dropIfExists('peers');
    $schema->dropIfExists('history');
    $schema->create('peers', static function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('user_id');
        $table->unsignedInteger('torrent_id');
        $table->boolean('active');
        $table->boolean('seeder');
        $table->timestamps();
        $table->index(['user_id', 'torrent_id', 'active']);
    });
    $schema->create('history', static function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('user_id');
        $table->unsignedInteger('torrent_id');
        $table->boolean('active');
        $table->timestamps();
        $table->unique(['user_id', 'torrent_id']);
    });

    $tests = [];
    $stale = '2026-10-29 21:00:00';
    $fresh = '2026-10-29 23:59:00';
    $tests['stale peer and lone stale history expire without changing timestamps'] = static function () use ($stale): void {
        peerRow(1, $stale);
        historyRow($stale);
        cleanup();
        check(!activePeer(1) && !activeHistory(), 'A genuinely stale peer and its history must expire');
        check(DB::table('peers')->where('id', 1)->value('updated_at') === $stale, 'Peer timestamp must be preserved');
        check(DB::table('history')->value('updated_at') === $stale, 'History timestamp must be preserved');
    };
    $tests['announce refresh between stale scan and expiry survives'] = static function () use ($stale, $fresh): void {
        peerRow(1, $stale);
        historyRow($stale);
        $refreshed = false;
        Peer::retrieved(static function (Peer $peer) use (&$refreshed, $fresh): void {
            if (!$refreshed && $peer->id === 1) {
                $refreshed = true;
                // The command already read stale row values; simulate the actual
                // announce write before it attempts to expire that stale snapshot.
                DB::table('peers')->where('id', 1)->update(['updated_at' => $fresh, 'active' => true]);
            }
        });
        cleanup();
        check($refreshed, 'The announce refresh interleaving must run');
        check(activePeer(1), 'A refreshed peer must not be overwritten by cleanup');
        check(activeHistory(), 'Skipping a refreshed peer must not deactivate its history');
        check(DB::table('peers')->where('id', 1)->value('updated_at') === $fresh, 'Fresh announce timestamp must remain');
    };
    $tests['another active location preserves user torrent history'] = static function () use ($stale, $fresh): void {
        peerRow(1, $stale);
        peerRow(2, $fresh);
        historyRow($stale);
        cleanup();
        check(!activePeer(1) && activePeer(2), 'Only the stale location should expire');
        check(activeHistory(), 'The other active location must preserve shared history');
    };
    $tests['fresh history survives expiry of a stale peer'] = static function () use ($stale, $fresh): void {
        peerRow(1, $stale);
        historyRow($fresh);
        cleanup();
        check(!activePeer(1), 'The stale peer should expire');
        check(activeHistory(), 'A newer history update must survive');
        check(DB::table('history')->value('updated_at') === $fresh, 'Fresh history timestamp must remain');
    };
    $tests['last stale location expires shared history'] = static function () use ($stale): void {
        peerRow(1, $stale);
        peerRow(2, $stale);
        historyRow($stale);
        cleanup();
        check(!activePeer(1) && !activePeer(2), 'Both stale locations should expire');
        check(!activeHistory(), 'No active location remains to support history');
    };
    $tests['two hour expiry threshold remains exclusive'] = static function (): void {
        peerRow(1, '2026-10-29 22:00:00');
        peerRow(2, '2026-10-29 21:59:59', torrentId: 11);
        historyRow('2026-10-29 22:00:00');
        historyRow('2026-10-29 21:59:59', torrentId: 11);
        cleanup();
        check(activePeer(1) && activeHistory(), 'Exactly two hours old must survive');
        check(!activePeer(2) && !activeHistory(torrentId: 11), 'One second older must expire');
    };
    $tests['another user or torrent does not preserve unrelated stale history'] = static function () use ($stale, $fresh): void {
        peerRow(1, $stale);
        peerRow(2, $fresh, userId: 2);
        peerRow(3, $fresh, torrentId: 11);
        historyRow($stale);
        cleanup();
        check(!activeHistory(), 'Only the same user and torrent can preserve shared history');
        check(activePeer(2) && activePeer(3), 'Other users and torrents must remain untouched');
    };
    $tests['two day inactive retention threshold remains exclusive'] = static function (): void {
        peerRow(1, '2026-10-28 00:00:00', active: false);
        peerRow(2, '2026-10-27 23:59:59', active: false);
        cleanup();
        check(DB::table('peers')->where('id', 1)->exists(), 'Exactly two days old must remain');
        check(!DB::table('peers')->where('id', 2)->exists(), 'Older than two days must still be removed');
        check(Container::getInstance()->make('cache')->decrements === ['user-leeching-count:1'], 'Only the actually deleted peer changes the existing counter');
    };
    $tests['inactive peer refreshed after deletion scan survives without counter change'] = static function () use ($fresh): void {
        peerRow(1, '2026-10-27 23:59:59', active: false);
        $refreshed = false;
        Peer::retrieved(static function (Peer $peer) use (&$refreshed, $fresh): void {
            if (!$refreshed && $peer->id === 1) {
                $refreshed = true;
                DB::table('peers')->where('id', 1)->update(['updated_at' => $fresh, 'active' => true]);
            }
        });
        cleanup();
        check($refreshed && activePeer(1), 'A peer resumed after the inactive scan must survive');
        check(DB::table('peers')->where('id', 1)->value('updated_at') === $fresh, 'Fresh announce timestamp must survive deletion');
        check(Container::getInstance()->make('cache')->decrements === [], 'A peer preserved by the freshness guard must not change the counter');
    };

    $failed = 0;

    foreach ($tests as $label => $test) {
        Carbon::setTestNow('2026-10-30T00:00:00Z');
        Peer::flushEventListeners();
        $container->make('cache')->decrements = [];
        DB::table('peers')->delete();
        DB::table('history')->delete();

        try {
            $test();
            echo 'PASS '.$label.PHP_EOL;
        } catch (Throwable $error) {
            $failed++;
            echo 'FAIL '.$label.': '.$error->getMessage().PHP_EOL;
        }
    }
    Carbon::setTestNow();
    echo \count($tests).' cleanup regression groups on '.$driver.', '.$failed.' failed'.PHP_EOL;

    exit($failed === 0 ? 0 : 1);
}
