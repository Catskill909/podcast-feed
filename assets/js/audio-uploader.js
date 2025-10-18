/**
 * Modern Audio Uploader
 * Handles drag & drop, progress, metadata extraction, and preview
 */

class AudioUploader {
    constructor(zoneId, options = {}) {
        this.zone = document.getElementById(zoneId);
        this.fileInput = this.zone.querySelector('input[type="file"]');
        this.options = {
            maxSize: options.maxSize || 500 * 1024 * 1024, // 500MB
            allowedTypes: options.allowedTypes || ['audio/mpeg', 'audio/mp3'],
            allowedExtensions: options.allowedExtensions || ['.mp3'],
            onUploadStart: options.onUploadStart || (() => {}),
            onUploadProgress: options.onUploadProgress || (() => {}),
            onUploadComplete: options.onUploadComplete || (() => {}),
            onUploadError: options.onUploadError || (() => {}),
            ...options
        };
        
        this.currentFile = null;
        this.audioElement = null;
        
        this.init();
    }
    
    init() {
        // Drag and drop events
        this.zone.addEventListener('dragover', (e) => this.handleDragOver(e));
        this.zone.addEventListener('dragleave', (e) => this.handleDragLeave(e));
        this.zone.addEventListener('drop', (e) => this.handleDrop(e));
        
        // File input change
        this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
    }
    
    handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        this.zone.classList.add('drag-over');
    }
    
    handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        this.zone.classList.remove('drag-over');
    }
    
    handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        this.zone.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            // Set the file to the input element using DataTransfer
            try {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(files[0]);
                this.fileInput.files = dataTransfer.files;
                console.log('✅ File set to input via drag & drop:', this.fileInput.files[0].name);
            } catch (error) {
                console.error('❌ DataTransfer not supported, file may not submit:', error);
                // Fallback: Store file reference but it won't submit with form
                this.currentFile = files[0];
            }
            
            this.processFile(files[0]);
        }
    }
    
    handleFileSelect(e) {
        const files = e.target.files;
        if (files.length > 0) {
            this.processFile(files[0]);
        }
    }
    
    processFile(file) {
        // Validate file
        const validation = this.validateFile(file);
        if (!validation.valid) {
            this.showError(validation.message);
            return;
        }
        
        this.currentFile = file;
        this.hideError();
        this.hideZone();
        this.showProgress();
        
        // Extract metadata FIRST
        this.extractMetadata(file).then(metadata => {
            // Start the actual upload - callback will be called when upload completes
            this.uploadFile(file, metadata);
        }).catch(error => {
            this.showError('Failed to read audio file: ' + error.message);
            this.showZone();
            this.hideProgress();
        });
    }
    
    validateFile(file) {
        // Check file type
        if (!this.options.allowedTypes.includes(file.type)) {
            return {
                valid: false,
                message: 'Invalid file type. Please upload an MP3 file.'
            };
        }
        
        // Check file extension
        const ext = '.' + file.name.split('.').pop().toLowerCase();
        if (!this.options.allowedExtensions.includes(ext)) {
            return {
                valid: false,
                message: 'Invalid file extension. Only MP3 files are allowed.'
            };
        }
        
        // Check file size
        if (file.size > this.options.maxSize) {
            const maxSizeMB = this.options.maxSize / (1024 * 1024);
            return {
                valid: false,
                message: `File too large. Maximum size is ${maxSizeMB}MB.`
            };
        }
        
        return { valid: true };
    }
    
    extractMetadata(file) {
        return new Promise((resolve, reject) => {
            const audio = document.createElement('audio');
            const url = URL.createObjectURL(file);
            
            audio.addEventListener('loadedmetadata', () => {
                const metadata = {
                    duration: audio.duration,
                    durationFormatted: this.formatDuration(audio.duration),
                    fileSize: file.size,
                    fileSizeFormatted: this.formatFileSize(file.size),
                    fileName: file.name
                };
                
                URL.revokeObjectURL(url);
                resolve(metadata);
            });
            
            audio.addEventListener('error', () => {
                URL.revokeObjectURL(url);
                reject(new Error('Could not load audio file'));
            });
            
            audio.src = url;
        });
    }
    
    uploadFile(file, metadata) {
        this.options.onUploadStart(file, metadata);
        this.currentFile = file;
        
        // Create FormData for AJAX upload
        const formData = new FormData();
        formData.append('audio_file', file);
        formData.append('podcast_id', this.options.podcastId);
        formData.append('episode_id', this.options.episodeId || 'ep_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9));
        
        // Use XMLHttpRequest for real upload with progress
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percent = (e.loaded / e.total) * 100;
                this.updateProgress(percent);
                this.options.onUploadProgress(percent);
            }
        });
        
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        this.hideProgress();
                        this.showSuccess(file, metadata);
                        // Store the uploaded file URL for form submission
                        this.uploadedFileUrl = response.url;
                        this.uploadedDuration = response.duration;
                        this.uploadedFileSize = response.file_size;
                        // Call completion callback with server response
                        this.options.onUploadComplete(file, {
                            ...metadata,
                            url: response.url,
                            duration: response.duration,
                            fileSize: response.file_size
                        });
                    } else {
                        this.showError('Upload failed: ' + response.message);
                        this.options.onUploadError(response.message);
                    }
                } catch (e) {
                    this.showError('Upload failed: Invalid response');
                    this.options.onUploadError('Invalid response');
                }
            } else {
                this.showError('Upload failed: ' + xhr.statusText);
                this.options.onUploadError(xhr.statusText);
                this.showZone();
                this.hideProgress();
            }
        });
        
        xhr.addEventListener('error', () => {
            this.showError('Upload failed. Please check your connection.');
            this.options.onUploadError('Network error');
            this.showZone();
            this.hideProgress();
        });
        
        xhr.open('POST', '/api/upload-audio-chunk.php');
        xhr.send(formData);
    }
    
    showProgress() {
        const progressEl = this.zone.parentElement.querySelector('.upload-progress');
        if (progressEl) {
            progressEl.classList.add('active');
        }
    }
    
    hideProgress() {
        const progressEl = this.zone.parentElement.querySelector('.upload-progress');
        if (progressEl) {
            progressEl.classList.remove('active');
        }
    }
    
    updateProgress(percent) {
        const progressBar = this.zone.parentElement.querySelector('.progress-bar');
        const progressText = this.zone.parentElement.querySelector('.progress-text');
        
        if (progressBar) {
            progressBar.style.width = percent + '%';
        }
        
        if (progressText) {
            progressText.textContent = `Uploading... ${Math.round(percent)}%`;
        }
    }
    
    showSuccess(file, metadata, serverResponse = {}) {
        const successEl = this.zone.parentElement.querySelector('.upload-success');
        if (!successEl) return;
        
        // Update success info
        const filenameEl = successEl.querySelector('.upload-filename');
        const metaEl = successEl.querySelector('.upload-meta');
        
        if (filenameEl) {
            filenameEl.textContent = file.name;
        }
        
        if (metaEl) {
            metaEl.innerHTML = `
                <span class="upload-meta-item">
                    <i class="fas fa-file"></i> ${metadata.fileSizeFormatted}
                </span>
                <span class="upload-meta-item">
                    <i class="fas fa-clock"></i> ${metadata.durationFormatted}
                </span>
                <span class="upload-meta-item">
                    <i class="fas fa-music"></i> MP3
                </span>
            `;
        }
        
        // Create audio preview
        this.createAudioPreview(file);
        
        successEl.classList.add('active');
    }
    
    createAudioPreview(file) {
        const playerContainer = this.zone.parentElement.querySelector('.audio-preview-player');
        if (!playerContainer) return;
        
        const url = URL.createObjectURL(file);
        
        playerContainer.innerHTML = `
            <audio preload="metadata">
                <source src="${url}" type="audio/mpeg">
            </audio>
        `;
        
        playerContainer.classList.add('audio-player-container');
        
        this.audioElement = playerContainer.querySelector('audio');
        
        // Initialize custom player
        if (window.CustomAudioPlayer) {
            new window.CustomAudioPlayer(playerContainer);
        }
    }
    
    showError(message) {
        const errorEl = this.zone.parentElement.querySelector('.upload-error');
        if (!errorEl) return;
        
        const messageEl = errorEl.querySelector('.upload-error-message');
        if (messageEl) {
            messageEl.textContent = message;
        }
        
        errorEl.classList.add('active');
        this.options.onUploadError(message);
    }
    
    hideError() {
        const errorEl = this.zone.parentElement.querySelector('.upload-error');
        if (errorEl) {
            errorEl.classList.remove('active');
        }
    }
    
    showZone() {
        this.zone.style.display = 'flex';
    }
    
    hideZone() {
        this.zone.style.display = 'none';
    }
    
    remove() {
        // Clean up
        if (this.audioElement) {
            URL.revokeObjectURL(this.audioElement.src);
        }
        
        this.currentFile = null;
        this.fileInput.value = '';
        
        // Hide success, show zone
        const successEl = this.zone.parentElement.querySelector('.upload-success');
        if (successEl) {
            successEl.classList.remove('active');
        }
        
        this.showZone();
        this.hideError();
    }
    
    formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);
        
        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
}

// Export for use
window.AudioUploader = AudioUploader;
