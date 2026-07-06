/**
 * SeederLinux Lite - Main JavaScript
 * Common utilities and functions
 */

// API helper with relative path (works when public/ is DocumentRoot)
const API = {
    baseUrl: 'api/index.php',  // <- CORRIGIDO: apontando para o index.php

    async request(action, method = 'GET', data = null) {
        // Build URL with action parameter
        const url = `${this.baseUrl}?action=${encodeURIComponent(action)}`;

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
        if (!container) {
            // Create container if it doesn't exist
            const newContainer = document.createElement('div');
            newContainer.id = 'toast-container';
            newContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 400px;
                width: 100%;
            `;
            document.body.appendChild(newContainer);
        }

        const toastContainer = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `
            background: ${this.getBgColor(type)};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            border-left: 4px solid ${this.getBorderColor(type)};
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
            transition: all 0.3s ease;
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
        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100px)';
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
    },

    getInitials(name) {
        if (!name) return '?';
        return name.split(' ')
            .filter(word => word.length > 0)
            .map(word => word[0].toUpperCase())
            .slice(0, 2)
            .join('');
    },

    truncateText(text, maxLength = 50) {
        if (!text) return '';
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }
};

// Make available globally
window.API = API;
window.Toast = Toast;
window.Utils = Utils;

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
`;
document.head.appendChild(style);

console.log('SeederLinux Lite - App initialized');