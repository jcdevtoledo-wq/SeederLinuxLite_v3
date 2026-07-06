/**
 * SeederLinux Lite - Admin Panel JavaScript
 * Handles OM management, variables, and bundle generation
 */

document.addEventListener('DOMContentLoaded', async () => {
    // Check authentication
    try {
        const session = await API.get('session');
        if (!session.success) {
            window.location.href = '/public/login.html';
            return;
        }

        // Update user info
        document.getElementById('user-name').textContent = session.data.full_name || session.data.username;
        document.getElementById('user-initial').textContent = (session.data.full_name || session.data.username).charAt(0).toUpperCase();
        document.getElementById('user-role').textContent = session.data.role === 'admin' ? 'Administrador' : 'Gerente';
    } catch (error) {
        window.location.href = '/public/login.html';
        return;
    }

    // Initialize
    await loadDashboard();
    await loadOrganizations();

    // Event listeners
    setupEventListeners();
});

// State
let currentOrgId = null;
let organizations = [];

// Load dashboard data
async function loadDashboard() {
    try {
        const [stats, orgs, scripts] = await Promise.all([
            API.get('stats'),
            API.get('organizations'),
            API.get('scripts')
        ]);

        if (stats.success) {
            document.getElementById('dash-orgs').textContent = stats.data.organizations;
            document.getElementById('dash-scripts').textContent = stats.data.core_scripts;
            document.getElementById('dash-vars').textContent = stats.data.variables;
            document.getElementById('dash-stations').textContent = stats.data.stations;
        }

        if (orgs.success) {
            organizations = orgs.data;

            // Recent orgs
            const recentOrgsEl = document.getElementById('recent-orgs');
            if (orgs.data.length === 0) {
                recentOrgsEl.innerHTML = '<p class="text-slate-400 text-center">Nenhuma organização cadastrada</p>';
            } else {
                recentOrgsEl.innerHTML = orgs.data.map(org => `
                    <div class="flex items-center justify-between py-2 border-b border-slate-700 last:border-0">
                        <div>
                            <span class="font-semibold text-blue-400">${Utils.escapeHtml(org.acronym)}</span>
                            <span class="text-slate-400 text-sm ml-2">${Utils.escapeHtml(org.name)}</span>
                        </div>
                        <span class="text-slate-500 text-xs">${Utils.formatDate(org.created_at)}</span>
                    </div>
                `).join('');
            }
        }

        if (scripts.success) {
            const recentScriptsEl = document.getElementById('recent-scripts');
            recentScriptsEl.innerHTML = scripts.data.map(script => `
                <div class="flex items-center justify-between py-2 border-b border-slate-700 last:border-0">
                    <div>
                        <span class="font-medium text-white">${Utils.escapeHtml(script.name)}</span>
                        <span class="text-slate-500 text-xs ml-2">${script.filename}</span>
                    </div>
                    <span class="px-2 py-1 text-xs rounded ${script.is_core ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700 text-slate-400'}">
                        ${script.is_core ? 'Core' : 'Custom'}
                    </span>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        Toast.error('Erro ao carregar dados');
    }
}

// Load organizations list
async function loadOrganizations() {
    const orgList = document.getElementById('om-list');

    if (organizations.length === 0) {
        orgList.innerHTML = `
            <div class="text-center py-4 text-slate-500 text-sm">
                Nenhuma OM cadastrada
            </div>
        `;
        return;
    }

    orgList.innerHTML = organizations.map(org => `
        <button
            class="nav-item w-full flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white transition-colors text-left"
            data-org-id="${org.id}"
            onclick="selectOrganization(${org.id})"
        >
            <span class="w-8 h-8 bg-slate-700 rounded-lg flex items-center justify-center text-sm font-semibold">
                ${org.acronym.substring(0, 2)}
            </span>
            <div class="overflow-hidden">
                <span class="block font-medium truncate">${Utils.escapeHtml(org.acronym)}</span>
                <span class="block text-xs text-slate-500 truncate">${Utils.escapeHtml(org.name)}</span>
            </div>
        </button>
    `).join('');
}

// Select organization
async function selectOrganization(orgId) {
    currentOrgId = orgId;

    // Update active state in sidebar
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (parseInt(item.dataset.orgId) === orgId) {
            item.classList.add('active');
        }
    });

    // Load organization data
    const org = organizations.find(o => o.id === orgId);
    if (!org) return;

    // Update header
    document.getElementById('page-title').textContent = org.acronym;
    document.getElementById('page-subtitle').textContent = org.name;

    // Show OM detail view
    document.getElementById('view-dashboard').classList.add('hidden');
    document.getElementById('view-om-detail').classList.remove('hidden');

    // Update OM display
    document.getElementById('om-acronym-badge').textContent = org.acronym.substring(0, 3);
    document.getElementById('om-display-name').textContent = org.name;
    document.getElementById('om-display-domain').textContent = org.domain || 'Sem domínio configurado';

    // Update settings form
    document.getElementById('edit-om-name').value = org.name;
    document.getElementById('edit-om-acronym').value = org.acronym;
    document.getElementById('edit-om-domain').value = org.domain || '';
    document.getElementById('edit-om-description').value = org.description || '';

    // Load variables
    await loadVariables(orgId);

    // Load scripts for bundle
    await loadScriptsForBundle(orgId);

    // Update download link
    document.getElementById('download-link').href = `/api/?action=bundle-download&id=${encodeURIComponent(org.acronym)}`;
    document.getElementById('bundle-filename').textContent = `provision-${org.acronym.toLowerCase()}.sh`;
}

// Load variables for organization
async function loadVariables(orgId) {
    try {
        const response = await API.get(`variables&id=${orgId}`);
        if (!response.success) {
            Toast.error('Erro ao carregar variáveis');
            return;
        }

        const varsList = document.getElementById('vars-list');
        const vars = response.data.variables || [];

        // Group by category
        const categories = {};
        vars.forEach(v => {
            if (!categories[v.category]) {
                categories[v.category] = [];
            }
            categories[v.category].push(v);
        });

        let html = '';
        const categoryLabels = {
            'dominio': 'Domínio e Autenticação',
            'rede': 'Configuração de Rede',
            'proxy': 'Proxy e Internet',
            'inventario': 'Inventário',
            'navegador': 'Navegador',
            'seguranca': 'Segurança',
            'branding': 'Identidade Visual',
            'general': 'Geral'
        };

        for (const [category, categoryVars] of Object.entries(categories)) {
            html += `
                <div class="col-span-2">
                    <h4 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-3 mt-4 first:mt-0">
                        ${categoryLabels[category] || category}
                    </h4>
                </div>
            `;

            categoryVars.forEach(v => {
                html += `
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            ${Utils.escapeHtml(v.name)}
                            ${v.is_required ? '<span class="text-red-400">*</span>' : ''}
                        </label>
                        <input
                            type="text"
                            name="var_${v.id}"
                            value="${Utils.escapeHtml(v.current_value || '')}"
                            data-var-id="${v.id}"
                            class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="${Utils.escapeHtml(v.default_value || '')}"
                        >
                        <p class="text-slate-500 text-xs mt-1">${Utils.escapeHtml(v.description)}</p>
                    </div>
                `;
            });
        }

        varsList.innerHTML = html;
    } catch (error) {
        console.error('Error loading variables:', error);
        Toast.error('Erro ao carregar variáveis');
    }
}

// Load scripts for bundle
async function loadScriptsForBundle(orgId) {
    try {
        const response = await API.get('scripts');
        if (!response.success) return;

        const scriptsList = document.getElementById('bundle-scripts-list');
        scriptsList.innerHTML = response.data.map((script, index) => `
            <div class="flex items-center justify-between p-3 bg-slate-900 rounded-lg">
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 bg-slate-700 rounded flex items-center justify-center text-xs text-slate-400">${index + 1}</span>
                    <div>
                        <span class="font-medium text-white">${Utils.escapeHtml(script.name)}</span>
                        <span class="text-slate-500 text-xs ml-2">${script.filename}</span>
                    </div>
                </div>
                <span class="px-2 py-1 text-xs rounded ${script.is_core ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700 text-slate-400'}">
                    ${script.is_core ? 'Core' : 'Custom'}
                </span>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading scripts:', error);
    }
}

// Setup event listeners
function setupEventListeners() {
    // New OM button
    document.getElementById('btn-new-org').addEventListener('click', () => {
        document.getElementById('modal-new-org').classList.remove('hidden');
    });

    // Close modal
    document.getElementById('close-modal').addEventListener('click', closeModal);
    document.getElementById('cancel-new-org').addEventListener('click', closeModal);
    document.getElementById('modal-backdrop').addEventListener('click', closeModal);

    // New OM form
    document.getElementById('new-org-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        await createOrganization();
    });

    // Save variables
    document.getElementById('btn-save-vars').addEventListener('click', saveVariables);

    // OM settings form
    document.getElementById('om-settings-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        await updateOrganization();
    });

    // Delete org
    document.getElementById('btn-delete-org').addEventListener('click', async () => {
        if (confirm('Tem certeza que deseja excluir esta organização? Esta ação não pode ser desfeita.')) {
            await deleteOrganization();
        }
    });

    // Logout
    document.getElementById('btn-logout').addEventListener('click', async () => {
        try {
            await API.post('logout');
            window.location.href = '/public/login.html';
        } catch (error) {
            Toast.error('Erro ao sair');
        }
    });

    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            switchTab(tab);
        });
    });
}

