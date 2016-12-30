(function() {
    var hhvariableReplaceRegex = /{{ [^}]+ }}|{% [^\}]+ %}/g;
    
    CKEDITOR.plugins.add(
        'hh', 
        {
            requires : ['richcombo'],
            init : function( editor ) {
                
                editor.ui.addRichCombo(
                    'hhDelivery', {
                        label : editor.config.hh.lang.delivery.label,
                        title : editor.config.hh.lang.delivery.title,
                        className : 'cke_hhdelivery',
                        panel : {
                            css : editor.skin.editor.css.concat( editor.config.contentsCss ),
                            multiSelect : false,
                            attributes : { 'aria-label' : editor.config.hh.lang.delivery.title }
                        },
                        init : function() {
                            editor.focus();
                            
                            for (var x = 0; x < this._options.length; ++x) {
                                this.add(
                                    this._options[x]['id'],
                                    this._options[x]['value'],
                                    this._options[x]['title']
                                );
                            }
                        },
                        onClick : function(value) {
                            editor.focus();
                            
                            if (value.indexOf('W') == -1) {
                                return;
                            }
                            
                            editor.fire('saveSnapshot');

                            $.getJSON(
                                '/shares',
                                {
                                    week: value
                                },
                                function (data, textStatus, jqXHR) {
                                    var scrollTop = $(window.document).scrollTop();
                                    
                                    editor.insertHtml(data + "<p></p>");
                                    
                                    $(window.document).scrollTop(scrollTop);
                                }
                            );
                        },
                        onRender : function() {
                            var $this = this;
                            
                            $.getJSON(
                                '/shares',
                                {
                                    start: 0,
                                    length: 10
                                },
                                function (data, textStatus, jqXHR) {
                                    $this._options = [];
                                    
                                    if ($.isArray(data) && data.length) {
                                        for (var x = 0; x < data.length; ++x) {
                                            
                                            var weekArray = data[x].week.split("W");
                                            var week = weekArray[1],
                                                year = weekArray[0],
                                                date = new Date(),
                                                range = "";

                                            date.setYear(year);
                                            date.setWeek(week);

                                            if (date.getDay() > 1) {
                                                date.setDate(date.getDate() - (date.getDay() - 1));
                                            } else if (date.getDay() < 1) {
                                                date.setDate(date.getDate() + 1);
                                            }

                                            range = date.toString("yyyy-MM-dd") + " - ";

                                            date.setDate(date.getDate() + 6);

                                            range += date.toString("yyyy-MM-dd");
                                            
                                            var weekTxt = editor.config.hh.lang.delivery.week;
                                            weekTxt.replace('%w', week).replace('%y', year);
                                            
                                            $this._options.push({
                                                id: data[x].week,
                                                value: weekTxt.replace('%w', week).replace('%y', year),
                                                title: range
                                            });
                                        }
                                    }
                                }
                            );
                            
                            editor.on(
                                'selectionChange', 
                                function( ev ) {
                                    if (this._.value) {
                                        this.setValue('', editor.config.hh.lang.delivery.label);
                                    }
                                },
                                this
                            );
                        }
                    }
                );

                editor.ui.addRichCombo(
                    'hhVariable', {
                        label : editor.config.hh.lang.variable.label,
                        title : editor.config.hh.lang.variable.title,
                        className : 'cke_hhvariable',
                        panel : {
                            css : editor.skin.editor.css.concat(editor.config.contentsCss),
                            multiSelect : false,
                            attributes : { 'aria-label' : editor.config.hh.lang.variable.title }
                        },
                        init : function() {
                            editor.on('contentDom', function() {
                                editor.document.getBody().on('resizestart', function(evt) {
                                    if (editor.getSelection().getSelectedElement().data( 'cke-hhvariable' )) {
                                        evt.data.preventDefault();
                                    }
                                });
                            });
                            
                            editor.focus();
                            
                            this.startGroup(editor.config.hh.lang.variable.customer.title);
                            
                            this.add(
                                '{{ customer.firstName }}',
                                editor.config.hh.lang.variable.customer.firstName,
                                editor.config.hh.lang.variable.customer.firstNameTooltip
                            );
                                
                            this.add(
                                '{{ customer.lastName }}',
                                editor.config.hh.lang.variable.customer.lastName,
                                editor.config.hh.lang.variable.customer.lastNameTooltip
                            );

                            this.add(
                                '{{ customer.address }}',
                                editor.config.hh.lang.variable.customer.address,
                                editor.config.hh.lang.variable.customer.addressTooltip
                            );

                            this.add(
                                '{{ customer.address2 }}',
                                editor.config.hh.lang.variable.customer.address2,
                                editor.config.hh.lang.variable.customer.address2Tooltip
                            );

                            this.add(
                                '{{ customer.city }}',
                                editor.config.hh.lang.variable.customer.city,
                                editor.config.hh.lang.variable.customer.cityToolip
                            );

                            this.add(
                                '{{ customer.state }}',
                                editor.config.hh.lang.variable.customer.state,
                                editor.config.hh.lang.variable.customer.stateTooltip
                            );

                            this.add(
                                '{{ customer.zipCode }}',
                                editor.config.hh.lang.variable.customer.zipCode,
                                editor.config.hh.lang.variable.customer.zipCode
                            );

                            this.add(
                                '{{ customer.telephone }}',
                                editor.config.hh.lang.variable.customer.telephone,
                                editor.config.hh.lang.variable.customer.telephone
                            );

                            this.add(
                                '{{ customer.email }}',
                                editor.config.hh.lang.variable.customer.email,
                                editor.config.hh.lang.variable.customer.email
                            );

                            this.add(
                                '{{ customer.balance }}',
                                editor.config.hh.lang.variable.customer.balance,
                                editor.config.hh.lang.variable.customer.balance
                            );

                            this.add(
                                '{{ customer.userName }}',
                                editor.config.hh.lang.variable.customer.userName,
                                editor.config.hh.lang.variable.customer.userName
                            );
                                
                            this.startGroup(editor.config.hh.lang.variable.farm.title);
                            
                            this.add(
                                '{{ customer.name }}',
                                editor.config.hh.lang.variable.customer.name,
                                editor.config.hh.lang.variable.customer.nameTooltip
                            );

                            this.add(
                                '{{ farm.address }}',
                                editor.config.hh.lang.variable.farm.address,
                                editor.config.hh.lang.variable.farm.addressTooltip
                            );

                            this.add(
                                '{{ farm.address2 }}',
                                editor.config.hh.lang.variable.farm.address2,
                                editor.config.hh.lang.variable.farm.address2Tooltip
                            );

                            this.add(
                                '{{ farm.city }}',
                                editor.config.hh.lang.variable.farm.city,
                                editor.config.hh.lang.variable.farm.cityToolip
                            );

                            this.add(
                                '{{ farm.state }}',
                                editor.config.hh.lang.variable.farm.state,
                                editor.config.hh.lang.variable.farm.stateTooltip
                            );

                            this.add(
                                '{{ farm.zipCode }}',
                                editor.config.hh.lang.variable.farm.zipCode,
                                editor.config.hh.lang.variable.farm.zipCode
                            );

                            this.add(
                                '{{ farm.telephone }}',
                                editor.config.hh.lang.variable.farm.telephone,
                                editor.config.hh.lang.variable.farm.telephone
                            );

                            this.add(
                                '{{ farm.email }}',
                                editor.config.hh.lang.variable.farm.email,
                                editor.config.hh.lang.variable.farm.email
                            );

                        },
                        onClick : function(value) {
                            editor.focus();
                            editor.fire('saveSnapshot');

                            CKEDITOR.plugins.hhvariable.createPlaceholder(editor, value);
                        },
                        onRender : function() {
                            editor.on(
                                'selectionChange', 
                                function(ev) {
                                    if (this._.value) {
                                        this.setValue('', editor.config.hh.lang.variable.label);
                                    }
                                },
                                this
                            );
                        }
                    }
                );
                
            },
            afterInit: function(editor) {
                editor.addCss(
                    '.cke_hhvariable' +
                    '{' +
                        'background-color: #ffff00;' +
                        ( CKEDITOR.env.gecko ? 'cursor: default;' : '' ) +
                    '}'
                );
                
                var dataProcessor = editor.dataProcessor,
                    dataFilter = dataProcessor && dataProcessor.dataFilter,
                    htmlFilter = dataProcessor && dataProcessor.htmlFilter;

                if (dataFilter) {
                    dataFilter.addRules({
                        text: function(text) {
                            return text.replace(hhvariableReplaceRegex, function(match) {
                                return CKEDITOR.plugins.hhvariable.createPlaceholder(editor, match, 1);
                            });
                        }
                    });
                }

                if (htmlFilter) {
                    htmlFilter.addRules({
                        elements: {
                            'span' : function(element) {
                                if (element.attributes && element.attributes['data-cke-hhvariable']) {
                                    delete element.name;
                                }
                            }
                        }
                    });
                }
            }
        }
    );

})();

CKEDITOR.plugins.hhvariable = {
	createPlaceholder: function(editor, text, isGet) {
		var element = new CKEDITOR.dom.element('span', editor.document);
        
		element.setAttributes({
            contentEditable: 'false',
            'data-cke-hhvariable': 1,
            'class': 'cke_hhvariable'
        });

		text && element.setText(text);

		if (isGet) {
			return element.getOuterHtml();
        }

        editor.insertElement(element);
        
		return null;
	},
	getSelectedPlaceHoder: function(editor) {
		var range = editor.getSelection().getRanges()[0];
		range.shrink(CKEDITOR.SHRINK_TEXT);
		var node = range.startContainer;
		while(node && !(node.type == CKEDITOR.NODE_ELEMENT && node.data('cke-hhvariable'))) {
			node = node.getParent();
        }
		return node;
	}
};