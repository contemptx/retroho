<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Console\Commands;

use App\Models\History;
use App\Models\Peer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

class AutoFlushPeers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto:flush_peers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flushes Ghost Peers';

    /**
     * Execute the console command.
     *
     * @throws Exception|Throwable If there is an error during the execution of the command.
     */
    final public function handle(): void
    {
        $carbon = new Carbon();
        $cutoff = $carbon->copy()->subHours(2)->toDateTimeString();
        $peers = Peer::select(['id', 'torrent_id', 'user_id', 'seeder', 'updated_at'])
            ->where('updated_at', '<', $cutoff)
            ->where('active', '=', 1)
            ->get();

        foreach ($peers as $peer) {
            DB::transaction(static function () use ($peer, $cutoff): void {
                // An announce may have refreshed this peer after the initial scan.
                $expired = Peer::query()
                    ->whereKey($peer->id)
                    ->where('active', '=', 1)
                    ->where('updated_at', '<', $cutoff)
                    ->update([
                        'active'     => false,
                        'updated_at' => DB::raw('updated_at'),
                    ]);

                if ($expired === 0) {
                    return;
                }

                // History belongs to the user/torrent pair, not this one location.
                History::query()
                    ->where('torrent_id', '=', $peer->torrent_id)
                    ->where('user_id', '=', $peer->user_id)
                    ->where('active', '=', 1)
                    ->where('updated_at', '<', $cutoff)
                    ->whereNotExists(static fn ($query) => $query
                        ->selectRaw('1')
                        ->from('peers')
                        ->whereColumn('peers.user_id', 'history.user_id')
                        ->whereColumn('peers.torrent_id', 'history.torrent_id')
                        ->where('peers.active', '=', 1))
                    ->update([
                        'active'     => false,
                        'updated_at' => DB::raw('updated_at'),
                    ]);
            }, 5);
        }

        // Keep peers that stopped being announced without a `stopped` event
        // in case a user has internet issues and comes back online within the
        // next 2 days
        $deleteCutoff = $carbon->copy()->subDays(2)->toDateTimeString();
        $peers = Peer::select(['id', 'user_id'])
            ->where('updated_at', '<', $deleteCutoff)
            ->where('active', '=', 0)
            ->get();

        foreach ($peers as $peer) {
            // A stopped peer may resume after the deletion candidates were read.
            $deleted = Peer::query()
                ->whereKey($peer->id)
                ->where('active', '=', 0)
                ->where('updated_at', '<', $deleteCutoff)
                ->delete();

            if ($deleted === 1) {
                cache()->decrement('user-leeching-count:'.$peer->user_id);
            }
        }

        $this->comment('Automated Flush Ghost Peers Command Complete');
    }
}
