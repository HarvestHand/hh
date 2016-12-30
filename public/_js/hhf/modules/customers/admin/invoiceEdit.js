/**
 * @requires jquery_validate
 * @requires jquery_metadata
 * @requires jquery_spinner
 */
(function() {
    var page = hh.namespace('hhf.modules.customers.admin.invoiceEdit');

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
        $(".quantity").spinner({
            max: 99,
            min: 1,
            change: page.updateTotals
        });

        $(".unit-price").spinner({
            max: 10000,
            min: 0.00,
            change: page.updateTotals
        });

        $("#invoice").validate({
            errorContainer: $("#formError"),
            errorPlacement: function(error, element) {
                error.insertAfter(element.closest('p').children().last());
            }
        });
    };

    page.updateTotals = function() {
        var id = $(this).data('id'),
            unitPrice = $('#lines_' + id + '_unitPrice').val(),
            quantity = $('#lines_' + id + '_quantity').val(),
            total = 0;

        $('#lines_' + id + ' .item-total').text(
            page.formatCurrency(unitPrice * quantity)
        );

        $('#lines_' + id + '_total').val(unitPrice * quantity);

        $('.item .line-total').each(function() {
            total += parseFloat($(this).val());
        });

        $('#subTotal').val(total).siblings('span').text(page.formatCurrency(total));
        $('#total').val(total).siblings('span').text(page.formatCurrency(total));
    };

    page.formatCurrency = function(num) {
        num = num.toString().replace(/\$|\,/g,'');
        if(isNaN(num))
        num = "0";
        sign = (num == (num = Math.abs(num)));
        num = Math.floor(num*100+0.50000000001);
        cents = num%100;
        num = Math.floor(num/100).toString();
        if(cents<10)
        cents = "0" + cents;
        for (var i = 0; i < Math.floor((num.length-(1+i))/3); i++)
        num = num.substring(0,num.length-(4*i+3))+','+
        num.substring(num.length-(4*i+3));
        return (((sign)?'':'-') + '$' + num + '.' + cents);
    };
})();