/**
 * Feed Cloner JavaScript
 * Handles podcast feed cloning UI and progress tracking
 */

let cloneJobId = null;
let cloneProgressInterval = null;

/**
 * Show clone modal
 */
function showCloneModal() {
    const modal = document.getElementById('cloneFeedModal');
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        resetCloneModal();
    }
}

/**
 * Hide clone modal
 */
function hideCloneModal() {
    const modal = document.getElementById('cloneFeedModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        
        // Stop progress polling if active
        if (cloneProgressInterval) {
            clearInterval(cloneProgressInterval);
            cloneProgressInterval = null;
        }
    }
}

/**
 * Close and reload page
 */
function closeAndReload() {
    window.location.reload();
}

/**
 * Reset clone modal to initial state
 */
function resetCloneModal() {
    document.getElementById('cloneStep1').style.display = 'block';
    document.getElementById('cloneStep2').style.display = 'none';
    document.getElementById('cloneStep3').style.display = 'none';
    document.getElementById('cloneStep4').style.display = 'none';
    
    document.getElementById('cloneFeedUrlInput').value = '';
    document.getElementById('cloneValidateButton').style.display = 'inline-block';
    document.getElementById('cloneStartButton').style.display = 'none';
    document.getElementById('cloneBackButton').style.display = 'none';
    
    hideCloneError();
}

/**
 * Validate feed for cloning
 */
async function validateCloneFeed() {
    const feedUrl = document.getElementById('cloneFeedUrlInput').value.trim();
    
    if (!feedUrl) {
        showCloneError('Please enter a feed URL');
        return;
    }
    
    hideCloneError();
    showCloneLoading('Validating feed...');
    
    const validateBtn = document.getElementById('cloneValidateButton');
    validateBtn.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('feed_url', feedUrl);
        formData.append('action', 'validate');
        
        const response = await fetch('api/clone-feed.php?action=validate', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success && result.can_clone) {
            // Show preview
            displayClonePreview(result);
        } else {
            showCloneError(result.message || 'Feed validation failed');
        }
        
    } catch (error) {
        console.error('Validation error:', error);
        showCloneError('Network error. Please check your connection.');
    } finally {
        hideCloneLoading();
        validateBtn.disabled = false;
    }
}

/**
 * Display clone preview
 */
function displayClonePreview(result) {
    const feedData = result.feed_data;
    const estimate = result.estimate;
    
    // Hide step 1, show step 2
    document.getElementById('cloneStep1').style.display = 'none';
    document.getElementById('cloneStep2').style.display = 'block';
    
    // Update buttons
    document.getElementById('cloneValidateButton').style.display = 'none';
    document.getElementById('cloneStartButton').style.display = 'inline-block';
    document.getElementById('cloneBackButton').style.display = 'inline-block';
    
    // Display feed info
    document.getElementById('clonePodcastTitle').textContent = feedData.title || 'Unknown';
    document.getElementById('cloneEpisodeCount').textContent = estimate.episode_count || 0;
    document.getElementById('cloneTotalSize').textContent = estimate.total_size_formatted || '0 B';
    document.getElementById('cloneAvgSize').textContent = estimate.average_episode_size_formatted || '0 B';
    
    // Show warnings if any
    if (result.warnings && result.warnings.length > 0) {
        const warningDiv = document.getElementById('cloneWarnings');
        warningDiv.innerHTML = '<strong>⚠️ Warnings:</strong><ul>' + 
            result.warnings.map(w => '<li>' + (w.message || w) + '</li>').join('') + 
            '</ul>';
        warningDiv.style.display = 'block';
    } else {
        document.getElementById('cloneWarnings').style.display = 'none';
    }
}

/**
 * Start cloning
 */
