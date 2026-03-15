@component('mail::message')
# Welcome to {{ config('app.name') }}!

Hi {{ $user->first_name }},

Thank you for signing up with Google! To complete your registration and secure your account, please verify that it's really you.

@component('mail::button', ['url' => $verificationLink])
Verify It's Me
@endcomponent

Or copy and paste this link in your browser:
{{ $verificationLink }}

This verification link will expire in 24 hours.

**Didn't sign up?** If this wasn't you, please ignore this email or contact our support team.

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
