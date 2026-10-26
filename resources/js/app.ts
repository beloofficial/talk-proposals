import { createPinia } from 'pinia';
import { createApp } from 'vue';
import App from '@/App.vue';
import router from '@/router';
import { useAuthStore } from '@/stores/auth';

const app = createApp(App).use(createPinia());

await useAuthStore().fetchUser();

app.use(router).mount('#app');
