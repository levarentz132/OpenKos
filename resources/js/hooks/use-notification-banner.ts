import { usePage } from '@inertiajs/react';
import { useState, useSyncExternalStore } from 'react';
import type { Auth } from '@/types';

// Permanent opt-out (localStorage) vs. this-session dismissal (sessionStorage, so it
// stays hidden while navigating/reloading in this tab but returns on the next visit).
const PERMANENT_KEY = 'notif-setup-banner-hidden';
const SESSION_KEY = 'notif-setup-banner-dismissed';

const subscribe = (callback: () => void) => {
    if (typeof window === 'undefined') {
        return () => {};
    }

    window.addEventListener('storage', callback);

    return () => window.removeEventListener('storage', callback);
};

const getClientSnapshot = () =>
    typeof window !== 'undefined' &&
    (localStorage.getItem(PERMANENT_KEY) === '1' ||
        sessionStorage.getItem(SESSION_KEY) === '1');

const getServerSnapshot = () => false;

export function useNotificationBanner() {
    const { auth, notificationChannels } = usePage<{
        auth: Auth;
        notificationChannels: { mail: boolean; whatsapp: boolean };
    }>().props;

    const isHiddenFromStorage = useSyncExternalStore(
        subscribe,
        getClientSnapshot,
        getServerSnapshot,
    );

    const [dismissed, setDismissed] = useState(false);
    const hidden = isHiddenFromStorage || dismissed;

    const visible =
        auth.role === 'owner' &&
        !notificationChannels?.mail &&
        !notificationChannels?.whatsapp &&
        !hidden;

    function dismiss() {
        if (typeof window !== 'undefined') {
            sessionStorage.setItem(SESSION_KEY, '1');
            window.dispatchEvent(new Event('storage'));
        }

        setDismissed(true);
    }

    function dontShowAgain() {
        if (typeof window !== 'undefined') {
            localStorage.setItem(PERMANENT_KEY, '1');
            window.dispatchEvent(new Event('storage'));
        }

        setDismissed(true);
    }

    return { visible, dismiss, dontShowAgain };
}
