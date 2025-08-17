/**
 * WooCommerce Warranty Manager JavaScript
 * Path: /wp-content/plugins/woocommerce-warranty-manager/assets/js/warranty-manager.js
 * Version: 1.0.0
 * 
 * Complete JavaScript functionality for warranty management system
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize warranty manager
    const WarrantyManager = {
        
        init: function() {
            this.bindEvents();
            this.initFormValidation();
            this.handleURLParams();
            this.initTooltips();
            this.enhanceAccessibility();
            this.enhanceKeyboardNavigation();
            this.monitorConnection();
            this.initAutoSave();
            console.log('Warranty Manager initialized successfully');
        },
        
        bindEvents: function() {
            // Warranty Activation Form
            $("#warranty-activation-form").on("submit", this.handleActivationSubmit.bind(this));
            
            // Warranty Check Form
            $("#warranty-check-form").on("submit", this.handleCheckSubmit.bind(this));
            
            // Admin functions
            $(".activate-warranty").on("click", this.handleAdminActivate.bind(this));
            $(".delete-warranty").on("click", this.handleAdminDelete.bind(this));
            $(".edit-warranty").on("click", this.handleAdminEdit.bind(this));
            
            // Auto-fetch order details
            let orderFetchTimer;
            $('#order_id').on('input', function() {
                clearTimeout(orderFetchTimer);
                const orderId = $(this).val().trim();
                
                if (orderId.length >= 3) {
                    orderFetchTimer = setTimeout(function() {
                        WarrantyManager.fetchOrderDetails(orderId);
                    }, 500);
                }
            });
            
            // Phone validation
            $('#phone_number').on('blur', function() {
                const phone = $(this).val().trim();
                const orderId = $('#order_id').val().trim();
                
                if (phone && orderId) {
                    WarrantyManager.validateOrderPhone(orderId, phone);
                }
            });
            
            // Search tab switching
            $('.search-tab').on('click', this.handleTabSwitch.bind(this));
            
            // Real-time field validation
            $('.form-control').on('blur', function() {
                WarrantyManager.validateField($(this));
            });
            
            // Clear errors on input
            $('.form-control').on('input', function() {
                WarrantyManager.clearFieldError($(this));
                WarrantyManager.trackFormInteraction('input', $(this).attr('name') || $(this).attr('id'));
            });
            
            // Format phone number as user types
            $('#search_phone, #phone_number').on('input', this.formatPhoneNumber);
            
            // Handle verification from URL
            this.handleVerificationFromURL();
            
            // Form focus tracking
            $('.form-control').on('focus', function() {
                const fieldName = $(this).attr('name') || $(this).attr('id');
                WarrantyManager.trackFormInteraction('focus', fieldName);
            });
            
            $('.form-control').on('blur', function() {
                const fieldName = $(this).attr('name') || $(this).attr('id');
                const hasValue = $(this).val().length > 0;
                WarrantyManager.trackFormInteraction('blur', fieldName, hasValue ? 'has_value' : 'empty');
            });
        },
        
        handleActivationSubmit: function(e) {
            e.preventDefault();
            
            const form = $(e.target);
            const submitBtn = form.find("button[type=submit]");
            const resultDiv = $("#warranty-activation-result");
            
            // Validate form first
            if (!this.validateActivationForm(form)) {
                this.showMessage(resultDiv, 'error', warranty_ajax.messages.required_field || 'Please fill in all required fields');
                return;
            }
            
            // Show loading state
            this.setButtonLoading(submitBtn, true);
            resultDiv.hide();
            
            const formData = {
                action: "warranty_activate",
                nonce: warranty_ajax.nonce,
                customer_name: $("#customer_name").val().trim(),
                order_id: $("#order_id").val().trim(),
                phone_number: $("#phone_number").val().trim(),
                product_name: $("#product_name").val().trim(),
                warranty_months: $("#warranty_months").val()
            };
            
            this.performanceMonitor.start('warranty_activation');
            
            $.ajax({
                url: warranty_ajax.ajax_url,
                type: "POST",
                data: formData,
                timeout: 30000,
                success: function(response) {
                    WarrantyManager.performanceMonitor.end('warranty_activation');
                    
                    if (response.success) {
                        WarrantyManager.showMessage(resultDiv, 'success', response.data);
                        form[0].reset();
                        WarrantyManager.clearAutoSavedData('warranty-activation-form');
                        WarrantyManager.trackEvent('warranty_activated', {
                            order_id: formData.order_id
                        });
                        WarrantyManager.showToast('Warranty activated successfully!', 'success');
                    } else {
                        WarrantyManager.showMessage(resultDiv, 'error', response.data);
                        WarrantyManager.showErrorSuggestions($('#order_id'), 'order_not_found');
                    }
                },
                error: function(xhr, status, error) {
                    WarrantyManager.performanceMonitor.end('warranty_activation');
                    WarrantyManager.handleAjaxError(xhr, status, error, resultDiv, 'WarrantyManager.retryActivation()');
                },
                complete: function() {
                    WarrantyManager.setButtonLoading(submitBtn, false);
                }
            });
        },
        
        handleCheckSubmit: function(e) {
            e.preventDefault();
            
            const form = $(e.target);
            const submitBtn = form.find("button[type=submit]");
            const resultDiv = $("#warranty-check-result");
            
            const orderId = $("#search_order_id").val().trim();
            const phone = $("#search_phone").val().trim();
            
            if (!orderId && !phone) {
                this.showMessage(resultDiv, 'error', 'Please provide either Order ID or Phone Number');
                return;
            }
            
            // Show loading state
            this.setButtonLoading(submitBtn, true);
            resultDiv.hide();
            
            const formData = {
                action: "warranty_check",
                nonce: warranty_ajax.nonce,
                search_order_id: orderId,
                search_phone: phone
            };
            
            this.performanceMonitor.start('warranty_check');
            
            $.ajax({
                url: warranty_ajax.ajax_url,
                type: "POST",
                data: formData,
                timeout: 30000,
                success: function(response) {
                    WarrantyManager.performanceMonitor.end('warranty_check');
                    
                    if (response.success) {
                        WarrantyManager.displayWarrantyResults(resultDiv, response.data);
                        WarrantyManager.trackEvent('warranty_checked', {
                            order_id: orderId || 'phone_search'
                        });
                    } else {
                        WarrantyManager.showMessage(resultDiv, 'error', response.data);
                        if (orderId) {
                            WarrantyManager.showErrorSuggestions($('#search_order_id'), 'order_not_found');
                        } else {
                            WarrantyManager.showErrorSuggestions($('#search_phone'), 'invalid_phone');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    WarrantyManager.performanceMonitor.end('warranty_check');
                    WarrantyManager.handleAjaxError(xhr, status, error, resultDiv, 'WarrantyManager.retryCheck()');
                },
                complete: function() {
                    WarrantyManager.setButtonLoading(submitBtn, false);
                }
            });
        },
        
        handleAdminActivate: function(e) {
            const warrantyId = $(e.target).data("id");
            const button = $(e.target);
            
            if (confirm(warranty_ajax.messages.confirm_activate || "Are you sure you want to activate this warranty?")) {
                button.prop("disabled", true).text("Activating...");
                
                $.ajax({
                    url: warranty_ajax.ajax_url,
                    type: "POST",
                    data: {
                        action: "admin_activate_warranty",
                        nonce: warranty_ajax.nonce,
                        warranty_id: warrantyId
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert("Error: " + response.data);
                            button.prop("disabled", false).text("Activate");
                        }
                    },
                    error: function() {
                        alert("Something went wrong. Please try again.");
                        button.prop("disabled", false).text("Activate");
                    }
                });
            }
        },
        
        handleAdminDelete: function(e) {
            const warrantyId = $(e.target).data("id");
            const button = $(e.target);
            
            if (confirm("Are you sure you want to delete this warranty? This action cannot be undone.")) {
                button.prop("disabled", true).text("Deleting...");
                
                $.ajax({
                    url: warranty_ajax.ajax_url,
                    type: "POST",
                    data: {
                        action: "admin_delete_warranty",
                        nonce: warranty_ajax.nonce,
                        warranty_id: warrantyId
                    },
                    success: function(response) {
                        if (response.success) {
                            button.closest('tr').fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert("Error: " + response.data);
                            button.prop("disabled", false).text("Delete");
                        }
                    },
                    error: function() {
                        alert("Something went wrong. Please try again.");
                        button.prop("disabled", false).text("Delete");
                    }
                });
            }
        },
        
        handleAdminEdit: function(e) {
            const warrantyId = $(e.target).data("id");
            // This would open a modal or redirect to edit page
            window.location.href = 'admin.php?page=warranty-edit&id=' + warrantyId;
        },
        
        handleTabSwitch: function(e) {
            const tabType = $(e.target).data('tab') || $(e.target).closest('.search-tab').data('tab');
            
            // Update active tab
            $('.search-tab').removeClass('active');
            $(e.target).closest('.search-tab').addClass('active');
            
            // Switch panels
            $('.search-panel').removeClass('active');
            $('#' + tabType + '-panel').addClass('active');
            
            // Clear the other input
            if (tabType === 'order') {
                $('#search_phone').val('');
                this.clearFieldError($('#search_phone'));
            } else {
                $('#search_order_id').val('');
                this.clearFieldError($('#search_order_id'));
            }
            
            // Focus on the active input
            setTimeout(function() {
                if (tabType === 'order') {
                    $('#search_order_id').focus();
                } else {
                    $('#search_phone').focus();
                }
            }, 100);
        },
        
        fetchOrderDetails: function(orderId) {
            if (!orderId || orderId.length < 3) return;
            
            $.ajax({
                url: warranty_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_order_details',
                    nonce: warranty_ajax.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        // Auto-fill customer information
                        if (data.customer_name && !$('#customer_name').val()) {
                            $('#customer_name').val(data.customer_name);
                        }
                        
                        if (data.phone_number && !$('#phone_number').val()) {
                            $('#phone_number').val(data.phone_number);
                        }
                        
                        // Auto-fill product if only one product
                        if (data.products && data.products.length === 1 && !$('#product_name').val()) {
                            $('#product_name').val(data.products[0].name);
                        }
                        
                        // Show success indicator
                        WarrantyManager.showFieldSuccess($('#order_id'), 'Order found');
                    }
                },
                error: function() {
                    WarrantyManager.showFieldError($('#order_id'), 'Order not found');
                    WarrantyManager.showErrorSuggestions($('#order_id'), 'order_not_found');
                }
            });
        },
        
        validateOrderPhone: function(orderId, phone) {
            if (!orderId || !phone) return;
            
            $.ajax({
                url: warranty_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'validate_order_phone',
                    nonce: warranty_ajax.nonce,
                    order_id: orderId,
                    phone_number: phone
                },
                success: function(response) {
                    if (response.success) {
                        WarrantyManager.showFieldSuccess($('#phone_number'), 'Phone verified');
                    } else {
                        WarrantyManager.showFieldError($('#phone_number'), response.data);
                        WarrantyManager.showErrorSuggestions($('#phone_number'), 'invalid_phone');
                    }
                },
                error: function() {
                    WarrantyManager.showFieldError($('#phone_number'), 'Verification failed');
                }
            });
        },
        
        validateField: function($field) {
            const value = $field.val().trim();
            const fieldName = $field.attr('name') || $field.attr('id');
            let result = { valid: true, message: '' };
            
            // Required field check
            if ($field.prop('required') && !value) {
                result = { valid: false, message: 'This field is required' };
            }
            // Phone validation with multiple patterns
            else if ((fieldName === 'phone_number' || fieldName === 'search_phone') && value) {
                const phoneValidation = this.validateWithPattern(value, this.validationPatterns.phone);
                if (!phoneValidation.valid) {
                    result = { valid: false, message: 'Please enter a valid phone number' };
                }
            }
            // Order ID validation
            else if ((fieldName === 'order_id' || fieldName === 'search_order_id') && value) {
                const orderValidation = this.validateWithPattern(value, this.validationPatterns.order_id);
                if (!orderValidation.valid) {
                    result = { valid: false, message: 'Please enter a valid order ID' };
                } else if (value.length < 3) {
                    result = { valid: false, message: 'Order ID must be at least 3 characters' };
                }
            }
            // Name validation
            else if (fieldName === 'customer_name' && value) {
                const nameValidation = this.validateWithPattern(value, this.validationPatterns.name);
                if (!nameValidation.valid) {
                    result = { valid: false, message: 'Please enter a valid name' };
                }
            }
            // Warranty months validation
            else if (fieldName === 'warranty_months' && value) {
                if (parseInt(value) <= 0) {
                    result = { valid: false, message: 'Please select a valid warranty period' };
                }
            }
            
            // Update field state
            if (!result.valid) {
                this.showFieldError($field, result.message);
            } else {
                this.clearFieldError($field);
            }
            
            return result.valid;
        },
        
        validateActivationForm: function(form) {
            let isValid = true;
            const requiredFields = ['customer_name', 'order_id', 'phone_number', 'warranty_months'];
            
            requiredFields.forEach(fieldName => {
                const $field = form.find('[name="' + fieldName + '"]');
                if (!this.validateField($field)) {
                    isValid = false;
                }
            });
            
            return isValid;
        },
        
        formatPhoneNumber: function(e) {
            let value = $(this).val().replace(/\D/g, '');
            
            // Basic formatting for common formats
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = value;
                } else if (value.length <= 6) {
                    value = value.substring(0, 3) + '-' + value.substring(3);
                } else if (value.length <= 10) {
                    value = value.substring(0, 3) + '-' + value.substring(3, 6) + '-' + value.substring(6);
                } else {
                    // Handle longer numbers (international)
                    value = value.substring(0, 15); // Limit length
                }
                $(this).val(value);
            }
        },
        
        showFieldError: function($field, message) {
            $field.addClass('field-error-state');
            $field.siblings('.field-error').text(message).show();
            
            // Add shake animation
            $field.addClass('shake');
            setTimeout(() => $field.removeClass('shake'), 500);
        },
        
        showFieldSuccess: function($field, message) {
            $field.removeClass('field-error-state').addClass('field-success-state');
            const $errorDiv = $field.siblings('.field-error');
            
            $errorDiv.removeClass('field-error')
                    .addClass('field-success')
                    .text(message)
                    .show();
            
            setTimeout(function() {
                $field.removeClass('field-success-state');
                $errorDiv.hide().removeClass('field-success').addClass('field-error');
            }, 3000);
        },
        
        clearFieldError: function($field) {
            $field.removeClass('field-error-state field-success-state');
            $field.siblings('.field-error').hide();
        },
        
        setButtonLoading: function(button, loading) {
            const $btn = $(button);
            const $btnText = $btn.find('.btn-text');
            const $btnLoader = $btn.find('.btn-loader');
            
            if (loading) {
                $btn.prop('disabled', true).addClass('loading');
                $btnText.hide();
                $btnLoader.show();
            } else {
                $btn.prop('disabled', false).removeClass('loading');
                $btnText.show();
                $btnLoader.hide();
            }
        },
        
        showMessage: function(container, type, message) {
            const $container = $(container);
            let $content = $container.find('.warranty-result-content');
            
            if ($content.length === 0) {
                $container.html('<div class="warranty-result-content"></div>');
                $content = $container.find('.warranty-result-content');
            }
            
            $container.removeClass('success error warning info')
                     .addClass(type);
            
            $content.html('<strong>' + this.getMessageTitle(type) + '</strong> ' + message);
            
            $container.fadeIn(300);
            
            // Auto-scroll to result
            setTimeout(() => {
                $container[0].scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }, 100);
        },
        
        getMessageTitle: function(type) {
            switch(type) {
                case 'success': return 'Success!';
                case 'error': return 'Error!';
                case 'warning': return 'Warning!';
                case 'info': return 'Info:';
                default: return '';
            }
        },
        
        displayWarrantyResults: function(container, data) {
            const $container = $(container);
            let html = '<strong>Warranty Information Found!</strong>';
            
            // Status message
            if (data.status_message) {
                html += '<div class="warranty-status-message warranty-status-' + data.status_class + '">';
                html += '<div class="status-icon">' + this.getStatusIcon(data.status_icon || 'check-circle') + '</div>';
                html += '<span>' + data.status_message + '</span>';
                html += '</div>';
            }
            
            // Warranty details
            html += '<div class="warranty-details">';
            
            const details = [
                ['Customer Name', data.customer_name],
                ['Order ID', '#' + data.order_id],
                ['Phone Number', data.phone_number],
                ['Product', data.product_name],
                ['Purchase Date', data.purchase_date],
                ['Warranty Period', data.total_warranty || (data.warranty_months + ' months')],
            ];
            
            if (data.activation_date) {
                details.push(['Activation Date', data.activation_date]);
            }
            
            if (data.expiry_date) {
                details.push(['Expiry Date', data.expiry_date]);
            }
            
            if (data.warranty_remaining) {
                details.push(['Remaining', data.warranty_remaining]);
            }
            
            details.forEach(([label, value]) => {
                if (value) {
                    html += '<div class="warranty-detail-item">';
                    html += '<span class="warranty-detail-label">' + label + ':</span>';
                    html += '<span class="warranty-detail-value">' + value + '</span>';
                    html += '</div>';
                }
            });
            
            html += '</div>';
            
            // Certificate for active warranties
            if (data.certificate_html) {
                html += data.certificate_html;
            } else if (data.status === 'active') {
                html += '<div class="warranty-certificate-placeholder">';
                html += '<p><strong>Warranty Certificate Available</strong></p>';
                html += '<p>Your warranty is active and you can print or download your warranty certificate.</p>';
                html += '</div>';
            }
            
            $container.removeClass('error warning info').addClass('success');
            $container.find('.warranty-result-content').html(html);
            $container.fadeIn(300);
            
            // Auto-scroll to result
            setTimeout(() => {
                $container[0].scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }, 100);
        },
        
        getStatusIcon: function(iconName) {
            const icons = {
                'check-circle': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22,4 12,14.01 9,11.01"></polyline></svg>',
                'alert-triangle': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
                'alert-circle': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
                'x-circle': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
                'clock': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12,6 12,12 16,14"></polyline></svg>',
                'help-circle': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
            };
            
            return icons[iconName] || icons['help-circle'];
        },
        
        initFormValidation: function() {
            // Add CSS for validation animations
            if (!$('#warranty-validation-styles').length) {
                $('<style id="warranty-validation-styles">')
                    .text(`
                        .shake {
                            animation: shake 0.5s ease-in-out;
                        }
                        @keyframes shake {
                            0%, 100% { transform: translateX(0); }
                            25% { transform: translateX(-5px); }
                            75% { transform: translateX(5px); }
                        }
                        .warranty-status-message {
                            display: flex;
                            align-items: center;
                            gap: 12px;
                            padding: 16px;
                            border-radius: 8px;
                            margin: 16px 0;
                            font-weight: 500;
                        }
                        .warranty-status-active {
                            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
                            color: #166534;
                            border: 1px solid #86efac;
                        }
                        .warranty-status-pending {
                            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
                            color: #92400e;
                            border: 1px solid #fbbf24;
                        }
                        .warranty-status-expired {
                            background: linear-gradient(135deg, #fed7d7 0%, #fca5a5 100%);
                            color: #991b1b;
                            border: 1px solid #f87171;
                        }
                        .warranty-status-expiring {
                            background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
                            color: #c2410c;
                            border: 1px solid #fb923c;
                        }
                        .status-icon {
                            flex-shrink: 0;
                        }
                        .warranty-certificate-placeholder {
                            margin-top: 20px;
                            padding: 20px;
                            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
                            border: 1px solid #7dd3fc;
                            border-radius: 8px;
                            text-align: center;
                        }
                        .warranty-restore-notification {
                            background: linear-gradient(135deg, #fff7ed 0%, #fed7aa 100%);
                            border: 1px solid #fb923c;
                            border-radius: 8px;
                            padding: 16px;
                            margin-bottom: 20px;
                            text-align: center;
                        }
                        .warranty-field-suggestions {
                            background: #f0f9ff;
                            border: 1px solid #7dd3fc;
                            border-radius: 4px;
                            padding: 12px;
                            margin-top: 8px;
                            font-size: 13px;
                        }
                        .warranty-field-suggestions ul {
                            margin: 8px 0 0 0;
                            padding-left: 20px;
                        }
                        .warranty-field-suggestions li {
                            margin-bottom: 4px;
                        }
                        .warranty-toast {
                            position: fixed;
                            top: 20px;
                            right: 20px;
                            background: white;
                            border-radius: 8px;
                            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
                            padding: 16px 20px;
                            border-left: 4px solid #667eea;
                            z-index: 10000;
                            transform: translateX(100%);
                            transition: transform 0.3s ease;
                            max-width: 350px;
                        }
                        .warranty-toast.show {
                            transform: translateX(0);
                        }
                        .warranty-toast-success {
                            border-left-color: #48bb78;
                        }
                        .warranty-toast-error {
                            border-left-color: #f56565;
                        }
                        .warranty-toast-warning {
                            border-left-color: #ed8936;
                        }
                        .warranty-offline {
                            opacity: 0.6;
                            pointer-events: none;
                        }
                        .warranty-offline-notice {
                            position: fixed;
                            top: 10px;
                            left: 50%;
                            transform: translateX(-50%);
                            z-index: 10001;
                            min-width: 300px;
                            text-align: center;
                        }
                    `)
                    .appendTo('head');
            }
        },
        
        handleURLParams: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const verifyCode = urlParams.get('verify');
            
            if (verifyCode && $('#warranty-check-form').length) {
                // Auto-verify warranty from QR code or link
                this.verifyWarrantyFromURL(verifyCode);
            }
            
            // Auto-fill form from URL parameters
            if (urlParams.get('order_id')) {
                $('#order_id, #search_order_id').val(urlParams.get('order_id'));
            }
            
            if (urlParams.get('phone')) {
                $('#phone_number, #search_phone').val(urlParams.get('phone'));
            }
        },
        
        verifyWarrantyFromURL: function(verifyCode) {
            const resultDiv = $("#warranty-check-result");
            
            $.ajax({
                url: warranty_ajax.ajax_url,
                type: "POST",
                data: {
                    action: "warranty_verify_code",
                    nonce: warranty_ajax.nonce,
                    verify_code: verifyCode
                },
                success: function(response) {
                    if (response.success) {
                        WarrantyManager.displayWarrantyResults(resultDiv, response.data);
                    } else {
                        WarrantyManager.showMessage(resultDiv, 'error', response.data);
                    }
                },
                error: function() {
                    WarrantyManager.showMessage(resultDiv, 'error', 'Invalid verification code');
                }
            });
        },
        
        initTooltips: function() {
            // Simple tooltip implementation
            $('[data-tooltip]').on('mouseenter', function() {
                const tooltip = $(this).data('tooltip');
                const $tooltip = $('<div class="warranty-tooltip">' + tooltip + '</div>');
                
                $('body').append($tooltip);
                
                const rect = this.getBoundingClientRect();
                $tooltip.css({
                    position: 'absolute',
                    top: rect.top - $tooltip.outerHeight() - 5,
                    left: rect.left + (rect.width / 2) - ($tooltip.outerWidth() / 2),
                    zIndex: 9999,
                    background: '#333',
                    color: '#fff',
                    padding: '5px 10px',
                    borderRadius: '4px',
                    fontSize: '12px',
                    whiteSpace: 'nowrap',
                    opacity: 0
                }).animate({ opacity: 1 }, 200);
                
            }).on('mouseleave', function() {
                $('.warranty-tooltip').fadeOut(200, function() {
                    $(this).remove();
                });
            });
        },
        
        handleVerificationFromURL: function() {
            // Check if we're on warranty check page with verification parameter
            const urlParams = new URLSearchParams(window.location.search);
            const verifyParam = urlParams.get('verify');
            
            if (verifyParam && $('#warranty-check-form').length) {
                // Automatically trigger verification
                setTimeout(() => {
                    this.verifyWarrantyFromURL(verifyParam);
                }, 500);
            }
        },
        
        trackEvent: function(eventName, properties = {}) {
            // Basic event tracking - can be enhanced with Google Analytics, etc.
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, {
                    event_category: 'warranty_manager',
                    ...properties
                });
            }
            
            // Console log for debugging
            console.log('Warranty Manager Event:', eventName, properties);
        },
        
        trackFormInteraction: function(action, field, value) {
            const eventData = {
                action: action,
                field: field,
                timestamp: Date.now(),
                page: window.location.pathname
            };
            
            // Don't track sensitive data
            if (!['phone_number', 'customer_name'].includes(field)) {
                eventData.value = value;
            }
            
            this.trackEvent('form_interaction', eventData);
        },
        
        // Enhanced error handling with retry functionality
        handleAjaxError: function(xhr, status, error, $resultDiv, retryCallback) {
            let errorMessage = warranty_ajax.messages.error || 'Something went wrong. Please try again.';
            let showRetry = false;
            
            if (status === 'timeout') {
                errorMessage = 'Request timed out. Please check your connection and try again.';
                showRetry = true;
            } else if (status === 'error') {
                if (xhr.status === 0) {
                    errorMessage = 'No internet connection. Please check your network and try again.';
                    showRetry = true;
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Please try again in a moment.';
                    showRetry = true;
                } else if (xhr.status === 403) {
                    errorMessage = 'Permission denied. Please refresh the page and try again.';
                } else if (xhr.responseText) {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        if (errorData.data) {
                            errorMessage = errorData.data;
                        }
                    } catch (e) {
                        // Use default message
                    }
                }
            }
            
            let messageHtml = errorMessage;
            if (showRetry && retryCallback) {
                messageHtml += '<br><br><button type="button" class="warranty-btn warranty-btn-secondary warranty-retry-btn" onclick="' + retryCallback + '">Retry</button>';
            }
            
            this.showMessage($resultDiv, 'error', messageHtml);
        },
        
        // Validation patterns for different input types
        validationPatterns: {
            phone: {
                us: /^[\+]?1?[-.\s]?\(?([0-9]{3})\)?[-.\s]?([0-9]{3})[-.\s]?([0-9]{4})$/,
                international: /^[\+]?[1-9]\d{1,14}$/,
                general: /^[\+]?[0-9\s\-\(\)]{10,}$/
            },
            
            order_id: {
                numeric: /^\d+$/,
                alphanumeric: /^[a-zA-Z0-9]+$/,
                woocommerce: /^\d{4,}$/
            },
            
            name: {
                basic: /^[a-zA-Z\s'-]{2,}$/,
                international: /^[\p{L}\s'-]{2,}$/u
            }
        },
        
        validateWithPattern: function(value, patterns) {
            for (let key in patterns) {
                if (patterns[key].test(value)) {
                    return { valid: true, pattern: key };
                }
            }
            return { valid: false, pattern: null };
        },
        
        // Error recovery suggestions
        showErrorSuggestions: function($field, errorType) {
            const fieldName = $field.attr('name') || $field.attr('id');
            let suggestions = [];
            
            switch (errorType) {
                case 'invalid_phone':
                    suggestions = [
                        'Include your country code (e.g., +1 for US)',
                        'Use only numbers, spaces, and dashes',
                        'Example: +1 (555) 123-4567'
                    ];
                    break;
                    
                case 'invalid_order':
                    suggestions = [
                        'Check your order confirmation email',
                        'Order IDs are usually 4-8 digits',
                        'Don\'t include # symbol'
                    ];
                    break;
                    
                case 'order_not_found':
                    suggestions = [
                        'Verify the order number is correct',
                        'Make sure the order was completed',
                        'Check if you\'re using the right account'
                    ];
                    break;
            }
            
            if (suggestions.length > 0) {
                const suggestionHtml = '<div class="warranty-field-suggestions">' +
                    '<strong>Suggestions:</strong>' +
                    '<ul>' + suggestions.map(s => '<li>' + s + '</li>').join('') + '</ul>' +
                    '</div>';
                
                $field.siblings('.warranty-field-suggestions').remove();
                $field.after(suggestionHtml);
                
                setTimeout(function() {
                    $('.warranty-field-suggestions').fadeOut(5000, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },
        
        // Performance monitoring
        performanceMonitor: {
            startTime: null,
            
            start: function(operation) {
                this.startTime = performance.now();
                console.log('Starting:', operation);
            },
            
            end: function(operation) {
                if (this.startTime) {
                    const duration = performance.now() - this.startTime;
                    console.log(operation + ' completed in:', duration.toFixed(2) + 'ms');
                    
                    // Track slow operations
                    if (duration > 3000) {
                        console.warn('Slow operation detected:', operation, duration + 'ms');
                    }
                }
            }
        },
        
        // Auto-save functionality
        initAutoSave: function() {
            let autoSaveInterval;
            
            $('.warranty-form').on('input', function() {
                const $form = $(this);
                const formId = $form.attr('id') || 'warranty_form';
                
                clearTimeout(autoSaveInterval);
                autoSaveInterval = setTimeout(function() {
                    WarrantyManager.saveFormData(formId, $form);
                }, 2000); // Save after 2 seconds of inactivity
            });
            
            // Restore auto-saved data on page load
            $('.warranty-form').each(function() {
                const $form = $(this);
                const formId = $form.attr('id') || 'warranty_form';
                const savedData = WarrantyManager.loadAutoSavedData(formId);
                
                if (savedData) {
                    WarrantyManager.showRestoreNotification(formId, $form);
                }
            });
        },
        
        saveFormData: function(formId, $form) {
            if (typeof(Storage) === "undefined") return;
            
            const formData = {};
            $form.find('input, select, textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                if (name && $field.val()) {
                    formData[name] = $field.val();
                }
            });
            
            if (Object.keys(formData).length > 0) {
                localStorage.setItem('warranty_form_' + formId, JSON.stringify({
                    data: formData,
                    timestamp: Date.now()
                }));
            }
        },
        
        loadAutoSavedData: function(formId) {
            if (typeof(Storage) === "undefined") return null;
            
            const saved = localStorage.getItem('warranty_form_' + formId);
            if (saved) {
                try {
                    const parsed = JSON.parse(saved);
                    // Only restore if less than 1 hour old
                    if (Date.now() - parsed.timestamp < 3600000) {
                        return parsed.data;
                    }
                } catch (e) {
                    console.log('Error parsing saved form data:', e);
                }
            }
            return null;
        },
        
        clearAutoSavedData: function(formId) {
            if (typeof(Storage) !== "undefined") {
                localStorage.removeItem('warranty_form_' + formId);
            }
        },
        
        showRestoreNotification: function(formId, $form) {
            const restoreHtml = '<div class="warranty-restore-notification">' +
                '<p>We found some previously entered information. Would you like to restore it?</p>' +
                '<button type="button" class="warranty-btn warranty-btn-primary" onclick="WarrantyManager.restoreFormData(\'' + formId + '\')">Restore</button> ' +
                '<button type="button" class="warranty-btn warranty-btn-secondary" onclick="WarrantyManager.dismissRestore(\'' + formId + '\')">Dismiss</button>' +
                '</div>';
            
            $form.prepend(restoreHtml);
        },
        
        restoreFormData: function(formId) {
            const savedData = this.loadAutoSavedData(formId);
            if (savedData) {
                const $form = $('#' + formId);
                
                Object.keys(savedData).forEach(function(name) {
                    const $field = $form.find('[name="' + name + '"]');
                    if ($field.length) {
                        $field.val(savedData[name]).trigger('input');
                    }
                });
                
                $('.warranty-restore-notification').fadeOut(300, function() {
                    $(this).remove();
                });
                
                this.showToast('Form data restored successfully!', 'success');
            }
        },
        
        dismissRestore: function(formId) {
            this.clearAutoSavedData(formId);
            $('.warranty-restore-notification').fadeOut(300, function() {
                $(this).remove();
            });
        },
        
        showToast: function(message, type) {
            type = type || 'info';
            
            const toast = $('<div class="warranty-toast warranty-toast-' + type + '">' + message + '</div>');
            $('body').append(toast);
            
            setTimeout(function() {
                toast.addClass('show');
            }, 100);
            
            setTimeout(function() {
                toast.removeClass('show');
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }, 3000);
        },
        
        // Connection status monitoring
        monitorConnection: function() {
            function updateOnlineStatus() {
                const $forms = $('.warranty-form');
                
                if (navigator.onLine) {
                    $forms.removeClass('warranty-offline');
                    $('.warranty-offline-notice').remove();
                } else {
                    $forms.addClass('warranty-offline');
                    if (!$('.warranty-offline-notice').length) {
                        const offlineNotice = '<div class="warranty-offline-notice warranty-result error">' +
                            '<strong>No Internet Connection</strong> Please check your connection and try again.' +
                            '</div>';
                        $forms.before(offlineNotice);
                    }
                }
            }
            
            window.addEventListener('online', updateOnlineStatus);
            window.addEventListener('offline', updateOnlineStatus);
            
            // Initial check
            updateOnlineStatus();
        },
        
        // Accessibility enhancements
        enhanceAccessibility: function() {
            // Add ARIA labels to form controls
            $('.form-control').each(function() {
                const $field = $(this);
                const $label = $field.siblings('label').first();
                
                if ($label.length && !$field.attr('aria-labelledby')) {
                    const labelId = 'label_' + Math.random().toString(36).substr(2, 9);
                    $label.attr('id', labelId);
                    $field.attr('aria-labelledby', labelId);
                }
                
                // Add aria-describedby for help text
                const $help = $field.siblings('.field-help').first();
                if ($help.length) {
                    const helpId = 'help_' + Math.random().toString(36).substr(2, 9);
                    $help.attr('id', helpId);
                    $field.attr('aria-describedby', helpId);
                }
            });
            
            // Add role and aria-live to result containers
            $('.warranty-result').attr({
                'role': 'alert',
                'aria-live': 'polite'
            });
            
            // Add main landmark
            $('.warranty-form').attr('role', 'main').attr('id', 'warranty-main-form');
            $('.warranty-result').attr('id', 'warranty-results');
        },
        
        // Keyboard navigation enhancements
        enhanceKeyboardNavigation: function() {
            // Tab navigation for search tabs
            $('.search-tab').on('keydown', function(e) {
                const $tabs = $('.search-tab');
                const currentIndex = $tabs.index(this);
                let newIndex = currentIndex;
                
                switch(e.key) {
                    case 'ArrowLeft':
                        newIndex = currentIndex > 0 ? currentIndex - 1 : $tabs.length - 1;
                        break;
                    case 'ArrowRight':
                        newIndex = currentIndex < $tabs.length - 1 ? currentIndex + 1 : 0;
                        break;
                    case 'Home':
                        newIndex = 0;
                        break;
                    case 'End':
                        newIndex = $tabs.length - 1;
                        break;
                    default:
                        return;
                }
                
                e.preventDefault();
                $tabs.eq(newIndex).focus().click();
            });
            
            // Enter key support for custom elements
            $('.warranty-btn, .search-tab').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    $(this).click();
                }
            });
        },
        
        // Utility functions
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },
        
        formatCurrency: function(amount, currency = 'USD') {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency
            }).format(amount);
        },
        
        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        },
        
        // Retry functions for failed requests
        retryActivation: function() {
            $('#warranty-activation-form').trigger('submit');
        },
        
        retryCheck: function() {
            $('#warranty-check-form').trigger('submit');
        }
    };
    
    // Global functions for certificate actions
    window.warranty_download_pdf = function() {
        const certificateContent = document.querySelector('.warranty-certificate');
        if (!certificateContent) return;
        
        const printWindow = window.open('', '_blank');
        const styles = Array.from(document.styleSheets)
            .map(sheet => {
                try {
                    return Array.from(sheet.cssRules)
                        .map(rule => rule.cssText)
                        .join('\n');
                } catch (e) {
                    return '';
                }
            })
            .join('\n');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Warranty Certificate</title>
                <meta charset="utf-8">
                <style>
                    ${styles}
                    body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
                    .warranty-certificate { border: 2px solid #000; page-break-inside: avoid; }
                    .warranty-certificate-actions { display: none !important; }
                    @media print {
                        body { margin: 0; padding: 0; }
                        .warranty-certificate { border: 2px solid #000; margin: 0; }
                    }
                </style>
            </head>
            <body>
                ${certificateContent.outerHTML}
                <script>
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                            window.close();
                        }, 250);
                    };
                </script>
            </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.focus();
    };
    
    // Initialize when DOM is ready
    WarrantyManager.init();
    
    // Make WarrantyManager globally available
    window.WarrantyManager = WarrantyManager;
    
    // Auto-focus on first input after short delay
    setTimeout(function() {
        const $firstInput = $('.warranty-form .form-control:visible:first');
        if ($firstInput.length && !$firstInput.val()) {
            $firstInput.focus();
        }
    }, 500);
    
    // Clear search inputs when switching between order/phone search
    $('#search_order_id, #search_phone').on('input', function() {
        const currentInput = $(this);
        const otherInput = currentInput.attr('id') === 'search_order_id' ? $('#search_phone') : $('#search_order_id');
        
        if (currentInput.val().trim()) {
            otherInput.val('');
            WarrantyManager.clearFieldError(otherInput);
        }
    });
    
    // Enhanced form input validation
    $('.form-control').on('input', function() {
        const $field = $(this);
        const fieldName = $field.attr('name') || $field.attr('id');
        
        // Clear previous states
        WarrantyManager.clearFieldError($field);
        
        // Real-time validation for specific fields
        if (fieldName === 'phone_number' || fieldName === 'search_phone') {
            WarrantyManager.debounce(function() {
                WarrantyManager.validateField($field);
            }, 500)();
        }
        
        if (fieldName === 'order_id' || fieldName === 'search_order_id') {
            const value = $field.val().trim();
            if (value.length >= 3) {
                WarrantyManager.debounce(function() {
                    WarrantyManager.validateField($field);
                }, 300)();
            }
        }
        
        if (fieldName === 'customer_name') {
            if ($field.val().length >= 2) {
                WarrantyManager.debounce(function() {
                    WarrantyManager.validateField($field);
                }, 500)();
            }
        }
    });
    
    // Handle form reset
    $('.warranty-form').on('reset', function() {
        $(this).find('.field-error').hide();
        $(this).find('.form-control').removeClass('field-error-state field-success-state');
        $(this).find('.warranty-result').hide();
        
        // Clear auto-saved data
        const formId = $(this).attr('id') || 'warranty_form';
        WarrantyManager.clearAutoSavedData(formId);
    });
    
    // Prevent double submission
    $('.warranty-form').on('submit', function() {
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        
        if ($submitBtn.prop('disabled')) {
            return false;
        }
    });
    
    // Handle browser back button
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            // Reset form states when coming back via browser history
            $('.warranty-form')[0]?.reset();
            $('.warranty-result').hide();
            $('.form-control').removeClass('field-error-state field-success-state');
            $('.field-error').hide();
        }
    });
    
    // Enhanced error handling for network issues
    $(document).ajaxError(function(event, xhr, settings, thrownError) {
        if (xhr.status === 0 && xhr.readyState === 0) {
            // Network error
            WarrantyManager.showToast('Network connection lost. Please check your internet connection.', 'error');
        }
    });
    
    // Page visibility change handling
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            // Page became visible, check connection
            if (!navigator.onLine) {
                WarrantyManager.showToast('You are currently offline. Some features may not work.', 'warning');
            }
        }
    });
    
    // Initialize smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        
        const target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 20
            }, 500);
        }
    });
    
    console.log('Warranty Manager JavaScript fully loaded and initialized');
});