define([
    'jquery'
], function ($) {
    'use strict';

    function moveRegistration() {
        var $registration = $('.brewcraft-success-registration-wrapper');
        var $target = $('#brewcraft-guest-account-action');

        if (!$registration.length || !$target.length) {
            return;
        }

        if ($registration.parent().is($target)) {
            return;
        }

        $registration.appendTo($target);
    }

    $(document).ready(function () {
        moveRegistration();

        var observer = new MutationObserver(function () {
            moveRegistration();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
});
