/**
 * Widget Configurator JavaScript
 * 
 * File: assets/js/config.js
 */

(function($) {
    'use strict';
    
    let selectedBusiness = null;
    
    $(document).ready(function() {
        initBusinessSearch();
        initCopyShortcode();
        initLayoutSelection();
    });
    
    /**
     * Initialize business search
     */
    function initBusinessSearch() {
        let searchTimeout;
        
        $('#search-business-btn').on('click', function() {
            performSearch();
        });
        
        $('#business-search').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                performSearch();
            }
        });
        
        function performSearch() {
            const query = $('#business-search').val().trim();
            
            if (query.length < 3) {
                alert('Please enter at least 3 characters');
                return;
            }
            
            const $btn = $('#search-business-btn');
            const $results = $('#business-results');
            
            $btn.prop('disabled', true).text('Searching...');
            $results.html('<div class="searching">Searching for businesses...</div>');
            
            $.ajax({
                url: wizGR.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wiz_gr_search_business',
                    nonce: wizGR.ajax_nonce,
                    query: query
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        displayResults(response.data);
                    } else {
                        $results.html('<div class="no-results">No businesses found. Try a different search.</div>');
                    }
                },
                error: function() {
                    $results.html('<div class="error-message">An error occurred. Please try again.</div>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Search');
                }
            });
        }
        
        function displayResults(businesses) {
            const $results = $('#business-results');
            let html = '<h4>Select your business:</h4>';
            
            businesses.forEach(function(business) {
                html += `
                    <div class="business-result-item" data-place-id="${business.place_id}" data-name="${business.name}">
                        <div class="business-name">${business.name}</div>
                        <div class="business-address">${business.address}</div>
                        <div class="business-rating">⭐ ${business.rating} (${business.total_reviews} reviews)</div>
                    </div>
                `;
            });
            
            html += `
                <form method="post" action="${wizGR.adminPostUrl}" id="select-business-form">
                    <input type="hidden" name="action" value="wiz_gr_save_business">
                    <input type="hidden" name="wiz_gr_nonce" value="${wizGR.connect_nonce}">
                    <input type="hidden" name="place_id" id="selected-place-id">
                    <input type="hidden" name="business_name" id="selected-business-name">

                    <button type="submit" class="button button-primary" id="confirm-business" disabled style="margin-top: 20px;">
                        Connect This Business
                    </button>
                </form>
            `;
            
            $results.html(html);
            
            // Add nonce to form
//            $.post(wizGR.ajaxurl, {
//                action: 'wiz_gr_get_nonce'
//            }, function(nonce) {
//                $('input[name="wiz_gr_nonce"]').val(wizGR.nonce);
//            });
            
            // Handle business selection
            $('.business-result-item').on('click', function() {
                $('.business-result-item').removeClass('selected');
                $(this).addClass('selected');
                
                selectedBusiness = {
                    place_id: $(this).data('place-id'),
                    name: $(this).data('name')
                };
                
                $('#selected-place-id').val(selectedBusiness.place_id);
                $('#selected-business-name').val(selectedBusiness.name);
                $('#confirm-business').prop('disabled', false);
            });
        }
    }
    
    /**
     * Initialize copy shortcode functionality
     */
    function initCopyShortcode() {
        $('.copy-shortcode').on('click', function() {
            const $btn = $(this);
            const targetId = $btn.data('clipboard-target');
            const $target = $(targetId);
            
            if ($target.length) {
                const text = $target.text();
                
                // Create temporary textarea
                const $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                
                try {
                    document.execCommand('copy');
                    $btn.text('Copied!');
                    
                    setTimeout(function() {
                        $btn.text('Copy');
                    }, 2000);
                } catch (err) {
                    console.error('Copy failed:', err);
                }
                
                $temp.remove();
            }
        });
    }
    
    /**
     * Initialize layout selection highlighting
     */
    function initLayoutSelection() {
        $('input[name="layout"]').on('change', function() {
            $('.layout-option').removeClass('selected');
            $(this).closest('.layout-option').addClass('selected');
        });
        
        $('input[name="theme"]').on('change', function() {
            $('.theme-option').removeClass('selected');
            $(this).closest('.theme-option').addClass('selected');
        });
    }
    
})(jQuery);