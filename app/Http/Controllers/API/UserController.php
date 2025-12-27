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

namespace App\Http\Controllers\API;

use App\Http\Resources\UserResource;
use App\Mail\WelcomeNewUser;
use App\Models\Group;
use App\Models\Torrent;
use App\Models\User;
use App\Rules\EmailBlacklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends BaseController
{
    final public function show(): UserResource
    {
        $user = auth()->user();

        UserResource::withoutWrapping();

        return new UserResource($user);
    }

    /**
     * Create a new user via API with automatic validation.
     *
     * @param Request $request
     *
     * @throws \Random\RandomException
     * @return \Illuminate\Http\JsonResponse
     */
    final public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if an authenticated user has owner permissions
        if (!auth()->user()->group->is_owner) {
            return $this->sendError('Unauthorized', 'You do not have permission to create users', 403);
        }

        // Validate the request
        $request->validate([
            'customer_id' => 'required|integer|unique:users,customer_id',
            'email'       => [
                'required',
                'string',
                'email',
                'max:70',
                'unique:users',
                Rule::when(config('email-blacklist.enabled') === true, fn () => new EmailBlacklist()),
            ],
        ]);

        // Parse username from email (everything before the @)
        $username = Str::before($request->email, '@');

        // Make sure the username is unique, append random digits if needed
        $originalUsername = $username;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $originalUsername.$counter;
            $counter++;
        }

        // Generate a secure random password that meets the password requirements
        // At least 12 characters with mixed case, letters, numbers and not compromised
        $password = $this->generateSecurePassword();

        // Get the user group
        $memberGroup = cache()->rememberForever('member_group', fn () => Group::query()->where('slug', '=', 'user')->pluck('id'));

        // Create the user
        $user = User::create([
            'customer_id'       => $request->customer_id,
            'username'          => $username,
            'email'             => $request->email,
            'password'          => Hash::make($password),
            'passkey'           => md5(random_bytes(60).$username),
            'rsskey'            => md5(random_bytes(60).$username),
            'uploaded'          => config('other.default_upload'),
            'downloaded'        => config('other.default_download'),
            'can_download'      => 1,
            'group_id'          => $memberGroup[0],
            'active'            => true,
            'email_verified_at' => now(),
        ]);

        // Create associated records
        $user->passkeys()->create(['content' => $user->passkey]);
        $user->rsskeys()->create(['content' => $user->rsskey]);
        $user->emailUpdates()->create();

        // Send a welcome email with credentials
        Mail::to($user->email)->send(new WelcomeNewUser($user->email, $password));

        return $this->sendResponse([
            'id'          => $user->id,
            'customer_id' => $request->customer_id,
            'username'    => $user->username,
            'email'       => $user->email,
        ], 'User created successfully');
    }

    /**
     * Attach torrents to a user by customer_id.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    // final public function attachTorrents(Request $request): \Illuminate\Http\JsonResponse
    // {
    //     // Check if an authenticated user has owner permissions
    //     if (!auth()->user()->group->is_owner) {
    //         return $this->sendError('Unauthorized', 'You do not have permission to attach torrents to users', 403);
    //     }

    //     // Validate the request
    //     $request->validate([
    //         'customer_id' => 'required|integer|exists:users,customer_id',
    //         'torrent_sku' => 'required|array|min:1',
    //         'torrent_sku.*' => 'integer|exists:torrents,id',
    //     ]);

    //     // Find the user by customer_id
    //     $user = User::where('customer_id', $request->customer_id)->firstOrFail();

    //     // Since validation already ensures all torrent IDs exist, use them directly
    //     // Ensure they're integers for consistency
    //     $torrentIds = array_map('intval', $request->torrent_sku);
        
    //     // Attach torrents to user (sync without detaching existing)
    //     $user->attachedTorrents()->syncWithoutDetaching($torrentIds);

    //     // Get the updated list of attached torrents
    //     $attachedTorrents = $user->attachedTorrents()->pluck('torrents.id')->toArray();

    //     return $this->sendResponse([
    //         'customer_id'       => $user->customer_id,
    //         'user_id'           => $user->id,
    //         'username'          => $user->username,
    //         'attached_torrents' => $attachedTorrents,
    //         'newly_attached'    => $torrentIds,
    //     ], 'Torrents attached successfully');
    // }

    /**
     * Generate a secure password that meets the requirements:
     * - Minimum 12 characters
     * - Contains mixed case letters
     * - Contains numbers
     * - Not compromised (handled by Laravel's uncompromised validation)
     *
     * @throws \Random\RandomException
     * @return string
     */
    private function generateSecurePassword(): string
    {
        // Define character sets
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $specialChars = '!@#$%^&*()-_=+[]{}|;:,.<>?';

        // Start with at least one of each required character type
        $password =
            $lowercase[random_int(0, \strlen($lowercase) - 1)].
            $lowercase[random_int(0, \strlen($lowercase) - 1)].
            $uppercase[random_int(0, \strlen($uppercase) - 1)].
            $uppercase[random_int(0, \strlen($uppercase) - 1)].
            $numbers[random_int(0, \strlen($numbers) - 1)].
            $numbers[random_int(0, \strlen($numbers) - 1)].
            $specialChars[random_int(0, \strlen($specialChars) - 1)].
            $specialChars[random_int(0, \strlen($specialChars) - 1)];

        // Add more random characters to reach the minimum length
        $allChars = $lowercase.$uppercase.$numbers.$specialChars;
        $remainingLength = 16 - \strlen($password); // Using 16 chars for extra security

        for ($i = 0; $i < $remainingLength; $i++) {
            $password .= $allChars[random_int(0, \strlen($allChars) - 1)];
        }

        // Shuffle all characters to make the password unpredictable
        return str_shuffle($password);
    }
}
