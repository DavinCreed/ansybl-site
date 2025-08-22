/**
 * Configuration Manager
 * Handles site configuration management in the admin interface
 */

class ConfigManager {
    constructor() {
        this.apiBaseUrl = '../api/config.php';
        this.currentConfig = {};
        this.eventListeners = [];
        this.hasUnsavedChanges = false;
        
        this.init();
    }
    
    async init() {
        await this.loadSiteConfig();
        this.bindEvents();
        this.populateForms();
        this.startAutoSave();
    }
    
    /**
     * Load site configuration from API
     */
    async loadSiteConfig() {
        try {
            const response = await fetch(`${this.apiBaseUrl}/site`);
            const data = await response.json();
            
            if (data.success) {
                this.currentConfig = data.data;
            } else {
                console.warn('Failed to load site config, using defaults');
                this.currentConfig = this.getDefaultConfig();
            }
        } catch (error) {
            console.error('Failed to load site config:', error);
            this.currentConfig = this.getDefaultConfig();
        }
    }
    
    /**
     * Get default configuration
     */
    getDefaultConfig() {
        return {
            version: '1.0',
            site: {
                title: 'Ansybl Site',
                description: 'A dynamic content site powered by Ansybl feeds',
                language: 'en',
                timezone: 'UTC'
            },
            display: {
                theme: 'default',
                items_per_page: 10,
                show_timestamps: true,
                date_format: 'Y-m-d H:i:s',
                excerpt_length: 150
            },
            features: {
                search_enabled: true,
                comments_enabled: false,
                social_sharing: true
            }
        };
    }
    
