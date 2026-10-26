import axios, { isAxiosError } from 'axios';
import { ref } from 'vue';

export const http = axios.create({
    headers: { Accept: 'application/json' },
});

export const requestError = ref<string | null>(null);

export type ValidationErrors = Record<string, string[]>;

export function validationErrors(error: unknown): ValidationErrors {
    if (isAxiosError(error) && error.response?.status === 422) {
        return error.response.data.errors;
    }

    throw error;
}
