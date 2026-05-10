<meta name="guest-broadcast-driver" content="{{ config('broadcasting.default') }}">
<meta name="guest-reverb-key" content="{{ config('broadcasting.connections.reverb.key') ?? '' }}">
<meta name="guest-reverb-host" content="{{ config('broadcasting.connections.reverb.options.host') ?? '' }}">
<meta name="guest-reverb-port" content="{{ config('broadcasting.connections.reverb.options.port', 8080) }}">
<meta name="guest-reverb-scheme" content="{{ config('broadcasting.connections.reverb.options.scheme', 'http') }}">
<meta name="guest-pusher-key" content="{{ config('broadcasting.connections.pusher.key') ?? '' }}">
<meta name="guest-pusher-cluster" content="{{ config('broadcasting.connections.pusher.options.cluster', 'mt1') }}">
