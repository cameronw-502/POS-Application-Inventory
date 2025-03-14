import "./bootstrap";
import {
    Livewire,
    Alpine,
} from "../../vendor/livewire/livewire/dist/livewire.esm";

// Initialize Alpine.js
window.Alpine = Alpine;
Alpine.start();

// Initialize Livewire
Livewire.start();

// Force style refresh by toggling a tiny CSS change
document.addEventListener("DOMContentLoaded", () => {
    console.log("Application initialized with Laravel 12 defaults");

    // Add tiny CSS trigger to force browser to re-evaluate styles
    document.body.classList.add("styles-refreshed");

    // Clear any cached stylesheets by appending a cachebuster
    const styleTags = document.querySelectorAll('link[rel="stylesheet"]');
    styleTags.forEach((link) => {
        if (!link.href.includes("?")) {
            link.href = link.href + "?v=" + new Date().getTime();
        }
    });
});
