/**
 * Reviews Slider JavaScript
 * 
 * File: assets/js/slider.js
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initSliders();
    });
    
    function initSliders() {
        $('.wiz-gr-slider-wrapper').each(function() {
            const $wrapper = $(this);
            const $slider = $wrapper.find('.wiz-gr-slider');
            const $slides = $slider.find('.wiz-gr-slide');
            const $prev = $wrapper.find('.wiz-gr-prev');
            const $next = $wrapper.find('.wiz-gr-next');
            
            if ($slides.length === 0) return;
            
            let currentIndex = 0;
            let slidesToShow = getSlidesToShow();
            const totalSlides = $slides.length;
            const maxIndex = Math.max(0, totalSlides - slidesToShow);
            
            // Create dots
            createDots();
            
            // Handle window resize
            let resizeTimeout;
            $(window).on('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    slidesToShow = getSlidesToShow();
                    updateSlider();
                }, 250);
            });
            
            // Navigation buttons
            $prev.on('click', function() {
                if (currentIndex > 0) {
                    currentIndex--;
                    updateSlider();
                }
            });
            
            $next.on('click', function() {
                if (currentIndex < maxIndex) {
                    currentIndex++;
                    updateSlider();
                }
            });
            
            // Dot navigation
            $wrapper.on('click', '.wiz-gr-dot', function() {
                currentIndex = $(this).data('index');
                updateSlider();
            });
            
            // Touch/swipe support
            let touchStartX = 0;
            let touchEndX = 0;
            
            $slider.on('touchstart', function(e) {
                touchStartX = e.originalEvent.touches[0].clientX;
            });
            
            $slider.on('touchmove', function(e) {
                touchEndX = e.originalEvent.touches[0].clientX;
            });
            
            $slider.on('touchend', function() {
                const diff = touchStartX - touchEndX;
                
                if (Math.abs(diff) > 50) {
                    if (diff > 0 && currentIndex < maxIndex) {
                        currentIndex++;
                    } else if (diff < 0 && currentIndex > 0) {
                        currentIndex--;
                    }
                    updateSlider();
                }
            });
            
            function getSlidesToShow() {
                const width = $(window).width();
                if (width < 640) return 1;
                if (width < 968) return 2;
                return 3;
            }
            
            function updateSlider() {
                const slideWidth = $slides.first().outerWidth(true);
                const offset = -(currentIndex * slideWidth);
                
                $slider.css('transform', `translateX(${offset}px)`);
                
                // Update buttons state
                $prev.prop('disabled', currentIndex === 0);
                $next.prop('disabled', currentIndex >= maxIndex);
                
                // Update dots
                $wrapper.find('.wiz-gr-dot').removeClass('active')
                    .eq(currentIndex).addClass('active');
            }
            
            function createDots() {
                const $dotsContainer = $wrapper.find('.wiz-gr-dots');
                $dotsContainer.empty();
                
                const dotsCount = Math.ceil(totalSlides / slidesToShow);
                
                for (let i = 0; i < dotsCount; i++) {
                    const $dot = $('<span>')
                        .addClass('wiz-gr-dot')
                        .attr('data-index', i);
                    
                    if (i === 0) $dot.addClass('active');
                    
                    $dotsContainer.append($dot);
                }
            }
            
            // Initialize
            updateSlider();
        });
    }
    
})(jQuery);