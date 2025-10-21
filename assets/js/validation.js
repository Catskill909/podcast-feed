/**
 * Podcast Directory Manager - Form Validation
 * Client-side validation for forms
 */

class PodcastValidator {
    constructor() {
        this.rules = {
            title: {
                required: true,
                minLength: 3,
                maxLength: 200,
                message: 'Title must be between 3 and 200 characters'
            },
            feed_url: {
                required: true,
                pattern: /^https?:\/\/.+/i,
                message: 'Please enter a valid RSS feed URL starting with http:// or https://'
            },
            cover_image: {
                required: false,
                maxSize: 2 * 1024 * 1024, // 2MB
                allowedTypes: ['image/jpeg', 'image/png', 'image/gif'],
                minWidth: 1400,
                minHeight: 1400,
                maxWidth: 3000,
                maxHeight: 3000,
                message: 'Image must be between 1400x1400 and 3000x3000 pixels, max 2MB'
            }
        };

        this.errorMessages = {
            image_too_small: 'Image too small. Minimum size required: 1400x1400 pixels',
            image_too_large: 'Image too large. Maximum size allowed: 3000x3000 pixels',
            invalid_format: 'Please upload a JPG, PNG, or GIF image',
            file_too_large: 'File size too large. Maximum 2MB allowed',
            invalid_url: 'Please enter a valid RSS feed URL',
            title_required: 'Podcast title is required',
            title_too_long: 'Title must be less than 200 characters'
        };
    }

    /**
     * Validate a single field
     */
    validateField(fieldName, value, file = null) {
        const rule = this.rules[fieldName];
        if (!rule) return { valid: true };

        // Convert value to string if it's not already
        const stringValue = value ? String(value) : '';

        // Check required fields
        if (rule.required && (!stringValue || stringValue.trim() === '')) {
            return {
                valid: false,
                message: `${this.capitalizeFirst(fieldName)} is required`
            };
        }

        // Skip further validation if field is empty and not required
        if (!rule.required && (!stringValue || stringValue.trim() === '')) {
            return { valid: true };
        }

        // Validate based on field type
        switch (fieldName) {
            case 'title':
                return this.validateTitle(stringValue);
            case 'feed_url':
                return this.validateUrl(stringValue);
            case 'cover_image':
                return this.validateImage(file);
            default:
                return { valid: true };
        }
    }

    /**
     * Validate title field
     */
    validateTitle(title) {
        const trimmed = title.trim();

        if (trimmed.length < this.rules.title.minLength) {
            return {
                valid: false,
                message: `Title must be at least ${this.rules.title.minLength} characters`
            };
        }

        if (trimmed.length > this.rules.title.maxLength) {
            return {
                valid: false,
                message: `Title must be less than ${this.rules.title.maxLength} characters`
            };
        }

        return { valid: true };
    }

    /**
     * Validate URL field
     */
    validateUrl(url) {
        const trimmed = url.trim();

        if (!this.rules.feed_url.pattern.test(trimmed)) {
            return {
                valid: false,
                message: this.rules.feed_url.message
            };
        }

        // Additional URL validation
        try {
            new URL(trimmed);
        } catch {
            return {
                valid: false,
                message: 'Please enter a valid URL'
            };
        }

        return { valid: true };
    }

    /**
     * Validate image file
     */
    validateImage(file) {
        if (!file || !file.name) {
            return { valid: true }; // Not required
        }

        // Check file size
        if (file.size > this.rules.cover_image.maxSize) {
            return {
                valid: false,
                message: this.errorMessages.file_too_large
            };
        }

        // Check file type
        if (!this.rules.cover_image.allowedTypes.includes(file.type)) {
            return {
                valid: false,
                message: this.errorMessages.invalid_format
            };
        }

        // Image dimensions will be validated after loading
        return { valid: true, needsDimensionCheck: true };
    }

