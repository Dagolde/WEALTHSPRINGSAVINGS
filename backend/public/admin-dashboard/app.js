// Configuration
const API_BASE_URL = 'http://localhost:8002/api/v1';
let authToken = localStorage.getItem('admin_token');
let adminUser = JSON.parse(localStorage.getItem('admin_user') || 'null');
let currentSection = 'dashboard';

// Check if already logged in
if (authToken && adminUser) {
    showDashboard();
}

// Login Form Handler
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const loginBtn = document.getElementById('loginBtn');
    const loginError = document.getElementById('loginError');
    
    loginBtn.disabled = true;
    loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
    loginError.style.display = 'none';
    
    try {
        const response = await fetch(`${API_BASE_URL}/auth/admin/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (response.ok && data.success) {
            authToken = data.data.token;
            adminUser = data.data.user;
            
            localStorage.setItem('admin_token', authToken);
            localStorage.setItem('admin_user', JSON.stringify(adminUser));
            
            showDashboard();
        } else {
            loginError.textContent = data.error?.message || data.message || 'Login failed. Please check your credentials.';
            loginError.style.display = 'block';
        }
    } catch (error) {
        loginError.textContent = 'Connection error. Please check if the backend is running at ' + API_BASE_URL;
        loginError.style.display = 'block';
        console.error('Login error:', error);
    } finally {
        loginBtn.disabled = false;
        loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
    }
});

function showDashboard() {
    document.getElementById('loginScreen').style.display = 'none';
    document.getElementById('dashboardScreen').classList.add('active');
    document.getElementById('adminName').textContent = adminUser.name;
    
    loadDashboardData();
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        localStorage.removeItem('admin_token');
        localStorage.removeItem('admin_user');
        authToken = null;
        adminUser = null;
        
        document.getElementById('loginScreen').style.display = 'flex';
        document.getElementById('dashboardScreen').classList.remove('active');
    }
}

// Sidebar Toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('mainContent');
    sidebar.classList.toggle('collapsed');
    content.classList.toggle('expanded');
}

// Section Navigation
function showSection(section) {
    currentSection = section;
    
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(s => s.style.display = 'none');
    
    // Remove active class from all nav links
    document.querySelectorAll('.sidebar nav a').forEach(a => a.classList.remove('active'));
    
    // Show selected section
    document.getElementById(section + 'Section').style.display = 'block';
    
    // Add active class to clicked nav link
    document.querySelector(`[data-section="${section}"]`).classList.add('active');
    
    // Load section data
    switch(section) {
        case 'dashboard':
            loadDashboardData();
            break;
        case 'users':
            loadUsers();
            break;
        case 'kyc':
            loadKYC();
            break;
        case 'groups':
            loadGroups();
            break;
        case 'contributions':
            loadContributions();
            break;
        case 'withdrawals':
            loadWithdrawals();
            break;
        case 'transactions':
            loadTransactions();
            break;
        case 'analytics':
            loadAnalytics();
            break;
        case 'permissions':
            loadPermissions();
            break;
        case 'settings':
            loadSettings();
            break;
        case 'mobile':
            loadMobile();
            break;
        case 'profile':
            loadProfile();
            break;
    }
}

// API Helper
async function apiCall(endpoint, options = {}) {
    const defaultOptions = {
        headers: {
            'Authorization': `Bearer ${authToken}`,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    };
    
    const response = await fetch(`${API_BASE_URL}${endpoint}`, {
        ...defaultOptions,
        ...options,
        headers: { ...defaultOptions.headers, ...options.headers }
    });
    
    if (response.status === 401) {
        logout();
        throw new Error('Unauthorized');
    }
    
    return response.json();
}

// Dashboard
async function loadDashboardData() {
    const container = document.getElementById('statsContainer');
    
    try {
        const data = await apiCall('/admin/dashboard/stats');
        const stats = data.data;
        
        container.innerHTML = `
            <div class="stats-grid">
                <div class="stat-card primary">
                    <h3><i class="fas fa-users"></i> Total Users</h3>
                    <div class="value">${stats.users.total.toLocaleString()}</div>
                    <div class="change positive">
                        <i class="fas fa-arrow-up"></i> ${stats.users.active} active
                    </div>
                </div>
                <div class="stat-card success">
                    <h3><i class="fas fa-check-circle"></i> KYC Verified</h3>
                    <div class="value">${stats.users.kyc_verified.toLocaleString()}</div>
                    <div class="change warning">
                        <i class="fas fa-clock"></i> ${stats.users.kyc_pending} pending
                    </div>
                </div>
                <div class="stat-card info">
                    <h3><i class="fas fa-layer-group"></i> Active Groups</h3>
                    <div class="value">${stats.groups.active.toLocaleString()}</div>
                    <div class="change">
                        ${stats.groups.total} total groups
                    </div>
                </div>
                <div class="stat-card warning">
                    <h3><i class="fas fa-hand-holding-usd"></i> Contributions</h3>
                    <div class="value">${stats.transactions.total_contributions.toLocaleString()}</div>
                    <div class="change positive">
                        <i class="fas fa-arrow-up"></i> ${stats.transactions.success_rate}% success
                    </div>
                </div>
                <div class="stat-card success">
                    <h3><i class="fas fa-money-bill-wave"></i> Total Volume</h3>
                    <div class="value">₦${parseFloat(stats.transactions.total_volume).toLocaleString()}</div>
                    <div class="change">
                        ${stats.transactions.total_payouts} payouts
                    </div>
                </div>
                <div class="stat-card danger">
                    <h3><i class="fas fa-exclamation-triangle"></i> Pending Actions</h3>
                    <div class="value">${stats.system_health.pending_withdrawals + stats.system_health.pending_kyc}</div>
                    <div class="change">
                        ${stats.system_health.pending_withdrawals} withdrawals, ${stats.system_health.pending_kyc} KYC
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h3><i class="fas fa-chart-bar"></i> Visual Analytics</h3>
                <div class="charts-grid">
                    <div class="chart-container">
                        <h4>User Statistics</h4>
                        <canvas id="userStatsChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h4>Group Distribution</h4>
                        <canvas id="groupStatsChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h4>KYC Status</h4>
                        <canvas id="kycStatsChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h4>Transaction Overview</h4>
                        <canvas id="transactionStatsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h3><i class="fas fa-chart-line"></i> Quick Overview</h3>
                <table>
                    <tr>
                        <td><strong>Suspended Users</strong></td>
                        <td>${stats.users.suspended}</td>
                        <td><strong>Completed Groups</strong></td>
                        <td>${stats.groups.completed}</td>
                    </tr>
                    <tr>
                        <td><strong>KYC Rejected</strong></td>
                        <td>${stats.users.kyc_rejected}</td>
                        <td><strong>Failed Payouts</strong></td>
                        <td>${stats.system_health.failed_payouts}</td>
                    </tr>
                    <tr>
                        <td><strong>Inactive Users</strong></td>
                        <td>${stats.users.inactive}</td>
                        <td><strong>Cancelled Groups</strong></td>
                        <td>${stats.groups.cancelled}</td>
                    </tr>
                </table>
            </div>
        `;
        
        // Update badges
        document.getElementById('kycBadge').textContent = stats.system_health.pending_kyc;
        document.getElementById('withdrawalBadge').textContent = stats.system_health.pending_withdrawals;
        
        // Create charts
        createDashboardCharts(stats);
        
    } catch (error) {
        container.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Failed to load statistics</div>';
        console.error('Stats error:', error);
    }
}

// Create Dashboard Charts
function createDashboardCharts(stats) {
    // User Statistics Chart
    const userCtx = document.getElementById('userStatsChart');
    if (userCtx) {
        new Chart(userCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Inactive', 'Suspended'],
                datasets: [{
                    data: [stats.users.active, stats.users.inactive, stats.users.suspended],
                    backgroundColor: ['#27ae60', '#95a5a6', '#e74c3c'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Group Distribution Chart
    const groupCtx = document.getElementById('groupStatsChart');
    if (groupCtx) {
        new Chart(groupCtx, {
            type: 'bar',
            data: {
                labels: ['Pending', 'Active', 'Completed', 'Cancelled'],
                datasets: [{
                    label: 'Groups',
                    data: [stats.groups.pending, stats.groups.active, stats.groups.completed, stats.groups.cancelled],
                    backgroundColor: ['#f39c12', '#27ae60', '#3498db', '#e74c3c'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // KYC Status Chart
    const kycCtx = document.getElementById('kycStatsChart');
    if (kycCtx) {
        new Chart(kycCtx, {
            type: 'pie',
            data: {
                labels: ['Verified', 'Pending', 'Rejected'],
                datasets: [{
                    data: [stats.users.kyc_verified, stats.users.kyc_pending, stats.users.kyc_rejected],
                    backgroundColor: ['#27ae60', '#f39c12', '#e74c3c'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Transaction Overview Chart
    const transCtx = document.getElementById('transactionStatsChart');
    if (transCtx) {
        new Chart(transCtx, {
            type: 'line',
            data: {
                labels: ['Contributions', 'Payouts', 'Withdrawals'],
                datasets: [{
                    label: 'Transaction Count',
                    data: [stats.transactions.total_contributions, stats.transactions.total_payouts, stats.transactions.total_withdrawals],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

// Users
async function loadUsers() {
    const container = document.getElementById('usersContainer');
    const statusFilter = document.getElementById('userStatusFilter').value;
    const kycFilter = document.getElementById('userKycFilter').value;
    
    let url = '/admin/users?per_page=50';
    if (statusFilter) url += `&status=${statusFilter}`;
    if (kycFilter) url += `&kyc_status=${kycFilter}`;
    
    try {
        const data = await apiCall(url);
        const users = data.data.data;
        
        if (users.length === 0) {
            container.innerHTML = '<div class="empty-state"><i class="fas fa-users"></i><p>No users found</p></div>';
            return;
        }
        
        container.innerHTML = `
            <div class="section">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>KYC</th>
                            <th>Wallet</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${users.map(user => `
                            <tr>
                                <td>${user.id}</td>
                                <td><strong>${user.name}</strong></td>
                                <td>${user.email}</td>
                                <td>${user.phone || 'N/A'}</td>
                                <td><span class="badge ${user.status === 'active' ? 'success' : 'danger'}">${user.status}</span></td>
                                <td><span class="badge ${user.kyc_status === 'verified' ? 'success' : user.kyc_status === 'pending' ? 'warning' : 'danger'}">${user.kyc_status}</span></td>
                                <td>₦${parseFloat(user.wallet_balance || 0).toLocaleString()}</td>
                                <td><span class="badge ${user.role === 'admin' ? 'info' : 'primary'}">${user.role}</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="viewUser(${user.id})">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="editUser(${user.id})">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        ${user.role !== 'admin' && user.status === 'active' ? `
                                            <button class="btn btn-sm btn-danger" onclick="suspendUser(${user.id})">
                                                <i class="fas fa-ban"></i> Suspend
                                            </button>
                                        ` : ''}
                                        ${user.role !== 'admin' && user.status === 'suspended' ? `
                                            <button class="btn btn-sm btn-success" onclick="activateUser(${user.id})">
                                                <i class="fas fa-check"></i> Activate
                                            </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        container.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Failed to load users</div>';
        console.error('Users error:', error);
    }
}

async function viewUser(userId) {
    try {
        const data = await apiCall(`/admin/users/${userId}`);
        const user = data.data;
        
        document.getElementById('userModalContent').innerHTML = `
            <div class="modal-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>User ID</h3>
                        <div class="value">${user.id}</div>
                    </div>
                    <div class="stat-card">
                        <h3>Status</h3>
                        <div class="value"><span class="badge ${user.status === 'active' ? 'success' : 'danger'}">${user.status}</span></div>
                    </div>
                    <div class="stat-card">
                        <h3>KYC Status</h3>
                        <div class="value"><span class="badge ${user.kyc_status === 'verified' ? 'success' : 'warning'}">${user.kyc_status}</span></div>
                    </div>
                    <div class="stat-card">
                        <h3>Wallet Balance</h3>
                        <div class="value">₦${parseFloat(user.wallet_balance).toLocaleString()}</div>
                    </div>
                </div>
                
                <h4>Personal Information</h4>
                <table>
                    <tr><td><strong>Name</strong></td><td>${user.name}</td></tr>
                    <tr><td><strong>Email</strong></td><td>${user.email}</td></tr>
                    <tr><td><strong>Phone</strong></td><td>${user.phone}</td></tr>
                    <tr><td><strong>Role</strong></td><td>${user.role}</td></tr>
                    <tr><td><strong>Joined</strong></td><td>${new Date(user.created_at).toLocaleDateString()}</td></tr>
                </table>
                
                <div class="action-buttons" style="margin-top: 20px;">
                    ${user.role !== 'admin' && user.status === 'active' ? `
                        <button class="btn btn-danger" onclick="suspendUser(${user.id}); closeModal('userModal');">
                            <i class="fas fa-ban"></i> Suspend User
                        </button>
                    ` : ''}
                    ${user.role !== 'admin' && user.status === 'suspended' ? `
                        <button class="btn btn-success" onclick="activateUser(${user.id}); closeModal('userModal');">
                            <i class="fas fa-check"></i> Activate User
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
        
        openModal('userModal');
    } catch (error) {
        alert('Failed to load user details');
        console.error(error);
    }
}

async function suspendUser(userId) {
    const reason = prompt('Enter reason for suspension:');
    if (!reason) return;
    
    try {
        await apiCall(`/admin/users/${userId}/suspend`, {
            method: 'PUT',
            body: JSON.stringify({ reason })
        });
        
        showNotification('User suspended successfully', 'success');
        loadUsers();
    } catch (error) {
        showNotification('Failed to suspend user', 'error');
        console.error(error);
    }
}

async function activateUser(userId) {
    if (!confirm('Are you sure you want to activate this user?')) return;
    
    try {
        await apiCall(`/admin/users/${userId}/activate`, {
            method: 'PUT'
        });
        
        showNotification('User activated successfully', 'success');
        loadUsers();
    } catch (error) {
        showNotification('Failed to activate user', 'error');
        console.error(error);
    }
}

// KYC
async function loadKYC() {
    const container = document.getElementById('kycContainer');
    
    try {
        const data = await apiCall('/admin/kyc/pending?per_page=50');
        const submissions = data.data.data;
        
        if (submissions.length === 0) {
            container.innerHTML = '<div class="empty-state"><i class="fas fa-id-card"></i><p>No pending KYC submissions</p></div>';
            return;
        }
        
        container.innerHTML = `
            <div class="section">
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Document</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${submissions.map(user => `
                            <tr>
                                <td>${user.id}</td>
                                <td><strong>${user.name}</strong></td>
                                <td>${user.email}</td>
                                <td>
                                    ${user.kyc_document_url ? `
                                        <a href="${user.kyc_document_url}" target="_blank" class="btn btn-sm">
                                            <i class="fas fa-file"></i> View
                                        </a>
                                    ` : 'No document'}
                                </td>
                                <td>${new Date(user.created_at).toLocaleDateString()}</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-success" onclick="approveKYC(${user.id})">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectKYC(${user.id})">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        container.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Failed to load KYC submissions</div>';
        console.error('KYC error:', error);
    }
}

async function approveKYC(userId) {
    if (!confirm('Are you sure you want to approve this KYC submission?')) return;
    
    try {
        await apiCall(`/admin/kyc/${userId}/approve`, {
            method: 'POST'
        });
        
        showNotification('KYC approved successfully', 'success');
        loadKYC();
        loadDashboardData();
    } catch (error) {
        showNotification('Failed to approve KYC', 'error');
        console.error(error);
    }
}

async function rejectKYC(userId) {
    const reason = prompt('Enter reason for rejection:');
    if (!reason) return;
    
    try {
        await apiCall(`/admin/kyc/${userId}/reject`, {
            method: 'POST',
            body: JSON.stringify({ reason })
        });
        
        showNotification('KYC rejected successfully', 'success');
        loadKYC();
        loadDashboardData();
    } catch (error) {
        showNotification('Failed to reject KYC', 'error');
        console.error(error);
    }
}

// Groups
async function loadGroups() {
    const container = document.getElementById('groupsContainer');
    const statusFilter = document.getElementById('groupStatusFilter').value;
    
    let url = '/admin/groups?per_page=50';
    if (statusFilter) url += `&status=${statusFilter}`;
    
    try {
        const data = await apiCall(url);
        const groups = data.data.data;
        
        if (groups.length === 0) {
            container.innerHTML = '<div class="empty-state"><i class="fas fa-layer-group"></i><p>No groups found</p></div>';
            return;
        }
        
        container.innerHTML = `
            <div class="section">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Members</th>
                            <th>Contribution</th>
                            <th>Frequency</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${groups.map(group => `
                            <tr>
                                <td>${group.id}</td>
                                <td><strong>${group.name}</strong></td>
                                <td>${group.current_members}/${group.max_members}</td>
                                <td>₦${parseFloat(group.contribution_amount).toLocaleString()}</td>
                                <td>${group.contribution_frequency}</td>
                                <td><span class="badge ${group.status === 'active' ? 'success' : group.status === 'pending' ? 'warning' : 'danger'}">${group.status}</span></td>
                                <td>${new Date(group.created_at).toLocaleDateString()}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="viewGroup(${group.id})">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        container.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Failed to load groups</div>';
        console.error('Groups error:', error);
    }
}

async function viewGroup(groupId) {
    try {
        const data = await apiCall(`/admin/groups/${groupId}`);
        const group = data.data;
        
        document.getElementById('groupModalContent').innerHTML = `
            <div class="modal-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Group ID</h3>
                        <div class="value">${group.id}</div>
                    </div>
                    <div class="stat-card">
                        <h3>Members</h3>
                        <div class="value">${group.current_members}/${group.max_members}</div>
                    </div>
                    <div class="stat-card">
                        <h3>Contribution</h3>
                        <div class="value">₦${parseFloat(group.contribution_amount).toLocaleString()}</div>
                    </div>
                    <div class="stat-card">
                        <h3>Status</h3>
                        <div class="value"><span class="badge ${group.status === 'active' ? 'success' : 'warning'}">${group.status}</span></div>
                    </div>
                </div>
                
                <h4>Group Information</h4>
                <table>
                    <tr><td><strong>Name</strong></td><td>${group.name}</td></tr>
                    <tr><td><strong>Description</strong></td><td>${group.description || 'N/A'}</td></tr>
                    <tr><td><strong>Frequency</strong></td><td>${group.contribution_frequency}</td></tr>
                    <tr><td><strong>Start Date</strong></td><td>${group.start_date ? new Date(group.start_date).toLocaleDateString() : 'Not started'}</td></tr>
                    <tr><td><strong>Created</strong></td><td>${new Date(group.created_at).toLocaleDateString()}</td></tr>
                </table>
                
                <div class="action-buttons" style="margin-top: 20px;">
                    ${group.status === 'pending' && group.current_members >= 2 ? `
                        <button class="btn btn-success" onclick="startGroup(${group.id}); closeModal('groupModal');">
                            <i class="fas fa-play"></i> Start Group
                        </button>
                    ` : ''}
                    ${group.status === 'active' ? `
                        <button class="btn btn-primary" onclick="viewGroupMembers(${group.id})">
                            <i class="fas fa-users"></i> View Members
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
        
        openModal('groupModal');
    } catch (error) {
        alert('Failed to load group details');
        console.error(error);
    }
}

function openCreateGroupModal() {
    document.getElementById('createGroupForm').reset();
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('groupStartDate').min = today;
    openModal('createGroupModal');
}

document.getElementById('createGroupForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    await createGroup();
});

async function createGroup() {
    const groupData = {
        name: document.getElementById('groupName').value,
        description: document.getElementById('groupDescription').value,
        max_members: parseInt(document.getElementById('groupMaxMembers').value),
        contribution_amount: parseFloat(document.getElementById('groupContributionAmount').value),
        contribution_frequency: document.getElementById('groupFrequency').value,
        start_date: document.getElementById('groupStartDate').value
    };
    
    try {
        await apiCall('/groups', {
            method: 'POST',
            body: JSON.stringify(groupData)
        });
        
        showNotification('Group created successfully', 'success');
        closeModal('createGroupModal');
        loadGroups();
    } catch (error) {
        showNotification('Failed to create group: ' + (error.message || 'Unknown error'), 'error');
        console.error(error);
    }
}

async function startGroup(groupId) {
    if (!confirm('Are you sure you want to start this group? This action cannot be undone.')) return;
    
    try {
        await apiCall(`/groups/${groupId}/start`, {
            method: 'POST'
        });
        
        showNotification('Group started successfully', 'success');
        loadGroups();
    } catch (error) {
        showNotification('Failed to start group', 'error');
        console.error(error);
    }
}

async function viewGroupMembers(groupId) {
    try {
        const data = await apiCall(`/admin/groups/${groupId}/members`);
        const members = data.data;
        
        document.getElementById('groupModalContent').innerHTML = `
            <div class="modal-body">
                <h4><i class="fas fa-users"></i> Group Members</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${members.map(member => `
                            <tr>
                                <td><strong>${member.user.name}</strong></td>
                                <td>${member.user.email}</td>
                                <td>${member.position_in_queue}</td>
                                <td><span class="badge ${member.status === 'active' ? 'success' : 'warning'}">${member.status}</span></td>
                                <td>${new Date(member.joined_at).toLocaleDateString()}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        showNotification('Failed to load group members', 'error');
        console.error(error);
    }
}

// Withdrawals
async function loadWithdrawals() {
    const container = document.getElementById('withdrawalsContainer');
    
    try {
        const data = await apiCall('/admin/withdrawals/pending?per_page=50');
        const withdrawals = data.data.data;
        
        if (withdrawals.length === 0) {
            container.innerHTML = '<div class="empty-state"><i class="fas fa-money-bill-wave"></i><p>No pending withdrawals</p></div>';
            return;
        }
        
        container.innerHTML = `
            <div class="section">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Bank Account</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${withdrawals.map(w => `
                            <tr>
                                <td>${w.id}</td>
                                <td><strong>${w.user.name}</strong><br><small>${w.user.email}</small></td>
                                <td><strong>₦${parseFloat(w.amount).toLocaleString()}</strong></td>
                                <td>${w.bank_account ? w.bank_account.bank_name : 'N/A'}</td>
                                <td>${new Date(w.created_at).toLocaleDateString()}</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-success" onclick="approveWithdrawal(${w.id})">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectWithdrawal(${w.id})">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        container.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Failed to load withdrawals</div>';
        console.error('Withdrawals error:', error);
    }
}

async function approveWithdrawal(withdrawalId) {
    if (!confirm('Are you sure you want to approve this withdrawal?')) return;
    
    try {
        await apiCall(`/admin/withdrawals/${withdrawalId}/approve`, {
            method: 'POST'
        });
        
        showNotification('Withdrawal approved successfully', 'success');
        loadWithdrawals();
        loadDashboardData();
    } catch (error) {
        showNotification('Failed to approve withdrawal', 'error');
        console.error(error);
    }
}

async function rejectWithdrawal(withdrawalId) {
    const reason = prompt('Enter reason for rejection:');
    if (!reason) return;
    
    try {
        await apiCall(`/admin/withdrawals/${withdrawalId}/reject`, {
            method: 'POST',
            body: JSON.stringify({ reason })
        });
        
        showNotification('Withdrawal rejected successfully', 'success');
        loadWithdrawals();
        loadDashboardData();
    } catch (error) {
        showNotification('Failed to reject withdrawal', 'error');
        console.error(error);
    }
}

// Contributions
async function loadContributions() {
    const container = document.getElementById('contributionsContainer');
    const statusFilter = document.getElementById('contributionStatusFilter').value;
    const groupFilter = document.getElementById('contributionGroupFilter').value;
    
    let url = '/admin/contributions?per_page=50';
    if (statusFilter) url += `&status=${statusFilter}`;
    if (groupFilter) url += `&group_id=${groupFilter}`;
    
    try {
        // Load groups for filter dropdown
        const groupsData = await apiCall('/admin/groups?per_page=100');
        const groupFilterSelect = document.getElementById('contributionGroupFilter');
        if (groupFilterSelect.options.length === 1) {
            groupsData.data.data.forEach(group => {
                const option = document.createElement('option');
                option.value = group.id;
                option.textContent = group.name;
                groupFilterSelect.appendChild(option);
            });
        }
        
        // Load contributions
        const data = await apiCall(url);
        const contributions = data.data.data || data.data;
        
        if (!contributions || contributions.length === 0) {
            container.innerHTML = '<div class="empty-state"><i class="fas fa-hand-holding-usd"></i><p>No contributions found</p></div>';
            return;
        }
        
        container.innerHTML = `
            <div class="section">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Group</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${contributions.map(contrib => `
                            <tr>
                                <td>${contrib.id}</td>
                                <td><strong>${contrib.user?.name || 'N/A'}</strong><br><small>${contrib.user?.email || ''}</small></td>
                                <td>${contrib.group?.name || 'N/A'}</td>
                                <td><strong>₦${parseFloat(contrib.amount).toLocaleString()}</strong></td>
                                <td><span class="badge primary">${contrib.payment_method || 'N/A'}</span></td>
                                <td><span class="badge ${contrib.payment_status === 'successful' ? 'success' : contrib.payment_status === 'pending' ? 'warning' : 'danger'}">${contrib.payment_status}</span></td>
                                <td>${new Date(contrib.created_at).toLocaleDateString()}</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-primary" onclick="viewContribution(${contrib.id})">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        ${contrib.payment_status === 'pending' ? `
                                            <button class="btn btn-sm btn-success" onclick="verifyContribution(${contrib.id})">
                                                <i class="fas fa-check"></i> Verify
                                            </button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        container.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Failed to load contributions</div>';
        console.error('Contributions error:', error);
    }
}

async function viewContribution(contributionId) {
    try {
        const data = await apiCall(`/admin/contributions/${contributionId}`);
        const contrib = data.data;
        
        document.getElementById('userModalContent').innerHTML = `
            <div class="modal-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Contribution ID</h3>
                        <div class="value">${contrib.id}</div>
                    </div>
                    <div class="stat-card">
                        <h3>Amount</h3>
                        <div class="value">₦${parseFloat(contrib.amount).toLocaleString()}</div>
                    </div>
                    <div class="stat-card">
                        <h3>Status</h3>
                        <div class="value"><span class="badge ${contrib.payment_status === 'successful' ? 'success' : 'warning'}">${contrib.payment_status}</span></div>
                    </div>
                    <div class="stat-card">
                        <h3>Payment Method</h3>
                        <div class="value"><span class="badge primary">${contrib.payment_method}</span></div>
                    </div>
                </div>
                
                <h4>Contribution Details</h4>
                <table>
                    <tr><td><strong>User</strong></td><td>${contrib.user?.name || 'N/A'}</td></tr>
                    <tr><td><strong>Email</strong></td><td>${contrib.user?.email || 'N/A'}</td></tr>
                    <tr><td><strong>Group</strong></td><td>${contrib.group?.name || 'N/A'}</td></tr>
                    <tr><td><strong>Payment Reference</strong></td><td>${contrib.payment_reference || 'N/A'}</td></tr>
                    <tr><td><strong>Transaction ID</strong></td><td>${contrib.transaction_id || 'N/A'}</td></tr>
                    <tr><td><strong>Date</strong></td><td>${new Date(contrib.created_at).toLocaleString()}</td></tr>
                </table>
            </div>
        `;
        
        openModal('userModal');
    } catch (error) {
        showNotification('Failed to load contribution details', 'error');
        console.error(error);
    }
}

async function verifyContribution(contributionId) {
    if (!confirm('Are you sure you want to verify this contribution?')) return;
    
    try {
        await apiCall(`/admin/contributions/${contributionId}/verify`, {
            method: 'POST'
        });
        
        showNotification('Contribution verified successfully', 'success');
        loadContributions();
    } catch (error) {
        showNotification('Failed to verify contribution', 'error');
        console.error(error);
    }
}

function openRecordContributionModal() {
    // Load groups
    loadGroupsForContribution();
    openModal('recordContributionModal');
}

async function loadGroupsForContribution() {
    try {
        const data = await apiCall('/admin/groups?status=active&per_page=100');
        const groups = data.data.data;
        
        const select = document.getElementById('contribGroupId');
        select.innerHTML = '<option value="">Select a group...</option>';
        
        groups.forEach(group => {
            const option = document.createElement('option');
            option.value = group.id;
            option.textContent = `${group.name} (₦${parseFloat(group.contribution_amount).toLocaleString()})`;
            option.dataset.amount = group.contribution_amount;
            select.appendChild(option);
        });
    } catch (error) {
        showNotification('Failed to load groups', 'error');
        console.error(error);
    }
}

async function loadGroupMembers() {
    const groupId = document.getElementById('contribGroupId').value;
    const amountField = document.getElementById('contribAmount');
    const memberSelect = document.getElementById('contribUserId');
    
    if (!groupId) {
        memberSelect.innerHTML = '<option value="">Select a member...</option>';
        return;
    }
    
    // Set contribution amount from group
    const selectedOption = document.getElementById('contribGroupId').selectedOptions[0];
    if (selectedOption.dataset.amount) {
        amountField.value = selectedOption.dataset.amount;
    }
    
    try {
        const data = await apiCall(`/admin/groups/${groupId}/members`);
        const members = data.data;
        
        memberSelect.innerHTML = '<option value="">Select a member...</option>';
        members.forEach(member => {
            const option = document.createElement('option');
            option.value = member.user_id;
            option.textContent = `${member.user.name} (${member.user.email})`;
            memberSelect.appendChild(option);
        });
    } catch (error) {
        showNotification('Failed to load group members', 'error');
        console.error(error);
    }
}

document.getElementById('recordContributionForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    await recordContribution();
});

