jQuery(document).ready(function($) {
    // Handle toggle click
    $(document).on('click', '.bfs-wishlist-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        if ($btn.hasClass('loading')) return;

        var productId = $btn.data('product-id');
        var isActive = $btn.hasClass('active');
        var url = bfsWishlistData.restUrl + (isActive ? '/remove' : '/add');
        var method = isActive ? 'DELETE' : 'POST';

        $btn.addClass('loading');

        $.ajax({
            url: url,
            method: method,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', bfsWishlistData.nonce);
            },
            data: {
                product_id: productId
            },
            success: function(response) {
                $btn.removeClass('loading');
                if (response.success) {
                    $btn.toggleClass('active');
                    var $svg = $btn.find('svg');
                    if ($btn.hasClass('active')) {
                        $svg.attr('fill', 'currentColor');
                        $btn.attr('title', 'Remove from Wishlist');
                        $btn.attr('aria-label', 'Remove from Wishlist');
                    } else {
                        $svg.attr('fill', 'none');
                        $btn.attr('title', 'Add to Wishlist');
                        $btn.attr('aria-label', 'Add to Wishlist');
                    }
                    
                    // Update header wishlist count badge
                    updateWishlistCount(response.data ? response.data.length : 0);
                }
            },
            error: function(err) {
                $btn.removeClass('loading');
                console.error('Wishlist error:', err);
            }
        });
    });

    // Handle remove click from the wishlist shortcode page
    $(document).on('click', '.bfs-wishlist-remove', function(e) {
        e.preventDefault();
        var $removeLink = $(this);
        var productId = $removeLink.data('product-id');
        var $row = $removeLink.closest('.bfs-wishlist-item');
        var url = bfsWishlistData.restUrl + '/remove';

        if ($removeLink.hasClass('loading')) return;
        $removeLink.addClass('loading');

        $.ajax({
            url: url,
            method: 'DELETE',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', bfsWishlistData.nonce);
            },
            data: {
                product_id: productId
            },
            success: function(response) {
                if (response.success) {
                    // Update header wishlist count badge
                    var count = response.data ? response.data.length : 0;
                    updateWishlistCount(count);

                    // Fade out and remove row
                    $row.fadeOut(300, function() {
                        $row.remove();
                        // If no more items, show empty message
                        if ($('.bfs-wishlist-table tbody tr').length === 0) {
                            $('.bfs-wishlist-container').replaceWith(
                                '<div class="bfs-wishlist-empty">' +
                                '<p>Your wishlist is currently empty.</p>' +
                                '<a class="button wc-backward" href="' + bfsWishlistData.shopUrl + '">Return to shop</a>' +
                                '</div>'
                            );
                        }
                    });
                }
            },
            error: function(err) {
                $removeLink.removeClass('loading');
                console.error('Wishlist error:', err);
            }
        });
    });

    function updateWishlistCount(count) {
        var $badge = $('.wishlist-btn .wishlist-count');
        if ($badge.length) {
            $badge.text(count);
        }
    }
});
