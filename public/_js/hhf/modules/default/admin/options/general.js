(function() {
    var options = hh.namespace('hhf.modules.default.admin.options.general');

    options.config = {};

    options.init = function(config) {
        $.extend(options.config, config);

        jQuery.validator.addMethod(
            "date",
            function(value, element) {
                return this.optional(element) || ((Date.parse(value) != null) ? true : false);
            },
            options.config.lang.validate.date
        );

        $(document).ready(options.documentInit);
    };

    options.documentInit = function() {

        $.fn.dataTableExt.aoFeatures.push({
            "fnInit": function(oSettings) {
                return $('<ul class="dataTables_add dropdown-container"><li class="dropdown btn-group">' +
                    '<a href="" title="' + options.config.lang.addNetwork +
                    '" id="child-network-join" class="btn">' + options.config.lang.add + '</a></li></ul>')[0];
            },
            "cFeature": "a",
            "sFeature": "Add"
        });

        $.fn.dataTableExt.oApi.fnReloadAjax = function ( oSettings, sNewSource, fnCallback, bStandingRedraw )
        {
            // DataTables 1.10 compatibility - if 1.10 then versionCheck exists.
            // 1.10s API has ajax reloading built in, so we use those abilities
            // directly.
            if ( $.fn.dataTable.versionCheck ) {
                var api = new $.fn.dataTable.Api( oSettings );

                if ( sNewSource ) {
                    api.ajax.url( sNewSource ).load( fnCallback, !bStandingRedraw );
                }
                else {
                    api.ajax.reload( fnCallback, !bStandingRedraw );
                }
                return;
            }

            if ( sNewSource !== undefined && sNewSource !== null ) {
                oSettings.sAjaxSource = sNewSource;
            }

            // Server-side processing should just call fnDraw
            if ( oSettings.oFeatures.bServerSide ) {
                this.fnDraw();
                return;
            }

            this.oApi._fnProcessingDisplay( oSettings, true );
            var that = this;
            var iStart = oSettings._iDisplayStart;
            var aData = [];

            this.oApi._fnServerParams( oSettings, aData );

            oSettings.fnServerData.call( oSettings.oInstance, oSettings.sAjaxSource, aData, function(json) {
                /* Clear the old information from the table */
                that.oApi._fnClearTable( oSettings );

                /* Got the data - add it to the table */
                var aData =  (oSettings.sAjaxDataProp !== "") ?
                    that.oApi._fnGetObjectDataFn( oSettings.sAjaxDataProp )( json ) : json;

                for ( var i=0 ; i<aData.length ; i++ )
                {
                    that.oApi._fnAddData( oSettings, aData[i] );
                }

                oSettings.aiDisplay = oSettings.aiDisplayMaster.slice();

                that.fnDraw();

                if ( bStandingRedraw === true )
                {
                    oSettings._iDisplayStart = iStart;
                    that.oApi._fnCalculateEnd( oSettings );
                    that.fnDraw( false );
                }

                that.oApi._fnProcessingDisplay( oSettings, false );

                /* Callback user function - for event handlers etc */
                if ( typeof fnCallback == 'function' && fnCallback !== null )
                {
                    fnCallback( oSettings );
                }
            }, oSettings );
        };

        var isChildInit = false,
            isParentInit = false,
            childNetworks = null,
            parentNetworks = null;

        $("#tabs").tabs({
            show: function (event, ui) {
                switch ($(ui.panel).attr('id')) {
                    case 'parent-network':
                        if (!isParentInit) {
                            parentNetworks = $("#parent-networks").dataTable({
                                "bJQueryUI": true,
                                "bProcessing": true,
                                "bPaginate": false,
                                "sAjaxSource": window.location.pathname + '?a=parent-network' ,
                                "sDom": '<"H"<"clear">r<"right"i>>t<"F"<"right"i>>',
                                "aoColumnDefs": [
                                    {
                                        "bSearchable": false,
                                        "bVisible": false,
                                        "aTargets": [ 0 ]
                                    }
                                ],
                                "aoColumns": [
                                    {},
                                    {},
                                    {},
                                    {
                                        "sClass": "right",
                                        "fnRender": function (oObj) {
                                            console.log(oObj);
                                            var html = '<select data-id="' + oObj.aData[0] + '" class="parent-network-toggle" style="width: 100%">';

                                            html += '<option ' + (oObj.aData[3] == 'PENDING' ? ' selected ' : '') + 'value="PENDING">' + options.config.lang.pending + '</option>';
                                            html += '<option ' + (oObj.aData[3] == 'APPROVED' ? ' selected ' : '') + 'value="APPROVED">' + options.config.lang.approved + '</option>';
                                            html += '<option ' + (oObj.aData[3] == 'CLOSED' ? ' selected ' : '') + 'value="CLOSED">' + options.config.lang.closed + '</option>';

                                            return html;
                                        }
                                    }
                                ]
                            })
                                .delegate("tbody tr", "mouseover mouseout", function(e) {
                                    if (e.type == "mouseover") {
                                        $(e.currentTarget).addClass('hover');
                                    } else {
                                        $(e.currentTarget).removeClass('hover');
                                    }
                                });

                            isParentInit = true;
                        }

                        break;

                    case 'child-network':
                        if (!isChildInit) {
                            childNetworks = $("#child-networks").dataTable({
                                "bJQueryUI": true,
                                "bProcessing": true,
                                "bPaginate": false,
                                "sAjaxSource": window.location.pathname + '?a=child-network',
                                "sDom": '<"H"a<"clear">r<"right"i>>t<"F"<"right"i>>'
                            })
                                .delegate("tbody tr", "mouseover mouseout", function(e) {
                                    if (e.type == "mouseover") {
                                        $(e.currentTarget).addClass('hover');
                                    } else {
                                        $(e.currentTarget).removeClass('hover');
                                    }
                                });

                            isChildInit = true;
                        }

                        break;
                }
            }
        }).on('click', '#child-network-join', function(e) {
            e.stopPropagation();

            jQuery.get(
                window.location.pathname,
                {
                    a: 'distributors'
                },
                function(result) {
                    var $modal = $('#child-network-modal'),
                        $select =  $modal.find('select');

                    $select.empty();

                    if (result && result.length) {
                        $modal.find('form').show();
                        $modal.find('.info').hide();

                        var options = '<option></option>';

                        for (var l = result.length, c = 0; c < l; ++c) {
                            options += '<option value="' + hh.lib.html.escape(result[c].id) + '">' + hh.lib.html.escape(result[c].name) + '</option>';
                        }

                        $select.append(options);
                    } else {
                        $modal.find('form').hide();
                        $modal.find('.info').show();
                    }

                    $modal.dialog({
                        modal: true,
                        autoOpen: true,
                        width: 650,
                        height: 200,
                        resizable: true,
                        autoResize: true,
                        open: function() {
                            var $modal = $(this);

                            $(this).find('form').validate({
                                errorContainer: $("#childFormError"),
                                submitHandler: function(form) {
                                    jQuery.post(
                                        window.location.pathname + '?a=add-distributor',
                                        $(form).serialize(),
                                        function() {
                                            childNetworks.fnReloadAjax();
                                            $modal.dialog('close');
                                        }
                                    );
                                }
                            });
                        }
                    });
                }
            );

            return false;
        }).on('click', '.parent-network-toggle', function() {
            jQuery.post(
                window.location.pathname + '?a=toggle-relation',
                {
                    relationId: $(this).data('id'),
                    status: $(this).val()
                },
                function() {

                }
            );
        });

        $("#options").validate({
            rules: {
                "paypal-businessType" : {
                    required : "#paypal-enabled_1:checked"
                },
                "paypal-averagePrice" : {
                    required : "#paypal-enabled_1:checked"
                },
                "paypal-averageMonthlyVolume" : {
                    required : "#paypal-enabled_1:checked"
                },
                "paypal-percentageRevenueFromOnline" : {
                    required : "#paypal-enabled_1:checked"
                },
                "paypal-business" : {
                    required : "#paypal-enabled_1:checked"
                },
                "paypal-certId" : {
                    required : "#paypal-enabled_1:checked"
                },
                "paypal-cert" : {
                    required : "#paypal-enabled_1:checked"
                },
                "paypal-privkey" : {
                    required : "#paypal-enabled_1:checked"
                },
                "paypal-signcert" : {
                    required : "#paypal-enabled_1:checked"
                },
                "paypal-dateOfEstablishment" : {
                    date: true,
                    required : "#paypal-enabled_1:checked"
                },
                "paypal-dateOfBirth" : {
                    date : true,
                    required : "#paypal-enabled_1:checked"
                }
            },
            messages : {
                "paypal-dateOfEstablishment" : {
                    "date" : options.config.lang.validate.date
                },
                "paypal-dateOfBirth" : {
                    "date" : options.config.lang.validate.date
                }
            },
            errorContainer: $("#formError")
        });

        $("input#paypal-dateOfEstablishment").change(options.convertDate);
        $("input#paypal-dateOfBirth").change(options.convertDate);

        $("#paypal-agreement").click(function(e){
            e.preventDefault();
            var $this = $(this),
                horizontalPadding = 30,
                verticalPadding = 30;

            $('<iframe id="externalSite" class="externalSite" src="' + this.href + '" />').dialog({
                title: $this.attr('title'),
                autoOpen: true,
                width: 800,
                height: 500,
                modal: true,
                resizable: true,
                autoResize: true
            }).width(800 - horizontalPadding).height(500 - verticalPadding);
        });

        $("button.facebook-new").click(function(){
            window.location = options.config.facebookUrl;
        });

        $("button.twitter-new").click(function(){
            window.location = '/admin/default/twitter_request';
        });

        $("input[name=paypal-enabled]").change(function(){
            if ($(this).val() == 1) {
                $(".paypal").show('normal');
            } else {
                $(".paypal").hide('normal');
            }
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

    options.convertDate = function () {
        var $this = $(this);
        var val = $this.val();

        var timestamp = Date.parse(val);
        if (timestamp != null) {
            $this.val(timestamp.toString('yyyy-MM-dd'));
        }
    };

})();