async function recordContribution() {
    const contributionData = {
        group_id: document.getElementById('contribGroupId').value,
        user_id: document.getElementById('contribUserId').value,
        amount: document.getElementById('contribAmount').value,
        payment_method: document.getElementById('contribPaymentMethod').value,
        payment_reference: document.getElementById('contribReference').value,
        notes: document.getElementById('contribNotes').value,
        payment_status: 'successful' // Admin recorded contributions are automatically successful
    };
    
    try {
        await apiCall('/admin/contributions', {
            method: 'POST',
            body: JSON.stringify(contributionData)
        });
        
        showNotification('Contribution recorded successfully', 'success');
        closeModal('recordContributionModal');
        loadContributions();
    } catch (error) {
        showNotification('Failed to record contribution: ' + (error.message || 'Unknown error'), 'error');
        console.error(error);
    }
}

function searchContributions() {
    // Implement search functionality
}

function exportContributions() {
    window.open(`${API_BASE_URL}/admin/analytics/transactions?export=csv`, '_blank');
}

// Transactions
async function loadTransactions() {
    const container = document.getElementById('transactionsContainer');
    container.innerHTML = '<div class="info"><i class="fas fa-info-circle"></i> Transaction management coming soon</div>';
}

// Analytics
async function loadAnalytics() {
    const container = document.getElementById('analyticsContainer');
    
    // Show loading state
    container.innerHTML = '<div class="loading">Loading analytics...</div>';
    
    try {
        // Get date range (default: last 30 days)
        const endDate = new Date().toISOString().split('T')[0];
        const startDate = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        
        // Fetch all analytics data in parallel
        const [userAnalytics, groupAnalytics, transactionAnalytics, revenueAnalytics] = await Promise.all([
            apiCall(`/admin/analytics/users?start_date=${startDate}&end_date=${endDate}`),
            apiCall(`/admin/analytics/groups?start_date=${startDate}&end_date=${endDate}`),
            apiCall(`/admin/analytics/transactions?start_date=${startDate}&end_date=${endDate}`),
            apiCall(`/admin/analytics/revenue?start_date=${startDate}&end_date=${endDate}`)
        ]);
        
        const users = userAnalytics.data;
        const groups = groupAnalytics.data;
        const transactions = transactionAnalytics.data;
        const revenue = revenueAnalytics.data;
        
        container.innerHTML = `
            <div class="section">
                <div class="section-header" style="margin-bottom: 20px;">
                    <div>
                        <h3><i class="fas fa-calendar-alt"></i> Date Range</h3>
                        <p style="margin: 5px 0; color: var(--text-secondary);">
                            ${new Date(users.period.start_date).toLocaleDateString()} - ${new Date(users.period.end_date).toLocaleDateString()}
                        </p>
                    </div>
                    <div class="header-actions">
                        <select id="analyticsDateRange" onchange="changeAnalyticsDateRange()">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="365">Last Year</option>
                        </select>
                        <button class="btn btn-sm" onclick="exportAllAnalytics()">
                            <i class="fas fa-download"></i> Export All
                        </button>
                    </div>
                </div>
                
                <!-- Revenue Overview -->
                <h3><i class="fas fa-dollar-sign"></i> Revenue Overview</h3>
                <div class="stats-grid">
                    <div class="stat-card success">
                        <h3>Total Revenue</h3>
                        <div class="value">₦${parseFloat(revenue.total_revenue || 0).toLocaleString()}</div>
                        <div class="change">Platform earnings</div>
                    </div>
                    <div class="stat-card info">
                        <h3>Funding Fees</h3>
                        <div class="value">₦${parseFloat(revenue.funding_fees || 0).toLocaleString()}</div>
                        <div class="change">From wallet funding</div>
                    </div>
                    <div class="stat-card warning">
                        <h3>Withdrawal Fees</h3>
                        <div class="value">₦${parseFloat(revenue.withdrawal_fees || 0).toLocaleString()}</div>
                        <div class="change">From withdrawals</div>
                    </div>
                    <div class="stat-card primary">
                        <h3>Avg Revenue/User</h3>
                        <div class="value">₦${parseFloat(revenue.revenue_per_user || 0).toLocaleString()}</div>
                        <div class="change">${revenue.active_users || 0} active users</div>
                    </div>
                </div>
                
                <!-- User Analytics -->
                <h3><i class="fas fa-users"></i> User Analytics</h3>
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <h3>New Users</h3>
                        <div class="value">${users.new_users || 0}</div>
                        <div class="change">In selected period</div>
                    </div>
                    <div class="stat-card success">
                        <h3>Active Users</h3>
                        <div class="value">${users.active_users || 0}</div>
                        <div class="change">${users.retention_rate || 0}% retention rate</div>
                    </div>
                    <div class="stat-card info">
                        <h3>Total Users</h3>
                        <div class="value">${users.total_users || 0}</div>
                        <div class="change">All time</div>
                    </div>
                    <div class="stat-card warning">
                        <h3>KYC Verification</h3>
                        <div class="value">${users.kyc_verification_rate || 0}%</div>
                        <div class="change">Verification rate</div>
                    </div>
                </div>
                
                <div class="charts-grid">
                    <div class="chart-container">
                        <h4>User Growth Trend</h4>
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h4>User Metrics</h4>
                        <canvas id="userMetricsChart"></canvas>
                    </div>
                </div>
                
                <!-- Group Analytics -->
                <h3><i class="fas fa-layer-group"></i> Group Analytics</h3>
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <h3>Groups Started</h3>
                        <div class="value">${groups.groups_started || 0}</div>
                        <div class="change">In selected period</div>
                    </div>
                    <div class="stat-card success">
                        <h3>Groups Completed</h3>
                        <div class="value">${groups.groups_completed || 0}</div>
                        <div class="change">${groups.completion_rate || 0}% completion rate</div>
                    </div>
                    <div class="stat-card info">
                        <h3>Avg Group Size</h3>
                        <div class="value">${groups.average_group_size || 0}</div>
                        <div class="change">Members per group</div>
                    </div>
                    <div class="stat-card warning">
                        <h3>Avg Contribution</h3>
                        <div class="value">₦${parseFloat(groups.average_contribution_amount || 0).toLocaleString()}</div>
                        <div class="change">Per member</div>
                    </div>
                </div>
                
                <div class="charts-grid">
                    <div class="chart-container">
                        <h4>Group Creation Trend</h4>
                        <canvas id="groupCreationChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h4>Groups by Status</h4>
                        <canvas id="groupStatusChart"></canvas>
                    </div>
                </div>
                
                <!-- Transaction Analytics -->
                <h3><i class="fas fa-exchange-alt"></i> Transaction Analytics</h3>
                <div class="stats-grid">
                    <div class="stat-card success">
                        <h3>Contribution Volume</h3>
                        <div class="value">₦${parseFloat(transactions.total_contribution_volume || 0).toLocaleString()}</div>
                        <div class="change">${transactions.contribution_success_rate || 0}% success rate</div>
                    </div>
                    <div class="stat-card info">
                        <h3>Payout Volume</h3>
                        <div class="value">₦${parseFloat(transactions.total_payout_volume || 0).toLocaleString()}</div>
                        <div class="change">${transactions.payout_success_rate || 0}% success rate</div>
                    </div>
                    <div class="stat-card warning">
                        <h3>Withdrawal Volume</h3>
                        <div class="value">₦${parseFloat(transactions.total_withdrawal_volume || 0).toLocaleString()}</div>
                        <div class="change">Total withdrawals</div>
                    </div>
                    <div class="stat-card primary">
                        <h3>Total Volume</h3>
                        <div class="value">₦${parseFloat(transactions.total_transaction_volume || 0).toLocaleString()}</div>
                        <div class="change">All transactions</div>
                    </div>
                </div>
                
                <div class="charts-grid">
                    <div class="chart-container" style="grid-column: span 2;">
                        <h4>Transaction Trends</h4>
                        <canvas id="transactionTrendsChart"></canvas>
                    </div>
                </div>
                
                <div class="charts-grid">
                    <div class="chart-container">
                        <h4>Transaction Volume Distribution</h4>
                        <canvas id="volumeDistributionChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h4>Success Rates</h4>
                        <canvas id="successRatesChart"></canvas>
                    </div>
                </div>
            </div>
        `;
        
        // Create all charts
        createAnalyticsCharts(users, groups, transactions);
        
    } catch (error) {
        container.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Failed to load analytics</div>';
        console.error('Analytics error:', error);
    }
}

