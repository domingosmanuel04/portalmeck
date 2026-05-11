// js/theme.js - Theme and UI Management

document.addEventListener('alpine:init', () => {
    Alpine.store('theme', {
        darkMode: localStorage.getItem('theme') === 'dark',

        toggle() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
            document.body.classList.toggle('dark-mode', this.darkMode);
        },

        init() {
            if (this.darkMode) {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
        }
    });
});

// Immediate application to prevent FLASH of un-styled content
if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.classList.add('dark'); // for tailwind dark: utilities
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('dark-mode'));
}
