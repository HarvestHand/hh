(function () {
    var category = hh.namespace('hhf.modules.shares.admin.addon.category');

    category.config = {};

    category.init = function (config) {
        $.extend(category.config, config);

        $(document).ready(category.documentInit);
    };

    category.documentInit = function () {
        $("#item").validate();
    };
})();
