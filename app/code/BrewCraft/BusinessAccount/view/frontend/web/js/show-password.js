define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        var $checkbox = $(element);
        var $password = $('#password');
        var $confirmation = $('#password_confirmation');

        $checkbox.on('change', function () {
            var type = this.checked ? 'text' : 'password';

            $password.attr('type', type);
            $confirmation.attr('type', type);
        });
    };
});
