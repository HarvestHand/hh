(function () {
    var page = hh.namespace('hhf.modules.customers.public.payment');

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
        page.updateTotal();

        $('.invoice').change(page.updateTotal);

        $('#amount').bind('change keyup', page.updateTotal);

        $('#payment').validate({
            errorContainer: $('#formError')
        });

        $('body').on('mouseover', 'span.tooltip', function(event) {
            if ($(this).data('qtip') != undefined) {
                return;
            }
            $(this).qtip({
                    overwrite: false,
                    style: {
                        classes: 'ui-tooltip-shadow ui-tooltip-rounded',
                        widget: true,
                        width: '380px'
                    },
                    content: {
                        text: 'Loading...',
                        ajax: {
                            url: '/customers/payment?invoiceId=' + $(this).data('invoiceid'),
                            error: function (xh, status, error){
                                this.set('content.text', 'Error loading invoice');
                            },
                            loading: true,
                            success: function(data, status) {
                                if (data.indexOf('<title>') != -1) {
                                    this.set('content.text', 'Error loading invoice');
                                } else {
                                    this.set('content.text', data);
                                }

                            }
                        },
                        title: {
                            text: 'Invoice #' + $(this).data('invoiceid')
                        }
                    },
                    show: {
                        solo: true,
                        event: event.type,
                        ready: true,
                        delay: 140
                    },
                    position: {
                        my: 'bottom left',
                        at: 'top center',
                        effect: false,
                        viewport: $(window),
                        adjust: {
                            method: 'flip'
                        }
                    }
                },
                event);
        });
    };

    page.updateTotal = function() {

        var total = parseFloat($('#amount').val());

        if (isNaN(total)) {
            total = 0;
        }

        $('.invoice:checked').each(function(){
            total += parseFloat($(this).data('outstandingamount'));
        });

        $('#total').html(page.formatCurrency(total));
    };

    page.formatCurrency = function (num) {
        num = num.toString().replace(/\$|\,/g,'');
        if(isNaN(num)) {
            num = '0';
        }
        sign = (num == (num = Math.abs(num)));
        num = Math.floor(num*100+0.50000000001);
        cents = num%100;
        num = Math.floor(num/100).toString();

        if(cents<10) {
            cents = '0' + cents;
        }

        for (var i = 0; i < Math.floor((num.length-(1+i))/3); i++) {
            num = num.substring(0,num.length-(4*i+3))+','+
            num.substring(num.length-(4*i+3));
        }

        return (((sign)?'':'-') + '$' + num + '.' + cents);
    };
})();
