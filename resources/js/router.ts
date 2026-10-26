import { createRouter, createWebHistory } from 'vue-router';
import LoginPage from '@/pages/LoginPage.vue';
import NewProposalPage from '@/pages/NewProposalPage.vue';
import ProposalsPage from '@/pages/ProposalsPage.vue';
import RegisterPage from '@/pages/RegisterPage.vue';
import { useAuthStore } from '@/stores/auth';
import type { Role } from '@/types';

declare module 'vue-router' {
    interface RouteMeta {
        guest?: boolean;
        roles?: Role[];
    }
}

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'proposals', component: ProposalsPage },
        {
            path: '/proposals/new',
            name: 'proposals.create',
            component: NewProposalPage,
            meta: { roles: ['speaker'] },
        },
        { path: '/login', name: 'login', component: LoginPage, meta: { guest: true } },
        { path: '/register', name: 'register', component: RegisterPage, meta: { guest: true } },
        { path: '/:pathMatch(.*)*', redirect: { name: 'proposals' } },
    ],
});

router.beforeEach((to) => {
    const auth = useAuthStore();

    if (to.meta.guest) {
        return auth.user ? { name: 'proposals' } : true;
    }

    if (!auth.user) {
        return { name: 'login' };
    }

    if (to.meta.roles && !to.meta.roles.includes(auth.user.role)) {
        return { name: 'proposals' };
    }
});

export default router;
