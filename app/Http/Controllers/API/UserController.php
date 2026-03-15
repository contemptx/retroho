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
            'username'    => [
                'nullable',
                'string',
                'alpha_dash',
                'between:3,25',
                'unique:users',
            ],
        ]);

        // Use provided username or parse from email (everything before the @)
        $username = $request->username ?? Str::before($request->email, '@');

        // If username was derived from email, make sure it's unique, append digits if needed
        // If username was provided, validation already ensures uniqueness
        if (!$request->has('username')) {
            $originalUsername = $username;
            $counter = 1;

            while (User::where('username', $username)->exists()) {
                $username = $originalUsername.$counter;
                $counter++;
            }
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
        Mail::to($user->email)->send(new WelcomeNewUser($user->username, $password));

        return $this->sendResponse([
            'id'          => $user->id,
            'customer_id' => $request->customer_id,
            'username'    => $user->username,
            'email'       => $user->email,
            'password'    => $user->password,
        ], 'User created successfully');
    }

    /**
 * Attach torrents to a user by customer_id, username, or email.
 *
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
final public function attachTorrents(Request $request): \Illuminate\Http\JsonResponse
{
    // Check if an authenticated user has owner permissions
    if (!auth()->user()->group->is_owner) {
        return $this->sendError('Unauthorized', 'You do not have permission to attach torrents to users', 403);
    }

    // Validate the request - require exactly one identifier
    $request->validate([
        'customer_id' => 'nullable|integer|exists:users,customer_id',
        'username'    => 'nullable|string|exists:users,username',
        'email'       => 'nullable|string|email|exists:users,email',
        'torrent_sku' => 'required|array|min:1',
        'torrent_sku.*' => 'integer|exists:torrents,id',
    ], [
        'customer_id.exists' => 'No user found with the provided customer_id.',
        'username.exists'    => 'No user found with the provided username.',
        'email.exists'      => 'No user found with the provided email.',
    ]);

    // Ensure exactly one identifier is provided
    $identifiers = array_filter([
        'customer_id' => $request->customer_id,
        'username'    => $request->username,
        'email'       => $request->email,
    ]);

    if (count($identifiers) !== 1) {
        return $this->sendError(
            'Validation Error',
            'You must provide exactly one of: customer_id, username, or email',
            422
        );
    }

    // Find the user by the provided identifier
    $user = null;
    if ($request->has('customer_id') && $request->customer_id !== null) {
        $user = User::where('customer_id', $request->customer_id)->firstOrFail();
    } elseif ($request->has('username') && $request->username !== null) {
        $user = User::where('username', $request->username)->firstOrFail();
    } elseif ($request->has('email') && $request->email !== null) {
        $user = User::where('email', $request->email)->firstOrFail();
    }

    if (!$user) {
        return $this->sendError('User Not Found', 'Unable to find user with the provided identifier', 404);
    }

    // Since validation already ensures all torrent IDs exist, use them directly
    // Ensure they're integers for consistency
    $torrentIds = array_map('intval', $request->torrent_sku);
    
    // Attach torrents to user (sync without detaching existing)
    $user->attachedTorrents()->syncWithoutDetaching($torrentIds);

    // Get the updated list of attached torrents
    $attachedTorrents = $user->attachedTorrents()->pluck('torrents.id')->toArray();

    return $this->sendResponse([
        'customer_id'       => $user->customer_id,
        'user_id'           => $user->id,
        'username'          => $user->username,
        'email'             => $user->email,
        'attached_torrents' => $attachedTorrents,
        'newly_attached'    => $torrentIds,
    ], 'Torrents attached successfully');
}

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