// Switch tab
function switchTab(tabName) {
    // Update buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active', 'border-blue-500', 'text-blue-400');
        btn.classList.add('border-transparent', 'text-slate-400');
    });

    const activeBtn = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active', 'border-blue-500', 'text-blue-400');
        activeBtn.classList.remove('border-transparent', 'text-slate-400');
    }

    // Update content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });

    const activeContent = document.getElementById(`tab-${tabName}`);
    if (activeContent) {
        activeContent.classList.remove('hidden');
    }
}

// Create organization
async function createOrganization() {
    const name = document.getElementById('new-org-name').value.trim();
    const acronym = document.getElementById('new-org-acronym').value.trim().toUpperCase();
    const domain = document.getElementById('new-org-domain').value.trim();
    const description = document.getElementById('new-org-description').value.trim();

    if (!name || !acronym) {
        Toast.error('Nome e sigla são obrigatórios');
        return;
    }

    try {
        const response = await API.post('organizations', {
            name,
            acronym,
            domain,
            description
        });

        if (response.success) {
            Toast.success('Organização criada com sucesso');
            closeModal();
            document.getElementById('new-org-form').reset();
            await loadDashboard();
            await loadOrganizations();
            selectOrganization(response.data.id);
        } else {
            Toast.error(response.error || 'Erro ao criar organização');
        }
    } catch (error) {
        Toast.error('Erro ao criar organização');
    }
}

