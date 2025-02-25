/**
 * Password Validation Module
 * 
 * Validation for login form passwords with toggle visibility
 * support and basic security checks.
 * 
 * @package Attributes\Assets\JS
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * PasswordToggle Class
     * 
     * Handles password visibility toggling for login forms.
     */
    class PasswordToggle {
        /**
         * Initialize password toggle functionality.
         * 
         * @param {HTMLElement} form - The form element containing password fields
         * @return {void}
         */
        constructor(form) {
            this.form = $(form);
            this.toggleButtons = this.form.find('.attrua-toggle-password');
            this.initialize();
        }

        /**
         * Initialize event listeners for password toggle.
         * 
         * @return {void}
         */
        initialize() {
            this.toggleButtons.each((_, button) => {
                const $button = $(button);
                
                $button.on('click', (e) => {
                    e.preventDefault();
                    
                    const $field = $button.closest('.attrua-password-field');
                    const $input = $field.find('input');
                    const $icon = $button.find('.ti');
                    
                    if ($input.length) {
                        // Toggle input type
                        const isPassword = $input.attr('type') === 'password';
                        $input.attr('type', isPassword ? 'text' : 'password');
                        
                        // Update icon
                        $icon
                            .removeClass(isPassword ? 'ti-eye' : 'ti-eye-off')
                            .addClass(isPassword ? 'ti-eye-off' : 'ti-eye');
                            
                        // Update accessibility label
                        $button.attr('aria-label', 
                            isPassword ? 'Hide password' : 'Show password'
                        );
                    }
                });
                
                // Set initial state
                $button.find('.ti').addClass('ti-eye');
                $button.attr('aria-label', 'Show password');
            });
        }
    }

    /**
     * LoginForm Class
     * 
     * Handles login form validation and submission behavior.
     */
    class LoginForm {
        /**
         * Initialize login form functionality.
         * 
         * @param {HTMLElement} form - The login form element
         * @param {Object} options - Configuration options
         * @return {void}
         */
        constructor(form, options) {
            this.form = $(form);
            this.options = $.extend({}, LoginForm.defaults, options);
            
            // Form elements
            this.submitButton = this.form.find(this.options.submitButtonSelector);
            this.usernameField = this.form.find('#attrua_username');
            this.passwordField = this.form.find('#attrua_password');
            this.requiredFields = this.form.find('input[required]');
            
            // Field error containers
            this.usernameError = this.usernameField.siblings('.attrua-field-error');
            this.passwordError = this.passwordField.siblings('.attrua-field-error');
            
            this.initialize();
        }

        /**
         * Initialize event listeners and form behavior.
         * 
         * @return {void}
         */
        initialize() {
            // Handle form submission
            this.form.on('submit', (e) => this.handleSubmit(e));
            
            // Input validations
            this.requiredFields.on('input', () => this.validateForm());
            
            // Initialize password toggle
            new PasswordToggle(this.form);
            
            // Initial validation
            this.validateForm();
        }

        /**
         * Validate form inputs.
         * 
         * @return {boolean} Whether the form is valid
         */
        validateForm() {
            let isValid = true;
            
            // Clear previous errors
            this.usernameError.text('');
            this.passwordError.text('');
            
            // Username validation
            if (!this.usernameField.val().trim()) {
                isValid = false;
            }
            
            // Password validation
            if (!this.passwordField.val()) {
                isValid = false;
            }
            
            // Update submit button state
            this.submitButton.prop('disabled', !isValid);
            
            return isValid;
        }

        /**
         * Handle form submission.
         * 
         * @param {Event} event - Form submit event
         * @return {void}
         */
        handleSubmit(event) {
            // Only intercept if configured
            if (!this.options.ajaxLogin) {
                return;
            }
            
            event.preventDefault();
            
            if (!this.validateForm()) {
                return;
            }
            
            // Visual feedback
            this.submitButton
                .prop('disabled', true)
                .text(this.options.i18n.loggingIn);
            
            // Form submission will proceed naturally
            // This is a hook point for future AJAX login implementation
            this.form.off('submit').submit();
        }
    }

    // Default options
    LoginForm.defaults = {
        submitButtonSelector: '.attrua-submit-button',
        ajaxLogin: false,
        i18n: {
            loggingIn: 'Logging in...',
            invalidCredentials: 'Invalid username or password.'
        }
    };

    /**
     * Initialize plugin
     */
    $(document).ready(function() {
        // Initialize for login form
        $('.attrua-login-form').each(function() {
            const form = $(this);
            if (!$.data(this, 'attrua-login')) {
                $.data(this, 'attrua-login', new LoginForm(this, window.attruaFront || {}));
            }
        });
    });

})(jQuery);