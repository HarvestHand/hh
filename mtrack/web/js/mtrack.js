function mtrack_scroll_into_view(element)
{
  element = $(element);
  var cont = element.parent();
  var cont_top = cont.scrollTop();
  var cont_bot = cont_top + cont.height();
  var elem_top = cont_top + element.position().top;
  var elem_bot = elem_top + element.height();
  var new_top;
  if (elem_top < cont_top) {
    new_top = elem_top;
  } else if (elem_bot > cont_bot) {
    new_top = elem_bot - cont.height();
  } else {
    return; // already in view
  }
  cont.animate({scrollTop: new_top}, {duration: 300, easing: "easeOutQuint"});
}

function mtrack_markitup(ele, preview, opts)
{
  var trac_markup = [
      {
        name:'Heading 1', key:'1',
        openWith:'= ', closeWith:' =',
        placeHolder:'Heading 1'
      },
      {
        name:'Heading 2', key:'2',
        openWith:'== ', closeWith:' ==',
        placeHolder:'Heading 2'
      },
      {
        name:'Heading 3', key:'3',
        openWith:'=== ', closeWith:' ===',
        placeHolder:'Heading 3'
      },
      {
        name:'Heading 4', key:'4',
        openWith:'==== ', closeWith:' ====',
        placeHolder:'Heading 4'
      },
      {
        name:'Heading 5', key:'5',
        openWith:'===== ', closeWith:' =====',
        placeHolder:'Heading 5'
      },
      {separator:'---------------' },

      {name:'Bold', key:'B', openWith:"'''", closeWith:"'''"},
      {name:'Italic', key:'I', openWith:"''", closeWith:"''"},
      {name:'Strike through', key:'S', openWith:'~~', closeWith:'~~'},
      {separator:'---------------' },

      {
        name:'Bulleted list', key: 'L',
        openWith:' * ', multiline: true
      },
      {
        name:'Numeric list', key: 'N',
        openWith:' 1. ', multiline: true
      },
      {separator:'---------------' },

      {name:'Quotes', openWith:'(!(> |!|>)!)'},
      {name:'Code', openBlockWith:'{{{\n', closeBlockWith:'\n}}}'}
    ];
  var markdown = [
      {
        name:'Heading 1', key:'1',
        openWith:'# ',
        placeHolder:'Heading 1'
      },
      {
        name:'Heading 2', key:'2',
        openWith:'## ',
        placeHolder:'Heading 2'
      },
      {
        name:'Heading 3', key:'3',
        openWith:'### ',
        placeHolder:'Heading 3'
      },
      {
        name:'Heading 4', key:'4',
        openWith:'#### ',
        placeHolder:'Heading 4'
      },
      {
        name:'Heading 5', key:'5',
        openWith:'##### ',
        placeHolder:'Heading 5'
      },
      {separator:'---------------' },

      {name:'Bold', key:'B', openWith:"**", closeWith:"**"},
      {name:'Italic', key:'I', openWith:"_", closeWith:"_"},
      {name:'Strike through', key:'S', openWith:'<del>', closeWith:'</del>'},
      {separator:'---------------' },

      {
        name:'Bulleted list', key: 'L',
        openWith:'* ', multiline: true
      },
      {
        name:'Numeric list', key: 'N',
        openWith:'1. ', multiline: true
      },
      {separator:'---------------' },

      {name:'Quotes', openWith:'(!(> |!|>)!)'},
      {name:'Code', openBlockWith: '```\n', closeBlockWith: '\n```' }
    ];
  var common = [
      {separator:'---------------' },
      {name:'Templates',  className: 'markItUpHelp',
        dropMenu: [
          {
            name: 'Help on templates',
            call: function() {
              window.open(ABSWEB + 'help.php/WikiTemplates', 'mtrackhelp');
              return false;
            }
          }
        ]
      },
      {separator:'---------------' },
      { name:'Wiki Formatting Help',
        className: 'markItUpHelp',
        call: function() {
          var url = ABSWEB + 'help.php/';
          if (mtrack_wiki_syntax == 'markdown') {
            url += 'WikiFormatting';
          } else {
            url += 'TracWikiSyntax';
          }
          window.open(url, 'mtrackhelp');
          return false;
        }
      }
  ];

  var mopts = $.extend({
    nameSpace:          "wiki",
    previewParserPath:  ABSWEB + "markitup-preview.php",
    root: ABSWEB + "js",
    onShiftEnter:       {keepDefault:false, replaceWith:'\n\n'},
    afterInsert: function () {
      ele.trigger('markitupAfterInsert');
    },
    markupSet:  mtrack_wiki_syntax == 'markdown' ?
                  markdown : trac_markup
  }, opts);

  /* append common menu items */
  _.each(common, function (m) {
    mopts.markupSet[mopts.markupSet.length] = m;
  });

  /* add in templates */
  var menu = null;
  _.each(mopts.markupSet, function (m) {
    if (m.name == 'Templates') {
      menu = m.dropMenu;
    }
  });
  /* we load the template text on-demand, caching it for subsequent re-use
   * in a given page load */
  _.each(mtrack_wiki_templates, function (pagename) {
    menu.push({
      name: pagename,
      replaceWith: function (h) {
        /* is it in our template cache ? */
        if (pagename in mtrack_wiki_template_cache) {
          return mtrack_wiki_template_cache[pagename];
        }
        /* otherwise we need to fetch it */
        var wiki = new MTrackWiki({id: "Templates/" + pagename});
        wiki.fetch({
          success: function(model, data) {
            /* put it in the cache */
            mtrack_wiki_template_cache[pagename] = data.content;
            /* drive the markitup instance (we're no longer in the original
             * calling context, so we can't simply return the value) */
            $.markItUp({target: h.textarea, replaceWith: data.content});
          }
        });
      }
    });
  });

  if (preview) {
    mopts.markupSet.push({
      separator:'---------------'
    });
    mopts.markupSet.push({
      name: 'Preview',
      call: 'preview',
      className: 'preview'
    });
  }

  ele.markItUp(mopts);
}