// Update organization
async function updateOrganization() {
    if (!currentOrgId) return;

    const name = document.getElementById('edit-om-name').value.trim();
    const domain = document.getElementById('edit-om-domain').value.trim();
    const description = document.getElementById('edit-om-description').value.trim();

    if (!name) {
        Toast.error('Nome é obrigatório');
        return;
    }

    try {
        const data = await API.put('organization', currentOrgId, { name, domain, description });

        if (data.success) {
            Toast.success('Organização atualizada');
            await loadDashboard();
            await loadOrganizations();
        } else {
            Toast.error(data.error || 'Erro ao atualizar');
        }
    } catch (error) {
        Toast.error('Erro ao atualizar organização');
    }
}

// Delete organization
async function deleteOrganization() {
    if (!currentOrgId) return;

    try {
        const data = await API.delete('organization', currentOrgId);

        if (data.success) {
            Toast.success('Organização excluída');
            showDashboard();
            await loadDashboard();
            await loadOrganizations();
        } else {
            Toast.error(data.error || 'Erro ao excluir');
        }
    } catch (error) {
        Toast.error('Erro ao excluir organização');
    }
}

// Save variables
async function saveVariables() {
    if (!currentOrgId) return;

    const inputs = document.querySelectorAll('#vars-form input[data-var-id]');
    const variables = {};

    inputs.forEach(input => {
        const varId = input.dataset.varId;
        variables[varId] = input.value;
    });

    try {
        const response = await API.post('variables-update', {
            organization_id: currentOrgId,
            variables
        });

        if (response.success) {
            Toast.success('Variáveis salvas com sucesso');
        } else {
            Toast.error(response.error || 'Erro ao salvar variáveis');
        }
    } catch (error) {
        Toast.error('Erro ao salvar variáveis');
    }
}

// Show dashboard
function showDashboard() {
    currentOrgId = null;

    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });

    document.getElementById('view-dashboard').classList.remove('hidden');
    document.getElementById('view-om-detail').classList.add('hidden');

    document.getElementById('page-title').textContent = 'Dashboard';
    document.getElementById('page-subtitle').textContent = 'Visão geral do sistema';
}

// Close modal
function closeModal() {
    document.getElementById('modal-new-org').classList.add('hidden');
}

// Make selectOrganization available globally
window.selectOrganization = selectOrganization;
