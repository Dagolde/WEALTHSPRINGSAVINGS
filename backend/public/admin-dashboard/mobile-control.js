// Mobile App Control Functions

// Load Mobile App Control Section
async function loadMobile() {
    const container = document.getElementById('mobileContainer');
    
    try {
        // Load app settings
        const settingsData = await apiCall('/admin/mobile/settings');
        const settings = settingsData.data;
        
        // Load app usage stats
        const usageData = await apiCall('/admin/mobile/usage');
        const usage = usageData.data;
        
        // Load active sessions
        const sessionsData = await apiCall('/admin/mobile/sessions?per_page=10');
        const sessions = sessionsData.data.data || [];
        
        container.innerHTML = `
            <!-- App Usage Stats -->
            <div class="section">
                <h3><i class="fas fa-chart-bar"></i> App Usage Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-card success">
                        <h3>Active Now</h3>
                        <div class="value">${usage.active_sessions}</div>
                        <small>Users online</small>
                    </div>
                    <div class="stat-card primary">
                        <h3>Daily Active</h3>
                        <div class="value">${usage.daily_active_users}</div>
                        <small>Last 24 hours</small>
                    </div>
                    <div class="stat-card info">
                        <h3>Weekly Active</h3>
                        <div class="value">${usage.weekly_active_users}</div>
                        <small>Last 7 days</small>
                    </div>
                    <div class="stat-card warning">
                        <h3>Monthly Active</h3>
                        <div class="value">${usage.monthly_active_users}</div>
                        <small>Last 30 days</small>
                    </div>
                </div>
                
                <h4>Platform Distribution</h4>
                <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
                    <div class="stat-card">
                        <h3><i class="fab fa-android"></i> Android</h3>
                        <div class="value">${usage.platform_distribution.android}</div>
                    </div>
                    <div class="stat-card">
                        <h3><i class="fab fa-apple"></i> iOS</h3>
                        <div class="value">${usage.platform_distribution.ios}</div>
                    </div>
                    <div class="stat-card">
                        <h3><i class="fas fa-globe"></i> Other</h3>
                        <div class="value">${usage.platform_distribution.other}</div>
                    </div>
                </div>
            </div>
            
            <!-- App Settings -->
            <div class="section">
                <div class="section-header">
                    <h3><i class="fas fa-cog"></i> App Settings</h3>
                    <button class="btn btn-primary" onclick="openMobileSettingsModal()">
                        <i class="fas fa-edit"></i> Edit Settings
                    </button>
                </div>
                <table>
                    <tr>
                        <td><strong>Current Version</strong></td>
                        <td>${settings.app_version}</td>
                        <td><strong>Min Supported Version</strong></td>
                        <td>${settings.min_supported_version}</td>
                    </tr>
                    <tr>
                        <td><strong>Force Update</strong></td>
                        <td><span class="badge ${settings.force_update ? 'danger' : 'success'}">${settings.force_update ? 'Enabled' : 'Disabled'}</span></td>
                        <td><strong>Maintenance Mode</strong></td>
                        <td><span class="badge ${settings.maintenance_mode ? 'warning' : 'success'}">${settings.maintenance_mode ? 'Active' : 'Inactive'}</span></td>
                    </tr>
                    ${settings.maintenance_mode ? `
                    <tr>
                        <td colspan="4"><strong>Maintenance Message:</strong> ${settings.maintenance_message}</td>
                    </tr>
                    ` : ''}
                </table>
                
                <h4>Feature Flags</h4>
                <table>
                    <tr>
                        <td><strong>Wallet</strong></td>
                        <td><span class="badge ${settings.features.wallet_enabled ? 'success' : 'danger'}">${settings.features.wallet_enabled ? 'Enabled' : 'Disabled'}</span></td>
                        <td><strong>Groups</strong></td>
                        <td><span class="badge ${settings.features.groups_enabled ? 'success' : 'danger'}">${settings.features.groups_enabled ? 'Enabled' : 'Disabled'}</span></td>
                    </tr>
                    <tr>
                        <td><strong>Contributions</strong></td>
                        <td><span class="badge ${settings.features.contributions_enabled ? 'success' : 'danger'}">${settings.features.contributions_enabled ? 'Enabled' : 'Disabled'}</span></td>
                        <td><strong>Withdrawals</strong></td>
                        <td><span class="badge ${settings.features.withdrawals_enabled ? 'success' : 'danger'}">${settings.features.withdrawals_enabled ? 'Enabled' : 'Disabled'}</span></td>
                    </tr>
                    <tr>
                        <td><strong>KYC Required</strong></td>
                        <td><span class="badge ${settings.features.kyc_required ? 'warning' : 'success'}">${settings.features.kyc_required ? 'Yes' : 'No'}</span></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
            </div>
            
            <!-- Push Notifications -->
            <div class="section">
                <div class="section-header">
                    <h3><i class="fas fa-bell"></i> Push Notifications</h3>
                    <button class="btn btn-success" onclick="openPushNotificationModal()">
                        <i class="fas fa-paper-plane"></i> Send Notification
                    </button>
                </div>
                <p class="info-text">
                    <i class="fas fa-info-circle"></i> 
                    Send push notifications to all users or specific users. Notifications will be delivered to their mobile devices.
                </p>
            </div>
            
            <!-- Active Sessions -->
            <div class="section">
                <div class="section-header">
                    <h3><i class="fas fa-users"></i> Active Sessions (Last 10)</h3>
                    <button class="btn btn-primary" onclick="loadMobile()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                ${sessions.length > 0 ? `
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Device</th>
                            <th>Last Active</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${sessions.map(session => `
                            <tr>
                                <td><strong>${session.user_name}</strong><br><small>${session.user_email}</small></td>
                                <td>${session.device_name || 'Unknown Device'}</td>
                                <td>${session.last_used_at ? new Date(session.last_used_at).toLocaleString() : 'Never'}</td>
                                <td><span class="badge ${session.is_active ? 'success' : 'warning'}">${session.is_active ? 'Active' : 'Idle'}</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-danger" onclick="revokeSession(${session.id})">
                                            <i class="fas fa-ban"></i> Revoke
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="revokeAllUserSessions(${session.user_id})">
                                            <i class="fas fa-sign-out-alt"></i> Logout User
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                ` : '<div class="empty-state"><i class="fas fa-users-slash"></i><p>No active sessions</p></div>'}
            </div>
        `;
        
    } catch (error) {
        container.innerHTML = '<div class="error"><i class="fas fa-exclamation-circle"></i> Failed to load mobile app control</div>';
        console.error('Mobile control error:', error);
    }
}

// Open Mobile Settings Modal
async function openMobileSettingsModal() {
    try {
        const data = await apiCall('/admin/mobile/settings');
        const settings = data.data;
        
        document.getElementById('mobileSettingsContent').innerHTML = `
            <div class="modal-body">
                <form id="mobileSettingsForm">
                    <h4>Version Control</h4>
                    <div class="form-group">
                        <label for="appVersion"><i class="fas fa-code-branch"></i> Current App Version</label>
                        <input type="text" id="appVersion" value="${settings.app_version}" required>
                        <small>Version displayed to users (e.g., 1.0.0)</small>
                    </div>
                    <div class="form-group">
                        <label for="minVersion"><i class="fas fa-shield-alt"></i> Minimum Supported Version</label>
                        <input type="text" id="minVersion" value="${settings.min_supported_version}" required>
                        <small>Users below this version will be prompted to update</small>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="forceUpdate" ${settings.force_update ? 'checked' : ''}>
                            Force Update (Users must update to continue)
                        </label>
                    </div>
                    
                    <hr style="margin: 20px 0;">
                    
                    <h4>Maintenance Mode</h4>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="maintenanceMode" ${settings.maintenance_mode ? 'checked' : ''}>
                            Enable Maintenance Mode
                        </label>
                        <small>When enabled, users will see a maintenance message</small>
                    </div>
                    <div class="form-group">
                        <label for="maintenanceMessage"><i class="fas fa-comment"></i> Maintenance Message</label>
                        <textarea id="maintenanceMessage" rows="3">${settings.maintenance_message}</textarea>
                    </div>
                    
                    <div class="action-buttons" style="margin-top: 30px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                        <button type="button" class="btn btn-danger" onclick="closeModal('mobileSettingsModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        openModal('mobileSettingsModal');
        
        // Form handler
        document.getElementById('mobileSettingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveMobileSettings();
        });
        
    } catch (error) {
        showNotification('Failed to load mobile settings', 'error');
        console.error(error);
    }
}

// Save Mobile Settings
async function saveMobileSettings() {
    const settingsData = {
        app_version: document.getElementById('appVersion').value,
        min_supported_version: document.getElementById('minVersion').value,
        force_update: document.getElementById('forceUpdate').checked,
        maintenance_mode: document.getElementById('maintenanceMode').checked,
        maintenance_message: document.getElementById('maintenanceMessage').value,
    };
    
    try {
        await apiCall('/admin/mobile/settings', {
            method: 'PUT',
            body: JSON.stringify(settingsData)
        });
        
        showNotification('Mobile app settings updated successfully', 'success');
        closeModal('mobileSettingsModal');
        loadMobile();
    } catch (error) {
        showNotification('Failed to update mobile settings: ' + (error.message || 'Unknown error'), 'error');
        console.error(error);
    }
}

// Open Push Notification Modal
function openPushNotificationModal() {
    document.getElementById('pushNotificationForm').reset();
    openModal('pushNotificationModal');
}

// Send Push Notification
document.getElementById('pushNotificationForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    await sendPushNotification();
});

