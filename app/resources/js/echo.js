import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

console.log('Echo Config:', {
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
});

const isSecure = window.location.protocol === 'https:';
const currentPort = window.location.port || (isSecure ? 443 : 80);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: window.location.hostname,
    wsPort: currentPort,
    wssPort: currentPort,
    forceTLS: isSecure,
    enabledTransports: ['ws', 'wss'],
});

window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('Echo connected to Reverb!');
});

window.Echo.connector.pusher.connection.bind('error', (err) => {
    console.error('Echo connection error:', err);
});
