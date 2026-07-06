/**
 * SeederLinux Lite - Main JavaScript
 * Common utilities and functions
 */

// Detect base path for API calls based on current location
const getBasePath = () => {
    const path = window.location.pathname;
    const segments = path.split('/').filter(s => s.length > 0);

    // If we're in /public/ directory (e.g., login.html, admin.html)
    if (segments.includes('public')) {
        return '../api/';
    }

    // If we're at root or somewhere else, use relative path
    // Calculate how many levels to go up
    const depth = segments.length;
    if (depth === 0 || segments[segments.length - 1].endsWith('.html')) {
        // At root or HTML file
        if (segments.includes('public')) {
            return '../api/';
        }
        return 'api/';
    }

    // Default: try to reach api from current location
    return './api/';
};

// API helper
const API = {
    baseUrl: getBasePath(),

    async request(action, method = 'GET', data = null) {
        // Build URL with action parameter
        let url;
        if (this.baseUrl.endsWith('?')) {
            url = `${this.baseUrl}action=${encodeURIComponent(action)}`;
        } else if (this.baseUrl.includes('?')) {
            url = `${this.baseUrl}&action=${encodeURIComponent(action)}`;
        } else {
            url = `${this.baseUrl}?action=${encodeURIComponent(action)}`;
        }

        const options = {
            method,
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin' // Include cookies for session
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    async get(action) {
        return this.request(action, 'GET');
    },

    async post(action, data) {
        return this.request(action, 'POST', data);
    },

    async put(action, id, data) {
        return this.request(`${action}&id=${id}`, 'PUT', data);
    },

    async delete(action, id) {
        return this.request(`${action}&id=${id}`, 'DELETE');
    }
};

// Toast notifications
const Toast = {
    show(message, type = 'info', duration = 4000) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `
            background: ${this.getBgColor(type)};
            border-left: 4px solid ${this.getBorderColor(type)};
        `;
        toast.innerHTML = `
            <div class="flex items-center gap-3">
                ${this.getIcon(type)}
                <span>${message}</span>
                <button class="ml-2 opacity-70 hover:opacity-100" onclick="this.parentElement.parentElement.remove()">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    getBgColor(type) {
        const colors = {
            success: '#065f46',
            error: '#991b1b',
            warning: '#92400e',
            info: '#1e40af'
        };
        return colors[type] || colors.info;
    },

    getBorderColor(type) {
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };
        return colors[type] || colors.info;
    },

    getIcon(type) {
        const icons = {
            success: '<svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
            error: '<svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
            warning: '<svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
            info: '<svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
        };
        return icons[type] || icons.info;
    },

    success(message) { this.show(message, 'success'); },
    error(message) { this.show(message, 'error'); },
    warning(message) { this.show(message, 'warning'); },
    info(message) { this.show(message, 'info'); }
};

// Utility functions
const Utils = {
    formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    slugify(text) {
        return text
            .toString()
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-');
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Make available globally
window.API = API;
window.Toast = Toast;
window.Utils = Utils;