async function sendPushNotification() {
    const notificationData = {
        title: document.getElementById('pushTitle').value,
        message: document.getElementById('pushMessage').value,
        type: document.getElementById('pushType').value,
    };
    
    // If not sending to all, we would need to select specific users
    // For now, we'll send to all if checkbox is checked
    if (!document.getElementById('pushToAll').checked) {
        showNotification('Please select specific users (feature coming soon)', 'warning');
        return;
    }
    
    try {
        const result = await apiCall('/admin/mobile/notifications/push', {
            method: 'POST',
            body: JSON.stringify(notificationData)
        });
        
        showNotification(`Push notification sent to ${result.data.sent} users`, 'success');
        closeModal('pushNotificationModal');
    } catch (error) {
        showNotification('Failed to send push notification: ' + (error.message || 'Unknown error'), 'error');
        console.error(error);
    }
}

// Revoke Session
async function revokeSession(sessionId) {
    if (!confirm('Are you sure you want to revoke this session? The user will be logged out.')) return;
    
    try {
        await apiCall(`/admin/mobile/sessions/${sessionId}`, {
            method: 'DELETE'
        });
        
        showNotification('Session revoked successfully', 'success');
        loadMobile();
    } catch (error) {
        showNotification('Failed to revoke session', 'error');
        console.error(error);
    }
}

// Revoke All User Sessions
async function revokeAllUserSessions(userId) {
    if (!confirm('Are you sure you want to logout this user from all devices?')) return;
    
    try {
        const result = await apiCall(`/admin/mobile/users/${userId}/sessions`, {
            method: 'DELETE'
        });
        
        showNotification(`Revoked ${result.data.revoked_count} sessions successfully`, 'success');
        loadMobile();
    } catch (error) {
        showNotification('Failed to revoke sessions', 'error');
        console.error(error);
    }
}
