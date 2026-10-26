import { createRouter, createWebHistory } from 'vue-router';
import HomePage from '@/pages/HomePage.vue';
import LoginPage from '@/pages/LoginPage.vue';
import RegisterPage from '@/pages/RegisterPage.vue';
import { useAuthStore } from '@/stores/auth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'home', component: HomePage },
        { path: '/login', name: 'login', component: LoginPage, meta: { guest: true } },
        { path: '/register', name: 'register', component: RegisterPage, meta: { guest: true } },
        { path: '/:pathMatch(.*)*', redirect: { name: 'home' } },
    ],
});

router.beforeEach((to) => {
    const auth = useAuthStore();

    if (!to.meta.guest && !auth.user) {
        return { name: 'login' };
    }

    if (to.meta.guest && auth.user) {
        return { name: 'home' };
    }
});

export default router;
