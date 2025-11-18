/**
 * PromptRocket Ratings JavaScript
 * Handles star interactions and AJAX submission
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Cache DOM elements
    const $ratingForms = $('.pr-rating-form');
    
    // Initialize each rating form
    $ratingForms.each(function() {
        const $form = $(this);
        const $stars = $form.find('.pr-star');
        const $starsContainer = $form.find('.pr-rating-stars');
        const $message = $form.find('.pr-rating-message');
        const postId = $form.data('post-id');
        
        // Skip if already rated
        if ($form.hasClass('pr-already-rated')) {
            return;
        }
        
        // Star hover effect
        $stars.on('mouseenter', function() {
            const rating = $(this).data('rating');
            const label = pr_ratings.labels[rating];
            
            // Show tooltip message
            if (label) {
                $message.text(label).removeClass('error');
            }
            
            // Add hovering class and highlight stars
            $starsContainer.addClass('hovering');
            $stars.each(function() {
                const starRating = $(this).data('rating');
                if (starRating <= rating) {
                    $(this).addClass('hover-active');
                } else {
                    $(this).removeClass('hover-active');
                }
            });
        });
        
        // Remove hover effect when leaving container
        $starsContainer.on('mouseleave', function() {
            $starsContainer.removeClass('hovering');
            $stars.removeClass('hover-active');
            
            // Clear message if no rating selected
            if (!$form.hasClass('rated')) {
                $message.text('');
            }
        });
        
        // Star click handler
        $stars.on('click', function() {
            const $clickedStar = $(this);
            const rating = $clickedStar.data('rating');
            
            // Prevent double submission
            if ($form.hasClass('loading') || $form.hasClass('rated')) {
                return;
            }
            
            // Visual feedback
            $form.addClass('loading');
            $stars.removeClass('active');
            $stars.each(function() {
                const starRating = $(this).data('rating');
                if (starRating <= rating) {
                    $(this).addClass('active');
                }
            });
            
            // Submit rating via AJAX
            $.ajax({
                url: pr_ratings.ajax_url,
                type: 'POST',
                data: {
                    action: 'pr_submit_rating',
                    post_id: postId,
                    rating: rating,
                    nonce: pr_ratings.nonce
                },
                success: function(response) {
                    $form.removeClass('loading');
                    
                    if (response.success) {
                        // Success feedback
                        $form.addClass('success rated');
                        $message.text(response.data.message).removeClass('error');
                        
                        // Update the display at top if it exists
                        updateRatingDisplay(response.data.rating_data);
                        
                        // After animation, show thank you message
                        setTimeout(function() {
                            $form.addClass('pr-already-rated')
                                 .removeClass('success')
                                 .html('<p>Thanks for rating this prompt!</p>');
                        }, 2000);
                    } else {
                        // Error handling
                        $message.text(response.data || 'Something went wrong. Please try again.')
                                .addClass('error');
                        $form.removeClass('rated');
                        
                        // Reset stars after error
                        setTimeout(function() {
                            $stars.removeClass('active');
                        }, 2000);
                    }
                },
                error: function() {
                    $form.removeClass('loading');
                    $message.text('Connection error. Please try again.')
                            .addClass('error');
                    
                    // Reset stars after error
                    setTimeout(function() {
                        $stars.removeClass('active');
                    }, 2000);
                }
            });
        });
    });
    
    /**
     * Update the rating display at the top of the post
     */
    function updateRatingDisplay(ratingData) {
        const $display = $('.pr-rating-display');
        
        if ($display.length && ratingData) {
            // Build new display HTML
            const stars = '⭐'.repeat(ratingData.stars) + '⭐'.repeat(5 - ratingData.stars);
            const label = pr_ratings.labels[ratingData.stars] || '';
            const ratingText = label + ' (' + ratingData.average + '/5 from ' + 
                              ratingData.count + ' ' + 
                              (ratingData.count === 1 ? 'rating' : 'ratings') + ')';
            
            // Create new summary HTML (keeping the H2)
            const newHtml = '<h2>Prompt Rating</h2>' +
                           '<div class="pr-rating-summary">' +
                           '<div class="pr-stars">' + generateStarsHtml(ratingData.stars) + '</div>' +
                           '<span class="pr-rating-text">' + ratingText + '</span>' +
                           '</div>';
            
            // Fade out, update, fade in
            $display.fadeOut(200, function() {
                $display.removeClass('pr-no-ratings')
                        .html(newHtml)
                        .fadeIn(200);
            });
        }
    }
    
    /**
     * Generate star HTML for display
     */
    function generateStarsHtml(rating) {
        let html = '';
        for (let i = 1; i <= 5; i++) {
            const filled = i <= rating ? 'filled' : 'empty';
            html += '<span class="pr-star ' + filled + '">⭐</span>';
        }
        return html;
    }
    
    /**
     * Keyboard accessibility
     */
    $('.pr-rating-stars').attr('role', 'radiogroup')
                         .attr('aria-label', 'Rate this prompt');
    
    $('.pr-rating-stars .pr-star').each(function(index) {
        $(this).attr('role', 'radio')
               .attr('aria-checked', 'false')
               .attr('aria-label', pr_ratings.labels[index + 1] || 'Rating ' + (index + 1))
               .attr('tabindex', '0');
    });
    
    // Keyboard navigation
    $('.pr-rating-stars .pr-star').on('keypress', function(e) {
        if (e.which === 13 || e.which === 32) { // Enter or Space
            e.preventDefault();
            $(this).click();
        }
    });
    
    // Arrow key navigation
    $('.pr-rating-stars').on('keydown', '.pr-star', function(e) {
        const $current = $(this);
        const $stars = $current.parent().find('.pr-star');
        const currentIndex = $stars.index($current);
        
        switch(e.which) {
            case 37: // Left arrow
            case 38: // Up arrow
                if (currentIndex > 0) {
                    $stars.eq(currentIndex - 1).focus();
                }
                e.preventDefault();
                break;
                
            case 39: // Right arrow
            case 40: // Down arrow
                if (currentIndex < $stars.length - 1) {
                    $stars.eq(currentIndex + 1).focus();
                }
                e.preventDefault();
                break;
        }
    });
});
