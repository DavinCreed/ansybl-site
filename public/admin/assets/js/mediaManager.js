/**
 * Media Manager
 * Handles file uploads and media management for local feeds
 */

class MediaManager {
    constructor() {
        this.apiBaseUrl = '../api/media.php';
        this.currentFeedId = null;
        this.uploadConfig = null;
        this.eventListeners = [];
        
        this.init();
    }
    
    async init() {
        // Set default config first
        this.setDefaultConfig();
        // Then try to load from API (will override defaults if successful)
        await this.loadUploadConfig();
        this.bindEvents();
    }
    
    /**
     * Load upload configuration
     */
    async loadUploadConfig() {
        try {
            const response = await fetch(`${this.apiBaseUrl}/info`);
            const data = await response.json();
            
            if (data.success) {
                this.uploadConfig = data.data;
            } else {
                this.setDefaultConfig();
            }
        } catch (error) {
            console.error('Failed to load upload config:', error);
            this.setDefaultConfig();
        }
    }
    
    /**
     * Set default upload configuration
     */
    setDefaultConfig() {
        this.uploadConfig = {
            maxFileSize: 10 * 1024 * 1024, // 10MB
            maxFileSizeFormatted: '10 MB',
            allowedTypes: {
                images: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                audio: ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4'],
                video: ['video/mp4', 'video/webm', 'video/ogg'],
                documents: ['application/pdf', 'text/plain']
            },
            allAllowedTypes: [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4',
                'video/mp4', 'video/webm', 'video/ogg',
                'application/pdf', 'text/plain'
            ]
        };
    }
    
