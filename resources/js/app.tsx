import '../css/app.css';
import './bootstrap';
import { createInertiaApp } from '@inertiajs/react';

createInertiaApp({
    title: (title) => (title ? `${title} - OpsAI` : 'OpsAI'),
    strictMode: true,
    pages: {
        path: './pages',
        extension: '.tsx',
    },
});