// Syntax highlighting
var mtrack_hl_color_scheme = 'wezterm';

function mtrack_apply_wiki_javascript($ele)
{
  var size = 20;
  var pager_html =
"<div class='pager'>" +
"<button class='first'>First</button>" +
"<button class='prev'>Prev</button>" +
"<input type='text' readonly='readonly' class='pagedisplay'>" +
"<button class='next'>Next</button>" +
"<button class='last'>Last</button>" +
"<select class='pagesize'>" +
"      <option value='10'>10</option>" +
"      <option value='20'>20</option>" +
"      <option value='30'>30</option>" +
"      <option value='40'>40</option>" +
"    </select>" +
"</div>"
  ;

  $("table.report, table.wiki", $ele).tablesorter({
    textExtraction: function(node) {
      var kid = node.childNodes[0];
      if (kid && kid.tagName == 'ABBR') {
        // assuming that this abbr is of class='timeinterval'
        return kid.title;
      }
      // default 'simple' behavior
      if (kid && kid.hasChildNodes()) {
        return kid.innerHTML;
      }
      return node.innerHTML;
    }
  }).each(function () {
    var tbl = this;
    /* this works well, but there's no good way to detect if we're
     * being printed, so we skip it for now */
    return;
    if (tbl.tBodies.length && tbl.tBodies[0].rows.length > size) {
      var p = $(pager_html);
      $(tbl).after(p);
      $('.pagesize', p).val(size);
      $(tbl).tablesorterPager({
        size: size,
        container: p,
        positionFixed: false
      });
    }
  });
  function applyhl(name) {
    if (mtrack_hl_color_scheme != '') {
      $('.source-code').removeClass(mtrack_hl_color_scheme);
    }
    if (name != '') {
      $('.source-code').addClass(name);
    }
    mtrack_hl_color_scheme = name;
  }
  $('.select-hl-scheme', $ele).change(function () {
    applyhl($(this).val());
    var val = $(this).val();
    $('.select-hl-scheme', $ele).each(function () {
      $(this).val(val);
    });
  });
  // Toggle line number display in diff visualizations, to make it easier
  // to copy the diff contents
  $('.togglediffcopy', $ele).click(function () {
    $('table.code.diff tr td.lineno', $ele).toggle();
    $('table.code.diff tr td.linelink', $ele).toggle();
    return false;
  });
}

/* inspect the wiki text in "view" and generate a document outline
 * based on the headers (which all are named anchors).
 * Store the outline into "outlinetarget", replacing its prior contents */
function mtrack_wiki_outline(view, outlinetarget)
{
  var menu = '';
  var collected = [];

  /* legacy trac style wiki generates named anchors for each heading,
   * but the newer markdown bits do not.  Look for named anchors first */
  $("a.wiki[name]", view).each(function () {
    collected.push(this);
  });
  if (collected.length == 0) {
    /* Didn't find any anchors, so try to grab headings with ids instead;
     * synthesize the anchors; we need them because the nav bar
     * at the top causes the links to be offset and obscures them.
     * We have CSS rules to compensate, but only if the target is
     * an anchor, not an id */
    $("h1[id], h2[id], h3[id], h4[id], h5[id], h6[id]", view).each(function () {
      var a = $('<a class="wiki"> </a>');
      a.attr('name', $(this).attr('id'));
      $(this).attr('id', null);
      a.appendTo(this);
      collected.push(a);
    });
  }
  _.each(collected, function(ele) {
    var p;
    var anchor;
    var cls;

    anchor = $(ele).attr('name');
    p = $(ele).parent()[0];
    cls = p.nodeName;

    menu += '<li><a href="#' + anchor + '" class="' + cls + '">'
            + $(p).text() + '</a></li>';

    $('.wikipmark', p).remove();
    var pmark = $('<a class="wikipmark">#</a>');
    pmark.attr('href', '#' + anchor);
    pmark.attr('title', 'Link to this heading');
    pmark.appendTo(p);
  });

  outlinetarget.html(menu);
}