    /**
     * Bind event listeners
     */
    bindEvents() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('.media-manager-btn')) {
                const feedId = e.target.dataset.feedId;
                this.showMediaManager(feedId);
            } else if (e.target.matches('.delete-media-btn')) {
                const feedId = e.target.dataset.feedId;
                const filename = e.target.dataset.filename;
                this.deleteFile(feedId, filename);
            } else if (e.target.matches('.insert-media-btn')) {
                const url = e.target.dataset.url;
                this.insertMediaIntoForm(url);
            }
        });
        
        // Drag and drop functionality
        document.addEventListener('dragover', (e) => {
            const dropZone = e.target.closest('.media-drop-zone');
            if (dropZone) {
                e.preventDefault();
                dropZone.classList.add('drag-over');
            }
        });
        
        document.addEventListener('dragleave', (e) => {
            const dropZone = e.target.closest('.media-drop-zone');
            if (dropZone && !dropZone.contains(e.relatedTarget)) {
                dropZone.classList.remove('drag-over');
            }
        });
        
        document.addEventListener('drop', (e) => {
            const dropZone = e.target.closest('.media-drop-zone');
            if (dropZone) {
                e.preventDefault();
                dropZone.classList.remove('drag-over');
                
                const feedId = dropZone.dataset.feedId;
                const files = Array.from(e.dataTransfer.files);
                this.uploadFiles(feedId, files);
            }
        });
    }
    
    /**
     * Show media manager modal
     */
    async showMediaManager(feedId) {
        this.currentFeedId = feedId;
        
        try {
            const files = await this.loadFeedMedia(feedId);
            this.showMediaManagerModal(feedId, files);
        } catch (error) {
            console.error('Failed to load media files:', error);
            alert('Failed to load media files: ' + error.message);
        }
    }
    
    /**
     * Show media manager modal
     */
    showMediaManagerModal(feedId, files) {
        const modalHtml = `
            <div id="media-manager-modal" class="modal-overlay large-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Media Manager</h3>
                        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="media-upload-section">
                            <div class="media-drop-zone" data-feed-id="${feedId}">
                                <div class="drop-zone-content">
                                    <div class="drop-zone-icon">📁</div>
                                    <p>Drag and drop files here or click to select</p>
                                    <input type="file" id="media-file-input" multiple accept="${this.getAcceptTypes()}" style="display: none;">
                                    <button type="button" class="button primary" onclick="document.getElementById('media-file-input').click()">
                                        Select Files
                                    </button>
                                </div>
                                <div class="upload-info">
                                    <small>
                                        Max file size: ${this.uploadConfig ? this.uploadConfig.maxFileSizeFormatted : '10MB'}<br>
                                        Allowed types: Images, Audio, Video, Documents
                                    </small>
                                </div>
                            </div>
                            
                            <div id="upload-progress" class="upload-progress" style="display: none;">
                                <div class="progress-bar">
                                    <div class="progress-fill"></div>
                                </div>
                                <span class="progress-text">Uploading...</span>
                            </div>
                        </div>
                        
                        <div class="media-files-section">
                            <h4>Media Files (${files.length})</h4>
                            <div id="media-files-grid" class="media-files-grid">
                                ${this.renderMediaFiles(files, feedId)}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Bind file input change
        const fileInput = document.getElementById('media-file-input');
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.uploadFiles(feedId, Array.from(e.target.files));
            }
        });
    }
    
    /**
     * Render media files grid
     */
    renderMediaFiles(files, feedId) {
        if (files.length === 0) {
            return `
                <div class="empty-state">
                    <p>No media files uploaded yet.</p>
                </div>
            `;
        }
        
        return files.map(file => {
            const isImage = file.type.startsWith('image/');
            const isAudio = file.type.startsWith('audio/');
            const isVideo = file.type.startsWith('video/');
            
            return `
                <div class="media-file-card" data-file-path="${file.path}">
                    <div class="media-preview">
                        ${isImage ? 
                            `<img src="${file.url}" alt="${file.name}" loading="lazy">` :
                            `<div class="media-icon">
                                ${isAudio ? '🎵' : isVideo ? '🎬' : '📄'}
                            </div>`
                        }
                    </div>
                    <div class="media-info">
                        <div class="media-name" title="${file.name}">${this.truncateText(file.name, 20)}</div>
                        <div class="media-size">${this.formatFileSize(file.size)}</div>
                        <div class="media-type">${file.type}</div>
                    </div>
                    <div class="media-actions">
                        <button class="media-action-btn insert-media-btn" 
                                data-url="${file.url}" 
                                title="Insert into form">
                            ➕
                        </button>
                        <button class="media-action-btn copy-url-btn" 
                                onclick="navigator.clipboard.writeText('${file.url}'); this.textContent='✓'"
                                title="Copy URL">
                            📋
                        </button>
                        <button class="media-action-btn delete-media-btn" 
                                data-feed-id="${feedId}" 
                                data-filename="${file.path}"
                                title="Delete">
                            🗑️
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    /**
     * Upload files
     */
    async uploadFiles(feedId, files) {
        const progressContainer = document.getElementById('upload-progress');
        const progressFill = progressContainer.querySelector('.progress-fill');
        const progressText = progressContainer.querySelector('.progress-text');
        
        progressContainer.style.display = 'block';
        
        try {
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                // Validate file
                if (!this.validateFile(file)) {
                    continue;
                }
                
                progressText.textContent = `Uploading ${file.name} (${i + 1}/${files.length})...`;
                progressFill.style.width = `${((i) / files.length) * 100}%`;
                
                await this.uploadFile(feedId, file);
            }
            
            progressFill.style.width = '100%';
            progressText.textContent = 'Upload complete!';
            
            // Refresh media files list
            setTimeout(() => {
                this.refreshMediaManager();
                progressContainer.style.display = 'none';
            }, 1000);
            
        } catch (error) {
            console.error('Upload failed:', error);
            progressText.textContent = 'Upload failed: ' + error.message;
            progressContainer.style.display = 'none';
        }
    }
    
    /**
     * Upload single file
     */
    async uploadFile(feedId, file) {
        const formData = new FormData();
        formData.append('file', file);
        
        const response = await fetch(`${this.apiBaseUrl}/${feedId}/upload`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error.message);
        }
        
        this.notifyListeners('fileUploaded', data.data);
        return data.data;
    }
    
    /**
     * Delete file
     */
    async deleteFile(feedId, filename) {
        if (!confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
            return false;
        }
        
        try {
            const response = await fetch(`${this.apiBaseUrl}/${feedId}/${filename}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error.message);
            }
            
            // Remove from UI
            const fileCard = document.querySelector(`[data-file-path="${filename}"]`);
            if (fileCard) {
                fileCard.remove();
            }
            
            this.notifyListeners('fileDeleted', { feedId, filename });
            this.showMessage('success', 'File deleted successfully!');
            
        } catch (error) {
            console.error('Failed to delete file:', error);
            this.showMessage('error', 'Failed to delete file: ' + error.message);
        }
    }
    
    /**
     * Load media files for feed
     */
    async loadFeedMedia(feedId) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/${feedId}`);
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error.message);
            }
            
            return data.data.files;
            
        } catch (error) {
            console.error('Failed to load media files:', error);
            throw error;
        }
    }
    
    /**
     * Refresh media manager
     */
    async refreshMediaManager() {
        if (!this.currentFeedId) return;
        
        try {
            const files = await this.loadFeedMedia(this.currentFeedId);
            const filesGrid = document.getElementById('media-files-grid');
            if (filesGrid) {
                filesGrid.innerHTML = this.renderMediaFiles(files, this.currentFeedId);
            }
        } catch (error) {
            console.error('Failed to refresh media manager:', error);
        }
    }
    
    /**
     * Insert media URL into form
     */
    insertMediaIntoForm(url) {
        // Find the currently open item editor form
        const itemForm = document.getElementById('item-editor-form');
        if (itemForm) {
            const urlInput = itemForm.querySelector('#item-url');
            if (urlInput) {
                urlInput.value = url;
                
                // Determine media type from URL
                const mediaTypeInput = itemForm.querySelector('#item-media-type');
                if (mediaTypeInput && !mediaTypeInput.value) {
                    const extension = url.split('.').pop().toLowerCase();
                    const mediaType = this.getMediaTypeFromExtension(extension);
                    if (mediaType) {
                        mediaTypeInput.value = mediaType;
                    }
                }
            }
        }
        
        // Close media manager
        const mediaModal = document.getElementById('media-manager-modal');
        if (mediaModal) {
            mediaModal.remove();
        }
        
        this.showMessage('success', 'Media URL inserted into form!');
    }
    
    /**
     * Validate file before upload
     */
    validateFile(file) {
        if (!this.uploadConfig) {
            return true; // Can't validate without config
        }
        
        // Check file size
        if (file.size > this.uploadConfig.maxFileSize) {
            this.showMessage('error', `File ${file.name} is too large. Maximum size: ${this.uploadConfig.maxFileSizeFormatted}`);
            return false;
        }
        
        // Check file type
        if (!this.uploadConfig.allAllowedTypes.includes(file.type)) {
            this.showMessage('error', `File type ${file.type} is not allowed for ${file.name}`);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get accept types for file input
     */
    getAcceptTypes() {
        if (!this.uploadConfig || !this.uploadConfig.allAllowedTypes) {
            return '*/*';
        }
        
        return this.uploadConfig.allAllowedTypes.join(',');
    }
    
    /**
     * Get media type from file extension
     */
    getMediaTypeFromExtension(extension) {
        const mimeTypes = {
            'jpg': 'image/jpeg',
            'jpeg': 'image/jpeg',
            'png': 'image/png',
            'gif': 'image/gif',
            'webp': 'image/webp',
            'mp3': 'audio/mpeg',
            'wav': 'audio/wav',
            'ogg': 'audio/ogg',
            'm4a': 'audio/mp4',
            'mp4': 'video/mp4',
            'webm': 'video/webm',
            'ogv': 'video/ogg',
            'pdf': 'application/pdf',
            'txt': 'text/plain'
        };
        
        return mimeTypes[extension] || null;
    }
    
    /**
     * Format file size
     */
    formatFileSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;
        
        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }
        
        return `${Math.round(size * 100) / 100} ${units[unitIndex]}`;
    }
    
    /**
     * Truncate text
     */
    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.slice(0, maxLength) + '...';
    }
    
    /**
     * Show status message
     */
    showMessage(type, message) {
        const messageHtml = `
            <div class="status-message ${type}" style="margin-bottom: 1rem; position: fixed; top: 20px; right: 20px; z-index: 10000;">
                <span>${message}</span>
                <button class="status-message-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', messageHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            const messageEl = document.querySelector(`.status-message.${type}`);
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
                    console.error('Error in event listener:', error);
                }
            });
    }
}

// Create global instance
window.mediaManager = new MediaManager();