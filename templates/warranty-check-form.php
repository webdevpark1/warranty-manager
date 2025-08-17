<?php
/**
 * Warranty Check Form Template
 * 
 * Path: /wp-content/plugins/woocommerce-warranty-manager/templates/warranty-check-form.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="warranty-check-container">
    <div class="warranty-form-wrapper">
        <?php if ($atts['show_title'] === 'yes'): ?>
            <h2 class="warranty-title"><?php echo esc_html($atts['title']); ?></h2>
            <p class="warranty-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
        <?php endif; ?>
        
        <form id="warranty-check-form" class="warranty-form" method="post">
            <div class="warranty-search-options">
                <div class="search-option-tabs">
                    <button type="button" class="search-tab active" data-tab="order">
                        <span class="tab-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
                        </span>
                        <?php _e('Search by Order ID', 'warranty-manager'); ?>
                    </button>
                    <button type="button" class="search-tab" data-tab="phone">
                        <span class="tab-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                        </span>
                        <?php _e('Search by Phone', 'warranty-manager'); ?>
                    </button>
                </div>
                
                <div class="search-content">
                    <div class="search-panel active" id="order-panel">
                        <div class="form-group">
                            <label for="search_order_id">
                                <?php _e('Order ID', 'warranty-manager'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="search_order_id" 
                                   name="search_order_id" 
                                   class="form-control form-control-large" 
                                   placeholder="<?php esc_attr_e('Enter your order ID (e.g., 12345)', 'warranty-manager'); ?>">
                            <div class="field-error" id="search_order_id_error"></div>
                            <small class="field-help">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M9,12l2,2 4,-4"></path>
                                </svg>
                                <?php _e('You can find your order ID in your order confirmation email', 'warranty-manager'); ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="search-panel" id="phone-panel">
                        <div class="form-group">
                            <label for="search_phone">
                                <?php _e('Phone Number', 'warranty-manager'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="tel" 
                                   id="search_phone" 
                                   name="search_phone" 
                                   class="form-control form-control-large" 
                                   placeholder="<?php esc_attr_e('Enter your phone number', 'warranty-manager'); ?>">
                            <div class="field-error" id="search_phone_error"></div>
                            <small class="field-help">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M9,12l2,2 4,-4"></path>
                                </svg>
                                <?php _e('Use the same phone number from your order', 'warranty-manager'); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="warranty-form-actions">
                <button type="submit" class="warranty-btn warranty-btn-primary" id="check-warranty">
                    <span class="btn-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                    </span>
                    <span class="btn-text"><?php _e('Check Warranty', 'warranty-manager'); ?></span>
                    <span class="btn-loader" style="display: none;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 12a9 9 0 11-6.219-8.56"/>
                        </svg>
                        <?php _e('Checking...', 'warranty-manager'); ?>
                    </span>
                </button>
            </div>
            
            <div class="warranty-quick-tips">
                <h4><?php _e('Quick Tips:', 'warranty-manager'); ?></h4>
                <div class="tips-grid">
                    <div class="tip-item">
                        <div class="tip-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3 8-8"></path>
                                <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9c2.22 0 4.24.815 5.8 2.16"></path>
                            </svg>
                        </div>
                        <div class="tip-content">
                            <strong><?php _e('Order ID Search', 'warranty-manager'); ?></strong>
                            <p><?php _e('Most accurate method if you have your order confirmation', 'warranty-manager'); ?></p>
                        </div>
                    </div>
                    
                    <div class="tip-item">
                        <div class="tip-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                        </div>
                        <div class="tip-content">
                            <strong><?php _e('Phone Search', 'warranty-manager'); ?></strong>
                            <p><?php _e('Shows all warranties linked to your phone number', 'warranty-manager'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <div id="warranty-check-result" class="warranty-result" style="display: none;">
            <div class="warranty-result-content"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching functionality
    $('.search-tab').on('click', function() {
        const tabType = $(this).data('tab');
        
        // Update active tab
        $('.search-tab').removeClass('active');
        $(this).addClass('active');
        
        // Switch panels
        $('.search-panel').removeClass('active');
        $('#' + tabType + '-panel').addClass('active');
        
        // Clear the other input
        if (tabType === 'order') {
            $('#search_phone').val('');
            clearFieldError($('#search_phone'));
        } else {
            $('#search_order_id').val('');
            clearFieldError($('#search_order_id'));
        }
        
        // Focus on the active input
        setTimeout(function() {
            $('#search_' + tabType + '_id, #search_' + tabType).focus();
        }, 100);
    });
    
    // Real-time validation
    $('#search_order_id, #search_phone').on('input', function() {
        clearFieldError($(this));
        validateSearchInput($(this));
    });
    
    // Format phone number as user types
    $('#search_phone').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length > 0) {
            // Add basic formatting (adjust based on your region)
            if (value.length <= 3) {
                value = value;
            } else if (value.length <= 6) {
                value = value.substring(0, 3) + '-' + value.substring(3);
            } else {
                value = value.substring(0, 3) + '-' + value.substring(3, 6) + '-' + value.substring(6, 10);
            }
            $(this).val(value);
        }
    });
    
    function validateSearchInput($input) {
        const value = $input.val().trim();
        const inputType = $input.attr('id');
        
        if (!value) return true;
        
        let isValid = true;
        let errorMessage = '';
        
        if (inputType === 'search_order_id') {
            // Basic order ID validation
            if (!/^\d+$/.test(value)) {
                isValid = false;
                errorMessage = 'Order ID should contain only numbers';
            } else if (value.length < 3) {
                isValid = false;
                errorMessage = 'Order ID should be at least 3 digits';
            }
        } else if (inputType === 'search_phone') {
            // Basic phone validation
            const cleanPhone = value.replace(/\D/g, '');
            if (cleanPhone.length < 10) {
                isValid = false;
                errorMessage = 'Phone number should be at least 10 digits';
            }
        }
        
        if (!isValid) {
            showFieldError($input, errorMessage);
        } else {
            clearFieldError($input);
        }
        
        return isValid;
    }
    
    function showFieldError($field, message) {
        $field.addClass('field-error-state');
        $field.siblings('.field-error').text(message).show();
    }
    
    function clearFieldError($field) {
        $field.removeClass('field-error-state');
        $field.siblings('.field-error').hide();
    }
    
    // Auto-focus on page load
    setTimeout(function() {
        $('#search_order_id').focus();
    }, 500);
});
</script>