/* decodes an ajax error response and returns the reason string */
function mtrack_ajax_error_string(resp) {
  var err;
  if (!_.isObject(resp)) {
    return resp;
  }
  err = resp.statusText;
  try {
    var r = JSON.parse(resp.responseText);
    err = r.message;
  } catch (e) {
  }
  return err;
}

/* converts an ajax error to an alert and puts it into the DOM */
function mtrack_ajax_error_to_dom(resp, container, cls)
{
  if (typeof(cls) == 'undefined') {
    cls = 'alert-danger';
  }

  resp = mtrack_ajax_error_string(resp);

  $('<div class="alert ' + cls + '">' +
      "<a class='close' data-dismiss='alert'>&times;</a>" +
      resp + '</div>').
      appendTo(container);
}

$(document).ready(function() {
  jQuery.timeago.settings.allowFuture = true;
  $('abbr.timeinterval').timeago();

  /* would like to use simply ":not(select[multiple])"
   * but this triggers an error in Chrome */
  $("select:not(select[multiple])").css('width', '220px').chosen({
    allow_single_deselect: true
  });

  $("select[multiple]").css('width', '300px').chosen({
  });


  if ($.browser.mozilla) {
    // http://www.ryancramer.com/journal/entries/radio_buttons_firefox/
    $("form").attr("autocomplete", "off");
  }
  mtrack_markitup($("textarea.wiki"), true);

  $('#mainsearch input.search-query').marcoPolo({
    url: ABSWEB + 'api.php/search/query/array',
    formatItem: function(data) {
      console.log("format", data);
      return data.link;
    },
    onSelect: function(data, $item) {
      console.log("selected", data);
      window.location = data.url;
    }
  });

  $.tablesorter.addParser({
    id: 'ticket',
    is: function(s) {
      return /^#\d+/.test(s);
    },
    format: function(s) {
      return $.tablesorter.formatFloat(s.replace(new RegExp(/#/g), ''));
    },
    type: 'numeric'
  });
  $.tablesorter.addParser({
    id: 'ord',
    is: function(s) {
      // don't auto-detect
      return false;
    },
    format: function(s) {
      return s;
    },
    type: 'numeric'
  });
  $.tablesorter.addParser({
    id: 'priority',
    is: function(s) {
      // don't auto-detect
      return false;
    },
    format: function(s) {
      return mtrack_priority_map[s];
    },
    type: 'numeric'
  });
  $.tablesorter.addParser({
    id: 'severity',
    is: function(s) {
      // don't auto-detect
      return false;
    },
    format: function(s) {
      return mtrack_severity_map[s];
    },
    type: 'numeric'
  });
  $.tablesorter.addParser({
    id: 'mtrackdate',
    is: function(s) {
      // don't auto-detect
      return false;
    },
    format: function(s) {
      // relies on the textExtraction routine below to pull a
      // date/time string out of the title portion of the abbr tag
      return $.tablesorter.formatFloat(new Date(s).getTime());
    },
    type: 'numeric'
  });
  mtrack_apply_wiki_javascript(null);
  // Convert links that are styled after buttons into actual buttons
  $('a.button[href]').each(function () {
    var href = $(this).attr('href');
    var but = $('<button type="button"/>');
    but.text($(this).text());
    $(this).replaceWith(but);
    but.click(function () {
      document.location.href = href;
      return false;
    });
  });

  $.fn.mtrackWatermark = function () {
    this.each(function () {
      var ph = $(this).attr('title');
      if (Modernizr.input.placeholder) {
        // Use HTML 5 placeholder for watermark
        $(this).attr('placeholder', ph);
      } else {
        // http://plugins.jquery.com/files/jquery.tinywatermark-2.0.0.js.txt
        var w;
        var me = $(this);
        me.focus(function () {
          if (w) {
            w = 0;
            me.removeClass('watermark').data('w', 0).val('');
          }
        })
        .blur(function () {
          if (!me.val()) {
            w = 1;
            me.addClass('watermark').data('w', 1).val(ph);
          }
        })
        .closest('form').submit(function () {
          if (w) {
            me.val('');
          }
        });
        me.blur();
      }
    });
  };
  // Watermarking
  $('input[title!=""]').mtrackWatermark();

  $('#ajaxspin').ajaxStart(function () {
    $(this).show();
  }).ajaxStop(function () {
    $(this).hide();
  });
});


if(typeof _gaq_code != 'undefined') {
var _gaq = _gaq || [];
_gaq.push(['_setAccount', _gaq_code]);
_gaq.push(['_trackPageview']);

(function() {
  var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
  ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
  var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();
}

// vim:ts=2:sw=2:et:
