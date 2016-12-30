(function() {
    var options = hh.namespace('hhf.modules.default.admin.options.farm');

    options.config = {};

    options.init = function(config) {
        $.extend(options.config, config);

        $(document).ready(options.documentInit);
    };

    options.documentInit = function() {

        $("#farm_city").autocomplete({
            source: function(request, response) {
                $.get(options.config.url,
                    {
                        country : options.config.city.country,
                        subdivision : $("#farm_state").val(),
                        unlocode: request.term
                    },
                    function(unlocodes) {
                        response(unlocodes);
                    },
                    'json'
                );
            }
        });

        $("#farm").validate({
            rules: {
            },
            messages: {
            },
            errorContainer: $("#formError")
        });

        $('.tooltip').qtip({
           style: {
               classes: 'ui-tooltip-shadow ui-tooltip-rounded',
               widget: true
           },
           position: {
              my: 'bottom right',
              at: 'top center',
              method: 'flip'
           }
        });
    };

})();
