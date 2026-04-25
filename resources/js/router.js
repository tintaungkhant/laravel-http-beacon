import { createRouter, createWebHistory } from 'vue-router'

import Dashboard from './pages/Dashboard.vue'
import IncomingList from './pages/IncomingList.vue'
import IncomingDetail from './pages/IncomingDetail.vue'
import OutgoingList from './pages/OutgoingList.vue'
import OutgoingDetail from './pages/OutgoingDetail.vue'

export const router = createRouter({
    history: createWebHistory('/beacon'),
    routes: [
        { path: '/', redirect: '/dashboard' },
        { path: '/dashboard', name: 'dashboard', component: Dashboard },
        { path: '/incoming', name: 'incoming.index', component: IncomingList },
        { path: '/incoming/:id', name: 'incoming.show', component: IncomingDetail, props: true },
        { path: '/outgoing', name: 'outgoing.index', component: OutgoingList },
        { path: '/outgoing/:id', name: 'outgoing.show', component: OutgoingDetail, props: true },
    ],
})