function createAnalyticsCharts(users, groups, transactions) {
    // User Growth Chart
    const userGrowthCtx = document.getElementById('userGrowthChart');
    if (userGrowthCtx && users.user_growth) {
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: users.user_growth.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'New Users',
                    data: users.user_growth.map(d => d.count),
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    // User Metrics Chart
    const userMetricsCtx = document.getElementById('userMetricsChart');
    if (userMetricsCtx) {
        new Chart(userMetricsCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active Users', 'Inactive Users'],
                datasets: [{
                    data: [users.active_users, users.total_users - users.active_users],
                    backgroundColor: ['#27ae60', '#95a5a6'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Group Creation Chart
    const groupCreationCtx = document.getElementById('groupCreationChart');
    if (groupCreationCtx && groups.group_creation) {
        new Chart(groupCreationCtx, {
            type: 'bar',
            data: {
                labels: groups.group_creation.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'Groups Created',
                    data: groups.group_creation.map(d => d.count),
                    backgroundColor: '#3498db',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    // Group Status Chart
    const groupStatusCtx = document.getElementById('groupStatusChart');
    if (groupStatusCtx && groups.groups_by_status) {
        const statusData = groups.groups_by_status;
        new Chart(groupStatusCtx, {
            type: 'pie',
            data: {
                labels: Object.keys(statusData).map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{
                    data: Object.values(statusData),
                    backgroundColor: ['#f39c12', '#27ae60', '#3498db', '#e74c3c'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Transaction Trends Chart
    const transactionTrendsCtx = document.getElementById('transactionTrendsChart');
    if (transactionTrendsCtx && transactions.contribution_trends) {
        new Chart(transactionTrendsCtx, {
            type: 'line',
            data: {
                labels: transactions.contribution_trends.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [
                    {
                        label: 'Contributions',
                        data: transactions.contribution_trends.map(d => d.count),
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Payouts',
                        data: transactions.payout_trends.map(d => d.count),
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Withdrawals',
                        data: transactions.withdrawal_trends.map(d => d.count),
                        borderColor: '#f39c12',
                        backgroundColor: 'rgba(243, 156, 18, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    // Volume Distribution Chart
    const volumeDistCtx = document.getElementById('volumeDistributionChart');
    if (volumeDistCtx) {
        new Chart(volumeDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Contributions', 'Payouts', 'Withdrawals'],
                datasets: [{
                    data: [
                        transactions.total_contribution_volume,
                        transactions.total_payout_volume,
                        transactions.total_withdrawal_volume
                    ],
                    backgroundColor: ['#27ae60', '#3498db', '#f39c12'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Success Rates Chart
    const successRatesCtx = document.getElementById('successRatesChart');
    if (successRatesCtx) {
        new Chart(successRatesCtx, {
            type: 'bar',
            data: {
                labels: ['Contributions', 'Payouts'],
                datasets: [{
                    label: 'Success Rate (%)',
                    data: [transactions.contribution_success_rate, transactions.payout_success_rate],
                    backgroundColor: ['#27ae60', '#3498db'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }
}

async function changeAnalyticsDateRange() {
    const days = parseInt(document.getElementById('analyticsDateRange').value);
    const endDate = new Date().toISOString().split('T')[0];
    const startDate = new Date(Date.now() - days * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    
    // Reload analytics with new date range
    loadAnalytics();
}

function exportAllAnalytics() {
    const days = parseInt(document.getElementById('analyticsDateRange')?.value || 30);
    const endDate = new Date().toISOString().split('T')[0];
    const startDate = new Date(Date.now() - days * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    
    // Export all analytics as CSV
    window.open(`${API_BASE_URL}/admin/analytics/users?start_date=${startDate}&end_date=${endDate}&export=csv`, '_blank');
    window.open(`${API_BASE_URL}/admin/analytics/groups?start_date=${startDate}&end_date=${endDate}&export=csv`, '_blank');
    window.open(`${API_BASE_URL}/admin/analytics/transactions?start_date=${startDate}&end_date=${endDate}&export=csv`, '_blank');
    window.open(`${API_BASE_URL}/admin/analytics/revenue?start_date=${startDate}&end_date=${endDate}&export=csv`, '_blank');
    
    showNotification('Exporting all analytics...', 'success');
}

// Permissions Management
async function loadPermissions() {
    const container = document.getElementById('permissionsContainer');
    
    try {
        // Load all admin users
        const data = await apiCall('/admin/users?role=admin&per_page=100');
        const admins = data.data.data.filter(u => u.role === 'admin');
        
        container.innerHTML = `
            <div class="section">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i> 
                    Manage permissions for admin users. Super admins have full access to all features. 
                    Regular admins can be granted specific permissions.
                </p>
                
                <h3><i class="fas fa-users-cog"></i> Admin Users</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Admin Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${admins.map(admin => `
                            <tr>
                                <td>${admin.id}</td>
                                <td><strong>${admin.name}</strong></td>
                                <td>${admin.email}</td>
                                <td>
                                    <span class="badge ${admin.id === adminUser.id ? 'danger' : 'info'}">
                                        ${admin.id === adminUser.id ? 'Super Admin (You)' : 'Admin'}
                                    </span>
                                </td>
                                <td><span class="badge ${admin.status === 'active' ? 'success' : 'danger'}">${admin.status}</span></td>
                                <td>
                                    ${admin.id !== adminUser.id ? `
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-primary" onclick="managePermissions(${admin.id})">
                                                <i class="fas fa-key"></i> Manage Permissions
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="viewAdminDetails(${admin.id})">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </div>
                                    ` : '<span class="badge info">Current User</span>'}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
        
    } catch (error) {
        container.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Failed to load admin users</div>';
        console.error('Permissions error:', error);
    }
}

async function managePermissions(adminId) {
    try {
        const data = await apiCall(`/admin/users/${adminId}`);
        const admin = data.data;
        
        // Get current permissions (from metadata or default)
        const currentPermissions = admin.permissions || {
            manage_users: false,
            approve_kyc: false,
            manage_groups: false,
            approve_withdrawals: false,
            view_analytics: false,
            manage_settings: false
        };
        
        document.getElementById('permissionModalContent').innerHTML = `
            <div class="modal-body">
                <h4><i class="fas fa-user"></i> ${admin.name}</h4>
                <p><strong>Email:</strong> ${admin.email}</p>
                <p><strong>Role:</strong> <span class="badge info">Admin</span></p>
                
                <hr style="margin: 20px 0;">
                
                <h4><i class="fas fa-key"></i> Permissions</h4>
                <p class="info-text">
                    <i class="fas fa-info-circle"></i> 
                    Select the permissions you want to grant to this admin user.
                </p>
                
                <form id="permissionsForm">
                    <input type="hidden" id="permissionAdminId" value="${adminId}">
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" id="perm_manage_users" ${currentPermissions.manage_users ? 'checked' : ''}>
                            <strong><i class="fas fa-users"></i> Manage Users</strong>
                            <small>View, suspend, activate, and edit user accounts</small>
                        </label>
                    </div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" id="perm_approve_kyc" ${currentPermissions.approve_kyc ? 'checked' : ''}>
                            <strong><i class="fas fa-id-card"></i> Approve KYC</strong>
                            <small>Review and approve/reject KYC submissions</small>
                        </label>
                    </div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" id="perm_manage_groups" ${currentPermissions.manage_groups ? 'checked' : ''}>
                            <strong><i class="fas fa-layer-group"></i> Manage Groups</strong>
                            <small>View and manage contribution groups</small>
                        </label>
                    </div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" id="perm_approve_withdrawals" ${currentPermissions.approve_withdrawals ? 'checked' : ''}>
                            <strong><i class="fas fa-money-bill-wave"></i> Approve Withdrawals</strong>
                            <small>Review and approve/reject withdrawal requests</small>
                        </label>
                    </div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" id="perm_view_analytics" ${currentPermissions.view_analytics ? 'checked' : ''}>
                            <strong><i class="fas fa-chart-line"></i> View Analytics</strong>
                            <small>Access analytics and reports</small>
                        </label>
                    </div>
                    
                    <div class="permission-item">
                        <label>
                            <input type="checkbox" id="perm_manage_settings" ${currentPermissions.manage_settings ? 'checked' : ''}>
                            <strong><i class="fas fa-cog"></i> Manage Settings</strong>
                            <small>Modify system settings and configurations</small>
                        </label>
                    </div>
                    
                    <div class="action-buttons" style="margin-top: 30px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Permissions
                        </button>
                        <button type="button" class="btn btn-danger" onclick="closeModal('permissionModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        openModal('permissionModal');
        
        // Form handler
        document.getElementById('permissionsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await savePermissions();
        });
        
    } catch (error) {
        showNotification('Failed to load admin details', 'error');
        console.error(error);
    }
}

async function savePermissions() {
    const adminId = document.getElementById('permissionAdminId').value;
    
    const permissions = {
        manage_users: document.getElementById('perm_manage_users').checked,
        approve_kyc: document.getElementById('perm_approve_kyc').checked,
        manage_groups: document.getElementById('perm_manage_groups').checked,
        approve_withdrawals: document.getElementById('perm_approve_withdrawals').checked,
        view_analytics: document.getElementById('perm_view_analytics').checked,
        manage_settings: document.getElementById('perm_manage_settings').checked
    };
    
    try {
        // Note: This endpoint needs to be implemented in the backend
        await apiCall(`/admin/users/${adminId}/permissions`, {
            method: 'PUT',
            body: JSON.stringify({ permissions })
        });
        
        showNotification('Permissions updated successfully', 'success');
        closeModal('permissionModal');
        loadPermissions();
    } catch (error) {
        showNotification('Failed to update permissions: ' + (error.message || 'Unknown error'), 'error');
        console.error(error);
    }
}

async function viewAdminDetails(adminId) {
    try {
        const data = await apiCall(`/admin/users/${adminId}`);
        const admin = data.data;
        
        document.getElementById('userModalContent').innerHTML = `
            <div class="modal-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>User ID</h3>
                        <div class="value">${admin.id}</div>
                    </div>
                    <div class="stat-card">
                        <h3>Role</h3>
                        <div class="value"><span class="badge info">Admin</span></div>
                    </div>
                    <div class="stat-card">
                        <h3>Status</h3>
                        <div class="value"><span class="badge ${admin.status === 'active' ? 'success' : 'danger'}">${admin.status}</span></div>
                    </div>
                    <div class="stat-card">
                        <h3>KYC Status</h3>
                        <div class="value"><span class="badge ${admin.kyc_status === 'verified' ? 'success' : 'warning'}">${admin.kyc_status}</span></div>
                    </div>
                </div>
                
                <h4>Personal Information</h4>
                <table>
                    <tr><td><strong>Name</strong></td><td>${admin.name}</td></tr>
                    <tr><td><strong>Email</strong></td><td>${admin.email}</td></tr>
                    <tr><td><strong>Phone</strong></td><td>${admin.phone}</td></tr>
                    <tr><td><strong>Role</strong></td><td>${admin.role}</td></tr>
                    <tr><td><strong>Joined</strong></td><td>${new Date(admin.created_at).toLocaleDateString()}</td></tr>
                </table>
            </div>
        `;
        
        openModal('userModal');
    } catch (error) {
        showNotification('Failed to load admin details', 'error');
        console.error(error);
    }
}

// Settings
async function loadSettings() {
    const container = document.getElementById('settingsContainer');
    
    try {
        const data = await apiCall('/admin/settings');
        const settings = data.data;
        
        container.innerHTML = `
            <div class="section">
                <h3><i class="fas fa-cog"></i> Application Settings</h3>
                <form id="settingsForm">
                    <div class="form-group">
                        <label for="appName"><i class="fas fa-tag"></i> Application Name</label>
                        <input type="text" id="appName" value="${settings.app_name || ''}" required>
                        <small>The name of your application</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="appLocale"><i class="fas fa-language"></i> Application Locale</label>
                        <select id="appLocale" required>
                            <option value="en" ${settings.app_locale === 'en' ? 'selected' : ''}>English (en)</option>
                            <option value="fr" ${settings.app_locale === 'fr' ? 'selected' : ''}>French (fr)</option>
                            <option value="es" ${settings.app_locale === 'es' ? 'selected' : ''}>Spanish (es)</option>
                            <option value="pt" ${settings.app_locale === 'pt' ? 'selected' : ''}>Portuguese (pt)</option>
                        </select>
                        <small>Default language for the application</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="appTimezone"><i class="fas fa-clock"></i> Application Timezone</label>
                        <select id="appTimezone" required>
                            <option value="Africa/Lagos" ${settings.app_timezone === 'Africa/Lagos' ? 'selected' : ''}>Africa/Lagos (WAT)</option>
                            <option value="UTC" ${settings.app_timezone === 'UTC' ? 'selected' : ''}>UTC</option>
                            <option value="America/New_York" ${settings.app_timezone === 'America/New_York' ? 'selected' : ''}>America/New_York (EST)</option>
                            <option value="Europe/London" ${settings.app_timezone === 'Europe/London' ? 'selected' : ''}>Europe/London (GMT)</option>
                            <option value="Asia/Dubai" ${settings.app_timezone === 'Asia/Dubai' ? 'selected' : ''}>Asia/Dubai (GST)</option>
                        </select>
                        <small>Timezone for dates and times</small>
                    </div>
                    
                    <hr style="margin: 30px 0; border: none; border-top: 2px solid var(--light);">
                    
                    <h3><i class="fas fa-credit-card"></i> Paystack Configuration</h3>
                    <p class="info-text"><i class="fas fa-info-circle"></i> Configure your Paystack API keys for payment processing</p>
                    
                    <div class="form-group">
                        <label for="paystackPublicKey"><i class="fas fa-key"></i> Paystack Public Key</label>
                        <input type="text" id="paystackPublicKey" value="${settings.paystack_public_key || ''}" placeholder="pk_test_xxxxxxxxxxxxxxxxxxxx" required>
                        <small>Your Paystack public key (starts with pk_test_ or pk_live_)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="paystackSecretKey"><i class="fas fa-lock"></i> Paystack Secret Key</label>
                        <input type="password" id="paystackSecretKey" value="${settings.paystack_secret_key || ''}" placeholder="sk_test_xxxxxxxxxxxxxxxxxxxx" required>
                        <small>Your Paystack secret key (starts with sk_test_ or sk_live_). Masked for security.</small>
                    </div>
                    
                    <hr style="margin: 30px 0; border: none; border-top: 2px solid var(--light);">
                    
                    <h3><i class="fas fa-envelope"></i> Email Configuration</h3>
                    <p class="info-text"><i class="fas fa-info-circle"></i> Configure email settings for system notifications</p>
                    
                    <div class="form-group">
                        <label for="mailFromAddress"><i class="fas fa-at"></i> From Email Address</label>
                        <input type="email" id="mailFromAddress" value="${settings.mail_from_address || ''}" placeholder="noreply@ajo.test" required>
                        <small>Email address used as sender for system emails</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="mailFromName"><i class="fas fa-signature"></i> From Name</label>
                        <input type="text" id="mailFromName" value="${settings.mail_from_name || ''}" placeholder="Ajo Platform" required>
                        <small>Name displayed as sender for system emails</small>
                    </div>
                    
                    <div class="action-buttons" style="margin-top: 30px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                        <button type="button" class="btn btn-danger" onclick="loadSettings()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        // Settings form handler
        document.getElementById('settingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSettings();
        });
        
    } catch (error) {
        container.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Failed to load settings</div>';
        console.error('Settings error:', error);
    }
}

async function saveSettings() {
    const settingsData = {
        app_name: document.getElementById('appName').value,
        app_locale: document.getElementById('appLocale').value,
        app_timezone: document.getElementById('appTimezone').value,
        paystack_public_key: document.getElementById('paystackPublicKey').value,
        paystack_secret_key: document.getElementById('paystackSecretKey').value,
        mail_from_address: document.getElementById('mailFromAddress').value,
        mail_from_name: document.getElementById('mailFromName').value,
    };
    
    try {
        await apiCall('/admin/settings', {
            method: 'PUT',
            body: JSON.stringify(settingsData)
        });
        
        showNotification('Settings saved successfully. Please restart the backend for changes to take effect.', 'success');
        loadSettings();
    } catch (error) {
        showNotification('Failed to save settings: ' + (error.message || 'Unknown error'), 'error');
        console.error(error);
    }
}

// Profile Management
async function loadProfile() {
    const container = document.getElementById('profileContainer');
    
    try {
        const data = await apiCall('/user/profile');
        const profile = data.data;
        
        container.innerHTML = `
            <div class="section">
                <div class="stats-grid">
                    <div class="stat-card info">
                        <h3>Admin ID</h3>
                        <div class="value">${profile.id}</div>
                    </div>
                    <div class="stat-card success">
                        <h3>Role</h3>
                        <div class="value"><span class="badge info">${profile.role}</span></div>
                    </div>
                    <div class="stat-card primary">
                        <h3>Status</h3>
                        <div class="value"><span class="badge success">${profile.status}</span></div>
                    </div>
                    <div class="stat-card warning">
                        <h3>Member Since</h3>
                        <div class="value" style="font-size: 18px;">${new Date(profile.created_at).toLocaleDateString()}</div>
                    </div>
                </div>
                
                <h3><i class="fas fa-user"></i> Personal Information</h3>
                <form id="profileForm" style="max-width: 600px;">
                    <div class="form-group">
                        <label for="profileName"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" id="profileName" value="${profile.name}" required>
                    </div>
                    <div class="form-group">
                        <label for="profileEmail"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="profileEmail" value="${profile.email}" required>
                    </div>
                    <div class="form-group">
                        <label for="profilePhone"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" id="profilePhone" value="${profile.phone || ''}" required>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
                
                <hr style="margin: 30px 0; border: none; border-top: 2px solid var(--light);">
                
                <h3><i class="fas fa-lock"></i> Change Password</h3>
                <form id="passwordForm" style="max-width: 600px;">
                    <div class="form-group">
                        <label for="currentPassword"><i class="fas fa-key"></i> Current Password</label>
                        <input type="password" id="currentPassword" required>
                    </div>
                    <div class="form-group">
                        <label for="newPassword"><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" id="newPassword" required minlength="8">
                        <small>Minimum 8 characters</small>
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword"><i class="fas fa-lock"></i> Confirm New Password</label>
                        <input type="password" id="confirmPassword" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        `;
        
        // Profile form handler
        document.getElementById('profileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await updateProfile();
        });
        
        // Password form handler
        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await changePassword();
        });
        
    } catch (error) {
        container.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Failed to load profile</div>';
        console.error('Profile error:', error);
    }
}

async function updateProfile() {
    const name = document.getElementById('profileName').value;
    const email = document.getElementById('profileEmail').value;
    const phone = document.getElementById('profilePhone').value;
    
    try {
        await apiCall('/user/profile', {
            method: 'PUT',
            body: JSON.stringify({ name, email, phone })
        });
        
        // Update stored admin user
        adminUser.name = name;
        adminUser.email = email;
        adminUser.phone = phone;
        localStorage.setItem('admin_user', JSON.stringify(adminUser));
        document.getElementById('adminName').textContent = name;
        
        showNotification('Profile updated successfully', 'success');
    } catch (error) {
        showNotification('Failed to update profile', 'error');
        console.error(error);
    }
}

async function changePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (newPassword !== confirmPassword) {
        showNotification('Passwords do not match', 'error');
        return;
    }
    
    try {
        await apiCall('/user/change-password', {
            method: 'PUT',
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword,
                new_password_confirmation: confirmPassword
            })
        });
        
        showNotification('Password changed successfully', 'success');
        document.getElementById('passwordForm').reset();
    } catch (error) {
        showNotification('Failed to change password', 'error');
        console.error(error);
    }
}

// User Creation
function openCreateUserModal() {
    document.getElementById('createUserForm').reset();
    openModal('createUserModal');
}

document.getElementById('createUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    await createUser();
});

async function createUser() {
    const userData = {
        name: document.getElementById('newUserName').value,
        email: document.getElementById('newUserEmail').value,
        phone: document.getElementById('newUserPhone').value,
        password: document.getElementById('newUserPassword').value,
        role: document.getElementById('newUserRole').value,
        status: document.getElementById('newUserStatus').value,
        kyc_status: document.getElementById('newUserKycStatus').value
    };
    
    try {
        await apiCall('/auth/register', {
            method: 'POST',
            body: JSON.stringify(userData)
        });
        
        showNotification('User created successfully', 'success');
        closeModal('createUserModal');
        loadUsers();
    } catch (error) {
        showNotification('Failed to create user: ' + (error.message || 'Unknown error'), 'error');
        console.error(error);
    }
}

// Edit User
async function editUser(userId) {
    try {
        const data = await apiCall(`/admin/users/${userId}`);
        const user = data.data;
        
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUserName').value = user.name;
        document.getElementById('editUserEmail').value = user.email;
        document.getElementById('editUserPhone').value = user.phone || '';
        document.getElementById('editUserRole').value = user.role;
        document.getElementById('editUserStatus').value = user.status;
        document.getElementById('editUserKycStatus').value = user.kyc_status;
        
        openModal('editUserModal');
    } catch (error) {
        showNotification('Failed to load user details', 'error');
        console.error(error);
    }
}

document.getElementById('editUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    await updateUser();
});

async function updateUser() {
    const userId = document.getElementById('editUserId').value;
    const userData = {
        name: document.getElementById('editUserName').value,
        email: document.getElementById('editUserEmail').value,
        phone: document.getElementById('editUserPhone').value,
        role: document.getElementById('editUserRole').value,
        status: document.getElementById('editUserStatus').value,
        kyc_status: document.getElementById('editUserKycStatus').value
    };
    
    try {
        await apiCall(`/admin/users/${userId}`, {
            method: 'PUT',
            body: JSON.stringify(userData)
        });
        
        showNotification('User updated successfully', 'success');
        closeModal('editUserModal');
        loadUsers();
    } catch (error) {
        showNotification('Failed to update user', 'error');
        console.error(error);
    }
}

// Utility Functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function refreshData() {
    showNotification('Refreshing data...', 'info');
    
    switch(currentSection) {
        case 'dashboard':
            loadDashboardData();
            break;
        case 'users':
            loadUsers();
            break;
        case 'kyc':
            loadKYC();
            break;
        case 'groups':
            loadGroups();
            break;
        case 'withdrawals':
            loadWithdrawals();
            break;
    }
}

function toggleNotifications() {
    alert('Notifications feature coming soon');
}

function searchUsers() {
    // Implement search functionality
}

function searchGroups() {
    // Implement search functionality
}

function exportUsers() {
    window.open(`${API_BASE_URL}/admin/analytics/users?export=csv`, '_blank');
}

function exportGroups() {
    window.open(`${API_BASE_URL}/admin/analytics/groups?export=csv`, '_blank');
}

function exportDashboard() {
    window.open(`${API_BASE_URL}/admin/analytics/revenue?export=csv`, '_blank');
}
