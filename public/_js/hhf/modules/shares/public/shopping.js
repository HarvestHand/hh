(function () {
    var page = hh.namespace('hhf.modules.shares.public.shopping');

    page.shoppingCache = {};

    page.ath = null;

    page.config = {};

    page.init = function (config) {
        $.extend(page.config, config);

        $(document).ready(page.documentInit);
    };

    page.documentInit = function () {

        var $body = $('#body-content-shopping');

        $body.on('click', '.product-details', function(){
            var $this = $(this),
                $panelBody = $this.closest('.product').find('.panel-body');

            if ($panelBody.is(':hidden')) {
                $this.empty().append('<i class="fa fa-chevron-up"></i>');
                $panelBody.show('fast');
            } else {
                $this.empty().append('<i class="fa fa-chevron-down"></i>');
                $panelBody.hide('fast');
            }
        }).on('click', '.to-source', function(event) {
            event.preventDefault();

            var aTag = $($(this).attr('href')),
                navbar = $('.navbar');
            $('html,body').animate({
                scrollTop: aTag.offset().top - navbar.height()
                },
                'fast'
            );

            return false;
        }).on('click', '.panel-title', function() {
            $(this).closest('.row')
                .find('.product-details')
                .click();
        }).on('change', '#location', function() {
            $('#week').val($(this).find(':selected').data('week'));
        }).on('click', '.showImage', function() {
            var $productImageModal = $('#productImageModal'),
                $this = $(this),
                src = $this.data('srclarge'),
                name = $this.attr('alt');

            if (src) {
                $productImageModal.find('.img-responsive').attr('src', src);
                $productImageModal.find('.modal-title').text(name);
                $productImageModal.modal('show');
            }
        }).on('shown.bs.modal', '#shop-filter', function() {
            var pos = $('figure.checked').offset().top - $('#categories').height() + 80;

            if (pos >= 80) {
                $('#categories').scrollTop(pos);
            } else if (pos <= -80) {
                $('#categories').scrollTop(0);
                var pos = $('figure.checked').offset().top - $('#categories').height() + 80;
                $('#categories').scrollTop(pos);
            }
        });

        if(sessionStorage) {
            $body.on('click', '.product-shop', function() {
                var $this = $(this),
                    $product = $this.closest('.product');

                if ($this.find('i').hasClass('fa-square-o')) {
                    // add
                    page.addItemToShoppingList({
                        categoryId: $product.data('categoryid'),
                        name: $product.data('name'),
                        id: $product.data('id'),
                        source: $product.data('source')
                    });

                    $this.attr('title', page.config.lang.shopRemove)
                        .empty()
                        .append('<i class="fa fa-check-square-o"></i>');
                } else {
                    // remove
                    page.removeItemFromShoppingList({
                        categoryId: $product.data('categoryid'),
                        id: $product.data('id')
                    });

                    $this.attr('title', page.config.lang.shopAdd)
                        .empty()
                        .append('<i class="fa fa-square-o"></i>');
                }
            }).on('click', '.printButton', function() {
                page.printShoppingList();
            }).on('click', '.viewButton', function() {
                page.viewShoppingList();
            }).on('click', '.emailFinalButton', function() {
                page.emailShoppingList();
            }).on('submit', '#emailForm', function(e) {
                e.preventDefault();
                return false;
            });

            page.initializeShoppingButtons();
        } else {
            $('.product-shop', '.printButton', '.emailButton').hide();
        }

        page.initializeFilters();

        if (localStorage) {
            if (!localStorage.getItem('firstTime')) {
                $('#firstTime')
                    .modal('show')
                    .on('hidden.bs.modal', function() {
                        $('#shop-filter').modal('show');
                    });
                localStorage.setItem('firstTime', true);
            } else if (!location.search || location.search.length == 0) {
                $('#shop-filter').modal('show');
            }
        } else if (!location.search || location.search.length == 0) {
            $('#shop-filter').modal('show');
        }

        page.ath = addToHomescreen({
            skipFirstVisit: true,
            maxDisplayCount: 1,
            onInit: function() {
                $('.toBookmark').show().on('click', 'a', function(e) {
                    e.preventDefault();
                    page.ath.show(true);
                });
            }
        });
    };

    page.initializeFilters = function() {
        var $categories = $('#categories'),
            $source = $('#source');

        $source.change(function() {
            if ($source.val() && $source.val().length) {
                if ($categories.find("input[value='']").length == 0) {
                    var category = '<figure class="category-container">' +
                        '<label for="category-">' +
                        '<img src="/_farms/images/themes/shopping/category.png" class="img-thumbnail img-responsive" />' +
                        '</label><figcaption>' +
                        '<input type="radio" name="category" class="category" id="category-" value="" title="' +
                        page.config.lang.allCategories + '" /><label for="category-">' +
                        page.config.lang.allCategories + '</label></figcaption></figure>';

                    $categories.prepend(category);
                }
            } else {
                $categories.find("input[value='']").closest('.category-container').remove();
            }
        });

        $('input.category').change(function() {
            var category = $(this).val();

            $('figure.category-container.checked').removeClass('checked');

            $('#category-' + category).closest('.category-container').addClass('checked');
        });
    };

    page.initializeShoppingButtons = function () {

        $.each(page.getShoppingList(), function(index) {
            var category = this,
                categoryFound = page.config.categories[index];

            $.each(category, function() {
                var item = this,
                    $product = $('#product' + item.id);

                if (!categoryFound) {
                    page.removeItemFromShoppingList(item);
                } else {

                    if ($product.length) {
                        $product.find('.product-shop')
                            .attr('title', page.config.lang.shopRemove)
                            .empty()
                            .append('<i class="fa fa-check-square-o"></i>');
                    }
                }
            })
        });
    };

    page.addItemToShoppingList = function (item) {
        var list = page.getShoppingList();

        if(!list[item.categoryId]) {
            list[item.categoryId] = [];
        }

        list[item.categoryId].push(item);

        page.updateShoppingList(list);
    };

    page.removeItemFromShoppingList = function(item) {
        var list = page.getShoppingList(),
            key = item.categoryId,
            category = list[key],
            arr = [];

        if (category) {
            for (var i = 0; i < category.length; i++) {
                if (!category[i] || category[i].id == item.id) {
                    continue;
                }

                arr.push(category[i]);
            }

            if (arr.length > 0) {
                list[key] = arr;
            } else {
                delete list[key];
            }
        }

        page.updateShoppingList(list);
    };

    page.printShoppingList = function() {
        var list = page.getShoppingList();

        $('#view').modal('hide');

        $('#shoppingList').empty();

        $.each(list, function(key) {
            var obj = {
                title: page.config.categories[key],
                items: []
            };

            $.each(this, function() {
                obj.items.push(this);
            })

            $('#shopping_list_template').tmpl(obj).appendTo('#shoppingList');
        });

        window.print();
    };

    page.viewShoppingList = function() {
        var list = page.getShoppingList(),
            $view = $('#view'),
            $body = $view.find('.shoppingList');

        $body.empty();

        $.each(list, function(key) {
            var obj = {
                title: key,
                items: []
            };

            $.each(this, function() {
                obj.items.push(this);
            });

            $('#shopping_list_template').tmpl(obj).appendTo($body);
        });

        $view.modal('show');
    };

    page.emailShoppingList = function() {
        var list = page.getShoppingList(),
            data = {
                week: $('#emailWeek').val(),
                location: $('#emailLocation').val(),
                email: $('#emailAddress').val()
            };

        data.addons = [];

        $.each(list, function(key) {
            $.each(this, function() {
                data.addons.push(this.id);
            });
        });

        $.ajax({
            type: 'POST',
            url: $('#emailForm').attr('action'),
            data: data,
            success: function(data) {
                if (!data.hasOwnProperty('status') || data.status == false) {
                    window.alert(
                        (data.hasOwnProperty('message') ? data.message : 'Well this is bad.  Error')
                    );
                } else if (data.hasOwnProperty('message')) {
                    window.alert(data.message);
                }

                $('#email').modal('hide');
            },
            error: function() {
                window.alert('Well this is bad. Error');
                $('#email').modal('hide');
            }
        });
    };

    page.getShoppingList = function() {
        var day = page.config.week + '-' + page.config.location;

        if (page.shoppingCache.hasOwnProperty(day)) {
            return page.shoppingCache[day];
        }

        var list = sessionStorage.getItem('shopping_list');

        if(list == null) {
            page.shoppingCache.all = {};
            page.shoppingCache[day] = {};
        } else {
            page.shoppingCache.all = JSON.parse(list);

            if (page.shoppingCache.all.hasOwnProperty(day)) {
                page.shoppingCache[day] = page.shoppingCache.all[day];
            } else {
                page.shoppingCache[day] = {};
            }
        }

        return page.shoppingCache[day];
    };

    page.updateShoppingList = function(list) {
        var day = page.config.week + '-' + page.config.location;

        page.shoppingCache[day] = list;
        page.shoppingCache.all[day] = list;

        sessionStorage.setItem('shopping_list', JSON.stringify(page.shoppingCache.all));
    };
})();
