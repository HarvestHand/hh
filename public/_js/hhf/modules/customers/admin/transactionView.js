/**
 * @requires jquery_validate
 * @requires jquery_metadata
 * @requires jquery_spinner
 */
(function() {
    var page = hh.namespace('hhf.modules.customers.admin.transactionView');

    /**
     * config
     */
    page.config = {};

    /**
     * page init
     *
     * @param {Object} config
     * @returns {undefined}
     */
    page.init = function(config) {
        // pull in userland defined config options
        $.extend(page.config, config);

        // init document ready handler
        $(document).ready(page.documentInit);
    };

    /**
     * document ready
     * @returns {undefined}
     */
    page.documentInit = function() {

        $('.dropdown-toggle').dropdown();


        $("body").delegate(".delete", "click", function(){
            return window.confirm(page.config.lang.delete);
        }).delegate(".unapply", "click", function(){
            return window.confirm(page.config.lang.unapply);
        });

        var height = parseInt($(window).height() * .8);

        $(".dropdown-container").on('click', '.apply-existing', function(event) {
            event.preventDefault();

            var $this = $(this),
                horizontalPadding = 30,
                verticalPadding = 30;

            $('#transaction-apply').dialog({
                modal: true,
                open: function () {
                    $('#invoices').multiselect({
                        header: false,
                        click: function(event, ui) {
                            var $total = $('#invoices-total'),
                                remainingToApply = parseFloat($total.data('remainingtoapply'));

                            $total.text(remainingToApply);

                            var invoices = $("#invoices").multiselect("getChecked");

                            if (invoices) {
                                for (var i = 0; i < invoices.length; ++i) {
                                    var invoiceId = $(invoices[i]).val();
                                    for (var x = 0; x < page.config.invoices.length; ++x) {
                                        if (page.config.invoices[x].id == invoiceId) {
                                            remainingToApply -= parseFloat(page.config.invoices[x].outstandingAmount);

                                            $total.text(remainingToApply.toFixed(2));
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    });
                },
                close: function(event, ui) {
                    //$(this).remove();
                },
                title: $this.attr('title'),
                autoOpen: true,
                width: 800,
                height: 500,
                modal: true,
                resizable: true,
                autoResize: true
            });

        }).on('click', '.apply-new', function(e) {

            if (!confirm(page.config.lang.applyNew)) {
                e.preventDefault();
                return false;
            }
        });

    };
})();