    /**
     * Bind event listeners
     */
    bindEvents() {
        // Form change detection
        const forms = document.querySelectorAll('#site-config-form, #display-config-form');
        forms.forEach(form => {
            form.addEventListener('input', (e) => this.handleFormChange(e));
            form.addEventListener('change', (e) => this.handleFormChange(e));
        });
        
        // Save button
        const saveButton = document.getElementById('save-all');
        if (saveButton) {
            saveButton.addEventListener('click', () => this.saveConfig());
        }
        
        // Preview changes
        document.addEventListener('click', (e) => {
            if (e.target.matches('.preview-changes-btn')) {
                this.previewChanges();
            }
        });
        
        // Reset to defaults
        document.addEventListener('click', (e) => {
            if (e.target.matches('.reset-defaults-btn')) {
                this.resetToDefaults();
            }
        });
        
        // Prevent leaving with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    }
    
    /**
     * Populate forms with current configuration
     */
    populateForms() {
        // Site Information
        this.setFormValue('site-title', this.currentConfig.site?.title);
        this.setFormValue('site-description', this.currentConfig.site?.description);
        this.setFormValue('site-language', this.currentConfig.site?.language);
        this.setFormValue('site-timezone', this.currentConfig.site?.timezone);
        
        // Display Settings
        this.setFormValue('items-per-page', this.currentConfig.display?.items_per_page);
        this.setFormValue('excerpt-length', this.currentConfig.display?.excerpt_length);
        this.setFormValue('date-format', this.currentConfig.display?.date_format);
        this.setFormValue('show-timestamps', this.currentConfig.display?.show_timestamps);
        this.setFormValue('search-enabled', this.currentConfig.features?.search_enabled);
    }
    
    /**
     * Set form field value
     */
    setFormValue(fieldId, value) {
        const field = document.getElementById(fieldId);
        if (!field || value === undefined) return;
        
        if (field.type === 'checkbox') {
            field.checked = Boolean(value);
        } else {
            field.value = value;
        }
    }
    
    /**
     * Get form field value
     */
    getFormValue(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return null;
        
        if (field.type === 'checkbox') {
            return field.checked;
        } else if (field.type === 'number') {
            return parseInt(field.value) || 0;
        } else {
            return field.value;
        }
    }
    
    /**
     * Handle form changes
     */
    handleFormChange(event) {
        this.hasUnsavedChanges = true;
        this.updateSaveButtonState();
        
        // Update current config object
        this.updateConfigFromForms();
        
        // Live preview for certain changes
        if (event.target.id === 'site-title') {
            this.previewTitleChange(event.target.value);
        }
        
        this.notifyListeners('configChanged', {
            field: event.target.id,
            value: this.getFormValue(event.target.id),
            config: this.currentConfig
        });
    }
    
    /**
     * Update configuration object from form values
     */
    updateConfigFromForms() {
        // Site Information
        this.currentConfig.site = {
            ...this.currentConfig.site,
            title: this.getFormValue('site-title') || '',
            description: this.getFormValue('site-description') || '',
            language: this.getFormValue('site-language') || 'en',
            timezone: this.getFormValue('site-timezone') || 'UTC'
        };
        
        // Display Settings
        this.currentConfig.display = {
            ...this.currentConfig.display,
            items_per_page: this.getFormValue('items-per-page') || 10,
            excerpt_length: this.getFormValue('excerpt-length') || 150,
            date_format: this.getFormValue('date-format') || 'Y-m-d H:i:s',
            show_timestamps: this.getFormValue('show-timestamps'),
        };
        
        // Features
        this.currentConfig.features = {
            ...this.currentConfig.features,
            search_enabled: this.getFormValue('search-enabled')
        };
    }
    
    /**
     * Save configuration to server
     */
    async saveConfig() {
        if (!this.hasUnsavedChanges) {
            this.showMessage('info', 'No changes to save');
            return;
        }
        
        try {
            this.updateConfigFromForms();
            
            const response = await fetch(`${this.apiBaseUrl}/site`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(this.currentConfig)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.hasUnsavedChanges = false;
                this.updateSaveButtonState();
                this.showMessage('success', 'Configuration saved successfully!');
                this.notifyListeners('configSaved', this.currentConfig);
            } else {
                throw new Error(data.error?.message || 'Failed to save configuration');
            }
            
        } catch (error) {
            console.error('Failed to save config:', error);
            this.showMessage('error', 'Failed to save configuration: ' + error.message);
        }
    }
    
    /**
     * Preview title change in real-time
     */
    previewTitleChange(newTitle) {
        // Update page title if visible
        const titleElements = document.querySelectorAll('title, .site-title');
        titleElements.forEach(el => {
            if (el.tagName === 'TITLE') {
                el.textContent = newTitle ? `${newTitle} - Admin` : 'Ansybl Site - Admin';
            } else {
                el.textContent = newTitle || 'Ansybl Site';
            }
        });
    }
    
    /**
     * Preview all changes
     */
    previewChanges() {
        const previewWindow = window.open('../', '_blank');
        if (previewWindow) {
            // Apply temporary config changes
            previewWindow.addEventListener('load', () => {
                // Override the config loader with our preview config
                if (previewWindow.AnsyblConfig && previewWindow.AnsyblConfig.loader) {
                    previewWindow.AnsyblConfig.loader.applyConfig(this.currentConfig);
                    this.showMessage('info', 'Preview opened with current configuration');
                }
            });
        } else {
            this.showMessage('error', 'Failed to open preview window. Please check popup blocker settings.');
        }
    }
    
    /**
     * Reset configuration to defaults
     */
    async resetToDefaults() {
        if (!confirm('Are you sure you want to reset all settings to defaults? This action cannot be undone.')) {
            return;
        }
        
        try {
            this.currentConfig = this.getDefaultConfig();
            this.populateForms();
            this.hasUnsavedChanges = true;
            this.updateSaveButtonState();
            this.showMessage('info', 'Configuration reset to defaults. Click Save to apply changes.');
            
        } catch (error) {
            console.error('Failed to reset config:', error);
            this.showMessage('error', 'Failed to reset configuration');
        }
    }
    
    /**
     * Update save button state
     */
    updateSaveButtonState() {
        const saveButton = document.getElementById('save-all');
        if (saveButton) {
            if (this.hasUnsavedChanges) {
                saveButton.textContent = 'Save Changes';
                saveButton.style.background = '#e67e22';
                saveButton.disabled = false;
            } else {
                saveButton.textContent = 'All Saved';
                saveButton.style.background = '#27ae60';
                saveButton.disabled = true;
            }
        }
    }
    
    /**
     * Auto-save configuration (every 30 seconds if changes exist)
     */
    startAutoSave() {
        setInterval(() => {
            if (this.hasUnsavedChanges) {
                console.log('Auto-saving configuration...');
                this.saveConfig();
            }
        }, 30000); // 30 seconds
    }
    
    /**
     * Export configuration
     */
    exportConfig() {
        const configBlob = new Blob([JSON.stringify(this.currentConfig, null, 2)], {
            type: 'application/json'
        });
        
        const url = URL.createObjectURL(configBlob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `ansybl-site-config-${new Date().toISOString().split('T')[0]}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        this.showMessage('success', 'Configuration exported successfully!');
    }
    
    /**
     * Import configuration
     */
    importConfig(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const importedConfig = JSON.parse(e.target.result);
                
                if (this.validateConfig(importedConfig)) {
                    this.currentConfig = importedConfig;
                    this.populateForms();
                    this.hasUnsavedChanges = true;
                    this.updateSaveButtonState();
                    this.showMessage('success', 'Configuration imported successfully! Click Save to apply.');
                } else {
                    throw new Error('Invalid configuration format');
                }
            } catch (error) {
                console.error('Failed to import config:', error);
                this.showMessage('error', 'Failed to import configuration: ' + error.message);
            }
        };
        
        reader.readAsText(file);
    }
    
    /**
     * Basic configuration validation
     */
    validateConfig(config) {
        return config && 
               typeof config === 'object' && 
               config.site && 
               config.display && 
               config.features;
    }
    
    /**
     * Show status message
     */
    showMessage(type, message) {
        const messageHtml = `
            <div class="status-message ${type}" style="margin-bottom: 1rem;">
                <span>${message}</span>
                <button class="status-message-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        `;
        
        const container = document.getElementById('status-messages') || document.body;
        container.insertAdjacentHTML('afterbegin', messageHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            const messageEl = container.querySelector(`.status-message.${type}`);
            if (messageEl) {
                messageEl.remove();
            }
        }, 5000);
    }
    
    /**
     * Add event listener
     */
    addEventListener(eventType, callback) {
        this.eventListeners.push({ eventType, callback });
    }
    
    /**
     * Notify event listeners
     */
    notifyListeners(eventType, data) {
        this.eventListeners
            .filter(listener => listener.eventType === eventType)
            .forEach(listener => {
                try {
                    listener.callback(data);
                } catch (error) {
                    console.error('Error in config event listener:', error);
                }
            });
    }
    
    /**
     * Get current configuration
     */
    getCurrentConfig() {
        return { ...this.currentConfig };
    }
    
    /**
     * Check if there are unsaved changes
     */
    hasChanges() {
        return this.hasUnsavedChanges;
    }
}

// Create global instance
window.configManager = new ConfigManager();