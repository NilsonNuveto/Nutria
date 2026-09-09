import { createApp } from 'vue';
import App from './App.vue';
import AccessApp from './AccessApp.vue';
import '../css/nutria.css';

const root = document.querySelector('#app');

const user = JSON.parse(root.dataset.user || 'null');

createApp(
    location.pathname === '/' && user
        ? App
        : AccessApp,
    {
        user,
        setup: root.dataset.adminSetup === '1',
        adminEmail: root.dataset.adminEmail || '',
    }
).mount('#app');