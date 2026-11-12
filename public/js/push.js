// public/js/push.js
// Gère l'abonnement aux notifications push côté navigateur.

(function () {
    'use strict';

    // Convertit la clé VAPID Base64 en Uint8Array (requis par l'API Push)
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // Enregistre le Service Worker
    async function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            throw new Error('Service Worker non supporté par ce navigateur.');
        }
        return navigator.serviceWorker.register('/service-worker.js');
    }

    // Met à jour le bouton de manière sécurisée (sans innerHTML)
    function updateButton(btn, iconClass, textContent) {
        // 1. On vide le bouton proprement
        btn.textContent = '';
        
        // 2. On crée l'icône
        const icon = document.createElement('i');
        icon.className = iconClass;
        
        // 3. On crée le texte
        const text = document.createTextNode(' ' + textContent);
        
        // 4. On assemble le tout
        btn.appendChild(icon);
        btn.appendChild(text);
    }

    // Fonction principale déclenchée au clic sur le bouton
    async function subscribeUser(btn) {
        try {
            // 1. Demander la permission
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                alert('❌ Permission refusée. Vous ne recevrez pas de notifications.');
                return;
            }

            // 2. Enregistrer le Service Worker
            const registration = await registerServiceWorker();
            await navigator.serviceWorker.ready;

            // 3. Vérifier si déjà abonné
            let subscription = await registration.pushManager.getSubscription();

            // 4. Récupérer la clé publique VAPID depuis l'API Symfony
            const resp = await fetch('/api/push/public-key', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!resp.ok) {
                throw new Error('Impossible de récupérer la clé publique (' + resp.status + ')');
            }
            const { publicKey } = await resp.json();

            // 5. S'abonner si pas déjà fait
            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicKey),
                });
            }

            // 6. Envoyer l'abonnement au serveur Symfony
            const sendResp = await fetch('/api/push/subscribe', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify(subscription.toJSON()),
            });

            if (!sendResp.ok) {
                const err = await sendResp.text();
                throw new Error('Erreur serveur : ' + sendResp.status + ' — ' + err);
            }

            // 7. Succès : On cache le bouton complètement
            btn.style.display = 'none';
            alert('✅ Notifications activées avec succès !');
            
        } catch (err) {
            console.error('Erreur push :', err);
            alert('❌ Erreur lors de l\'activation : ' + err.message);
        }
    }

    // Au chargement de la page, on vérifie l'état actuel
    document.addEventListener('DOMContentLoaded', async () => {
        const btn = document.getElementById('enable-notifications');
        if (!btn) return;

        // Vérifier le support navigateur
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            btn.disabled = true;
            // ✅ CORRECTION SÉCURITÉ XSS ICI (utilisation de notre fonction sécurisée)
            updateButton(btn, 'fas fa-ban', 'Non supporté');
            btn.title = 'Votre navigateur ne supporte pas les notifications push';
            return;
        }

        // Vérifier si l'utilisateur a déjà accordé la permission et s'il est abonné
        try {
            const registration = await navigator.serviceWorker.getRegistration('/');
            
            if (registration) {
                const sub = await registration.pushManager.getSubscription();
                if (sub) {
                    // L'utilisateur est DÉJÀ abonné : on cache le bouton immédiatement
                    btn.style.display = 'none';
                    return;
                }
            }
        } catch (e) {
            console.warn('Erreur lors de la vérification de l\'abonnement :', e);
        }

        // Si on arrive ici, l'utilisateur n'est pas encore abonné, on attache l'événement clic
        btn.addEventListener('click', () => subscribeUser(btn));
    });
})();