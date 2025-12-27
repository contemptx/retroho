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

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeNewUser extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $username,
        public string $password
    ) {
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        return $this->subject('Welcome to '.config('app.name'))
            ->markdown('emails.welcome', [
                'username' => $this->username,
                'password' => $this->password,
                'loginUrl' => route('login')
            ]);
    }
}
