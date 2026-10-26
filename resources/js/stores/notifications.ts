import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface LiveNotification {
    id: number;
    message: string;
    proposalId: number;
}

export const useNotificationsStore = defineStore('notifications', () => {
    const items = ref<LiveNotification[]>([]);
    let lastId = 0;

    function add(message: string, proposalId: number) {
        const id = ++lastId;
        items.value.push({ id, message, proposalId });
        setTimeout(() => dismiss(id), 8000);
    }

    function dismiss(id: number) {
        items.value = items.value.filter((item) => item.id !== id);
    }

    return { items, add, dismiss };
});
