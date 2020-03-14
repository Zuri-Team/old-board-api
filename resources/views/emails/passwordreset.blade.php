@component('mail::message')
# Change password request

Click the button below to change password

@component('mail::button', ['url' => 'https://dev.start.ng/?root=password_reset&token='.$token])
Reset Password
@endcomponent

Thanks,<br>
  HNG
@endcomponent
