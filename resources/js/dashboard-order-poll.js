/**
 * Dashboard entry: 2s order polling (toast + sound + floor JSON refresh). See services/dashboardOrderPollService.js
 */
import { createDashboardOrderPoller } from './services/dashboardOrderPollService';

function meta(name) {
    return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? '';
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('dashboard-floor-root');
    const pollUrl = meta('restaurant-api-latest-orders-url');
    if (!root || !pollUrl || window.__restaurantDashboardOrderPoll) {
        return;
    }
    window.__restaurantDashboardOrderPoll = true;

    const poller = createDashboardOrderPoller({
        pollUrl,
        pollMs: 2000,
        onRefresh: () => {
            window.dispatchEvent(new CustomEvent('restaurant:refresh-floor', { bubbles: true }));
        },
    });

    poller.start();

    window.addEventListener('pagehide', () => poller.stop());
});
