(function() {
    var options = hh.namespace('hhf.modules.shares.admin.options');

    options.config = {
        ckEditor: {
            customConfig: '',
            toolbar:
            [
                ['Bold', 'Italic', 'Underline', 'Strike'],
                [
                    'NumberedList', 'BulletedList',
                    '-', 'Outdent', 'Indent', 'Blockquote'
                ],
                ['Link', 'Unlink'],
                ['Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord'],
                ['Undo', 'Redo'],
                ['RemoveFormat']
            ],
            colorButton_enableMore: false,
            disableNativeSpellChecker: false
        }
    };

    options.init = function(config) {
        $.extend(options.config, config);

        $(document).ready(options.documentInit);
    };

    options.documentInit = function() {

        $('#shares-plansDetails').ckeditor(
            function() {
                var editor = $('#shares-plansDetails').ckeditorGet();
                editor.on('blur', function() {
                    this.updateElement();
                });
            },
            options.config.ckEditor
        );

        $('#options').submit(function() {
            $('#shares-plansDetails').ckeditorGet().updateElement();

            var $addOnCutOffTime = $('input[name=shares-addOnCutOffTime]'),
                val = $addOnCutOffTime.val();

            if (!$.isNumeric(val) || val > 0) {

                var timestamp = Date.parse(val);
                if (timestamp !== null) {
                    $addOnCutOffTime.val(timestamp.toString('HH:mm'));
                } else {
                    $addOnCutOffTime.val('');
                }
            }
        });

        $('input[name=shares-plansFixed]').change(function() {
            if ($(this).val() == 1) {
                $('#shares-plansFixedDatesField').show('normal');
            } else {
                $('#shares-plansFixedDatesField').hide('normal');
            }
        });

        $('input[name=shares-addOnCutOffTime]').change(options.convertTime);

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

    options.convertTime = function() {
        var $this = $(this),
            val = $this.val();

        if (!$.isNumeric(val) || val > 0) {
            if (!/[^0-9: ]/.test(val)) {
                val += 'am';
            }

            var timestamp = Date.parse(val);

            if (timestamp !== null) {
                $this.val(timestamp.toString('h:mm tt'));
            } else {
                $this.val('');
            }

        }
    };

})();
