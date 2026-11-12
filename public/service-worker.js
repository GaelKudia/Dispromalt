// public/service-worker.js
// Service Worker — reçoit les notifications push et les affiche
// Doit être servi à la racine du site pour avoir un scope global.

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : 'Nouvelle notification' };
    }

    const title = data.title || 'Dispromalt';
    const options = {
        body: data.body || 'Vous avez une nouvelle alerte.',
        icon: data.icon || '/images/logo.png',
        badge: data.badge || '/images/logo.png',
        vibrate: data.vibrate || [200, 100, 200],
        tag: data.tag || 'dispromalt-notification',
        requireInteraction: data.requireInteraction || false,
        data: {
            url: data.url || '/dashboard',
            timestamp: Date.now(),
        },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/dashboard';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Si une fenêtre de l'app est déjà ouverte → focus + navigate
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            // Sinon → ouvre une nouvelle fenêtre
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
