import './bootstrap';
import Alpine from 'alpinejs';
import { bootPwa } from './pwa';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    bootPwa(Alpine);
});

Alpine.start();
