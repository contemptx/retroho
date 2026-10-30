<?php

declare(strict_types=1);

// Isolated controller regression suite: in-memory SQLite, synthetic cache/models
// and a queue stub. Optional localhost Redis uses unique, cleaned-up test keys.
// Never boot the production application or load its environment/credentials.

namespace {
    require getenv('TRACKER_TEST_AUTOLOAD') ?: __DIR__.'/../../vendor/autoload.php';
}

namespace App\Models {
    class Torrent extends \Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];
    }
    class User extends \Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];
    }
    class Peer extends \Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];
    }
    class Group extends \Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];
    }
}

namespace App\Jobs {
    class ProcessAnnounce
    {
        public static array $dispatched = [];
        public static function dispatch(mixed ...$arguments): void
        {
            self::$dispatched[] = $arguments;
        }
    }
}

namespace App\Http\Controllers {
    class Controller
    {
    }
}

namespace {
    use App\DTO\AnnounceQueryDTO;
    use App\Exceptions\TrackerException;
    use App\Http\Controllers\AnnounceController;
    use App\Jobs\ProcessAnnounce;
    use App\Models\Group;
    use App\Models\Peer;
    use App\Models\Torrent;
    use App\Models\User;
    use Illuminate\Container\Container;
    use Illuminate\Database\Capsule\Manager;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Http\Request;
    use Illuminate\Http\Response;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Collection;
    use Illuminate\Support\Facades\Redis;
    use Illuminate\Support\Facades\Facade;

    require __DIR__.'/../../app/DTO/AnnounceQueryDTO.php';

    require __DIR__.'/../../app/DTO/AnnounceGroupDTO.php';

    require __DIR__.'/../../app/DTO/AnnounceUserDTO.php';

    require __DIR__.'/../../app/DTO/AnnounceTorrentDTO.php';

    require __DIR__.'/../../app/Exceptions/TrackerException.php';

    require getenv('TRACKER_TEST_CONTROLLER') ?: __DIR__.'/../../app/Http/Controllers/AnnounceController.php';

    if (!\function_exists('config')) {
        function config(string $key): mixed
        {
            return Container::getInstance()->make('config')->get($key);
        }
    }

    if (!\function_exists('now')) {
        function now(): Carbon
        {
            return Carbon::now();
        }
    }

    if (!\function_exists('cache')) {
        function cache(): MemoryCache
        {
            return Container::getInstance()->make('cache');
        }
    }

