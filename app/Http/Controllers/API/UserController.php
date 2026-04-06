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

        // IMPORTANT: Return the PLAIN TEXT password, not the hashed version
        return $this->sendResponse([
            'id'          => $user->id,
            'customer_id' => $request->customer_id,
            'username'    => $user->username,
            'email'       => $user->email,
            'password'    => $password,  // Plain text password for customer portal
        ], 'User created successfully');
    }

    /**
     * Verify a user exists and get their account details.
     * Used by customer portal to verify account creation and status.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    final public function verify(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if an authenticated user has owner permissions
        if (!auth()->user()->group->is_owner) {
            return $this->sendError('Unauthorized', 'You do not have permission to verify users', 403);
        }

        // Validate the request - require at least one identifier
        $request->validate([
            'customer_id' => 'nullable|integer',
            'username'    => 'nullable|string',
            'email'       => 'nullable|string|email',
        ]);

        // Ensure at least one identifier is provided
        if (!$request->customer_id && !$request->username && !$request->email) {
            return $this->sendError(
                'Validation Error',
                'You must provide at least one of: customer_id, username, or email',
                422
            );
        }

        // Build query based on provided identifiers
        $query = User::query();
        
        if ($request->customer_id) {
            $query->orWhere('customer_id', $request->customer_id);
        }
        if ($request->username) {
            $query->orWhere('username', $request->username);
        }
        if ($request->email) {
            $query->orWhere('email', $request->email);
        }

        $user = $query->first();

        if (!$user) {
            return $this->sendResponse([
                'exists'    => false,
                'searched'  => [
                    'customer_id' => $request->customer_id,
                    'username'    => $request->username,
                    'email'       => $request->email,
                ],
            ], 'User not found');
        }

        // Get attached torrents count
        $attachedTorrentCount = $user->attachedTorrents()->count();
        $attachedTorrentIds = $user->attachedTorrents()->pluck('torrents.id')->toArray();

        return $this->sendResponse([
            'exists'              => true,
            'user_id'             => $user->id,
            'customer_id'         => $user->customer_id,
            'username'            => $user->username,
            'email'               => $user->email,
            'active'              => (bool) $user->active,
            'can_download'        => (bool) $user->can_download,
            'group_id'            => $user->group_id,
            'group_name'          => $user->group->name ?? null,
            'created_at'          => $user->created_at?->toIso8601String(),
            'last_login'          => $user->last_login?->toIso8601String(),
            'attached_torrents'   => $attachedTorrentIds,
            'attached_torrent_count' => $attachedTorrentCount,
        ], 'User found');
    }

    /**
     * Activate a user account.
     * Moves user from validating group to active member group and sets active=true.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    final public function activate(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if an authenticated user has owner permissions
        if (!auth()->user()->group->is_owner) {
            return $this->sendError('Unauthorized', 'You do not have permission to activate users', 403);
        }

        // Validate the request
        $request->validate([
            'customer_id' => 'nullable|integer',
            'username'    => 'nullable|string',
            'email'       => 'nullable|string|email',
        ]);

        // Ensure at least one identifier is provided
        if (!$request->customer_id && !$request->username && !$request->email) {
            return $this->sendError(
                'Validation Error',
                'You must provide at least one of: customer_id, username, or email',
                422
            );
        }

        // Find the user
        $query = User::query();
        
        if ($request->customer_id) {
            $query->orWhere('customer_id', $request->customer_id);
        }
        if ($request->username) {
            $query->orWhere('username', $request->username);
        }
        if ($request->email) {
            $query->orWhere('email', $request->email);
        }

        $user = $query->first();

        if (!$user) {
            return $this->sendError('User Not Found', 'Unable to find user with the provided identifier', 404);
        }

        // Get the member group (active users group)
        $memberGroup = Group::query()->where('slug', '=', 'user')->first();

        if (!$memberGroup) {
            return $this->sendError('Configuration Error', 'Member group not found', 500);
        }

        // Update the user - activate them and move to member group
        $previousGroupId = $user->group_id;
        $previousGroupName = $user->group->name ?? 'Unknown';
        $user->active = true;
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->group_id = $memberGroup->id;
        $user->save();

        return $this->sendResponse([
            'success'             => true,
            'user_id'             => $user->id,
            'customer_id'         => $user->customer_id,
            'username'            => $user->username,
            'email'               => $user->email,
            'active'              => (bool) $user->active,
            'group_id'            => $user->group_id,
            'group_name'          => $memberGroup->name,
            'previous_group_id'   => $previousGroupId,
            'previous_group_name' => $previousGroupName,
            'updated_at'          => $user->updated_at?->toIso8601String(),
        ], 'User activated successfully');
    }

    /**
     * Activate ALL users in validating group.
     * Bulk operation to fix users stuck in validation.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    final public function activateAll(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if an authenticated user has owner permissions
        if (!auth()->user()->group->is_owner) {
            return $this->sendError('Unauthorized', 'You do not have permission to activate users', 403);
        }

        // Get the validating group
        $validatingGroup = Group::query()->where('slug', '=', 'validating')->first();
        
        if (!$validatingGroup) {
            return $this->sendError('Configuration Error', 'Validating group not found', 500);
        }

        // Get the member group (active users group)
        $memberGroup = Group::query()->where('slug', '=', 'user')->first();

        if (!$memberGroup) {
            return $this->sendError('Configuration Error', 'Member group not found', 500);
        }

        // Find all users in validating group or with active=false
        $usersToActivate = User::where('group_id', $validatingGroup->id)
            ->orWhere('active', false)
            ->get();

        $activatedUsers = [];
        $errors = [];

        foreach ($usersToActivate as $user) {
            try {
                $previousGroupId = $user->group_id;
                $user->active = true;
                $user->email_verified_at = $user->email_verified_at ?? now();
                $user->group_id = $memberGroup->id;
                $user->save();

                $activatedUsers[] = [
                    'user_id'           => $user->id,
                    'username'          => $user->username,
                    'email'             => $user->email,
                    'previous_group_id' => $previousGroupId,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'user_id'  => $user->id,
                    'username' => $user->username,
                    'error'    => $e->getMessage(),
                ];
            }
        }

        return $this->sendResponse([
            'success'         => true,
            'total_found'     => $usersToActivate->count(),
            'total_activated' => count($activatedUsers),
            'total_errors'    => count($errors),
            'activated_users' => $activatedUsers,
            'errors'          => $errors,
        ], 'Bulk activation completed');
    }

    /**
     * Verify torrent access for a user.
     * Returns which torrents the user has access to.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    final public function verifyTorrents(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if an authenticated user has owner permissions
        if (!auth()->user()->group->is_owner) {
            return $this->sendError('Unauthorized', 'You do not have permission to verify torrent access', 403);
        }

        // Validate the request
        $request->validate([
            'customer_id' => 'nullable|integer',
            'username'    => 'nullable|string',
            'email'       => 'nullable|string|email',
            'torrent_ids' => 'nullable|array',
            'torrent_ids.*' => 'integer',
        ]);

        // Ensure at least one identifier is provided
        if (!$request->customer_id && !$request->username && !$request->email) {
            return $this->sendError(
                'Validation Error',
                'You must provide at least one of: customer_id, username, or email',
                422
            );
        }

        // Find the user
        $query = User::query();
        
        if ($request->customer_id) {
            $query->orWhere('customer_id', $request->customer_id);
        }
        if ($request->username) {
            $query->orWhere('username', $request->username);
        }
        if ($request->email) {
            $query->orWhere('email', $request->email);
        }

        $user = $query->first();

        if (!$user) {
            return $this->sendResponse([
                'exists'    => false,
                'verified'  => false,
                'searched'  => [
                    'customer_id' => $request->customer_id,
                    'username'    => $request->username,
                    'email'       => $request->email,
                ],
            ], 'User not found');
        }

        // Get all attached torrents for the user
        $attachedTorrents = $user->attachedTorrents()
            ->select('torrents.id', 'torrents.name', 'torrents.created_at')
            ->get();

        $attachedTorrentIds = $attachedTorrents->pluck('id')->toArray();

        // If specific torrent IDs were requested, check which ones the user has
        $requestedTorrents = $request->torrent_ids ?? [];
        $verificationResults = [];
        
        if (!empty($requestedTorrents)) {
            foreach ($requestedTorrents as $torrentId) {
                $verificationResults[$torrentId] = [
                    'has_access' => in_array($torrentId, $attachedTorrentIds),
                ];
            }
        }

        return $this->sendResponse([
            'exists'              => true,
            'verified'            => true,
            'user_id'             => $user->id,
            'customer_id'         => $user->customer_id,
            'username'            => $user->username,
            'email'               => $user->email,
            'active'              => (bool) $user->active,
            'can_download'        => (bool) $user->can_download,
            'attached_torrents'   => $attachedTorrents->toArray(),
            'attached_torrent_ids' => $attachedTorrentIds,
            'attached_torrent_count' => count($attachedTorrentIds),
            'verification_results' => $verificationResults,
            'all_requested_have_access' => empty($requestedTorrents) || empty(array_diff($requestedTorrents, $attachedTorrentIds)),
        ], 'Torrent access verified');
    }

    /**
     * Update a user's password.
     * Can accept a new password or generate a random one.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    final public function updatePassword(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if an authenticated user has owner permissions
        if (!auth()->user()->group->is_owner) {
            return $this->sendError('Unauthorized', 'You do not have permission to update user passwords', 403);
        }

        // Validate the request
        $request->validate([
            'customer_id' => 'nullable|integer',
            'username'    => 'nullable|string',
            'email'       => 'nullable|string|email',
            'password'    => 'nullable|string|min:8|max:128',
            'generate'    => 'nullable|boolean',
        ]);

        // Ensure at least one identifier is provided
        if (!$request->customer_id && !$request->username && !$request->email) {
            return $this->sendError(
                'Validation Error',
                'You must provide at least one of: customer_id, username, or email',
                422
            );
        }

        // Find the user
        $query = User::query();
        
        if ($request->customer_id) {
            $query->orWhere('customer_id', $request->customer_id);
        }
        if ($request->username) {
            $query->orWhere('username', $request->username);
        }
        if ($request->email) {
            $query->orWhere('email', $request->email);
        }

        $user = $query->first();

        if (!$user) {
            return $this->sendError('User Not Found', 'Unable to find user with the provided identifier', 404);
        }

        // Determine the new password
        $newPassword = null;
        $passwordGenerated = false;

        if ($request->has('password') && !empty($request->password)) {
            // Use provided password
            $newPassword = $request->password;
        } elseif ($request->boolean('generate', true)) {
            // Generate a new password
            $newPassword = $this->generateSecurePassword();
            $passwordGenerated = true;
        } else {
            return $this->sendError(
                'Validation Error',
                'You must provide a password or set generate=true',
                422
            );
        }

        // Update the user's password
        $user->password = Hash::make($newPassword);
        $user->save();

        return $this->sendResponse([
            'success'           => true,
            'user_id'           => $user->id,
            'customer_id'       => $user->customer_id,
            'username'          => $user->username,
            'email'             => $user->email,
            'password'          => $newPassword,
            'password_generated' => $passwordGenerated,
            'updated_at'        => $user->updated_at?->toIso8601String(),
        ], 'Password updated successfully');
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
        
        // Get the count before attaching
        $previousCount = $user->attachedTorrents()->count();
        
        // Attach torrents to user (sync without detaching existing)
        $user->attachedTorrents()->syncWithoutDetaching($torrentIds);

        // Get the updated list of attached torrents
        $attachedTorrents = $user->attachedTorrents()->pluck('torrents.id')->toArray();
        $newCount = count($attachedTorrents);

        // Verify which of the requested torrents are now attached
        $verificationResults = [];
        foreach ($torrentIds as $torrentId) {
            $verificationResults[$torrentId] = [
                'requested'   => true,
                'has_access'  => in_array($torrentId, $attachedTorrents),
            ];
        }

        return $this->sendResponse([
            'customer_id'           => $user->customer_id,
            'user_id'               => $user->id,
            'username'              => $user->username,
            'email'                 => $user->email,
            'attached_torrents'     => $attachedTorrents,
            'attached_torrent_count' => $newCount,
            'previous_count'        => $previousCount,
            'newly_attached'        => $torrentIds,
            'newly_attached_count'  => $newCount - $previousCount,
            'verification_results'  => $verificationResults,
            'all_verified'          => !in_array(false, array_column($verificationResults, 'has_access')),
        ], 'Torrents attached successfully');
    }

    /**
     * Remove all torrents from a user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    final public function removeAllTorrents(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if an authenticated user has owner permissions
        if (!auth()->user()->group->is_owner) {
            return $this->sendError('Unauthorized', 'You do not have permission to remove torrents from users', 403);
        }

        // Validate the request
        $request->validate([
            'customer_id' => 'nullable|integer',
            'username'    => 'nullable|string',
            'email'       => 'nullable|string|email',
        ]);

        // Ensure at least one identifier is provided
        if (!$request->customer_id && !$request->username && !$request->email) {
            return $this->sendError(
                'Validation Error',
                'You must provide at least one of: customer_id, username, or email',
                422
            );
        }

        // Find the user
        $query = User::query();
        
        if ($request->customer_id) {
            $query->orWhere('customer_id', $request->customer_id);
        }
        if ($request->username) {
            $query->orWhere('username', $request->username);
        }
        if ($request->email) {
            $query->orWhere('email', $request->email);
        }

        $user = $query->first();

        if (!$user) {
            return $this->sendError('User Not Found', 'Unable to find user with the provided identifier', 404);
        }

        // Get count before removing
        $previousCount = $user->attachedTorrents()->count();
        
        // Remove all torrents
        $user->attachedTorrents()->detach();

        return $this->sendResponse([
            'success'        => true,
            'user_id'        => $user->id,
            'customer_id'    => $user->customer_id,
            'username'       => $user->username,
            'email'          => $user->email,
            'removed_count'  => $previousCount,
        ], 'All torrents removed successfully');
    }

    /**
     * Generate a one-time auto-login token for a user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    final public function generateAutoLoginToken(Request $request): \Illuminate\Http\JsonResponse
    {
        // Check if an authenticated user has owner permissions
        if (!auth()->user()->group->is_owner) {
            return $this->sendError('Unauthorized', 'You do not have permission to generate auto-login tokens', 403);
        }

        // Validate the request
        $request->validate([
            'customer_id'        => 'nullable|integer',
            'username'           => 'nullable|string',
            'email'              => 'nullable|string|email',
            'expires_in_minutes' => 'nullable|integer|min:1|max:60',
        ]);

        // Ensure at least one identifier is provided
        if (!$request->customer_id && !$request->username && !$request->email) {
            return $this->sendError(
                'Validation Error',
                'You must provide at least one of: customer_id, username, or email',
                422
            );
        }

        // Find the user
        $query = User::query();
        
        if ($request->customer_id) {
            $query->orWhere('customer_id', $request->customer_id);
        }
        if ($request->username) {
            $query->orWhere('username', $request->username);
        }
        if ($request->email) {
            $query->orWhere('email', $request->email);
        }

        $user = $query->first();

        if (!$user) {
            return $this->sendError('User Not Found', 'Unable to find user with the provided identifier', 404);
        }

        // Generate a secure token
        $token = bin2hex(random_bytes(32));
        $expiresInMinutes = $request->expires_in_minutes ?? 5;
        $expiresAt = now()->addMinutes($expiresInMinutes);

        // Store the token (you might want to add a tokens table for this)
        // For now, we'll use a simple approach with cache
        cache()->put(
            'auto_login_token_' . $token,
            [
                'user_id'    => $user->id,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
            $expiresAt
        );

        // Build the auto-login URL
        $autoLoginUrl = config('app.url') . '/auto-login?token=' . $token;

        return $this->sendResponse([
            'success'        => true,
            'user_id'        => $user->id,
            'username'       => $user->username,
            'token'          => $token,
            'auto_login_url' => $autoLoginUrl,
            'expires_at'     => $expiresAt->toIso8601String(),
            'expires_in_minutes' => $expiresInMinutes,
        ], 'Auto-login token generated successfully');
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
