@component('mail::message')
# Change password request

Click the button below to change password

@component('mail::button', ['url' => 'http://localhost:9000/?root=password_reset&token='.$token])
Reset Password
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
