(function () {
    var page = hh.namespace('hhf.modules.shares.public.myShares');

    page.config = {};

    /**
     * @param config
     */
    page.init = function (config) {
        $.extend(page.config, config);

        $(document).ready(page.documentInit);
    };

    /**
     * document ready
     */
    page.documentInit = function () {
        $(".quantity").spinner({
            max: 99,
            min: 0,
            change: page.updateTotal
        });

        $("button.submit").button({
            icons : {
                primary:'ui-icon-cart'
            }
        });

        $("#my").validate({
            submitHandler: function(form) {
                var hasProduct = false;

                $(".quantity").each(function () {
                    if ($(this).val() > 0) {
                        hasProduct = true;
                        return false;
                    }
                });

                if (!hasProduct) {
                    alert(page.config.lang.alert);

                    return false;
                }

                form.submit();
           }
        });

        $(".addon-photo").colorbox({
            photo: true,
            current: page.config.lang.current,
            previous: page.config.lang.previous,
            next: page.config.lang.next,
            close: page.config.lang.close,
            xhrError: page.config.lang.xhrError,
            imgError: page.config.lang.imgError
        });

        $(".addonCategory").css('max-height', '400px');

        $("#addonCategories").accordion({
            collapsible: true,
            autoHeight: false,
            header: "h3"
        });

        page.updateTotal();
    };

	/**
	 * Update total event handler
	 * @param {Object} event
	 * @param {Object} ui
	 */
    page.updateTotal = function(event, ui) {

        var total = 0,
            hasPendingOnOrder = false;

        if (event !== undefined) {
            var $this = $(this),
                unitOrderMinimum = parseInt($this.data('unitorderminimum')),
                itemQty = parseInt($this.val());

            if (isNaN(itemQty)) {
                itemQty = 0;
            }

            if (isNaN(unitOrderMinimum)) {
                unitOrderMinimum = 0;
            }

            if (itemQty > 0 && itemQty < unitOrderMinimum) {

                // direction
                var up = $(event.currentTarget).hasClass('ui-spinner-up');

                if (up) {
                    $this.val(unitOrderMinimum);
                } else {
                    $this.val(0);
                }
            }

        }

        $(".quantity").each(function(){
            var _this = $(this),
                id = _this.data('id'),
                qty = parseInt(_this.val(), 10);

            if (isNaN(qty)) {
                qty = 0;
            }
            _this.val(qty);

            if (qty > 0) {

                if (parseInt(_this.data('pendingonorder'), 10) === 1) {
                    hasPendingOnOrder = true;
                }

                for (var x = 0; x < page.config.addons.length; x++) {
                    if (page.config.addons[x].id == id) {
                        if (page.config.addons[x].inventory != null && qty > page.config.addons[x].inventory) {
                            qty = page.config.addons[x].inventory;
                            _this.val(qty);
                        }

                        total += page.config.addons[x].price * qty;
                        break;
                    }
                }
            }
        });

        if (hasPendingOnOrder) {
            $('.pending-on-order').show('normal');
        } else {
            $('.pending-on-order').hide('normal');
        }

        $("#total").html(page.formatCurrency(total));
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