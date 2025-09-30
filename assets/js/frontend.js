/**
 * GF VerifyTX Frontend JavaScript
 */

(function($) {
    'use strict';

    var GFVerifyTX = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.gf-verifytx-verify-btn', this.handleVerification);
            $(document).on('change', '.gf-verifytx-field', this.validateField);

            if (window.gform) {
                gform.addFilter('gform_product_total', this.filterProductTotal);
            }
        },

        handleVerification: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $form = $button.closest('form');
            var formId = $form.data('formid') || $form.attr('id').replace('gform_', '');

            var fieldValues = GFVerifyTX.collectFieldValues($form);

            GFVerifyTX.setLoadingState($button, true);

            $.ajax({
                url: gf_verifytx_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'gf_verifytx_verify',
                    nonce: gf_verifytx_frontend.nonce,
                    form_id: formId,
                    field_values: JSON.stringify(fieldValues)
                },
                success: function(response) {
                    if (response.success) {
                        GFVerifyTX.handleSuccess($button, response.data);
                    } else {
                        GFVerifyTX.handleError($button, response.data);
                    }
                },
                error: function() {
                    GFVerifyTX.handleError($button, {
                        message: gf_verifytx_frontend.error
                    });
                },
                complete: function() {
                    GFVerifyTX.setLoadingState($button, false);
                }
            });
        },

        collectFieldValues: function($form) {
            var values = {};

            $form.find('.gf-verifytx-field').each(function() {
                var $field = $(this);
                var fieldName = $field.data('verifytx-field');

                if (fieldName) {
                    values[fieldName] = $field.val();
                }
            });

            return values;
        },

        setLoadingState: function($button, isLoading) {
            if (isLoading) {
                $button.prop('disabled', true)
                       .addClass('gf-verifytx-loading')
                       .text(gf_verifytx_frontend.verifying);
            } else {
                $button.prop('disabled', false)
                       .removeClass('gf-verifytx-loading');
            }
        },

        handleSuccess: function($button, data) {
            $button.addClass('gf-verifytx-verified')
                   .text(gf_verifytx_frontend.verified);

            var $container = $button.closest('.gf-verifytx-container');
            $container.find('.gf-verifytx-results').html(data.html || '');

            if (data.coverage) {
                GFVerifyTX.displayCoverage($container, data.coverage);
            }

            $container.trigger('verifytx:verified', [data]);
        },

        handleError: function($button, data) {
            $button.addClass('gf-verifytx-error')
                   .text(gf_verifytx_frontend.error);

            var $container = $button.closest('.gf-verifytx-container');
            var errorHtml = '<div class="gf-verifytx-error-message">' +
                           (data.message || gf_verifytx_frontend.error) +
                           '</div>';

            $container.find('.gf-verifytx-results').html(errorHtml);

            $container.trigger('verifytx:error', [data]);
        },

        displayCoverage: function($container, coverage) {
            var html = '<div class="gf-verifytx-coverage">';

            if (coverage.status) {
                html += '<div class="coverage-status ' + coverage.status.toLowerCase() + '">';
                html += '<strong>Status:</strong> ' + coverage.status;
                html += '</div>';
            }

            if (coverage.copay) {
                html += '<div class="coverage-copay">';
                html += '<strong>Copay:</strong> $' + coverage.copay;
                html += '</div>';
            }

            if (coverage.deductible) {
                html += '<div class="coverage-deductible">';
                html += '<strong>Deductible:</strong> $' + coverage.deductible.amount;
                html += ' (Met: $' + coverage.deductible.met + ')';
                html += '</div>';
            }

            if (coverage.outOfPocket) {
                html += '<div class="coverage-oop">';
                html += '<strong>Out of Pocket Max:</strong> $' + coverage.outOfPocket.max;
                html += ' (Met: $' + coverage.outOfPocket.met + ')';
                html += '</div>';
            }

            html += '</div>';

            $container.find('.gf-verifytx-coverage-details').html(html);
        },

        validateField: function() {
            var $field = $(this);
            var value = $field.val();
            var fieldType = $field.data('verifytx-field');

            if (fieldType === 'member_id') {
                value = value.replace(/[^a-zA-Z0-9]/g, '');
                $field.val(value);
            }

            if (fieldType === 'dob') {
                if (!GFVerifyTX.isValidDate(value)) {
                    $field.addClass('gf-verifytx-invalid');
                } else {
                    $field.removeClass('gf-verifytx-invalid');
                }
            }
        },

        isValidDate: function(dateString) {
            var regex = /^\d{2}\/\d{2}\/\d{4}$/;
            if (!regex.test(dateString)) return false;

            var parts = dateString.split('/');
            var month = parseInt(parts[0], 10);
            var day = parseInt(parts[1], 10);
            var year = parseInt(parts[2], 10);

            if (year < 1900 || year > new Date().getFullYear()) return false;
            if (month < 1 || month > 12) return false;

            var daysInMonth = new Date(year, month, 0).getDate();
            if (day < 1 || day > daysInMonth) return false;

            return true;
        },

        filterProductTotal: function(total, formId) {
            return total;
        }
    };

    $(document).ready(function() {
        GFVerifyTX.init();
    });

})(jQuery);