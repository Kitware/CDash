An authentication token associated with your account will expire on {{ $authToken->expires->toDateString() }}:

  * {{ $authToken->description ?? 'No Description' }}

Visit {{ url('/user') }} to manage your authentication tokens.

-CDash