    /**
     * Validate image dimensions
     */
    async validateImageDimensions(file) {
        return new Promise((resolve) => {
            if (!file || !file.type.startsWith('image/')) {
                resolve({ valid: true });
                return;
            }

            const img = new Image();
            const url = URL.createObjectURL(file);

            img.onload = () => {
                URL.revokeObjectURL(url);

                const width = img.naturalWidth;
                const height = img.naturalHeight;

                if (width < this.rules.cover_image.minWidth || height < this.rules.cover_image.minHeight) {
                    resolve({
                        valid: false,
                        message: this.errorMessages.image_too_small,
                        dimensions: { width, height }
                    });
                    return;
                }

                if (width > this.rules.cover_image.maxWidth || height > this.rules.cover_image.maxHeight) {
                    resolve({
                        valid: false,
                        message: this.errorMessages.image_too_large,
                        dimensions: { width, height }
                    });
                    return;
                }

                resolve({
                    valid: true,
                    dimensions: { width, height }
                });
            };

            img.onerror = () => {
                URL.revokeObjectURL(url);
                resolve({
                    valid: false,
                    message: this.errorMessages.invalid_format
                });
            };

            img.src = url;
        });
    }

    /**
     * Validate entire form
     */
    async validateForm(formData, files = {}) {
        const results = {};
        let isValid = true;

        // Validate text fields
        for (const [fieldName, value] of formData.entries()) {
            if (this.rules[fieldName]) {
                const result = this.validateField(fieldName, value);
                results[fieldName] = result;
                if (!result.valid) {
                    isValid = false;
                }
            }
        }

        // Validate image file if present
        if (files.cover_image) {
            const fileResult = this.validateField('cover_image', null, files.cover_image);

            if (!fileResult.valid) {
                results.cover_image = fileResult;
                isValid = false;
            } else if (fileResult.needsDimensionCheck) {
                const dimensionResult = await this.validateImageDimensions(files.cover_image);
                results.cover_image = dimensionResult;
                if (!dimensionResult.valid) {
                    isValid = false;
                }
            }
        }

        return {
            valid: isValid,
            results: results
        };
    }

    /**
     * Display validation error for a field
     */
    showFieldError(fieldName, message) {
        const field = document.getElementById(fieldName);
        const feedback = field?.parentElement?.querySelector('.invalid-feedback');

        if (field) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
        }

        if (feedback) {
            feedback.textContent = message;
            feedback.style.display = 'block';
        }
    }

    /**
     * Clear validation error for a field
     */
    clearFieldError(fieldName) {
        const field = document.getElementById(fieldName);
        const feedback = field?.parentElement?.querySelector('.invalid-feedback');

        if (field) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        }

        if (feedback) {
            feedback.style.display = 'none';
        }
    }

    /**
     * Clear all validation errors
     */
    clearAllErrors() {
        const fields = document.querySelectorAll('.form-control');
        fields.forEach(field => {
            field.classList.remove('is-invalid', 'is-valid');
        });

        const feedbacks = document.querySelectorAll('.invalid-feedback');
        feedbacks.forEach(feedback => {
            feedback.style.display = 'none';
        });
    }

    /**
     * Show validation success for a field
     */
    showFieldSuccess(fieldName) {
        const field = document.getElementById(fieldName);

        if (field) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        }
    }

    /**
     * Real-time validation setup
     */
    setupRealTimeValidation() {
        // Title field validation
        const titleField = document.getElementById('title');
        if (titleField) {
            titleField.addEventListener('input', (e) => {
                const result = this.validateField('title', e.target.value);
                if (result.valid) {
                    this.clearFieldError('title');
                    if (e.target.value.trim()) {
                        this.showFieldSuccess('title');
                    }
                } else {
                    this.showFieldError('title', result.message);
                }
            });
        }

        // URL field validation
        const urlField = document.getElementById('feed_url');
        if (urlField) {
            urlField.addEventListener('blur', (e) => {
                if (e.target.value.trim()) {
                    const result = this.validateField('feed_url', e.target.value);
                    if (result.valid) {
                        this.clearFieldError('feed_url');
                        this.showFieldSuccess('feed_url');
                    } else {
                        this.showFieldError('feed_url', result.message);
                    }
                }
            });
        }

        // Image field validation
        const imageField = document.getElementById('cover_image');
        if (imageField) {
            imageField.addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (file) {
                    const result = await this.validateImageDimensions(file);
                    if (result.valid) {
                        this.clearFieldError('cover_image');
                        this.showFieldSuccess('cover_image');
                    } else {
                        this.showFieldError('cover_image', result.message);
                    }
                }
            });
        }
    }

    /**
     * Utility function to capitalize first letter
     */
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ');
    }
}

// Global validator instance
window.podcastValidator = new PodcastValidator();

// Initialize real-time validation when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.podcastValidator.setupRealTimeValidation();
});