    if (!\function_exists('response')) {
        function response(string $content, int $status = 200, array $headers = []): Response
        {
            return new Response($content, $status, $headers);
        }
    }
    function check(bool $condition, string $reason): void
    {
        if (!$condition) {
            throw new RuntimeException($reason);
        }
    }
    function invoke(string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod(AnnounceController::class, $method))->invoke(new AnnounceController(), ...$arguments);
    }
    function query(string $event = '', int $numwant = 25, int $left = 1000, string $peerId = 'current-peer-0000000'): AnnounceQueryDTO
    {
        return new AnnounceQueryDTO(50000, 0, 0, $left, 0, $numwant, $event, '', 'qBittorrent/5.0', str_repeat('x', 20), $peerId, inet_pton('192.0.2.1'));
    }
    function peer(int $id, bool $seeder = true, bool $visible = true, bool $active = true, int $userId = 2, ?string $peerId = null, string $ip = '192.0.2.2'): Peer
    {
        return new Peer(['id' => $id, 'torrent_id' => 1, 'user_id' => $userId, 'peer_id' => $peerId ?? \sprintf('fixture-peer-%07d', $id), 'ip' => inet_pton($ip), 'port' => 50000 + $id, 'seeder' => $seeder, 'active' => $active, 'visible' => $visible]);
    }
    function torrent(array $peers): Torrent
    {
        return (new Torrent(['id' => 1, 'times_completed' => 42]))->setRelation('peers', new Collection($peers));
    }
    function user(): User
    {
        return new User(['id' => 1]);
    }
    function decode(string $value, int &$offset = 0): mixed
    {
        $type = $value[$offset];

        if ($type === 'i') {
            $end = strpos($value, 'e', ++$offset);
            $result = (int) substr($value, $offset, $end - $offset);
            $offset = $end + 1;

            return $result;
        }

        if ($type === 'd') {
            $offset++;
            $result = [];

            while ($value[$offset] !== 'e') {
                $key = decode($value, $offset);
                $result[$key] = decode($value, $offset);
            }
            $offset++;

            return $result;
        }
        $colon = strpos($value, ':', $offset);
        $length = (int) substr($value, $offset, $colon - $offset);
        $offset = $colon + 1;
        $result = substr($value, $offset, $length);
        $offset += $length;

        return $result;
    }
    function success(AnnounceQueryDTO $queries, Torrent $torrent): array
    {
        $body = invoke('generateSuccessAnnounceResponse', $queries, $torrent, user());
        $offset = 0;
        $result = decode($body, $offset);
        check($offset === \strlen($body), 'Bencoded response must consume all bytes');
        $keys = array_keys($result);
        $sorted = $keys;
        sort($sorted, SORT_STRING);
        check($keys === $sorted, 'Bencoded dictionary keys must be sorted');
        check($result['interval'] >= 1800 && $result['interval'] <= 3600, 'Regular interval preserved');
        check($result['min interval'] >= 1710 && $result['min interval'] <= 1800, 'Minimum interval preserved');

        return $result;
    }
    function expectTrackerError(int $code, callable $callback): void
    {
        try {
            $callback();
        } catch (TrackerException $exception) {
            check($exception->getCode() === $code, 'Unexpected tracker error '.$exception->getCode());

            return;
        }

        throw new RuntimeException('Expected tracker error '.$code);
    }
    class MemoryRedis
    {
        public array $values = [];
        public array $hashes = [];
        public array $hashExpires = [];
        public bool $failMetrics = false;
        public function eval(string $script, int $keyCount, mixed ...$arguments): int
        {
            if ($this->failMetrics) {
                throw new RuntimeException('Isolated metrics failure');
            }
            check($keyCount === 1 && \count($arguments) === 3, 'Metrics use exactly one key, one field and a TTL');
            check(str_contains($script, "redis.call('HINCRBY'") && str_contains($script, "redis.call('EXPIRE'"), 'Increment and TTL share one atomic script');
            [$key, $field, $ttl] = $arguments;
            $this->hashes[$key][$field] = ($this->hashes[$key][$field] ?? 0) + 1;
            $this->hashExpires[$key] = now()->timestamp + $ttl;

            return $this->hashes[$key][$field];
        }
        public function connection(string $name): self
        {
            check($name === 'announce', 'Only isolated announce connection is permitted');

            return $this;
        }
        public function command(string $command, array $arguments): mixed
        {
            $key = $arguments[0];
            $entry = $this->values[$key] ?? null;

            if ($entry !== null && $entry['expires'] <= now()->timestamp) {
                unset($this->values[$key]);
                $entry = null;
            }

            if ($command === 'TTL') {
                return $entry === null ? -2 : $entry['expires'] - now()->timestamp;
            }

            if ($command === 'DEL') {
                unset($this->values[$key]);

                return $entry === null ? 0 : 1;
            }
            check($command === 'SET', 'Unexpected Redis command '.$command);
            $options = $arguments[2];

            if ($entry === null || !\in_array('NX', $options, true)) {
                $this->values[$key] = ['value' => $arguments[1], 'expires' => now()->timestamp + $options['EX']];
            }

            return $entry['value'] ?? false;
        }
    }

    class MemoryCache
    {
        public bool $fail = false;
        public array $values;
        public function __construct()
        {
            $this->values = [
                'client_blacklist'                                   => [],
                'user:'.str_repeat('a', 32)                          => new User(['id' => 1, 'group_id' => 2, 'can_download' => true]),
                'denied_groups'                                      => (object) ['banned_id' => 91, 'validating_id' => 92, 'disabled_id' => 93],
                'group:2'                                            => new Group(['id' => 2, 'download_slots' => null, 'is_immune' => true, 'is_freeleech' => false, 'is_double_upload' => false]),
                'announce-torrents:by-infohash:'.str_repeat('x', 20) => new Torrent(['id' => 1, 'free' => 0, 'doubleup' => false, 'status' => 1, 'seeders' => 0, 'leechers' => 0, 'times_completed' => 42]),
            ];
        }
        public function rememberForever(string $key, callable $callback): mixed
        {
            return $this->remember($key, 3600, $callback);
        }
        public function remember(string $key, int $seconds, callable $callback): mixed
        {
            if ($this->fail) {
                throw new RuntimeException('Isolated cache failure');
            }
            check(\array_key_exists($key, $this->values), 'Unexpected cache lookup must not fall through to real data');

            return $this->values[$key];
        }
        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }
        public function increment(string $key): void
        {
            $this->values[$key] = ($this->values[$key] ?? 0) + 1;
        }
        public function decrement(string $key): void
        {
            $this->values[$key] = ($this->values[$key] ?? 0) - 1;
        }
    }

    function requestFixture(int $left = 0, string $event = 'started'): Request
    {
        $request = Request::create('/announce/'.str_repeat('a', 32), 'GET', [
            'info_hash' => str_repeat('x', 20), 'peer_id' => 'current-peer-0000000',
            'port'      => '50000', 'uploaded' => '0', 'downloaded' => '0', 'left' => (string) $left,
            'numwant'   => '0', 'event' => $event,
        ], [], [], ['REMOTE_ADDR' => '192.0.2.1', 'HTTP_USER_AGENT' => 'qBittorrent/5.0']);

        foreach (['accept-language', 'referer', 'accept-charset', 'want-digest'] as $header) {
            $request->headers->remove($header);
        }

        return $request;
    }
    function outcomeKey(): string
    {
        return config('cache.prefix').':announce:outcomes:'.now()->utc()->format('YmdH');
    }

    Container::setInstance(new Container());
    Container::getInstance()->instance('config', new class () extends Illuminate\Support\Fluent {
        public string $prefix = 'isolated-tracker-test';
        public function get($key, $default = null)
        {
            return ['cache.prefix' => $this->prefix, 'announce.rate_limit' => 3, 'announce.slots_system.enabled' => true][$key] ?? parent::get($key, $default);
        }
    });
    Container::getInstance()->instance(Illuminate\Contracts\Routing\ResponseFactory::class, new class () {
        public function make(string $content, int $status = 200, array $headers = []): Response
        {
            return new Response($content, $status, $headers);
        }
    });
    Facade::setFacadeApplication(Container::getInstance());
    $capsule = new Manager(Container::getInstance());
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $capsule->schema()->create('peers', static function (Blueprint $table): void {
        $table->increments('id');

        foreach (['torrent_id', 'user_id', 'downloaded', 'uploaded', 'left', 'port'] as $column) {
            $table->unsignedBigInteger($column)->default(0);
        }

        foreach (['seeder', 'active', 'visible'] as $column) {
            $table->boolean($column)->default(true);
        }
        $table->binary('peer_id');
        $table->binary('ip');
        $table->timestamps();
    });

    $tests = [];
    $tests['swarm counts exceed returned peer sample'] = static function (): void {
        $peers = [];

        for ($i = 1; $i <= 40; $i++) {
            $peers[] = peer($i);
        }

        for ($i = 41; $i <= 50; $i++) {
            $peers[] = peer($i, false);
        }
        $result = success(query(), torrent($peers));
        check($result['complete'] === 40 && $result['incomplete'] === 10, 'Counts must include the whole active visible swarm');
        check(\strlen($result['peers']) === 25 * 6, 'IPv4 sample remains bounded at25');
    };
    $tests['numwant zero returns no peers but accurate counts'] = static function (): void {
        $result = success(query(numwant: 0), torrent([peer(1), peer(2, false)]));
        check($result['peers'] === '', 'numwant0 must return an empty peer list');
        check($result['complete'] === 1 && $result['incomplete'] === 1, 'Counts independent of numwant');
    };
    $tests['counts and discovery exclude inactive and invisible peers'] = static function (): void {
        $result = success(query(), torrent([peer(1), peer(2, false), peer(3, visible: false), peer(4, active: false)]));
        check($result['complete'] === 1 && $result['incomplete'] === 1, 'Only active visible peers should be advertised and counted');
        check(\strlen($result['peers']) === 12, 'Only discoverable peers are returned');
    };
    $tests['seeder discovers only other users leechers'] = static function (): void {
        $result = success(query(left: 0), torrent([peer(1), peer(2, false), peer(3, false, userId: 1)]));
        check($result['peers'] === inet_pton('192.0.2.2').pack('n', 50002), 'Seeder discovery excludes seeders and own account');
        check($result['complete'] === 1 && $result['incomplete'] === 2, 'Discovery exclusions do not distort swarm counts');
    };
    $tests['IPv4 and IPv6 peers retain compact encoding'] = static function (): void {
        $result = success(query(), torrent([peer(1), peer(2, ip: '2001:db8::2')]));
        check($result['peers'] === inet_pton('192.0.2.2').pack('n', 50001), 'IPv4 compact payload exact');
        check($result['peers6'] === inet_pton('2001:db8::2').pack('n', 50002), 'IPv6 compact payload exact');
    };
    $tests['stopped event returns no peers and keeps swarm counts'] = static function (): void {
        $result = success(query(event: 'stopped'), torrent([peer(1), peer(2, false)]));
        check($result['peers'] === '', 'Stopped peers must receive no peer list');
        check($result['complete'] === 1 && $result['incomplete'] === 1, 'Stopped response must not invent zero swarm counts');
    };
    $tests['new fourth peer rejected at configured limit'] = static function (): void {
        $torrent = torrent([peer(1, userId: 1), peer(2, userId: 1), peer(3, userId: 1)]);
        expectTrackerError(138, static fn () => invoke('checkMaxConnections', $torrent, user(), query(event: 'started')));
    };
    $tests['existing peer can announce when old swarm exceeded limit'] = static function (): void {
        $torrent = torrent([peer(1, userId: 1, peerId: 'current-peer-0000000'), peer(2, userId: 1), peer(3, userId: 1), peer(4, userId: 1)]);
        invoke('checkMaxConnections', $torrent, user(), query());
    };
    $tests['stopped event allowed while connection limit exceeded'] = static function (): void {
        $torrent = torrent([peer(1, userId: 1), peer(2, userId: 1), peer(3, userId: 1), peer(4, userId: 1)]);
        invoke('checkMaxConnections', $torrent, user(), query(event: 'stopped'));
    };
    $tests['new peer below connection limit is admitted'] = static function (): void {
        $torrent = torrent([peer(1, userId: 1), peer(2, userId: 1), peer(3, userId: 2)]);
        invoke('checkMaxConnections', $torrent, user(), query(event: 'started'));
    };
    $tests['another user peer identity cannot bypass connection limit'] = static function (): void {
        $torrent = torrent([peer(1, userId: 1), peer(2, userId: 1), peer(3, userId: 1), peer(4, userId: 2, peerId: 'current-peer-0000000')]);
        expectTrackerError(138, static fn () => invoke('checkMaxConnections', $torrent, user(), query(event: 'started')));
    };
    $tests['inactive old peer cannot bypass connection limit'] = static function (): void {
        $torrent = torrent([peer(1, userId: 1), peer(2, userId: 1), peer(3, userId: 1), peer(4, active: false, userId: 1, peerId: 'current-peer-0000000')]);
        expectTrackerError(138, static fn () => invoke('checkMaxConnections', $torrent, user(), query(event: 'started')));
    };
    $tests['ordinary early announces retain minimum interval protection'] = static function (): void {
        invoke('checkMinInterval', torrent([]), query(), user());
        Carbon::setTestNow(now()->addSeconds(60));
        expectTrackerError(162, static fn () => invoke('checkMinInterval', torrent([]), query(), user()));
    };
    $tests['legitimate restart can announce before regular interval'] = static function (): void {
        invoke('checkMinInterval', torrent([]), query(), user());
        Carbon::setTestNow(now()->addSeconds(60));
        invoke('checkMinInterval', torrent([]), query(event: 'started'), user());
    };
    $tests['rapid stop and resume does not trap client for30 minutes'] = static function (): void {
        invoke('checkMinInterval', torrent([]), query(event: 'started'), user());
        Carbon::setTestNow(now()->addSeconds(5));
        invoke('checkMinInterval', torrent([]), query(event: 'stopped'), user());
        Carbon::setTestNow(now()->addSeconds(5));
        invoke('checkMinInterval', torrent([]), query(event: 'started'), user());
    };
    $tests['duplicate start still rejected without intervening stop'] = static function (): void {
        invoke('checkMinInterval', torrent([]), query(event: 'started'), user());
        Carbon::setTestNow(now()->addSeconds(5));
        expectTrackerError(162, static fn () => invoke('checkMinInterval', torrent([]), query(event: 'started'), user()));
    };
    $tests['completed and stopped events allowed before regular interval'] = static function (): void {
        invoke('checkMinInterval', torrent([]), query(), user());
        Carbon::setTestNow(now()->addSeconds(5));
        invoke('checkMinInterval', torrent([]), query(event: 'completed'), user());
        invoke('checkMinInterval', torrent([]), query(event: 'stopped'), user());
    };

    $tests['full request success records only bounded hourly outcome'] = static function (): void {
        $response = (new AnnounceController())->index(requestFixture(), str_repeat('a', 32));
        check($response->getStatusCode() === 200 && !str_contains($response->getContent(), 'failure reason'), 'Full valid controller request succeeds');
        check(\count(ProcessAnnounce::$dispatched) === 1, 'Exactly one isolated queue dispatch');
        check(Redis::getFacadeRoot()->hashes === [outcomeKey() => ['success' => 1]], 'Metrics contain only one aggregate success');
        check(Redis::getFacadeRoot()->hashExpires[outcomeKey()] === now()->timestamp + 172800, 'Outcome hash expires in48 hours');
    };
    $tests['full request warning records slot rejection without changing policy'] = static function (): void {
        cache()->values['group:2']->download_slots = 0;
        $response = (new AnnounceController())->index(requestFixture(left: 1000), str_repeat('a', 32));
        check(str_contains($response->getContent(), 'warning message'), 'Slot denied request remains a warning');
        check(ProcessAnnounce::$dispatched[0][3] === false, 'Denied peer remains invisible');
        check(Redis::getFacadeRoot()->hashes === [outcomeKey() => ['warning_164' => 1]], 'Slot warning is counted separately');
    };
    $tests['full request records duplicate and regular interval failures separately'] = static function (): void {
        $controller = new AnnounceController();
        $controller->index(requestFixture(), str_repeat('a', 32));
        $duplicate = $controller->index(requestFixture(), str_repeat('a', 32));
        $regular = $controller->index(requestFixture(event: ''), str_repeat('a', 32));
        check(str_contains($duplicate->getContent(), 'failure reason') && str_contains($regular->getContent(), 'failure reason'), 'Existing failure responses preserved');
        check(\count(ProcessAnnounce::$dispatched) === 1, 'Rejected repeats do not dispatch accounting');
        check(Redis::getFacadeRoot()->hashes[outcomeKey()] === ['success' => 1, 'rejected_162_duplicate' => 1, 'rejected_162_interval' => 1], 'Same controller resets request outcome and distinguishes guards');
    };
    $tests['full invalid request and internal failure record private fixed codes'] = static function (): void {
        $invalid = (new AnnounceController())->index(new Request(), str_repeat('a', 32));
        check(str_contains($invalid->getContent(), 'Invalid request !'), 'Invalid request response preserved');
        cache()->fail = true;
        $internal = (new AnnounceController())->index(requestFixture(), str_repeat('a', 32));
        check($internal->getContent() === 'd14:failure reason21:Internal Server Error8:intervali5400e12:min intervali5400ee', 'Internal error response preserved exactly');
        check(Redis::getFacadeRoot()->hashes[outcomeKey()] === ['rejected_129' => 1, 'internal_error' => 1], 'No raw exception or request data recorded');
    };
    $tests['broken metrics store cannot change tracker response'] = static function (): void {
        $expected = (new AnnounceController())->index(new Request(), str_repeat('a', 32));
        Redis::getFacadeRoot()->failMetrics = true;
        $actual = (new AnnounceController())->index(new Request(), str_repeat('a', 32));
        check($expected->getStatusCode() === $actual->getStatusCode() && $expected->getContent() === $actual->getContent(), 'Metrics failure must preserve exact HTTP status and body');
    };
    $tests['unknown outcome cannot create identifier fields'] = static function (): void {
        invoke('recordAnnounceOutcome', 'request-private-example');
        check(Redis::getFacadeRoot()->hashes === [outcomeKey() => ['internal_error' => 1]], 'Only fixed allowlisted fields can be stored');
    };


    $tests['known routine early announce is acknowledged without accounting'] = static function (): void {
        $p = peer(1, userId: 1, peerId: 'current-peer-0000000');
        $p->save();
        $controller = new AnnounceController();
        $controller->index(requestFixture(event: ''), str_repeat('a', 32));
        $timer = config('cache.prefix').'peer-last-announced:1-1-current-peer-0000000';
        $expiry = Redis::getFacadeRoot()->values[$timer]['expires'];
        $original = Peer::find(1)->getAttributes();
        Carbon::setTestNow(now()->addSeconds(60));
        $response = $controller->index(requestFixture(event: ''), str_repeat('a', 32));
        $offset = 0;
        $body = decode($response->getContent(), $offset);
        check(!isset($body['failure reason']) && $body['complete'] === 1, 'Known peer gets accurate non-failure response');
        check($body['interval'] === $expiry - now()->timestamp && $body['min interval'] === $body['interval'], 'Reply uses remaining timer, not another full delay');
        check(count(ProcessAnnounce::$dispatched) === 1, 'Early request must not dispatch transfer/history writes');
        check(Peer::find(1)->getAttributes() === $original, 'Acknowledgement must not mutate counters or last seen');
        check(Redis::getFacadeRoot()->values[$timer]['expires'] === $expiry, 'Acknowledgement must not extend original timer');
        check(Redis::getFacadeRoot()->hashes[outcomeKey()]['deferred_162_interval'] === 1, 'Deferred request has its own outcome');
        Carbon::setTestNow(Carbon::createFromTimestamp($expiry + 1, 'UTC'));
        $response = $controller->index(requestFixture(event: ''), str_repeat('a', 32));
        check(!str_contains($response->getContent(), 'failure reason'), 'Next due request succeeds');
        check(count(ProcessAnnounce::$dispatched) === 2, 'Normal accounting resumes after original timer expires');
    };
    $tests['known duplicate routine announces do not produce tracker errors'] = static function (): void {
        peer(1, userId: 1, peerId: 'current-peer-0000000')->save();
        $controller = new AnnounceController();
        $controller->index(requestFixture(event: ''), str_repeat('a', 32));
        $response = $controller->index(requestFixture(event: ''), str_repeat('a', 32));
        check(!str_contains($response->getContent(), 'failure reason'), 'Duplicate routine request is acknowledged');
        check(count(ProcessAnnounce::$dispatched) === 1, 'Duplicate must not be processed twice');
        check(Redis::getFacadeRoot()->hashes[outcomeKey()]['deferred_162_duplicate'] === 1, 'Duplicate is observable separately');
    };
    $tests['invisible stopped expired and foreign peers retain rejection'] = static function (): void {
        foreach (['invisible', 'stopped', 'expired', 'foreign'] as $case) {
            Peer::query()->delete();
            Redis::swap(new MemoryRedis());
            ProcessAnnounce::$dispatched = [];
            $p = peer(1, visible: $case !== 'invisible', active: $case !== 'stopped', userId: $case === 'foreign' ? 2 : 1, peerId: 'current-peer-0000000');
            $p->updated_at = $case === 'expired' ? now()->subHours(3) : now();
            $p->save();
            invoke('checkMinInterval', torrent([]), query(), user());
            $response = (new AnnounceController())->index(requestFixture(event: ''), str_repeat('a', 32));
            check(str_contains($response->getContent(), 'failure reason'), 'Do not acknowledge '.$case.' peer');
            check(ProcessAnnounce::$dispatched === [], 'Rejected request has no accounting side effects');
        }
    };
    $tests['lifecycle events never use routine acknowledgement'] = static function (): void {
        peer(1, userId: 1, peerId: 'current-peer-0000000')->save();
        $controller = new AnnounceController();
        $controller->index(requestFixture(event: 'started'), str_repeat('a', 32));
        $repeat = $controller->index(requestFixture(event: 'started'), str_repeat('a', 32));
        check(str_contains($repeat->getContent(), 'failure reason'), 'Duplicate lifecycle guard preserved');
        check(count(ProcessAnnounce::$dispatched) === 1, 'Lifecycle repeats not double processed');
        Carbon::setTestNow(now()->addSeconds(5));
        $stopped = $controller->index(requestFixture(event: 'stopped'), str_repeat('a', 32));
        check(!str_contains($stopped->getContent(), 'failure reason') && count(ProcessAnnounce::$dispatched) === 2, 'Stop is processed rather than acknowledged');
    };
    $tests['revoked download rights and banned accounts cannot use acknowledgement'] = static function (): void {
        peer(1, seeder: false, userId: 1, peerId: 'current-peer-0000000')->save();
        invoke('checkMinInterval', torrent([]), query(), user());
        cache()->values['user:'.str_repeat('a', 32)]->can_download = false;
        $denied = (new AnnounceController())->index(requestFixture(left: 1000, event: ''), str_repeat('a', 32));
        check(str_contains($denied->getContent(), 'failure reason'), 'Download-rights check remains before acknowledgement');
        cache()->values['user:'.str_repeat('a', 32)]->can_download = true;
        cache()->values['denied_groups']->banned_id = 2;
        $banned = (new AnnounceController())->index(requestFixture(event: ''), str_repeat('a', 32));
        check(str_contains($banned->getContent(), 'failure reason'), 'Banned group cannot receive peers');
        check(ProcessAnnounce::$dispatched === [], 'Neither denied request dispatches');
    };

    if (getenv('TRACKER_TEST_REDIS_HOST')) {
        $tests['real Redis NX GET lifecycle and private metrics TTL'] = static function (): void {
            $host = getenv('TRACKER_TEST_REDIS_HOST');
            check(\in_array($host, ['127.0.0.1', '::1', 'localhost'], true), 'Redis integration requires an isolated localhost service');
            check(\extension_loaded('redis'), 'The phpredis extension is required');
            $native = new \Redis();
            $native->connect($host, (int) (getenv('TRACKER_TEST_REDIS_PORT') ?: 6379), 2);
            $config = Container::getInstance()->make('config');
            $oldPrefix = $config->prefix;
            $config->prefix = 'isolated-tracker-'.bin2hex(random_bytes(12));
            $connection = new Illuminate\Redis\Connections\PhpRedisConnection($native);
            $proxy = new class ($connection) {
                public function __construct(private Illuminate\Redis\Connections\PhpRedisConnection $redis)
                {
                }
                public function connection(string $name): Illuminate\Redis\Connections\PhpRedisConnection
                {
                    check($name === 'announce', 'Only isolated announce connection permitted');

                    return $this->redis;
                }
            };
            Redis::swap($proxy);
            $testKey = $config->prefix.':nx-get';
            $timerKey = $config->prefix.'peer-last-announced:1-1-current-peer-0000000';
            $eventKey = $config->prefix.'announce-lock:1-1-current-peer-0000000-';
            $cleanup = [$testKey, $timerKey, $eventKey.'started', $eventKey.'stopped', $eventKey.'empty', outcomeKey()];

            try {
                check($connection->command('SET', [$testKey, 42, ['NX', 'GET', 'EX' => 1]]) === false, 'Fresh Redis NX GET returns false');
                check((int) $connection->command('SET', [$testKey, 99, ['NX', 'GET', 'EX' => 1]]) === 42, 'Existing Redis NX GET returns previous value without overwrite');
                sleep(2);
                check($connection->command('SET', [$testKey, 99, ['NX', 'GET', 'EX' => 30]]) === false, 'Expired Redis NX GET admits a fresh timer');
                invoke('checkMinInterval', torrent([]), query(event: 'started'), user());
                check($native->ttl($timerKey) >= 1528 && $native->ttl($timerKey) <= 1710, 'Actual regular timer TTL preserved');
                expectTrackerError(162, static fn () => invoke('checkMinInterval', torrent([]), query(event: 'started'), user()));
                invoke('checkMinInterval', torrent([]), query(event: 'stopped'), user());
                invoke('checkMinInterval', torrent([]), query(event: 'started'), user());
                invoke('recordAnnounceOutcome', 'success');
                invoke('recordAnnounceOutcome', 'unknown-private-value');
                $invalid = (new AnnounceController())->index(new Request(), str_repeat('a', 32));
                check(str_contains($invalid->getContent(), 'Invalid request !'), 'Real Redis metrics preserve full request response');
                $hash = $native->hGetAll(outcomeKey());
                ksort($hash);
                check($hash === ['internal_error' => '1', 'rejected_129' => '1', 'success' => '1'], 'Real Redis hash contains only fixed aggregate counts');
                check($native->ttl(outcomeKey()) >= 172798 && $native->ttl(outcomeKey()) <= 172800, 'Real Redis Lua applies48hour TTL');
            } finally {
                $native->del($cleanup);
                $native->close();
                $config->prefix = $oldPrefix;
            }
        };
    }

    $failed = 0;

    foreach ($tests as $label => $test) {
        Carbon::setTestNow('2026-10-30T00:00:00Z');
        Redis::swap(new MemoryRedis());
        Container::getInstance()->instance('cache', new MemoryCache());
        ProcessAnnounce::$dispatched = [];
        Peer::query()->delete();

        try {
            $test();
            echo 'PASS '.$label.PHP_EOL;
        } catch (Throwable $exception) {
            $failed++;
            echo 'FAIL '.$label.': '.$exception->getMessage().PHP_EOL;
        }
    }
    Carbon::setTestNow();
    echo \count($tests).' regression groups, '.$failed.' failed'.PHP_EOL;

    exit($failed === 0 ? 0 : 1);
}
