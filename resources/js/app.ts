import type { AxiosError } from 'axios';
import { createPinia } from 'pinia';
import { createApp } from 'vue';
import App from '@/App.vue';
import { http, requestError } from '@/http';
import router from '@/router';
import { useAuthStore } from '@/stores/auth';

const app = createApp(App).use(createPinia());
const auth = useAuthStore();

http.interceptors.response.use(undefined, (error: AxiosError) => {
    const status = error.response?.status;

    if ((status === 401 || status === 419) && auth.user) {
        auth.user = null;
        router.push({ name: 'login' });
    } else if (status === 429) {
        requestError.value = 'Too many attempts. Please wait a minute and try again.';
    } else if (status === undefined || status >= 500) {
        requestError.value = 'Something went wrong. Please try again.';
    }

    return Promise.reject(error);
});

router.afterEach(() => {
    requestError.value = null;
});

await auth.fetchUser();

app.use(router).mount('#app');