async function startCloning() {
    const feedUrl = document.getElementById('cloneFeedUrlInput').value.trim();
    const downloadImages = document.getElementById('cloneDownloadImages').checked;
    const importToDirectory = document.getElementById('cloneImportToDirectory').checked;
    const limitEpisodes = document.getElementById('cloneLimitEpisodes').value;
    
    // Hide step 2, show step 3 (progress)
    document.getElementById('cloneStep2').style.display = 'none';
    document.getElementById('cloneStep3').style.display = 'block';
    
    // Hide all footer buttons during cloning
    document.getElementById('cloneValidateButton').style.display = 'none';
    document.getElementById('cloneStartButton').style.display = 'none';
    document.getElementById('cloneBackButton').style.display = 'none';
    document.getElementById('cloneCancelButton').style.display = 'inline-block';
    
    try {
        const formData = new FormData();
        formData.append('feed_url', feedUrl);
        formData.append('action', 'start');
        formData.append('download_episode_images', downloadImages ? '1' : '0');
        formData.append('import_to_directory', importToDirectory ? '1' : '0');
        if (limitEpisodes) {
            formData.append('limit_episodes', limitEpisodes);
        }
        
        // Get episode count for time estimate
        const episodeCount = parseInt(document.getElementById('cloneEpisodeCount').textContent) || 5;
        const estimatedMinutes = Math.ceil(episodeCount * 0.5); // ~30 seconds per episode
        
        // Update UI to show cloning is happening
        document.getElementById('cloneCurrentAction').innerHTML = `
            <div style="display: flex; align-items: center; gap: 15px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem; color: #4CAF50;"></i>
                <div>
                    <div style="font-size: 1.1rem; font-weight: 600; margin-bottom: 5px;">
                        Cloning ${episodeCount} episodes...
                    </div>
                    <div style="font-size: 0.9rem; color: #9e9e9e;">
                        This may take ${estimatedMinutes}-${estimatedMinutes + 2} minutes. Please don't close this window.
                    </div>
                </div>
            </div>
        `;
        
        // Disable cancel button during cloning
        document.getElementById('cloneCancelButton').disabled = true;
        document.getElementById('cloneCancelButton').style.opacity = '0.5';
        
        // Start cloning (this is a BLOCKING call - it won't return until done)
        const response = await fetch('api/clone-feed.php?action=start', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        // Cloning is already complete when we get here
        if (result.success) {
            showCloneComplete(result);
        } else {
            showCloneError(result.message || 'Failed to clone podcast');
        }
        
    } catch (error) {
        console.error('Start cloning error:', error);
        showCloneError('Network error. Please try again.');
        console.error('Clone start error:', error);
        showCloneError('Failed to start cloning. Please try again.');
        document.getElementById('cloneStep3').style.display = 'none';
        document.getElementById('cloneStep2').style.display = 'block';
    }
}

/**
 * Start polling for progress
 */
function startProgressPolling() {
    // Poll every 2 seconds
    cloneProgressInterval = setInterval(updateCloneProgress, 2000);
    // Also update immediately
    updateCloneProgress();
}

/**
 * Update clone progress
 */
async function updateCloneProgress() {
    if (!cloneJobId) return;
    
    try {
        console.log('Polling progress for job:', cloneJobId);
        const response = await fetch(`api/clone-feed.php?action=progress&job_id=${cloneJobId}`);
        const result = await response.json();
        console.log('Progress result:', result);
        
        if (result.success && result.progress) {
            displayProgress(result.progress);
            
            // Check if complete
            if (result.progress.phase === 'complete') {
                clearInterval(cloneProgressInterval);
                cloneProgressInterval = null;
                showCloneComplete(result.progress);
            } else if (result.progress.phase === 'error') {
                clearInterval(cloneProgressInterval);
                cloneProgressInterval = null;
                showCloneError(result.progress.message || 'Cloning failed');
            }
        }
        
    } catch (error) {
        console.error('Progress update error:', error);
    }
}

/**
 * Display progress
 */
function displayProgress(progress) {
    // Update phase
    updatePhaseDisplay(progress.phase);
    
    // Update current action
    const actionText = getActionText(progress);
    document.getElementById('cloneCurrentAction').textContent = actionText;
    
    // Update progress bar
    const percent = progress.percent || 0;
    document.getElementById('cloneProgressBar').style.width = percent + '%';
    document.getElementById('cloneProgressPercent').textContent = Math.round(percent) + '%';
    
    // Update episode progress if available
    if (progress.current_episode && progress.total_episodes) {
        document.getElementById('cloneEpisodeProgress').textContent = 
            `Episode ${progress.current_episode} of ${progress.total_episodes}`;
        document.getElementById('cloneEpisodeProgress').style.display = 'block';
    }
    
    // Update stats
    if (progress.elapsed_formatted) {
        document.getElementById('cloneElapsedTime').textContent = progress.elapsed_formatted;
    }
    if (progress.remaining_formatted) {
        document.getElementById('cloneRemainingTime').textContent = progress.remaining_formatted;
    }
}

/**
 * Update phase display
 */
function updatePhaseDisplay(phase) {
    // Reset all phases
    document.querySelectorAll('.clone-phase').forEach(el => {
        el.classList.remove('active', 'complete');
    });
    
    // Update based on current phase
    switch (phase) {
        case 'validating':
        case 'creating_podcast':
            document.getElementById('clonePhase1').classList.add('active');
            break;
        case 'cloning_episodes':
            document.getElementById('clonePhase1').classList.add('complete');
            document.getElementById('clonePhase2').classList.add('active');
            break;
        case 'finalizing':
        case 'complete':
            document.getElementById('clonePhase1').classList.add('complete');
            document.getElementById('clonePhase2').classList.add('complete');
            break;
    }
}

/**
 * Get action text
 */
function getActionText(progress) {
    if (progress.episode_title) {
        return `Cloning: ${progress.episode_title}`;
    }
    
    switch (progress.action) {
        case 'downloading_audio':
            return 'Downloading audio file...';
        case 'downloading_image':
            return 'Downloading episode image...';
        case 'creating_metadata':
            return 'Creating episode metadata...';
        default:
            return progress.message || 'Processing...';
    }
}

/**
 * Show clone complete
 */
function showCloneComplete(result) {
    document.getElementById('cloneStep3').style.display = 'none';
    document.getElementById('cloneStep4').style.display = 'block';
    
    // Hide all footer buttons except Close
    document.getElementById('cloneValidateButton').style.display = 'none';
    document.getElementById('cloneStartButton').style.display = 'none';
    document.getElementById('cloneBackButton').style.display = 'none';
    document.getElementById('cloneCancelButton').style.display = 'none';
    document.getElementById('cloneCloseButton').style.display = 'inline-block';
    
    document.getElementById('cloneCompleteTitle').textContent = 
        result.podcast_title || 'Podcast cloned successfully!';
    document.getElementById('cloneCompleteEpisodes').textContent = result.episodes_cloned || 0;
    
    if (result.episodes_failed && result.episodes_failed > 0) {
        document.getElementById('cloneCompleteFailed').textContent = result.episodes_failed;
        document.getElementById('cloneFailedInfo').style.display = 'block';
    } else {
        document.getElementById('cloneFailedInfo').style.display = 'none';
    }
    
    // Store podcast ID for actions
    if (result.podcast_id) {
        document.getElementById('cloneViewPodcastBtn').onclick = function() {
            window.location.href = 'self-hosted-podcasts.php';
        };
        document.getElementById('cloneManageEpisodesBtn').onclick = function() {
            window.location.href = 'self-hosted-episodes.php?podcast_id=' + result.podcast_id;
        };
    }
}

/**
 * Show clone error
 */
function showCloneError(message) {
    const errorDiv = document.getElementById('cloneError');
    const errorMessage = document.getElementById('cloneErrorMessage');
    errorMessage.textContent = message;
    errorDiv.style.display = 'block';
}

/**
 * Hide clone error
 */
function hideCloneError() {
    document.getElementById('cloneError').style.display = 'none';
}

/**
 * Show clone loading
 */
function showCloneLoading(message) {
    const loadingDiv = document.getElementById('cloneLoading');
    const loadingMessage = document.getElementById('cloneLoadingMessage');
    loadingMessage.textContent = message;
    loadingDiv.style.display = 'block';
}

/**
 * Hide clone loading
 */
function hideCloneLoading() {
    document.getElementById('cloneLoading').style.display = 'none';
}

/**
 * Close modal and reload page
 */
function closeAndReload() {
    hideCloneModal();
    window.location.reload();
}

// Close modal on outside click
window.addEventListener('click', function(event) {
    const modal = document.getElementById('cloneFeedModal');
    if (event.target === modal) {
        hideCloneModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('cloneFeedModal');
        if (modal && modal.style.display === 'flex') {
            hideCloneModal();
        }
    }
});
