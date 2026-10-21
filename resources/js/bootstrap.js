import _ from 'lodash';
window._ = _;

import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Laravel Echo + Pusher (Laravel WebSockets compatible)
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

/**
 * IMPORTANT:
 * - We connect via domain (443) and wsPath "/ws" because the server exposes:
 *   wss://officialgold.site/ws/app/{key}?protocol=7...
 * - forceTLS must be true in production.
 */
window.Echo = new Echo({
  broadcaster: 'pusher',
  key: 'newGoldKey2026',

  wsHost: 'officialgold.site',
  wsPort: 443,
  wssPort: 443,

  // because we are using https/wss behind domain
  forceTLS: true,
  encrypted: true,

  // because websockets server is served under /ws
  wsPath: '/ws',

  disableStats: true,
  enabledTransports: ['ws', 'wss'],
});

/**
 * Listen to the public channel: gold-price
 * Event is broadcastAs(): "PriceUpdated" -> listen(".PriceUpdated")
 */
window.Echo.channel('gold-price')
  .listen('.PriceUpdated', (data) => {
    console.log('📢 PriceUpdated received:', data);

    // Optional UI update if element exists
    const el = document.getElementById('gold-price');
    if (el) {
      // data could be {metal,currency,market,buy,sell,timestamp} or whatever you send
      const priceText =
        data?.buy ? `Buy: ${data.buy}` :
        data?.market ? `Market: ${data.market}` :
        JSON.stringify(data);

      el.textContent = priceText;
    }
  });

/**
 * Optional: debug connection state in console
 */
if (window.Echo?.connector?.pusher?.connection) {
  window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Echo connected');
  });
  window.Echo.connector.pusher.connection.bind('error', (err) => {
    console.log('❌ Echo connection error:', err);
  });
}
