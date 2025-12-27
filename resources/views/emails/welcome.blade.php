@component('mail::message')
# Welcome to {{ config('app.name') }}!

Your account has been created successfully.

## Your Login Details
**Username:** {{ $username }}
**Password:** {{ $password }}

You can login using the button below:

@component('mail::button', ['url' => $loginUrl])
Login Now
@endcomponent

For security reasons, we recommend changing your password after your first login.

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
