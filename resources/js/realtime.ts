import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { useNotificationsStore } from '@/stores/notifications';
import type { ProposalStatus, User } from '@/types';

interface ProposalEvent {
    proposal_id: number;
    title: string;
}

let echo: Echo<'reverb'> | null = null;

export function startRealtime(user: User) {
    const notifications = useNotificationsStore();

    echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT),
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        Pusher,
    });

    if (user.role === 'reviewer') {
        echo.private('reviewers').listen('.proposal.submitted', (event: ProposalEvent & { speaker: string }) => {
            notifications.add(`${event.speaker} submitted “${event.title}”`, event.proposal_id);
        });
    }

    if (user.role === 'admin') {
        echo.private('admins').listen('.proposal.reviewed', (event: ProposalEvent & { reviewer: string; is_new: boolean }) => {
            const action = event.is_new ? 'reviewed' : 'updated the review of';
            notifications.add(`${event.reviewer} ${action} “${event.title}”`, event.proposal_id);
        });
    }

    if (user.role === 'speaker') {
        echo.private(`App.Models.User.${user.id}`).listen(
            '.proposal.status-changed',
            (event: ProposalEvent & { status: ProposalStatus }) => {
                notifications.add(`Your proposal “${event.title}” is now ${event.status}`, event.proposal_id);
            },
        );
    }
}

export function stopRealtime() {
    echo?.disconnect();
    echo = null;
}
