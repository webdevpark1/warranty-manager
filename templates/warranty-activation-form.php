<?php
/**
 * Warranty Activation Form Template
 * 
 * Path: /wp-content/plugins/woocommerce-warranty-manager/templates/warranty-activation-form.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$frontend = new WM_Frontend();
?>

<div class="warranty-activation-container">
    <div class="warranty-form-wrapper">
        <?php if ($atts['show_title'] === 'yes'): ?>
            <h2 class="warranty-title"><?php echo esc_html($atts['title']); ?></h2>
            <p class="warranty-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
        <?php endif; ?>
        
        <form id="warranty-activation-form" class="warranty-form" method="post">
            <div class="warranty-form-section">
                <h3 class="warranty-section-title"><?php _e('Customer Information', 'warranty-manager'); ?></h3>
                
                <div class="warranty-form-row">
                    <div class="form-group">
                        <label for="customer_name">
                            <?php _e('Customer Name', 'warranty-manager'); ?> 
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="customer_name" 
                               name="customer_name" 
                               class="form-control" 
                               placeholder="<?php esc_attr_e('Enter your full name', 'warranty-manager'); ?>"
                               required>
                        <div class="field-error" id="customer_name_error"></div>
                    </div>
                </div>
                
                <div class="warranty-form-row">
                    <div class="form-group form-group-half">
                        <label for="order_id">
                            <?php _e('Order ID', 'warranty-manager'); ?> 
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="order_id" 
                               name="order_id" 
                               class="form-control" 
                               placeholder="<?php esc_attr_e('e.g., 12345', 'warranty-manager'); ?>"
                               required>
                        <div class="field-error" id="order_id_error"></div>
                        <small class="field-help"><?php _e('Found in your order confirmation email', 'warranty-manager'); ?></small>
                    </div>
                    
                    <div class="form-group form-group-half">
                        <label for="phone_number">
                            <?php _e('Phone Number', 'warranty-manager'); ?> 
                            <span class="required">*</span>
                        </label>
                        <input type="tel" 
                               id="phone_number" 
                               name="phone_number" 
                               class="form-control" 
                               placeholder="<?php esc_attr_e('Enter phone number', 'warranty-manager'); ?>"
                               required>
                        <div class="field-error" id="phone_number_error"></div>
                        <small class="field-help"><?php _e('Must match your order details', 'warranty-manager'); ?></small>
                    </div>
                </div>
            </div>
            
            <div class="warranty-form-section">
                <h3 class="warranty-section-title"><?php _e('Product Information', 'warranty-manager'); ?></h3>
                
                <div class="warranty-form-row">
                    <div class="form-group">
                        <label for="product_name">
                            <?php _e('Product Name', 'warranty-manager'); ?> 
                            <span class="optional">(<?php _e('Optional', 'warranty-manager'); ?>)</span>
                        </label>
                        <input type="text" 
                               id="product_name" 
                               name="product_name" 
                               class="form-control" 
                               placeholder="<?php esc_attr_e('Enter product name or leave empty', 'warranty-manager'); ?>">
                        <div class="field-error" id="product_name_error"></div>
                        <small class="field-help"><?php _e('Leave empty if you purchased multiple items', 'warranty-manager'); ?></small>
                    </div>
                </div>
                
                <div class="warranty-form-row">
                    <div class="form-group">
                        <label for="warranty_months">
                            <?php _e('Warranty Period', 'warranty-manager'); ?> 
                            <span class="required">*</span>
                        </label>
                        <select id="warranty_months" 
                                name="warranty_months" 
                                class="form-control" 
                                required>
                            <option value=""><?php _e('Select warranty period', 'warranty-manager'); ?></option>
                            <?php echo $frontend->get_warranty_options(); ?>
                        </select>
                        <div class="field-error" id="warranty_months_error"></div>
                        <small class="field-help"><?php _e('Select the warranty period for your product', 'warranty-manager'); ?></small>
                    </div>
                </div>
            </div>
            
            <div class="warranty-form-actions">
                <button type="submit" class="warranty-btn warranty-btn-primary" id="submit-warranty">
                    <span class="btn-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20,6 9,17 4,12"></polyline>
                        </svg>
                    </span>
                    <span class="btn-text"><?php _e('Activate Warranty', 'warranty-manager'); ?></span>
                    <span class="btn-loader" style="display: none;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 12a9 9 0 11-6.219-8.56"/>
                        </svg>
                        <?php _e('Processing...', 'warranty-manager'); ?>
                    </span>
                </button>
            </div>
            
            <div class="warranty-form-info">
                <div class="warranty-info-box">
                    <h4><?php _e('Important Information:', 'warranty-manager'); ?></h4>
                    <ul>
                        <li><?php _e('Your Order ID and Phone Number must match your original order details', 'warranty-manager'); ?></li>
                        <li><?php _e('Warranty activation may require manual approval', 'warranty-manager'); ?></li>
                        <li><?php _e('You will receive a confirmation email once your warranty is activated', 'warranty-manager'); ?></li>
                        <li><?php _e('Keep your warranty certificate safe for future reference', 'warranty-manager'); ?></li>
                    </ul>
                </div>
            </div>
        </form>
        
        <div id="warranty-activation-result" class="warranty-result" style="display: none;">
            <div class="warranty-result-content"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Auto-fetch order details when order ID is entered
    let orderFetchTimer;
    $('#order_id').on('input', function() {
        clearTimeout(orderFetchTimer);
        const orderId = $(this).val().trim();
        
        if (orderId.length >= 3) {
            orderFetchTimer = setTimeout(function() {
                fetchOrderDetails(orderId);
            }, 500);
        }
    });
    
    // Validate phone number format
    $('#phone_number').on('blur', function() {
        const phone = $(this).val().trim();
        const orderId = $('#order_id').val().trim();
        
        if (phone && orderId) {
            validateOrderPhone(orderId, phone);
        }
    });
    
    // Real-time validation
    $('.form-control').on('blur', function() {
        validateField($(this));
    });
    
    // Clear errors on input
    $('.form-control').on('input', function() {
        clearFieldError($(this));
    });
    
    function fetchOrderDetails(orderId) {
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
                    $('#customer_name').val(data.customer_name);
                    $('#phone_number').val(data.phone_number);
                    
                    // Show products if available
                    if (data.products && data.products.length === 1) {
                        $('#product_name').val(data.products[0].name);
                    }
                }
            }
        });
    }
    
    function validateOrderPhone(orderId, phone) {
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
                    showFieldSuccess($('#phone_number'), warranty_ajax.messages.valid_order_phone || 'Order and phone number match');
                } else {
                    showFieldError($('#phone_number'), response.data);
                }
            }
        });
    }
    
    function validateField($field) {
        const value = $field.val().trim();
        const fieldName = $field.attr('name');
        let isValid = true;
        let errorMessage = '';
        
        if ($field.prop('required') && !value) {
            isValid = false;
            errorMessage = warranty_ajax.messages.required_field || 'This field is required';
        } else if (fieldName === 'phone_number' && value) {
            // Basic phone validation
            const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
            if (!phoneRegex.test(value.replace(/[\s\-\(\)]/g, ''))) {
                isValid = false;
                errorMessage = warranty_ajax.messages.invalid_phone || 'Please enter a valid phone number';
            }
        } else if (fieldName === 'warranty_months' && value) {
            if (parseInt(value) <= 0) {
                isValid = false;
                errorMessage = 'Please select a valid warranty period';
            }
        }
        
        if (!isValid) {
            showFieldError($field, errorMessage);
        } else {
            clearFieldError($field);
        }
        
        return isValid;
    }
    
    function showFieldError($field, message) {
        $field.addClass('field-error-state');
        $field.siblings('.field-error').text(message).show();
    }
    
    function showFieldSuccess($field, message) {
        $field.removeClass('field-error-state').addClass('field-success-state');
        $field.siblings('.field-error').removeClass('field-error').addClass('field-success').text(message).show();
        
        setTimeout(function() {
            $field.removeClass('field-success-state');
            $field.siblings('.field-success').hide().removeClass('field-success').addClass('field-error');
        }, 3000);
    }
    
    function clearFieldError($field) {
        $field.removeClass('field-error-state');
        $field.siblings('.field-error').hide();
    }
});
</script>