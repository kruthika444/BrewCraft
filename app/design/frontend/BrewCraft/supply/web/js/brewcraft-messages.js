define([
    'jquery'
], function ($) {
    'use strict';

    var scrollTimeout = null;


    /**
     * Return the newest visible Magento message.
     */
    function getVisibleMessage() {
        var $messages = $(
            '.page.messages .message:visible, ' +
            '.messages .message:visible'
        );

        if (!$messages.length) {
            return null;
        }

        return $messages.last().get(0);
    }


    /**
     * Check whether message is already visible.
     */
    function isMessageVisible(element) {
        if (!element) {
            return true;
        }

        var rect = element.getBoundingClientRect();
        var headerOffset = 140;

        return (
            rect.top >= headerOffset &&
            rect.bottom <= window.innerHeight
        );
    }


    /**
     * Scroll smoothly to Magento message.
     */
    function scrollToMessage() {
        clearTimeout(scrollTimeout);

        scrollTimeout = setTimeout(function () {
            var message = getVisibleMessage();

            if (!message) {
                return;
            }

            if (isMessageVisible(message)) {
                return;
            }

            var messageTop =
                message.getBoundingClientRect().top +
                window.pageYOffset -
                140;

            window.scrollTo({
                top: Math.max(messageTop, 0),
                behavior: 'smooth'
            });

        }, 350);
    }


    /**
     * Magento AJAX Add to Cart event.
     *
     * This is the important part for PDP/PLP Add to Cart.
     */
    $(document).on('ajax:addToCart', function () {
        scrollToMessage();
    });


    /**
     * Fallback for dynamically rendered Magento messages.
     */
    $(function () {
        var observer = new MutationObserver(function (mutations) {
            var hasMessageChange = false;

            mutations.forEach(function (mutation) {
                if (!mutation.addedNodes.length) {
                    return;
                }

                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) {
                        return;
                    }

                    if (
                        $(node).is('.message') ||
                        $(node).find('.message').length
                    ) {
                        hasMessageChange = true;
                    }
                });
            });

            if (hasMessageChange) {
                scrollToMessage();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });

});
