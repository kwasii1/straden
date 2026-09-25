import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Settings are rendered by the server at runtime (see partials/head.blade.php)
// so a prebuilt Docker image works on any host. Anything left empty falls back
// to build-time VITE_REVERB_* values (local `npm run dev`), and finally to the
// page's own origin, where the web server proxies /app to Reverb.
const runtime = window.StradenConfig?.reverb ?? {};

const scheme = runtime.scheme || import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '');
const host = runtime.host || import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const port = Number(runtime.port || import.meta.env.VITE_REVERB_PORT || window.location.port || (scheme === 'https' ? 443 : 80));

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: runtime.key || import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
});
