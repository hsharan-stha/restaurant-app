import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Pusher = Pusher;

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
if (pusherKey) {
    const echoOptions = {
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
        encrypted: true,
        disableStats: true,
    };
    if (import.meta.env.VITE_PUSHER_HOST) {
        Object.assign(echoOptions, {
            wsHost: import.meta.env.VITE_PUSHER_HOST,
            wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
            wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
            enabledTransports: ['ws', 'wss'],
        });
    }
    window.Echo = new Echo(echoOptions);
}
