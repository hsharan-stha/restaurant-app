@auth
<meta name="restaurant-notify" content="1">
<meta name="restaurant-broadcast-driver" content="{{ config('broadcasting.default') }}">
<meta name="restaurant-reverb-key" content="{{ config('broadcasting.connections.reverb.key') ?? '' }}">
<meta name="restaurant-reverb-host" content="{{ config('broadcasting.connections.reverb.options.host') ?? '' }}">
<meta name="restaurant-reverb-port" content="{{ config('broadcasting.connections.reverb.options.port', 8080) }}">
<meta name="restaurant-reverb-scheme" content="{{ config('broadcasting.connections.reverb.options.scheme', 'http') }}">
<meta name="restaurant-pusher-key" content="{{ config('broadcasting.connections.pusher.key') ?? '' }}">
<meta name="restaurant-pusher-cluster" content="{{ config('broadcasting.connections.pusher.options.cluster', 'mt1') }}">
<meta name="restaurant-floor-state-url" content="{{ url('/dashboard/floor/state') }}">
@endauth
