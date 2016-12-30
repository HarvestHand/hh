(function () {
    var addon = hh.namespace('hhf.modules.shares.admin.addon');

    addon.config = {};

    addon.cacheLocations = {};

    addon.cacheCertifications = {};

    addon.cacheCategories = {};

    addon.extenalAddon = false;

    addon.init = function (config) {
        $.extend(addon.config, config);

        $(document).ready(addon.documentInit);
    };

    addon.documentInit = function () {
        var config = {
            customConfig : '',
            toolbar:
            [
                ['Bold','Italic','Underline','Strike'],
                ['NumberedList','BulletedList','-','Outdent','Indent','Blockquote'],
                ['Link','Unlink'],
                ['Cut','Copy','Paste','PasteText','PasteFromWord'],
                ['Undo','Redo']
                ['RemoveFormat']
            ],
            colorButton_enableMore: false,
            disableNativeSpellChecker: false
        };

        $('#details').ckeditor(
            function(){
                var editor = $('#details').ckeditorGet();
                editor.on( "blur", function() {
                    this.updateElement();
                });
            },
            config
        );

        $("#addon").validate({
            rules: {
                distributorId: {
                    required:'#publishToNetwork:checked'
                }
            },
            messages : {},
            ignore: [],
            errorContainer: $("#formError"),
            errorPlacement: function(error, element) {
                error.insertAfter(element.parent().children().last());
            },
            submitHandler: function(form) {
                $('#details').ckeditorGet().updateElement();
                form.submit();
            }
        });

        $('#priceBy, #unitType').change(addon.changePriceUnit);

        $("#categoryId").change(addon.changeCategory);

        $('#inventory').change(addon.inventoryChange);

        $('#publishToNetwork').change(addon.publishToNetworkChange);

        var $distributorId = $('#distributorId');

        $distributorId.change(addon.buildSelectedNetworkOptions);

        $('#source').autocomplete({
            source: addon.config.sources
        });

        $('#locations').multiselect({
            header: false
        });

        $('#distributorLocations').multiselect({
            header: false
        });

        $(".tooltip").qtip({
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

        $("#expirationDate").datepicker({
            dateFormat: 'yy-mm-dd'
        });

        if (addon.config.distributorId && addon.config.externalId) {
            addon.buildSelectedNetworkOptions.call($distributorId);
        }
    };

    addon.publishToNetworkChange = function() {
        if ($(this).is(':checked')) {
            addon.buildNetworkOptions();
        } else {
            addon.destroyNetworkOptions();
        }
    };

    addon.buildNetworkOptions = function() {
        $('#distributorLocations').parent('p').hide();
        $('#distributorCertification').parent('p').hide();
        $('#distributorCategoryId').parent('p').hide();
        $('#distributionPick').show('fast');
    };

    addon.destroyNetworkOptions = function() {
        $('#distributionPick').hide('fast');
        $('#distributionOptions').hide();
        $('#distributorId').val('');
        $('#expirationDate')
            .removeClass('required')
            .siblings('label')
            .removeClass('required')
            .find('abbr')
            .remove();
        $('#distributorCategoryId')
            .removeClass('required')
            .siblings('label')
            .removeClass('required')
            .find('abbr')
            .remove();
    };

    addon.buildSelectedNetworkOptions = function() {
        var distributorId = $(this).val();

        addon.buildNetworkLocations(distributorId);
        addon.buildNetworkCertifications(distributorId);
        addon.buildNetworkAddonCategories(distributorId);

        if (distributorId === null || distributorId.length === 0) {
            $('#expirationDate')
                .removeClass('required')
                .siblings('label')
                .removeClass('required')
                .find('abbr')
                .remove();
            $('#distributorCategoryId')
                .removeClass('required')
                .siblings('label')
                .removeClass('required')
                .find('abbr')
                .remove();
            $('#distributorLocations')
                .removeClass('required')
                .siblings('label')
                .removeClass('required')
                .find('abbr')
                .remove();
        } else {

            $('#expirationDate').addClass('required')
                .siblings('label')
                .addClass('required')
                .append('<abbr>*</abbr>');

            $('#distributorCategoryId').addClass('required')
                .siblings('label')
                .addClass('required')
                .append('<abbr>*</abbr>');

            $('#distributorLocations').addClass('required')
                .siblings('label')
                .addClass('required')
                .append('<abbr>*</abbr>');
        }
    };

    addon.buildNetworkAddonCategories = function(distributorId) {
        if (distributorId === null || distributorId.length === 0) {
            $('#distributorCategoryId').parent('p').hide('fast');
            return;
        }

        addon.fetchNetworkAddonCategories(distributorId).then(
            function(categories) {

                var $distributorCategoryId = $('#distributorCategoryId'),
                    options = '<option></option>',
                    setValue = null;

                addon.getDefaultValue('categoryId', null).then(
                    function(result) {
                        setValue = result;

                        $distributorCategoryId.empty();

                        if (!$.isEmptyObject(categories)) {
                            $.each(categories, function(index, value) {
                                if (setValue == index) {
                                    options += '<option value="' + index + '" selected="selected">' + value + '</option>';
                                } else {
                                    options += '<option value="' + index + '">' + value + '</option>';
                                }
                            });

                            $distributorCategoryId.append(options);

                            if (!setValue) {
                                $distributorCategoryId.val($('#categoryId').val());
                            }

                            $('#distributorCategoryId').parent('p').show('fast');
                        }

                    }, function() {

                    }
                );


            },
            function() {

            }
        );
    };

    addon.fetchNetworkAddonCategories = function(farmId) {
        var deferredObj = $.Deferred();

        if (addon.cacheCategories.hasOwnProperty(farmId)) {
            deferredObj.resolve(addon.cacheCategories.farmId);
            return deferredObj.promise();
        }

        $.ajax({
            dataType: 'json',
            url: 'http://' + farmId + '.' + addon.config.domain + '/service/shares/addon-categories',
            cache: false,
            success: function(data) {
                addon.cacheCategories.farmId = data;
                deferredObj.resolve(data);
            }
        });

        return deferredObj.promise();
    };

    addon.buildNetworkLocations = function(distributorId) {
        if (distributorId === null || distributorId.length === 0) {
            $('#distributorLocations').parent('p').hide('fast');
            return;
        }

        addon.fetchNetworkLocations(distributorId).then(
            function(locations) {

                var $distributorLocations = $('#distributorLocations'),
                    options = '',
                    setValue = null;

                addon.getDefaultValue('locations', []).then(
                    function(result) {
                        setValue = result;

                        $distributorLocations.empty();

                        if ($.isArray(locations) && locations.length) {
                            $.each(locations, function(index, value) {
                                var dayOfWeek = value.dayOfWeek,
                                    time = value.timeStart.split(':'),
                                    timeEnd = value.timeEnd.split(':'),
                                    title = '';

                                if (dayOfWeek == 7) {
                                    dayOfWeek = 0;
                                }

                                var date = Date.today().moveToDayOfWeek(dayOfWeek);

                                date.set({
                                    millisecond: 000,
                                    second: 00,
                                    hour: parseInt(time[0]),
                                    minute: parseInt(time[1])
                                });

                                title += date.toString('dddd') + ', ' + date.toString('t');

                                date.set({
                                    millisecond: 000,
                                    second: 00,
                                    hour: parseInt(timeEnd[0]),
                                    minute: parseInt(timeEnd[1])
                                });

                                title += ' - ' + date.toString('t');

                                if ($.inArray(value.id, setValue) !== -1) {
                                    options += '<option value="' + value.id
                                        + '" title="' + title + '" selected="selected">'
                                        + value.name + ',' + value.city + '</option>';
                                } else {
                                    options += '<option value="' + value.id
                                        + '" title="' + title + '">'
                                        + value.name + ',' + value.city + '</option>';
                                }
                            });

                            $distributorLocations.append(options);
                            $distributorLocations.multiselect('refresh');

                            $('#distributorLocations').parent('p').show('fast');
                        }
                    }, function() {

                    }
                );
            },
            function() {

            }
        );
    };

    addon.fetchNetworkLocations = function(farmId) {
        var deferredObj = $.Deferred();

        if (addon.cacheLocations.hasOwnProperty(farmId)) {
            deferredObj.resolve(addon.cacheLocations.farmId);
            return deferredObj.promise();
        }

        $.ajax({
            dataType: 'json',
            url: 'http://' + farmId + '.' + addon.config.domain + '/service/shares/locations',
            cache: false,
            success: function(data) {
                addon.cacheLocations.farmId = data;
                deferredObj.resolve(data);
            }
        });

        return deferredObj.promise();
    };

    addon.buildNetworkCertifications = function(distributorId) {
        if (distributorId === null || distributorId.length === 0) {
            $('#distributorCertification').parent('p').hide('fast');
            return;
        }

        addon.fetchNetworkCertifications(distributorId).then(
            function(certifications) {

                var $distributorCertification = $('#distributorCertification'),
                    options = '',
                    setValue = null;

                addon.getDefaultValue('certification', '').then(
                    function(result) {
                        setValue = result;

                        $distributorCertification.empty();

                        if (!$.isEmptyObject(certifications)) {

                            $.each(certifications, function(index, value) {

                                if (index === setValue) {
                                    options += '<option value="' + index
                                        + '" selected="selected">'
                                        + value + '</option>';
                                } else {
                                    options += '<option value="' + index
                                        + '">' + value + '</option>';
                                }
                            });

                            $distributorCertification.append(options);

                            if (!setValue) {

                                $distributorCertification.val($('#certification').val());
                            }

                            $('#distributorCertification').parent('p').show('fast');
                        }
                    }, function() {

                    }
                );
            },
            function() {

            }
        );
    };

    addon.fetchNetworkCertifications = function(farmId) {
        var deferredObj = $.Deferred();

        if (addon.cacheCertifications.hasOwnProperty(farmId)) {
            deferredObj.resolve(addon.cacheCertifications.farmId);
            return deferredObj.promise();
        }

        $.ajax({
            dataType: 'json',
            url: 'http://' + farmId + '.' + addon.config.domain + '/service/shares/certifications',
            cache: false,
            success: function(data) {
                addon.cacheCertifications.farmId = data;
                deferredObj.resolve(data);
            }
        });

        return deferredObj.promise();
    };

    addon.getDefaultValue = function(key, defaultVal) {
        var deferredObj = $.Deferred();

        if (addon.extenalAddon === false && addon.config.distributorId && addon.config.externalId) {
            // load up external record
            $.ajax({
                dataType: 'json',
                url: 'http://' + addon.config.distributorId + '.' + addon.config.domain + '/service/shares/addon',
                cache: false,
                data: {
                    externalId: addon.config.externalId,
                    vendorId: addon.config.vendorId
                },
                success: function(data) {
                    if (data === false) {
                        addon.extenalAddon = null;
                    } else {
                        addon.extenalAddon = data;
                    }

                    deferredObj.resolve(addon._getDefaultValue(key, defaultVal));
                }
            });
        } else {
            deferredObj.resolve(addon._getDefaultValue(key, defaultVal));
        }

        return deferredObj.promise();
    };

    addon._getDefaultValue = function(key, defaultVal) {
        var domId = '#distributor' + (key.charAt(0).toUpperCase() + key.slice(1));

        var object = $(domId),
            val = object.val();

        if (val) {
            return val;
        }

        if ($.isPlainObject(addon.extenalAddon) && addon.extenalAddon.hasOwnProperty(key)) {
            return addon.extenalAddon[key];
        }

        return defaultVal;
    };

    addon.inventoryChange = function() {
        var inventory = $(this).val();

        if (inventory === null || inventory.length === 0) {
            $('#inventoryMinimumAlert').val('').parent('p').hide('normal');
        } else {
            $('#inventoryMinimumAlert').parent('p').show('normal');
        }
    };

    /**
     * Category change listener
     */
    addon.changeCategory = function (){
        var category = $(this),
            newCategory = $("#newCategory"),
            newCategoryInput = newCategory.children('input');

        if (category.val() === '_new') {

            newCategory.show();
            newCategoryInput.addClass('required').focus();
        } else {
            newCategory.hide();
            newCategoryInput.removeClass('required');
        }
    };

    /**
     * Price unit change listener
     */
    addon.changePriceUnit = function() {
        var $priceUnit = $('#priceUnit'),
            priceBy = $('#priceBy').val(),
            $unitType = $('#unitType');

        switch (priceBy) {
            case 'UNIT' :
                $unitType.parent('p').hide('normal');

                if (this.id === 'priceBy') {
                    var options = '<option value="UNIT">UNIT</option>';
                    $unitType.append(options);
                }

                $priceUnit.text('/ ' + addon.config.lang.unit);
                break;

            case 'WEIGHT' :
                $unitType.parent('p').show('normal');

                if (this.id === 'priceBy') {
                    $unitType.empty();

                    var options = '<option value="G"> ' + addon.config.lang.g + '</option>' +
                        '<option value="KG"> ' + addon.config.lang.kg + '</option>' +
                        '<option value="OZ"> ' + addon.config.lang.oz + '</option>' +
                        '<option value="LB"> ' + addon.config.lang.lb + '</option>';

                    $unitType.append(options);
                }

                var unit = $unitType.val();

                if (!unit || unit.length < 1) {
                    $priceUnit.text('/ ' + addon.config.lang.weight);
                } else {
                    $priceUnit.text('/ ' + unit.toLowerCase());
                }
                break;
        }
    };
})();
