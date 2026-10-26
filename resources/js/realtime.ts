import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { onUnmounted } from 'vue';
import { useNotificationsStore } from '@/stores/notifications';
import type { ProposalStatus, User } from '@/types';

interface ProposalEvent {
    proposal_id: number;
    title: string;
}

type ProposalListener = (proposalId: number) => void;

let echo: Echo<'reverb'> | null = null;
const listeners = new Set<ProposalListener>();

export function onProposalChange(listener: ProposalListener) {
    listeners.add(listener);
    onUnmounted(() => listeners.delete(listener));
}

export function startRealtime(user: User) {
    const notifications = useNotificationsStore();

    function received(message: string, proposalId: number) {
        notifications.add(message, proposalId);
        listeners.forEach((listener) => listener(proposalId));
    }

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
            received(`${event.speaker} submitted “${event.title}”`, event.proposal_id);
        });
    }

    if (user.role === 'admin') {
        echo.private('admins').listen('.proposal.reviewed', (event: ProposalEvent & { reviewer: string; is_new: boolean }) => {
            const action = event.is_new ? 'reviewed' : 'updated the review of';
            received(`${event.reviewer} ${action} “${event.title}”`, event.proposal_id);
        });
    }

    if (user.role === 'speaker') {
        echo.private(`App.Models.User.${user.id}`).listen(
            '.proposal.status-changed',
            (event: ProposalEvent & { status: ProposalStatus }) => {
                received(`Your proposal “${event.title}” is now ${event.status}`, event.proposal_id);
            },
        );
    }
}

export function stopRealtime() {
    echo?.disconnect();
    echo = null;
}
