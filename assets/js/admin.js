/**
 * Admin Interface JavaScript
 * 
 * Implements dynamic functionality for the plugin's admin interface including:
 * - Login page management (creation, deletion)
 * - Settings form handling
 * - UI state management
 * - Error handling
 * - User feedback mechanisms
 * 
 * @package Attributes\Assets\JS
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin interface management class.
     * 
     * Handles all JavaScript functionality for the plugin's admin interface.
     */
    class AttributesAdmin {
        /**
         * Initialize the admin interface.
         * 
         * @param {Object} config - Configuration options
         */
        constructor(config) {
            // Configuration
            this.config = $.extend({}, AttributesAdmin.defaults, config);
            this.notificationManager = new NotificationManager();
            
            // State management
            this.isProcessing = false;
            
            // Cache DOM elements
            this.form = $('.attrua-settings-form');
            this.submitButton = this.form.find(':submit');

            // Initialize functionality
            this.initializeEventListeners();
        }

        /**
         * Initialize event listeners for admin interface interactions.
         * 
         * Sets up handlers for page management, form submission, and UI interactions.
         * 
         * @return {void}
         */
        initializeEventListeners() {
            // Page management
            $(document).on('click', '.attrua-create-page', this.handlePageCreation.bind(this));
            $(document).on('click', '.attrua-delete-page', this.handlePageDeletion.bind(this));
            
            // Settings management
            $(document).on('change', '.attrua-redirect-toggle input', this.handleRedirectToggle.bind(this));
            this.form.on('submit', this.handleFormSubmission.bind(this));

            // UI interactions
            $(document).on('click', '.notice-dismiss', function() {
                $(this).closest('.notice').fadeOut();
            });

            // Handle both checkbox change and slider click
            $(document).on('change click', '.attrua-redirect-toggle input, .attrua-redirect-toggle .slider', (e) => {
                if (e.target.classList.contains('slider')) {
                    // If slider was clicked, toggle the checkbox
                    const checkbox = $(e.target).siblings('input[type="checkbox"]');
                    checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
                    e.preventDefault();
                } else {
                    // If checkbox changed, handle normally
                    this.handleRedirectToggle(e);
                }
            });

            // Shortcode copying functionality
            $(document).on('click', '.attrua-copy-shortcode', function(e) {
                e.preventDefault();
                
                const shortcode = $(this).data('shortcode');
                const button = $(this);
                
                // Create temporary textarea
                const textarea = document.createElement('textarea');
                textarea.value = shortcode;
                document.body.appendChild(textarea);
                
                // Copy text
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                
                // Visual feedback
                button.html('<i class="ti ti-check"></i>'); // Use .html() to render the icon
                setTimeout(() => {
                    button.html('<i class="ti ti-copy"></i>'); // Revert to the original text
                }, 2000);
            });
        }

        /**
         * Handle page creation requests.
         *
         * Creates a new WordPress page with the appropriate shortcode
         * and updates the settings accordingly.
         *
         * @param {Event} event - Click event object
         * @return {void}
         */
        handlePageCreation(event) {
            event.preventDefault();
        
            if (this.isProcessing) {
                return;
            }
        
            const button = $(event.currentTarget);
            const pageType = button.data('page-type');
            
            // Find the table that contains this button
            const table = button.closest('.attrua-pages-table');
            
            // Find inputs within the table
            const pageTitle = table.find('.attrua-page-title').val();
            const pageSlug = table.find('.attrua-page-slug').val();
            
            // Validation
            if (!pageType || !pageTitle) {
                this.showError(this.config.i18n.invalidData);
                return;
            }
            
            // Visual feedback
            this.isProcessing = true;
            button.prop('disabled', true)
                  .text(this.config.i18n.creatingPage);
            
            // Store original text for restoration
            button.data('original-text', button.text());
            
            // AJAX request
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'attrua_create_page',
                    _ajax_nonce: this.config.nonce,
                    page_type: pageType,
                    title: pageTitle,
                    slug: pageSlug
                },
                success: this.handlePageCreationSuccess.bind(this, button),
                error: this.handleAjaxError.bind(this, button)
            });
        }

        /**
         * Handle successful page creation.
         *
         * Updates the UI with the newly created page information
         * and enables page management controls.
         *
         * @param {jQuery} button - The clicked button element
         * @param {Object} response - AJAX response data
         * @return {void}
         */
        handlePageCreationSuccess(button, response) {
            if (!response.success) {
                this.handleAjaxError(button, response);
                return;
            }
        
            const pageRow = button.closest('.attrua-page-row');
            const pageType = response.data.page_type;
            const pageId = response.data.page_id;
        
            // Update Title Display
            const titleInput = pageRow.find('.attrua-page-title');
            const titleDisplay = $('<strong class="attrua-page-title-display"></strong>')
                .text(titleInput.val());
            titleInput.hide().after(titleDisplay);
        
            // Update Slug Display
            const slugInput = pageRow.find('.attrua-page-slug');
            const slugDisplay = $('<strong class="attrua-page-slug-display"></strong>')
                .html('<span class="attrua-page-prefix">/</span> ' + slugInput.val());
            slugInput.hide().after(slugDisplay);
        
            // Update Shortcode Display
            const shortCode = pageRow.find('.attrua-page-shortcode').parent();
            shortCode.html(this.getShortCodeTemplate(response.data));

            pageRow.find('.attrua-page-shortcode').show();
        
            // Update Page Actions
            const pageControl = button.closest('.attrua-page-control');
            pageControl.html(this.getPageActionsTemplate(response.data));
        
            // Add Redirect Toggle
            const redirectCell = pageRow.find('.attrua-redirect-toggle').parent();
            redirectCell.html(this.getPageRedirectTemplate(response.data));
        
            // Show success message
            this.showSuccess(this.config.i18n.pageCreated);
        
            // Reset state
            this.isProcessing = false;
        }

        /**
         * Handle page deletion requests.
         *
         * Removes the WordPress page and updates settings accordingly.
         *
         * @param {Event} event - Click event object
         * @return {void}
         */
        handlePageDeletion(event) {
            event.preventDefault();

            if (this.isProcessing) {
                return;
            }

            const button = $(event.currentTarget);
            const pageId = button.data('page-id');
            const pageType = button.data('page-type');

            // Confirmation
            if (!confirm(this.config.i18n.confirmDelete)) {
                return;
            }

            // Visual feedback
            this.isProcessing = true;
            button.prop('disabled', true)
                  .text(this.config.i18n.deletingPage);

            // AJAX request
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'attrua_delete_page',
                    _ajax_nonce: this.config.nonce,
                    page_id: pageId,
                    page_type: pageType
                },
                success: this.handlePageDeletionSuccess.bind(this, button, pageType),
                error: this.handleAjaxError.bind(this, button)
            });
        }

        /**
         * Handle successful page deletion.
         *
         * Updates the UI state after page deletion:
         * 1. Restores the title input field
         * 2. Resets and hides the redirect toggle
         * 3. Updates the page control interface
         *
         * @param {jQuery} button - The clicked delete button element
         * @param {string} pageType - Type identifier of the deleted page
         * @param {Object} response - AJAX response data
         * @return {void}
         */
        handlePageDeletionSuccess(button, pageType, response) {
            if (!response.success) {
                this.handleAjaxError(button, response);
                return;
            }

            // Get page row and relevant elements
            const pageRow = button.closest('.attrua-page-row');
            const titleInput = pageRow.find('.attrua-page-title');
            const titleDisplay = pageRow.find('.attrua-page-title-display');
            const slugInput = pageRow.find('.attrua-page-slug');
            const slugDisplay = pageRow.find('.attrua-page-slug-display');
            const shortcodeDisplay = pageRow.find('.attrua-page-shortcode');
            const redirectToggle = pageRow.find('.attrua-redirect-toggle');
            const redirectCheckbox = redirectToggle.find('input[type="checkbox"]');

            // Reset title field state
            titleDisplay.remove();
            titleInput
                .css('display', '') // Remove inline display:none
                .show().val(titleInput.attr('placeholder')); // Restore default title

            // Reset slug field state
            slugDisplay.remove();
            slugInput
                .css('display', '') // Remove inline display:none
                .show().val(slugInput.attr('placeholder')); // Restore default slug

            // Reset shortcode display
            shortcodeDisplay.hide();

            // Reset redirect toggle
            redirectCheckbox.prop('checked', false);
            redirectToggle.hide();

            // Update page control interface
            const pageControl = button.closest('.attrua-page-control');
            const template = this.getCreateButtonTemplate(pageType);
            pageControl.html(template);

            // Show success message
            this.showSuccess(this.config.i18n.pageDeleted);

            // Reset processing state
            this.isProcessing = false;
        }

        /**
         * Handle redirect toggle changes.
         *
         * Updates the redirect settings and URL display via AJAX when toggled.
         *
         * @param {Event} event - Change event object
         * @return {void}
         */
        handleRedirectToggle(event) {
            const checkbox = $(event.target).is(':checkbox') ? 
                $(event.target) : 
                $(event.target).siblings('input[type="checkbox"]');
            const pageType = checkbox.closest('[data-page-type]').data('page-type');
            const urlDisplay = checkbox.closest('.attrua-redirect-toggle')
                .find('.attrua-redirect-url small');
            const wpUrl = checkbox.data('wp-url');
            const customUrl = checkbox.data('custom-url');
            
            // Update URL display immediately
            urlDisplay.text(checkbox.prop('checked') ? customUrl : wpUrl);
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'attrua_toggle_redirect',
                    _ajax_nonce: this.config.nonce,
                    page_type: pageType,
                    enabled: checkbox.prop('checked')
                },
                success: response => {
                    if (response.success) {
                        this.showSuccess(this.config.i18n.settingsSaved);
                    } else {
                        this.showError(response.data.message);
                        checkbox.prop('checked', !checkbox.prop('checked'));
                        // Revert URL display on error
                        urlDisplay.text(checkbox.prop('checked') ? customUrl : wpUrl);
                    }
                },
                error: () => {
                    this.showError(this.config.i18n.error);
                    checkbox.prop('checked', !checkbox.prop('checked'));
                    // Revert URL display on error
                    urlDisplay.text(checkbox.prop('checked') ? customUrl : wpUrl);
                }
            });
        }

        /**
         * Handle form submission.
         *
         * Provides visual feedback during settings submission.
         *
         * @param {Event} event - Submit event object
         * @return {void}
         */
        handleFormSubmission(event) {
            // Visual feedback
            this.submitButton
                .prop('disabled', true)
                .val(this.config.i18n.saving);

            // Re-enable after submission
            setTimeout(() => {
                this.submitButton
                    .prop('disabled', false)
                    .val(this.config.i18n.saveChanges);
            }, 1000);
        }

        /**
         * Handle AJAX errors.
         *
         * Provides user feedback for failed AJAX operations.
         *
         * @param {jQuery} button - The affected button element
         * @param {Object} response - Error response data
         * @return {void}
         */
        handleAjaxError(button, response) {
            // Reset button state
            button.prop('disabled', false)
                  .text(button.data('original-text') || this.config.i18n.retry);

            // Show error message
            this.showError(
                response.data?.message || this.config.i18n.error
            );

            // Reset processing state
            this.isProcessing = false;
        }

        /**
         * Show success message.
         *
         * Displays a success notification to the user.
         *
         * @param {string} message - Success message to display
         * @return {void}
         */
        showSuccess(message) {
            this.showNotice(message, 'success');
        }

        /**
         * Show error message.
         *
         * Displays an error notification to the user.
         *
         * @param {string} message - Error message to display
         * @return {void}
         */
        showError(message) {
            this.showNotice(message, 'error');
        }

        /**
         * Show notice message.
         *
         * Handles the display of WordPress admin notices.
         *
         * @param {string} message - Notice message to display
         * @param {string} type - Notice type (success/error)
         * @return {void}
         */
        showNotice(message, type) {
            this.notificationManager.show(message, type);
        }

        /**
         * Generate Shortcode template for page control.
         * 
         * @param {Object} data - Page data including type identifier
         * @return {string} Generated HTML for page control
         */
        getShortCodeTemplate(data) {
            return `
                <div class="attrua-page-control">
                    <div class="attrua-page-shortcode">
                        <span>[attrutes_${this.escapeHtml(data.page_type)}_form]</span>
                        <button type="button" class="attrua-copy-shortcode" data-shortcode="[attrutes_${this.escapeHtml(data.page_type)}_form]"> <i class="ti ti-copy"></i></button>
                    </div>
                </div>
            `;
        }

        /**
         * Generate HTML template for page actions.
         *
         * @param {Object} data - Page data including URLs and identifiers
         * @return {string} Generated HTML for page actions
         */
        getPageActionsTemplate(data) {
            return `
                <div class="attrua-page-actions">
                    <a href="${this.escapeHtml(data.edit_url)}" class="button">
                        <i class="ti ti-pencil"></i>&nbsp;${this.escapeHtml(this.config.i18n.editPage)}
                    </a>
                    <a href="${this.escapeHtml(data.view_url)}" class="button" target="_blank">
                        <i class="ti ti-eye"></i>&nbsp;${this.escapeHtml(this.config.i18n.viewPage)}
                    </a>
                    <button type="button" 
                            class="button attrua-delete-page"
                            data-page-id="${this.escapeHtml(data.page_id)}"
                            data-page-type="${this.escapeHtml(data.page_type)}">
                        <i class="ti ti-trash"></i>&nbsp;${this.escapeHtml(this.config.i18n.delete)}
                    </button>
                </div>
            `;
        }

        /**
         * Generate HTML template for page redirect toggle.
         *
         * @param {Object} data - Page data including type identifier
         * @return {string} Generated HTML for redirect toggle
         */
        getPageRedirectTemplate(data) {
            return `
                <label class="attrua-redirect-toggle">
                    <input type="checkbox" 
                           name="attrua_redirect_options[${this.escapeHtml(data.page_type)}]" 
                           value="1"
                           data-wp-url="${this.escapeHtml(data.wp_url || window.attruaAdmin.wpLoginUrl)}"
                           data-custom-url="${this.escapeHtml(data.view_url)}">
                    <span class="slider round"></span>
                    <span class="attrua-redirect-url">
                    <small>${this.escapeHtml(data.wp_url || window.attruaAdmin.wpLoginUrl)}</small>
                    </span>
                </label>
            `;
        }

        /**
         * Get create button template.
         *
         * Generates HTML for the page creation button with proper data attributes
         * and localized text.
         *
         * @param {string} pageType - Type identifier for the page
         * @return {string} Generated HTML for create button
         */
        getCreateButtonTemplate(pageType) {
            const config = this.config.pageTypes[pageType] || {};
            const title = config.title || pageType;
            const slug = config.slug || pageType;

            return `
                <button type="button" 
                        class="button attrua-create-page"
                        data-page-type="${this.escapeHtml(pageType)}"
                        data-title="${this.escapeHtml(title)}"
                        data-slug="${this.escapeHtml(slug)}"
                        title="${this.escapeHtml(this.config.i18n.createPageTitle)}">
                    ${this.escapeHtml(this.config.i18n.createPage)}
                </button>
            `;
        }

        /**
         * HTML escape utility function.
         *
         * Ensures safe HTML string interpolation by escaping special characters.
         *
         * @param {string} str - String to escape
         * @return {string} Escaped HTML string
         */
        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    }

    class NotificationManager {
        constructor() {
            this.init();
        }
    
        init() {
            // Create container if it doesn't exist
            if (!document.querySelector('.attrua-notifications-container')) {
                const container = document.createElement('div');
                container.className = 'attrua-notifications-container';
                document.body.appendChild(container);
            }
        }
    
        show(message, type = 'success') {
            const container = document.querySelector('.attrua-notifications-container');
            const notification = document.createElement('div');
            const id = 'notification-' + Date.now();
            
            notification.className = `attrua-notification ${type}`;
            notification.id = id;
            notification.innerHTML = `
                <div class="attrua-notification-content">
                    ${message}
                    <button class="attrua-notification-dismiss" aria-label="Dismiss">
                        <span class="ti ti-x"></span>
                    </button>
                </div>
            `;
    
            container.appendChild(notification);
    
            // Show animation
            requestAnimationFrame(() => {
                notification.classList.add('show');
            });
    
            // Set up dismiss button
            const dismissButton = notification.querySelector('.attrua-notification-dismiss');
            dismissButton.addEventListener('click', () => this.dismiss(id));
    
            // Auto dismiss after 5 seconds
            setTimeout(() => this.dismiss(id), 5000);
        }
    
        dismiss(id) {
            const notification = document.getElementById(id);
            if (notification) {
                notification.classList.add('hide');
                setTimeout(() => notification.remove(), 600); // Wait for animation
            }
        }
    }

    // Default configuration
    AttributesAdmin.defaults = {
        ajax_url: '',
        nonce: '',
        pageTypes: {},
        i18n: {
            createPage: 'Create Page',
            createPageTitle: 'Create a custom login page',
            editPage: 'Edit Page',
            viewPage: 'View Page',
            delete: 'Delete',
            creatingPage: 'Creating...',
            deletingPage: 'Deleting...',
            saving: 'Saving...',
            saveChanges: 'Save Changes',
            retry: 'Retry',
            dismiss: 'Dismiss this notice',
            confirmDelete: 'Are you sure you want to delete this page?',
            pageCreated: 'Page created successfully.',
            pageDeleted: 'Page deleted successfully.',
            settingsSaved: 'Settings saved.',
            error: 'An error occurred.',
            invalidData: 'Invalid data provided.',
            redirectToggle: 'Redirect WordPress page to this page'
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Only initialize on plugin settings page
        if ($('.attrua-content-wrap').length) {
            new AttributesAdmin(window.attruaAdmin || {});
        }
    });

    // Save page titles and slugs
    $('.attrua-page-title, .attrua-page-slug').on('change', function() {
        const input = $(this);
        const row = input.closest('.attrua-page-row');
        const pageType = row.data('page-type');
        
        // Update create button data if present
        const createButton = row.find('.attrua-create-page');
        if (input.hasClass('attrua-page-title')) {
            createButton.data('title', input.val());
        } else if (input.hasClass('attrua-page-slug')) {
            createButton.data('slug', input.val());
        }
    });
})(jQuery);