@component('mail::message')
# Welcome to {{ config('app.name') }}!

Hi {{ $user->first_name }},

Thank you for signing up with Google! To complete your registration and secure your account, please verify that it's really you.

**Is this you?**

@component('mail::button', ['url' => $confirmationLink, 'color' => 'success'])
✓ Yes, It's Me
@endcomponent

@component('mail::button', ['url' => $rejectionLink, 'color' => 'error'])
✗ No, It's Not Me
@endcomponent

**Or copy and paste these links in your browser:**

Confirm: {{ $confirmationLink }}

Reject: {{ $rejectionLink }}

**What happens next:**
- If you click **"Yes, It's Me"**, your account will be activated and you can log in using Google Authentication.
- If you click **"No, It's Not Me"**, your registration will be cancelled and no account will be created.

This verification link will expire in 24 hours.

If you have any questions, please contact our support team.

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
