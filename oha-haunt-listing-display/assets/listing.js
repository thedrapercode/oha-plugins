/**
 * OHA Haunt Listing Display - listing.js
 * Handles: gallery lightbox, mobile bar visibility, claim modal.
 */
(function ($) {
    'use strict';

    /* ── Gallery lightbox ─────────────────────────────────────────────────── */
    function buildLightbox() {
        if ($('#oha-lightbox').length) return;

        var html = [
            '<div id="oha-lightbox" role="dialog" aria-modal="true" aria-label="Image lightbox" style="display:none">',
            '  <button id="oha-lb-close" aria-label="Close lightbox">&times;</button>',
            '  <button id="oha-lb-prev" aria-label="Previous image">&#8249;</button>',
            '  <img id="oha-lb-img" src="" alt="" />',
            '  <button id="oha-lb-next" aria-label="Next image">&#8250;</button>',
            '</div>'
        ].join('');

        $('body').append(html);

        var $lb     = $('#oha-lightbox');
        var $img    = $('#oha-lb-img');
        var $prev   = $('#oha-lb-prev');
        var $next   = $('#oha-lb-next');
        var items   = [];
        var current = 0;

        function show(idx) {
            current = (idx + items.length) % items.length;
            var src = items[current].href;
            var alt = items[current].alt || '';
            $lb.addClass('oha-lb-loading');
            var preload = new Image();
            preload.onload = function () {
                $img.attr('src', src).attr('alt', alt);
                $lb.removeClass('oha-lb-loading');
            };
            preload.onerror = function () {
                $img.attr('src', src).attr('alt', alt);
                $lb.removeClass('oha-lb-loading');
            };
            preload.src = src;
            $prev.toggle(items.length > 1);
            $next.toggle(items.length > 1);
        }

        function openLightbox(gallery, startIdx) {
            items = gallery;
            $lb.css('display', 'flex').attr('aria-hidden', 'false');
            $('body').css('overflow', 'hidden');
            show(startIdx);
            $('#oha-lb-close').trigger('focus');
        }

        function closeLightbox() {
            $lb.css('display', 'none').attr('aria-hidden', 'true');
            $lb.removeClass('oha-lb-loading');
            $('body').css('overflow', '');
        }

        // Touch / swipe support
        var touchStartX = 0;
        var touchStartY = 0;
        $lb[0].addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: true });
        $lb[0].addEventListener('touchend', function (e) {
            var dx = e.changedTouches[0].clientX - touchStartX;
            var dy = e.changedTouches[0].clientY - touchStartY;
            // Require mostly-horizontal swipe of at least 40px
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
                if (dx < 0) { show(current + 1); } // swipe left → next
                else        { show(current - 1); } // swipe right → prev
            }
        }, { passive: true });

        $(document).on('click', '.oha-gallery__item', function (e) {
            e.preventDefault();
            var galleryName = $(this).data('gallery') || 'oha-listing';
            var $galleryItems = $('[data-gallery="' + galleryName + '"]');
            var gallery = [];
            var startIdx = 0;
            var clicked = this;

            $galleryItems.each(function (i) {
                gallery.push({ href: $(this).attr('href'), alt: $(this).find('.sr-only').text() });
                if (this === clicked) startIdx = i;
            });

            openLightbox(gallery, startIdx);
        });

        $('#oha-lb-close, #oha-lightbox').on('click', function (e) {
            if (e.target === this) closeLightbox();
        });

        $prev.on('click', function (e) { e.stopPropagation(); show(current - 1); });
        $next.on('click', function (e) { e.stopPropagation(); show(current + 1); });

        $(document).on('keydown', function (e) {
            if ($lb.css('display') === 'none') return;
            if (e.key === 'Escape')     closeLightbox();
            if (e.key === 'ArrowLeft')  show(current - 1);
            if (e.key === 'ArrowRight') show(current + 1);
        });
    }

    /* ── Mobile bar visibility ───────────────────────────────────────────── */
    function setupMobileBarObserver() {
        var $mobileBar = $('.oha-mobile-bar');
        if (!$mobileBar.length) return;
        var $sidebar = $('.oha-sidebar');
        if (!$sidebar.length || !('IntersectionObserver' in window)) return;

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                $mobileBar.toggle(!entry.isIntersecting);
            });
        }, { threshold: 0.1 });

        observer.observe($sidebar[0]);
    }

    /* ── Claim modal ─────────────────────────────────────────────────────── */
    function setupClaimModal() {
        var $modal = $('#oha-claim-modal');
        if (!$modal.length) return;

        function openModal(listingId) {
            if (listingId) {
                $modal.find('input[name="listing_id"]').val(listingId);
            }
            $modal.prop('hidden', false).attr('aria-hidden', 'false');
            $('body').css('overflow', 'hidden');
            $modal.find('.oha-claim-modal__close').trigger('focus');
        }

        function closeModal() {
            $modal.prop('hidden', true).attr('aria-hidden', 'true');
            $('body').css('overflow', '');
        }

        // Open: click on claim button
        $(document).on('click', '[data-oha-claim-open]', function (e) {
            e.preventDefault();

            // If not logged in, OHA_LISTING.isLoggedIn is false - shouldn't reach here
            // because the PHP renders a login link instead of the button, but guard anyway
            if (typeof OHA_LISTING !== 'undefined' && !OHA_LISTING.isLoggedIn) {
                return;
            }

            openModal($(this).data('listing'));
        });

        // Close: X button or backdrop click
        $(document).on('click', '[data-oha-claim-close]', function (e) {
            e.preventDefault();
            closeModal();
        });

        $modal.on('click', function (e) {
            if (e.target === this) closeModal();
        });

        // Keyboard: Escape closes
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && !$modal.prop('hidden')) closeModal();
        });

        // Submit form via AJAX
        $('#oha-claim-form').on('submit', function (e) {
            e.preventDefault();

            var $form   = $(this);
            var $btn    = $form.find('.oha-claim-modal__submit');
            var $success = $modal.find('.oha-claim-modal__success');

            $btn.prop('disabled', true).text('Submitting...');

            var data = $form.serialize();

            $.post(
                (typeof OHA_LISTING !== 'undefined' ? OHA_LISTING.ajax : '/wp-admin/admin-ajax.php'),
                data,
                function (response) {
                    if (response && response.success) {
                        $form.prop('hidden', true);
                        $success.prop('hidden', false);
                    } else {
                        var msg = (response && response.data && response.data.msg)
                            ? response.data.msg
                            : 'There was a problem submitting your request. Please try again.';
                        alert(msg);
                        $btn.prop('disabled', false).text($btn.data('original-text') || 'Submit');
                    }
                },
                'json'
            ).fail(function () {
                alert('Network error. Please check your connection and try again.');
                $btn.prop('disabled', false);
            });
        });

        // Store original button text for re-enable after error
        var $submitBtn = $('#oha-claim-form .oha-claim-modal__submit');
        if ($submitBtn.length) {
            $submitBtn.data('original-text', $submitBtn.text());
        }
    }

    /* ── Visitor tips collapsible ────────────────────────────────────────── */
    function setupVisitorTips() {
        $(document).on('click', '.oha-visitor-tips__toggle', function () {
            var $btn  = $(this);
            var $body = $btn.next('.oha-visitor-tips__body');
            var open  = $btn.attr('aria-expanded') === 'true';

            $btn.attr('aria-expanded', open ? 'false' : 'true');
            $body.prop('hidden', open);
        });
    }

    /* ── Email reveal ────────────────────────────────────────────────────── */
    // Email is stored split at @ in data-u / data-d attributes so no full
    // address appears in source HTML. One click assembles and replaces the button
    // with a live mailto link.
    function setupEmailReveal() {
        $(document).on('click', '.oha-email-reveal', function () {
            var $btn = $(this);
            var u = $btn.data('u');
            var d = $btn.data('d');
            if (!u || !d) return;
            var email = u + '@' + d;
            var $link = $('<a class="oha-info-card__value oha-info-card__value--link"></a>')
                .attr('href', 'mailto:' + email)
                .text(email);
            $btn.replaceWith($link);
        });
    }

    /* ── Init ────────────────────────────────────────────────────────────── */
    $(function () {
        if ($('.oha-listing-wrap').length) {
            buildLightbox();
            setupMobileBarObserver();
            setupClaimModal();
            setupVisitorTips();
            setupEmailReveal();
        }
    });

}(jQuery));
