var hh = hh || {};

/**
 * Namespace object utility
 * @param {string} namespaceString
 */
hh.namespace = function (namespaceString) {
    var parts = namespaceString.split('.'),
        parent = window,
        currentPart = '';

    for(var i = 0, length = parts.length; i < length; i++) {
        currentPart = parts[i];
        parent[currentPart] = parent[currentPart] || {};
        parent = parent[currentPart];
    }

    return parent;
};

(function() {

    var lib = hh.namespace('hh.lib.html');

    lib.entityMap = {
        escape: {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#x27;',
            '/': '&#x2F;'
        }
    };

    /**
     * Escape string for HTML
     * @param {string} string
     * @returns {string}
     */
    lib.escape = function(string) {

        if (string == null) {
            return '';
        }

        return ('' + string).replace(new RegExp('[&<>"\'\/]', 'g'), function(match) {
            return lib.entityMap['escape'][match];
        });
    };

})();