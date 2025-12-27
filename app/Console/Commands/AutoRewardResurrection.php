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

use App\Models\Resurrection;
use App\Models\History;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Unit3dAnnounce;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class AutoRewardResurrection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto:reward_resurrection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically Hands Out Rewards For Successful Resurrections';

    /**
     * Execute the console command.
     *
     * @throws Exception|Throwable If there is an error during the execution of the command.
     */
    final public function handle(): void
    {
        foreach (Resurrection::where('rewarded', '!=', 1)->oldest()->get() as $resurrection) {
            $user = User::find($resurrection->user_id);

            $torrent = Torrent::find($resurrection->torrent_id);

            if (isset($user, $torrent)) {
                $history = History::where('torrent_id', '=', $torrent->id)
                    ->where('user_id', '=', $user->id)
                    ->where('seedtime', '>=', $resurrection->seedtime)
                    ->first();
            }

            if (isset($user, $torrent, $history)) {
                $resurrection->rewarded = true;
                $resurrection->save();

                $user->fl_tokens += config('graveyard.reward');
                $user->save();

                // Bump Torrent With FL
                $torrent->bumped_at = Carbon::now();
                $torrent->free = 100;
                $torrent->fl_until = Carbon::now()->addDays(3);
                $torrent->save();

                cache()->forget('announce-torrents:by-infohash:'.$torrent->info_hash);

                Unit3dAnnounce::addTorrent($torrent);

                // Send Private Message
                $user->sendSystemNotification(
                    subject: 'Successful Graveyard Resurrection',
                    message: sprintf('You have successfully resurrected [url=%s/torrents/', $appurl).$torrent->id.']'.$torrent->name.'[/url] ! Thank you for bringing a torrent back from the dead! Enjoy the freeleech tokens!',
                );
            }
        }

        $this->comment('Automated Reward Resurrections Command Complete');
    }
}
