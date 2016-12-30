/* vim:ts=2:sw=2:et:
 * For copyright and licensing terms, see the file named LICENSE */

MTrackShowModelAttributeView = Backbone.View.extend({
  initialize: function(options) {
    this.srcattr = options.srcattr;
    this.model.bind('change:' + this.srcattr, this.render, this);
    this.render();
  },
  render: function() {
    $(this.el).text(this.model.get(this.srcattr));
  }
});

MTrackClickToEditTextField = Backbone.View.extend({
  initialize: function(options) {
    this.editing = false;
    this.srcattr = options.srcattr;
    this.saveAfterEdit = options.saveAfterEdit;
    this.readonly = options.readonly;
    /* an additional source for click-to-edit behavior */
    this.label = options.label;
    this.type = 'input';
    if (options.type) {
      this.type = options.type;
      this.rows = options.rows;
      this.cols = options.cols;
    }

    if (options.placeholder) {
      this.placeholder = options.placeholder;
    } else {
      this.placeholder = "Edit";
    }
    this.model.bind('change:' + this.srcattr, this.render, this);
    this.render();
  },
  events: {
    "keypress input" : "keypress",
    "keypress textarea" : "keypress",
  },
  render: function() {
    var view = this;
    var el = $(this.el);
    var text = this.model.get(this.srcattr);
    var isempty = !text;
    if (!text && !this.readonly) {
      text = this.placeholder;
    }
    if (!this.editing) {
      var span;

      el.empty();
      span = $("<span class='click-to-edit'/>");
      if (isempty) {
        span.addClass('empty');
      }
      if (!this.readonly) {
        span.addClass('editable');
      }
      el.append(span);
      span.
        text(text);
      span.attr('title', text);
      if (!this.readonly) {
        var b = $('<button class="btn">Edit</button>');
        el.append(b);
        b.click(function () {
          view.editing = true;
          view.model.mtrack_edit_count++;
          view.render();
          return false;
        });
        if (isempty) {
          /* as a convenience, when it is empty, clicking
           * brings up the editor */
          el.click(function () {
            view.editing = true;
            view.model.mtrack_edit_count++;
            view.render();
            return false;
          });
        }
      }
    } else {
      el.empty();
      view.input = $("<" + this.type + "/>");
      if (this.rows) {
        view.input.attr("rows", this.rows);
      }
      if (this.cols) {
        view.input.attr("cols", this.cols);
      }
      view.input.
        val(view.model.get(this.srcattr)).
        appendTo(el).
        bind('blur', _.bind(view.close, view)).
        focus()
        ;
    }
  },
  keypress: function(e) {
    if (e.keyCode == 13) {
      // Enter
      this.close();
      return false;
    }
  },

  close: function() {
    this.editing = false;
    this.model.mtrack_edit_count--;
    var v = this.model.get(this.srcattr);
    var newval = this.input.val();
    if (newval == '') {
      newval = null;
    }
    if (v != newval) {
      var o = {};
      o[this.srcattr] = newval;
      if (this.saveAfterEdit) {
        this.model.save(o);
      } else {
        this.model.set(o);
      }
    }
    this.render();
  }
});

MTrackTagEditView = Backbone.View.extend({
  initialize: function(options) {
    this.editing = false;
    this.srcattr = options.srcattr;
    this.readonly = options.readonly;
    this.saveAfterEdit = options.saveAfterEdit;
    /* an additional source for click-to-edit behavior */
    this.label = options.label;

    if (options.placeholder) {
      this.placeholder = options.placeholder;
    }
    this.tagChanged = false;
    this.model.bind('change:' + this.srcattr, this.render, this);
    this.render();
  },
  render: function() {
    if (this.tagChanged) return this;
    var view = this;
    var el = $(this.el);
    var tags = this.model.get(this.srcattr);
    var text;
    if (_.isObject(tags)) {
      tags = _.values(tags);
    } else if (!_.isArray(tags)) {
      tags = [];
    }

    el.empty();
    el.css('display', 'inline-block');
    var m = $("<input/>");
    el.append(m);

    m.manifest({
      values: tags,
      formatRemove: function ($remove, $item) {
        return '';
      },
      marcoPolo: view.readonly ? false : {
        url: ABSWEB + 'api.php/keywords',
        formatItem: function(data) {
          return data.label;
        },
        formatNoResults: function(q, $item) {
          return "<em>No results for <strong>" + q +
            "</strong>, press comma to create a new keyword.</em>";
        }
      },
      onAdd: function (tag, $item) {
        if (_.isObject(tag)) tag = tag.label;
        if (!_.include(tags, tag)) {
          if (view.readonly) {
            return false;
          }
          tags.push(tag);
          view.tagschange(tags);
        }
      },
      onRemove: function (tag, $item) {
        if (_.isObject(tag)) tag = tag.label;
        if (view.readonly && _.include(tags, tag)) {
          return false;
        }
        tags = _.reject(tags, function (t) { return t === tag; });
        view.tagschange(tags);
      }
    });

    return this;
  },
  tagschange: function(tags) {
    var view = this;
    /* avoid rebuilding ourselves when we triggered the change */
    this.tagChanged = true;
    var o = {};
    /* need to make a clone, otherwise we're modifying the same
     * object that lives in the attributes, and the change event
     * won't trigger correctly */
    o[view.srcattr] = _.clone(tags);
    if (view.saveAfterEdit) {
      view.model.save(o);
    } else {
      view.model.set(o);
    }
    this.tagChanged = false;
  }
});

MTrackCcEditView = Backbone.View.extend({
  initialize: function(options) {
    this.editing = false;
    this.srcattr = options.srcattr || 'cc';
    this.readonly = options.readonly;
    this.saveAfterEdit = options.saveAfterEdit;
    /* an additional source for click-to-edit behavior */
    this.label = options.label;

    if (options.placeholder) {
      this.placeholder = options.placeholder;
    }
    this.tagChanged = false;
    this.model.bind('change:' + this.srcattr, this.render, this);
    this.render();
  },
  getCc: function() {
    /* we expect an object keyed by canonical name */
    var cc = this.model.get(this.srcattr);
    if (_.isArray(cc) || !_.isObject(cc)) {
      var t = cc;
      cc = {};
      _.each(t, function (item) {
        cc[item.id] = item;
      });
    } else {
      cc = _.clone(cc);
    }
    return cc;
  },
  render: function() {
    if (this.tagChanged) return this;
    var view = this;
    var el = $(this.el);
    var text;

    var cc = view.getCc();

    el.empty();
    el.css('display', 'inline-block');
    var m = $("<input/>");
    el.append(m);

    function make_item(tag) {
      if (_.isObject(tag)) return tag;
      return {
        id: tag,
        label: tag
      };
    }

    m.manifest({
      values: _.values(view.getCc()),
      formatRemove: function ($remove, $item) {
        return '';
      },
      formatDisplay: function(data) {
        if (_.isObject(data)) {
          return data.label;
        }
        return data;
      },
      marcoPolo: view.readonly ? false : {
        url: ABSWEB + 'api.php/ticket/meta/cc',
        formatItem: function(data) {
          return data.label;
        },
        formatNoResults: function(q, $item) {
          return "<em>No results for <strong>" + q +
            "</strong>, press comma to add a new Cc.</em>";
        }
      },
      onAdd: function (t, $item) {
        tag = make_item(t);
        if (!tag) {
          return false;
        }
        var cc = view.getCc();
        if (tag.id in cc) {
          return true;
        }
        /* adding a new entry */
        cc[tag.id] = tag;
        view.tagschange(cc);
      },
      onRemove: function (tag, $item) {
        tag = make_item(tag);
        var cc = view.getCc();
        if (tag.id in cc) {
          if (view.readonly) {
            return false;
          }
          delete cc[tag.id];
          view.tagschange(cc);
        }
      }
    });

    return this;
  },
  tagschange: function(tags) {
    var view = this;
    /* avoid rebuilding ourselves when we triggered the change */
    this.tagChanged = true;
    var o = {};
    /* need to make a clone, otherwise we're modifying the same
     * object that lives in the attributes, and the change event
     * won't trigger correctly */
    o[view.srcattr] = _.clone(tags);
    if (view.saveAfterEdit) {
      view.model.save(o);
    } else {
      view.model.set(o);
    }
    this.tagChanged = false;
  }
});

// Displays the list of children in a similar fashion to the ticket
// dependency views
MTrackTicketChildListView = Backbone.View.extend({
  initialize: function(options) {
    this.editing = false;
    this.label = options.label;
    if (options.placeholder) {
      this.placeholder = options.placeholder;
    }
    this.model.bind('change', this.render, this);
    var view = this;
    this.model.getChildren().fetch({
      success: function() {
        view.render();
      }
    });
  },
  render: function() {
    if (this.tagChanged) return this;
    var view = this;
    var el = $(this.el);

    el.empty();
    el.css('display', 'inline-block');
    var m = $("<input/>");
    el.append(m);

    var children = view.model.getChildren();

    m.manifest({
      values: _.clone(children.models),
      required: true,
      formatRemove: function ($remove, $item) {
        return '';
      },
      formatDisplay: function (data, $item, $mpItem) {
        if ($mpItem) {
          return $mpItem.html();
        }
        if (_.isObject(data)) {
          return '#' + data.get('nsident') + ' ' + data.get('summary');
        }
        return '#' + data;
      },
      marcoPolo: view.readonly ? false : {
        url: ABSWEB + 'api.php/ticket/' + view.model.id + '/children/candidates',
        formatItem: function(data) {
          return '#' + data.nsident + ' ' + data.summary;
        }
      },
      onAdd: function (tag, $item) {
        var ident;
        var T = null;
        if (tag.constructor == MTrackTicket) {
          ident = tag.id;
          T = tag;
        } else {
          ident = tag.tid;
        }
        if (!children.get(ident)) {
          if (view.readonly) {
            return false;
          }
          if (!T) {
            T = new MTrackTicket({id: ident});
            T.save({
              ptid: view.model.id,
              // Note that this copies the currently editing
              // tickets' milestone over that of the one that
              // the user just typed in; any other milestones
              // associated with that ticket are discarded
              milestones: view.model.get('milestones')
            }, {
              success: function() {
                children.add(T);
              }
            });
          } else {
            T.save({
              ptid: view.model.id,
            }, {
              success: function() {
                children.add(T);
              }
            });
          }
        }
      },
      onRemove: function (tag, $item) {
        var ident;
        if (tag.constructor == MTrackTicket) {
          ident = tag.id;
        } else {
          ident = tag.tid;
        }
        var T = children.get(ident);
        if (view.readonly && T) {
          return false;
        }
        if (T) {
          T.save({
            ptid: ''
          }, {
            success: function() {
              children.remove(T);
            }
          });
        }
      }
    });

    return this;
  },
});

MTrackTicketDepEditView = Backbone.View.extend({
  initialize: function(options) {
    this.editing = false;
    this.srcattr = options.srcattr;
    this.readonly = options.readonly;
    this.saveAfterEdit = options.saveAfterEdit;
    /* an additional source for click-to-edit behavior */
    this.label = options.label;

    if (options.placeholder) {
      this.placeholder = options.placeholder;
    }
    this.tagChanged = false;
    this.model.bind('change:' + this.srcattr, this.render, this);
    this.render();
  },
  render: function() {
    if (this.tagChanged) return this;
    var view = this;
    var el = $(this.el);
    var tags = this.model.get(this.srcattr);
    if (_.isObject(tags)) {
      tags = _.values(tags);
    } else if (!_.isArray(tags)) {
      tags = [];
    }

    el.empty();
    el.css('display', 'inline-block');
    var m = $("<input/>");
    el.append(m);

    function make_tag(tag) {
      if (_.isObject(tag)) {
        if ('label' in tag) {
          /* populated by initial page load */
          tag = tag.label;
        } else {
          /* returned from marcoPolo */
          tag = '#' + tag.nsident;
        }
      }
      return tag;
    }

    m.manifest({
      values: tags,
      required: true,
      formatRemove: function ($remove, $item) {
        return '';
      },
      formatDisplay: function (data, $item, $mpItem) {
        if ($mpItem) {
          return $mpItem.html();
        }
        if (_.isObject(data)) {
          return '#' + data.nsident + ' ' + data.summary;
        }
        return '#' + data;
      },
      marcoPolo: view.readonly ? false : {
        url: (view.srcattr != 'children') ?
          (ABSWEB + 'api.php/ticket/search/basic') :
          (ABSWEB + 'api.php/ticket/' + view.model.id + '/children/candidates')
        ,
        formatItem: function(data) {
          return '#' + data.nsident + ' ' + data.summary;
        }
      },
      onAdd: function (tag, $item) {
        tag = make_tag(tag);
        if (!_.include(tags, tag)) {
          if (view.readonly) {
            return false;
          }
          tags.push(tag);
          view.tagschange(tags);
        }
      },
      onRemove: function (tag, $item) {
        tag = make_tag(tag);
        if (view.readonly && _.include(tags, tag)) {
          return false;
        }
        tags = _.reject(tags, function (t) { return t === tag; });
        view.tagschange(tags);
      }
    });

    return this;
  },
  tagschange: function(tags) {
    var view = this;
    /* avoid rebuilding ourselves when we triggered the change */
    this.tagChanged = true;
    var o = {};
    /* need to make a clone, otherwise we're modifying the same
     * object that lives in the attributes, and the change event
     * won't trigger correctly */
    o[view.srcattr] = _.clone(tags);
    if (view.saveAfterEdit) {
      view.model.save(o);
    } else {
      view.model.set(o);
    }
    this.tagChanged = false;
  }
});

/* Displays rendered wiki text.
 * Click to edit.
 * Click OK to confirm edits and apply to the model.
 * Construct by passing in a model and the name of the
 * attribute that holds the source text.  Optionally pass
 * the name of the attribute that caches the rendered text.
 */
MTrackWikiTextAreaView = Backbone.View.extend({
  initialize: function(options) {
    _.extend(this, {
        srcattr: null, // attribute in the model to be edited
        renderedattr: null, // attribute in the model holding pre-rendered text
        wikiContext: null, // context for rendering (impacts Image() macro)
        placeholder: "Enter text",
        readonly: false,
        use_overlay: false,
        doubleclick: true,
        Caption: null,
        OKLabel: "OK", // label for OK button
        CancelLabel: "Cancel", // label for Cancel button
      }, options
    );
    this.editing = false;
    this.lastRendered = null;
    var view = this;
    if (this.renderedattr) {
      this.model.bind('change:' + this.renderedattr, this.change, this);
    }
    this.model.bind('change:' + this.srcattr, this.change, this);

    this.get_or_reset_rendered_attr();
  },

  change: function () {
    this.get_or_reset_rendered_attr();
    this.render();
  },

  get_or_reset_rendered_attr: function() {
    var view = this;
    if (!view.renderedattr) {
      view.refetchPreview(function (data) {
        view.html = data.html;
        view.render();
      });
    } else {
      view.html = view.model.get(view.renderedattr);
      view.render();
    }
  },

  clear_timer: function() {
    if (this.timer) {
      window.clearTimeout(this.timer);
      this.timer = null;
    }
    if (this.request) {
      this.request.abort();
      this.request = null;
      this.lastRendered = null;
    }
  },

  edit: function() {
    this.trigger('editstart');
    this.editing = true;
    this.model.mtrack_edit_count++;
    this.render();
    if (!this.renderedattr) {
      this.refetchPreview();
    }
  },

  render: function() {
    var model = this.model;
    var view = this;

    view.changed = false;
    view.clear_timer();

    var html;
    var src;

    src = model.get(view.srcattr);
    if (!src) {
      html = "<i class='placeholder'>" + view.placeholder + "</i>";
    } else {
      html = view.html;
    }

    if (!view.editing || view.readonly) {
      view.ta = null;
      $(view.el).empty();
      view.preview = $("<div class='wiki-preview'/>");
      $(view.el).append(view.preview);
      view.preview.html(html);
      if (!view.readonly) {
        view.preview.
          addClass('click-to-edit');
        if (view.doubleclick) {
          view.preview.bind('dblclick.wikiedit', function (e) {
            view.edit();
            return false;
          })
          ;
        } else if (!src) {
          /* as a convenience, when it is empty, clicking
           * brings up the editor */
          view.preview.click(function () {
            view.edit();
            return false;
          });
        }
      }
      mtrack_apply_wiki_javascript(view.preview);
    } else {
      var overlay = null;
      var container = $(view.el);

      if (this.use_overlay) {
        /* hide any pre-existing preview while we edit, so that it doesn't
         * collide with a possible search-for-text in page initiated by
         * the user */
        view.$('div.wiki-preview').remove();
        overlay = $('<div class="overlay"/>');
        overlay.appendTo('body').fadeIn('fast');
        container = $('<div class="popupForm"/>');
        container.appendTo('body');
      }
      var ta = $("<textarea rows=8 cols=50 class='code wiki'/>");
      view.preview = $("<div class='wiki-preview'/>");
      var ok = $("<button/>");
      ok.text(view.OKLabel);
      ok.addClass('btn btn-primary');
      var cancel = $("<button/>");
      cancel.addClass('btn');
      cancel.text(view.CancelLabel);
      view.ta = ta;
      container.
        empty();
      if (this.use_overlay && view.Caption) {
        var cap = $("<h1/>");
        cap.addClass('caption');
        cap.text(view.Caption);
        cap.appendTo(container);
      }
      var footer = $('<div class="modal-footer"/>');
      footer.
        append(cancel).
        append(ok);
      container.
        append(ta).
        append(footer).
        append(view.preview);

      if (this.use_overlay && !src) {
        /* if in popup mode, don't show placeholder (click-to-edit) text */
        view.preview.html('');
      } else {
        view.preview.html(html);
        mtrack_apply_wiki_javascript(view.preview);
      }
      ta.val(src);
      mtrack_markitup(ta, false);
      if (this.use_overlay) {
        // Add preview size toggle button
        var toggle = $('<button class="wikipreviewtoggle btn"><i class="icon-resize-small"></i> </button>');
        $('div.markItUpHeader', container).append(toggle);
        // There are three states:
        // "small", "full" and "twoup"
        var state = 0;
        var icon_classes = ['icon-resize-small', 'icon-resize-full',
                            'icon-resize-full'];
        var container_classes = ['', 'maximized', 'twoup'];
        var measure = $('<div style="width:50em"></div>');
        container.append(measure);

        function set_state(s) {
          container.removeClass(container_classes[state]);
          $('i', toggle).removeClass(icon_classes[state]);
          state = s;
          if (state == 2 && measure.width() * 1.75 > container.width()) {
            state = 0;
          }
          if (state > 2) state = 0;
          container.addClass(container_classes[state]);
          $('i', toggle).addClass(icon_classes[state]);
        }
        /* if we're wide enough, default to two-up */
        set_state(2);
        toggle.click(function () {
          set_state(state + 1);
        });
        toggle.tooltip({
          title: "Adjust preview/editor proportions",
          placement: "left",
        });
        /* for long-time users, draw attention to the button */
        toggle.tooltip('show');
        setTimeout(function () {
          toggle.tooltip('hide');
        }, 2000);
      }
      ta.bind('keyup.wikiedit markitupAfterInsert', function () {
        view.maybeUpdatePreview();
      });

      function close_editor() {
        if (overlay) {
          // make sure we take the tooltip(s) away when the editor
          // closes, so we don't orphan a tooltip
          $('button', container).tooltip('hide');
          overlay.fadeOut('fast', function () {
            overlay.remove();
          });
          container.fadeOut('fast', function () {
            container.remove();
          });
          measure.remove();
        }
        $(document).off('keyup.dismiss.wikimodal');
        $('body').removeClass('modal-open')
        view.ta = null;
      }
      // Have escape cancel, but only if no edits were made
      $(document).on('keyup.dismiss.wikimodal', function (e) {
        if (e.which == 27 && src == ta.val()) {
          cancel.trigger('click');
        }
      });
      $('body').addClass('modal-open')

      ok.click(function (e) {
        view.editing = false;
        view.model.mtrack_edit_count--;
        view.clear_timer();

        var o = {};
        var val = ta.val();
        o[view.srcattr] = val;
        model.set(o, {silent:true});

        /* apply changes */
        view.refetchPreview(function (data) {
          view.html = data.html;
          close_editor();
          if (view.renderedattr) {
            var o = {};
            o[view.renderedattr] = view.html;
            model.set(o, {silent:true});
          }
          /* notify for the srcattr set that we made silent above */
          model.trigger('change:' + view.srcattr, val);
          model.trigger('change');
          view.trigger('edited');
          view.trigger('editend');
        });
        return false;
      });

      cancel.click(function (e) {
        view.editing = false;
        view.model.mtrack_edit_count--;
        view.clear_timer();
        ta.val(model.get(view.srcattr));
        view.refetchPreview(function (data) {
          view.html = data.html;
          close_editor();

          if (view.renderedattr) {
            var o = {};
            o[view.renderedattr] = view.html;
            model.set(o);
          }

          view.render();
          view.trigger('canceledit');
          view.trigger('editend');
        });
        return false;
      });

      ta.focus();
    }
    return this;
  },

  maybeUpdatePreview: function() {
    var view = this;

    if (view.ta && view.lastRendered == view.ta.val()) {
      // Didn't change, nothing to do
      return;
    }

    if (!view.timer) {
      view.changed = false;
      view.timer = window.setTimeout(function () {
        view.refetchPreview();
      }, 1000);
    } else {
      view.changed = true;
      if (view.request) {
        view.request.abort();
        view.request = null;
        view.lastRendered = null;
      }
    }
  },

  refetchPreview: function(after) {
    var view = this;
    var complete = null;

    if (!view.ta) {
      return;
    }

    if (!after) {
      after = function(data) {
        if (view.editing) {
          view.html = data.html;
        }
        if (view.preview) {
          view.preview.html(view.html);
          mtrack_apply_wiki_javascript(view.preview);
        }
      };

      complete = function() {
        view.timer = null;
        if (view.editing && view.changed) {
          view.maybeUpdatePreview();
        }
      };
    }

    var params = '';
    if (this.wikiContext) {
      params = '?wikiContext=' + this.wikiContext + this.model.id;
    }

    if (view.request) {
      view.request.abort();
    }
    view.lastRendered = view.ta.val();

    view.request = $.ajax({
      url: ABSWEB + "api.php/wiki/render/html" + params,
      contentType: "application/json",
      context: view,
      type: 'POST',
      data: view.ta.val(),
      success: after,
      complete: complete
    });
  }
});

MTrackSelectEditorView = Backbone.View.extend({
  initialize: function(options) {
    this.editing = false;
    this.srcattr = options.srcattr;
    this.values = options.values;
    this.readonly = options.readonly;
    this.saveAfterEdit = options.saveAfterEdit;
    if (!this.options.width) {
      this.options.width = '220px';
    }
    if (!this.values) { // chosen doesn't like empty select elements
      this.values = [{id:"", label: ""}];
    }
    this.multiple = options.multiple;
    this.placeholder = options.placeholder;
    this.defval = options.defval;
    this.render();
    this.model.bind('change:' + this.srcattr, this.render, this);
  },
  render: function() {
    var view = this;
    var current_val = view.model.get(view.srcattr);

    /* normalize current value to something that we can use for comparison */
    if (_.isObject(current_val)) {
      var ids = [];
      for (var id in current_val) {
        ids.push(id);
      }
      current_val = ids;
    } else if (!_.isArray(current_val)) {
      if (view.defval && (!current_val || current_val.length == 0)) {
        current_val = view.defval;
      }
      current_val = [current_val];
    }

    $(this.el).empty();

    function find_label(val) {
      var label = val;
      _.each(view.values, function (v) {
        if (_.isObject(v)) {
          if (v.id == val) {
            label = v.label;
            return;
          }
        }
      });
      return label;
    }
    if (this.readonly) {
      // Chosen has no "readonly" mode, so we do some hand rolling
      var t = [];

      _.each(current_val, function (v) {
        t.push(find_label(v));
      });
      $(this.el).text(t.join(", "));
      return;
    }

    var sel = $("<select/>", {
      "data-placeholder": this.placeholder
    });
    if (this.multiple) {
      sel.attr('multiple', 'multiple');
    }
    $(this.el).append(sel);

    function add_items(cont, items) {
      _.each(items, function (item) {
        if (item.group) {
          var grp = $("<optgroup/>", {label: item.group});
          cont.append(grp);
          add_items(grp, item.items);
          return;
        }

        var opt = $("<option/>");
        opt.attr('value', item.id);
        _.each(current_val, function (v) {
          if (item.id == v) {
            opt.attr("selected", "selected");
          }
        });
        opt.text(item.label);
        cont.append(opt);
      });
    };

    add_items(sel, view.values);

    sel.css('width', view.options.width).chosen({
      allow_single_deselect: true
    }).change(function() {
      var o = {};
      if (this.multiple) {
        var v = {};
        _.each(sel.val(), function (opt) {
          v[opt] = opt;
        });
        o[view.srcattr] = v;
      } else {
        o[view.srcattr] = sel.val();
      }
      if (view.saveAfterEdit) {
        view.model.save(o);
      } else {
        view.model.set(o);
      }
    });
    return this;
  }
});

MTrackTicketAttachmentsView = Backbone.View.extend({
  initialize: function(options) {
    this.template = _.template($('#attach-template').html());
    this.model.bind('change', this.render, this);
    this.collection.bind('all', this.render, this);
    if (options.type) {
      this.type = options.type;
    } else {
      this.type = 'ticket';
    }
    if (options.button) {
      this.button = options.button;
    } else {
      this.button = null;
    }
    if ('editable' in options) {
      this.editable = options.editable;
    } else {
      this.editable = true;
    }
    this.render();
  },
  render: function() {
    var el = $(this.el);
    var view = this;
    el.empty();
    if (this.model.isNew()) {
      el.html("<i>Attachments unavailable until this " + view.type +
          " is saved</i>");
      if (this.button) {
        $(this.button).hide();
      }
      return;
    }

    var button = null;
    if (this.button) {
      button = $(this.button);
      if (!this.editable) {
        button.hide();
      } else {
        button.show();
      }
    } else if (this.editable) {
      button = $('<button class="btn add"><i class="icon-upload"></i> Add Attachment</button>');
      button.appendTo(el);
    }
    if (button) button.click(function () {
      var overlay = $('<div class="overlay"/>');
      overlay.appendTo('body').fadeIn('fast');
      var f = $('#attachment-form');
      f.fadeIn('fast');
      $('input[name=object]', f).val(view.type + ":" + view.model.id);

      $('#cancel-upload').click(function () {
        overlay.fadeOut('fast', function () {
          overlay.remove();
        });
        $('input:file', f).MultiFile('reset');
        f.fadeOut('fast');
        return false;
      });
      var iframe = $('#upload_target');
      $('#confirm-upload').click(function () {
        iframe.load(function () {
          var t = iframe.contents().find('pre').text();
          var res = JSON.parse(t);

          overlay.fadeOut('fast', function () {
            overlay.remove();
            /* now give this to the collection */
            if (res.attachments) {
              view.collection.reset(res.attachments);
            } else if (res.status == 'error') {
              alert(res.message);
            }
          });
          $('input:file', f).MultiFile('reset');
          f.fadeOut('fast');
        });

        f.submit();
      });

      return false;
    });

    var att_list = $('<div class="attachlist"/>');
    el.append(att_list);

    view.collection.each(function (att) {
      var t = $("<div/>");
      t.addClass('attachment');
      var o = att.toJSON();
      if ('width' in o) {
        o.image = true;
      } else {
        o.image = false;
      }
      t.html(view.template(o));
      t.appendTo(att_list);
      if (view.editable) {
        $('button', t).click(function () {
          var m = $("<div class='modal fade'><div class='modal-header'><a class='close' data-dismiss='modal'>x</a><h3>Delete Attachment?</h3></div><div class='modal-body'><p><b></b></p><p>Do you really want to delete this attachment?</p><p>You cannot undo this action!</p></div><div class='modal-footer'><button class='btn' data-dismiss='modal'>Close</button><button class='btn btn-danger'>Delete</button></div></div>");

          $('b', m).text(att.get('filename'));
          $('.btn-danger', m).click(function () {
            att.destroy();
            m.modal('hide');
          });

          m.on('hidden', function() {
            m.remove();
          });
          m.modal();
        });
      } else {
        $('button', t).hide();
      }
    });
    this.$('.timeinterval').timeago();

    return this;
  }
});

MTrackTicketChangesView = Backbone.View.extend({
  initialize: function(options) {
    this.template = _.template($('#ticket-change-template').html());
    this.collection.bind('all', this.render, this);
    this.render();
  },
  render: function() {
    var el = $(this.el);
    var view = this;
    el.empty();

    if (this.model.isNew()) {
      el.html("<i>No changes will show until this ticket is saved</i>");
      return;
    }

    /* some filtering support */
    var filter = $('<select class="filter"><option value="all">All</option><option value="comments">Comments</option><option value="nocom">No Comments</option></select>');
    filter.appendTo(el);
    filter.change(function () {
      var f = filter.val();
      switch (f) {
        case 'all':
          view.$('div').show();
          break;
        case 'comments':
          view.$('div.chg-no-comment').hide();
          view.$('div.chg-comment').show();
          break;
        case 'nocom':
          view.$('div.chg-no-comment').show();
          view.$('div.chg-comment').hide();
          break;
      }
    });

    var cont = $('<div class="change-list"/>');
    el.append(cont);

    this.collection.each(function (cs) {
      var d = $('<div/>');
      $(d).html(view.template(cs.toJSON()));
      var had_comment = false;
      var commit = false;
      _.each(cs.get('audit'), function (a) {
        if (a.label == 'Comment') {
          had_comment = true;
          if (a.value.match(/^\(In /)) {
            commit = true;
          }
        }
      });
      if (had_comment) {
        d.addClass('chg-comment');
        if (commit) {
          d.addClass('chg-commit');
        }
      } else {
        d.addClass('chg-no-comment');
      }
      d.appendTo(cont);
    });
    this.$('.toggle-desc').click(function () {
      $('#' + $(this).attr('desc-id')).toggle();
      return false;
    });
    this.$('.timeinterval').timeago();
  },
});

MTrackMainTicketEditorView = Backbone.View.extend({
  initialize: function(options) {
    this.readonly = options.readonly;
    this.fields = options.fieldset;
    this.template = _.template($('#ticket-edit-template').html());
    var tkt = this.model;
    tkt.bind('change:nsident', this.render, this);
    tkt.bind('change:comment', this.render, this);

    $(this.el).html(this.template(tkt.toJSON()));

    this.wiki = new MTrackWikiTextAreaView({
      model: tkt,
      wikiContext: "ticket:",
      readonly: this.readonly,
      doubleclick: false,
      Caption: "Editing ticket description",
      placeholder: "Enter a description",
      use_overlay: true,
      srcattr: "description",
      renderedattr: "description_html",
      el: "#issue-desc"
    });

    this.render();
  },
  render: function() {
    var tkt = this.model;
    var View = this;

    $(this.el).html(this.template(tkt.toJSON()));
    if (this.readonly) {
      $('#edit-description').hide();
    } else {
      $('#edit-description').click(function () {
        View.wiki.edit();
        return false;
      });
    }

    this.summary = new MTrackClickToEditTextField({
      model: tkt,
      srcattr: 'summary',
      readonly: View.readonly,
      placeholder: "Enter a summary",
      el: '#tkt-summary-text'
    });
    this.summary.render();
    this.wiki.render();

    var field_views = {};
    var align_views = [];
    var prop_el = $('#issue-props');
    prop_el.empty();

    function process_group(group) {
      if (group.name == "0") return;

      var group_el = $("<fieldset/>");
      group_el.appendTo(prop_el);
      var legend = $("<legend/>");
      legend.text(group.name);
      legend.appendTo(group_el);

      var nfields = 0;

      for (var fidx in group.fields) {
        var field = group.fields[fidx];
        if (field.name == 'description') continue;
        if (View.readonly && (
              !tkt.get(field.name) || tkt.get(field.name).length == 0)) {
          // If we're read-only and a field isn't set, don't render an
          // empty key/value pair
          continue;
        }

        nfields++;

        var view = null;
        var el = $('<div/>', {"class": "tktfield"});
        el.appendTo(group_el);
        var label = $("<label/>");
        label.appendTo(el);
        label.text(field.label);

        var id = "tkt-edit-" + field.name;
        var edit = $('<span/>', {
          id: id
        });
        edit.appendTo(el);

        if (field.type == 'readonly' || field.type == "text") {
          view = new MTrackClickToEditTextField({
            el: "#" + id,
            model: tkt,
            srcattr: field.name,
            label: label,
            placeholder: field.placeholder,
            readonly: View.readonly || field.type == 'readonly'
          });
          field_views[field.name] = view;
          align_views.push([label, view]);
          continue;
        }

        if (field.type == 'children') {
          view = new MTrackTicketChildListView({
            el: "#" + id,
            model: tkt,
            srcattr: field.name,
            label: label,
            readonly: View.readonly
          });
          field_views[field.name] = view;
          align_views.push([label, view]);
        }
        if (field.type == 'ticketdeps') {
          view = new MTrackTicketDepEditView({
            el: "#" + id,
            model: tkt,
            srcattr: field.name,
            label: label,
            readonly: View.readonly
          });
          field_views[field.name] = view;
          align_views.push([label, view]);
        }

        if (field.type == 'tags') {
          view = new MTrackTagEditView({
            el: "#" + id,
            model: tkt,
            srcattr: field.name,
            label: label,
            readonly: View.readonly
          });
          field_views[field.name] = view;
          align_views.push([label, view]);
        }

        if (field.type == 'cc') {
          view = new MTrackCcEditView({
            el: "#" + id,
            model: tkt,
            srcattr: field.name,
            label: label,
            readonly: View.readonly
          });
          field_views[field.name] = view;
          align_views.push([label, view]);
        }

        if (field.type == 'multi') {
          $("<br/>").insertBefore(edit);
          view = new MTrackClickToEditTextField({
            el: "#" + id,
            model: tkt,
            srcattr: field.name,
            type: 'textarea',
            rows: field.rows,
            cols: field.cols,
            label: label,
            readonly: View.readonly
          });
          field_views[field.name] = view;
        }

        if (field.type == 'select') {
          view = new MTrackSelectEditorView({
            el: "#" + id,
            model: tkt,
            srcattr: field.name,
            label: label,
            values: field.options,
            defval: field["default"],
            placeholder: field.placeholder,
            readonly: View.readonly
          });
          field_views[field.name] = view;
          align_views.push([label, view]);
        }

        if (field.type == 'multiselect') {
          view = new MTrackSelectEditorView({
            el: "#" + id,
            model: tkt,
            multiple: true,
            srcattr: field.name,
            label: label,
            values: field.options,
            placeholder: field.placeholder,
            readonly: View.readonly
          });
          field_views[field.name] = view;
          align_views.push([label, view]);
        }
      }
      if (nfields == 0) {
        group_el.remove();
      }
      return group_el;
    }

    /* synthesize status group */
    if (tkt.id) {
      var group = {
        name: "Status",
        fields: []
      };

      group.fields.push({
        name: "status",
        label: "Status",
        type: "select",
        options: mtrack_ticket_states
      });

      if (tkt.get('status') != 'closed') {
        group.fields.push({
          name: "resolution",
          label: "Resolution",
          placeholder: "Resolve ticket as...",
          type: "select",
          options: mtrack_resolutions
        });
      } else {
        group.fields.push({
            name: "resolution",
            label: "Resolution",
            type: "readonly",
          });
      }
      group.fields.push({
        label: "Remaining Effort",
        name: "remaining",
        type: "readonly"
      });
      /*
      if (tkt.get('ptid')) {
        group.fields.push({
          label: "Parent Ticket",
          name: "ptid",
          type: "readonly"
        });
      }
      */
      /* annotate with other metadata */
      var status_el = process_group(group);
      var pred = tkt.get('prediction');
      if (pred && tkt.get('status') != 'closed') {
        var g = $('<div/>', {'class':'prediction'});
        var d = _.sortBy(pred.montecarlo, function (item) {
          return item[0];
        });
        g.appendTo(status_el);
        $.plot(g, [
            {data: d}
        ], {
          series: {
            lines: { show: true, fill: true },
            points: { show: true }
          },
          yaxis: {
            tickFormatter: function (v) { return v + "%"; }
          },
          xaxis: {
            tickFormatter: function (v) { return v + " hrs"; }
          },
          grid: {
            backgroundColor: { colors: ['#fff', '#eee'] }
          }
        });
      }

      var el = $('<div/>', {"class": "tktfield"});
      var opened =
          _.template("<span>Opened <abbr class='timeinterval' title='<%- when %>'><%- when %></abbr> by <%- who %></span>", tkt.get('created'));
      var updated = '';
      if (tkt.get('updated') &&
            tkt.get('updated').cid != tkt.get('created').cid) {
          updated = _.template(
              "<br><span>Updated <abbr class='timeinterval' title='<%- when %>'><%- when %></abbr> by <%- who %></span>", tkt.get('updated'));
      }
      el.html(opened + updated);
      el.appendTo(status_el);
      $('.timeinterval', el).timeago();
    }

    if (tkt.get('comment')) {
      var container = $("<fieldset><legend>Comment</legend></fieldset>");
      prop_el.append(container);
      var t = $("<pre/>");
      t.text(tkt.get('comment'));
      t.appendTo(container);
      /* slightly funky encapsulation violation */
    }

    for (var gidx in this.fields) {
      var group = this.fields[gidx];
      process_group(group);
    }

    /* now line up the views */
    var lwidth = 0;
    _.each(align_views, function (tuple) {
      var label = tuple[0];
      var width = Math.round(label.width());
      lwidth = Math.max(lwidth, width);
    });
    lwidth += 16;
    _.each(align_views, function (tuple) {
      var view = tuple[1];
      var left = Math.round($(view.el).position().left);

      var diff = lwidth - left;
      $(view.el).css({
        position: "relative",
        left: diff,
      });

    });

    return this;
  }
});

MTrackPlanningTicketView = Backbone.View.extend({
  tagName: "li",
  showEditor: function(e) {
    e.preventDefault();
    var editor = new MTrackTktEditor({model: this.model});
    return false;
  },

  initialize: function(options) {
    this.rootView = options.rootView;
    this.model.bind('change', this.render, this);
    this.model.bind('destroy', this.remove, this);
    this.model.bind('childticketadd', this.childchanged, this);
    this.model.bind('childticketremove', this.childchanged, this);
    this.template = _.template($('#ticket-template').html());
  },
  childchanged: function() {
    if (this.model.get('hasChildren') && this.model.tktChildren &&
        this.model.tktChildren.length == 0) {
      this.model.set({hasChildren: false}, {silent: true});
    } else if (this.model.tktChildren && this.model.tktChildren.length) {
      this.model.set({hasChildren: true}, {silent: true});
    }
    if (!this.model.get('hasChildren')) {
      this.$('button').hide();
    } else {
      this.$('button:first').show();
    }
  },
  render: function() {
    $(this.el).html(this.template(this.model.toJSON()));
    $(this.el).data('plan-tkt', this.model);
    var tkt = this.model;
    var view = this;

    if (!tkt.get('hasChildren')) {
      this.$('button').hide();
    }

    this.$('button:first').click(function () {
      if (view.$('button i').attr('class') == 'icon-minus') {
        view.$('button i').removeClass('icon-minus').addClass('icon-plus');
        view.$('ul').remove();
        return;
      }
      tkt.getChildren().fetch({
        success: function(col, resp) {
          view.$('button i').removeClass('icon-plus').addClass('icon-minus');
          // And populate the child list.  Dragging between lists
          // may only bring over the main element and not an empty
          // (non-expanded) list.  To be safe, we simply remove
          // any <ul> that may be present and add a new one
          view.$('ul').remove();
          $(view.el).append('<ul class="ticketsinner"/>');
          var list = view.$('ul');

          col.each(function (item) {
            var kview = new MTrackPlanningTicketView({
              model: item,
              rootView: view.rootView,
              id: "tkt-" + item.cid
            });
            var el = $(kview.render().el);
            list.append(el);
          });
        }
      });
    });
    return this;
  },
});

MTrackPlanningTicketListView = Backbone.View.extend({
  initialize: function(options) {
    var view = this;
    this.ul = $('<ul class="tickets"/>');
    this.ul.nestedSortable({
      listType: 'ul',
      handle: 'div.handle',
      toleranceElement: '> div',
      items: 'li',
      maxLevels: 2,
      connectWith: 'ul.tickets',
      placeholder: 'ticketDragTarget',
      forcePlaceholderSize: true,
      tolerance: 'intersect',
      appendTo: 'body',
      helper: 'clone',
      scroll: true,
      srollSensitivity: 64,
      start: function (evt, ui) {
        ui.placeholder.height(ui.item.height());
        ui.helper.addClass('draggingTicket');

        var item_id = ui.item.attr('id');
        var tkt = $('#' + item_id).data('plan-tkt');
      },
      stop: function (evt, ui) {
        ui.item.removeClass('draggingTicket');
      },
      update: function(evt, ui) {
        view.reordered(ui.item);
        return true;
      }
    })
      .disableSelection()
      .appendTo(this.el);

    if (!options.model) {
      return;
    }
    var tkts = options.model.tickets;
    tkts.bind('add',   this.added, this);
    tkts.bind('reset', this.addAll, this);
    tkts.fetch();
  },

  /* locate the containing collection; it may be view.model
   * (eg: the milestone) or it may be some other ticket */
  resolveContainer: function(item) {
    item = item[0];
    var p = item.parentElement;
    if ($(p).hasClass('tickets')) {
      // This milestone
      return this.model;
    }
    // Must be another ticket; pull it out from the data on the node
    var tkt = $(p.parentElement).data('plan-tkt');
    return tkt;
  },

  /* called when the list is re-ordered by the user.
   * parent<->child relationships and cross-milestone moves make
   * this an interesting function.
   * We need to determine the old collection and the new collection
   * that are/were containers.
   * We need to insert into the new container at the equivalent
   * offset that we find in the drop target.
   */
  reordered: function(movedItem) {
    var item_id = movedItem.attr('id');
    var tkt = $('#' + item_id).data('plan-tkt');

    /* save original collection */
    var originObject = tkt.collection;

    /* find desired position in target milestone */
    var movedPos = 0;
    var targetObject = this.resolveContainer(movedItem);
    if (targetObject.constructor == MTrackMilestone) {
      /* find where we dropped the item within this view */
      var p = movedItem[0].parentElement;
      $(p).children().each(function (idx, elt) {
        if ($(elt).attr('id') == 'tkt-' + tkt.cid) {
          movedPos = idx;
        }
      });
    }

    var view = this;

    /* Some "interesting" interleaving here.
     * If we've changed milestone or parent, then we need to save
     * the ticket.  If we've changed milestone, we also need to
     * update the order, but we can't update the order until after
     * we've changed the milestone.
     * The milestone collection has code that automatically updates
     * the ticket milestone when a ticket is added, but we need
     * to update it prior to that.
     * Since the update is async, we need to defer the milestone
     * collection addition until that has happened.
     *
     * In addition, the ticket may have been updated by someone
     * else outside of this planning screen.  Since we only allow
     * changing scheduling options, we are safe to suppress any
     * conflicts by faking up a ticket model.
     */

    var pending_ops = 0;
    /* This is declared here but invoked only after we save the
     * ticket, if we need to save the ticket */
    var finalize = function() {
      /* remove from current container */
      tkt.collection.remove(tkt);
      if (targetObject.constructor == MTrackMilestone) {
        tcol = targetObject.tickets;
      } else {
        tcol = targetObject.getChildren();
      }
      tcol.add(tkt, {at: movedPos});

      if (targetObject.constructor == MTrackMilestone) {
        targetObject.tickets.savePriorities();
      } else {
        targetObject.collection.milestone.tickets.savePriorities();
      }
    };
    var op_done = function() {
      if (--pending_ops == 0) {
        finalize();
      }
    };


    /* This will be the ticket that we save */
    var T = {
      id: tkt.id,
      nsident: tkt.get('nsident'),
      ptid: tkt.get('ptid')
    };

    var need_save = false;

    /* assess how our milestone changed. */
    var src_milestone;
    if (originObject.constructor == MTrackMilestoneTicketCollection) {
      src_milestone = originObject.milestone;
    } else {
      src_milestone = originObject.ticket.collection.milestone;
    }
    var dest_milestone;
    if (targetObject.constructor == MTrackMilestone) {
      dest_milestone = targetObject;
    } else {
      dest_milestone = targetObject.collection.milestone;
    }

    if (targetObject.constructor != MTrackMilestone) {
      /* moving to a ticket */
      if (targetObject.id != T.ptid) {
        T.ptid = targetObject.id;
        need_save = true;
      }
    } else {
      /* moving to a milestone */
      if (T.ptid) {
        T.ptid = "";
        need_save = true;
      }
    }

    if (need_save) {
      pending_ops++;
    }

    if (src_milestone.id != dest_milestone.id) {
      if (!need_save) {
        need_save = true;
        pending_ops++;
      }

      var m = tkt.get('milestones');
      if (!m || (_.isArray(m) && m.length == 0)) {
        m = {};
      }
      delete m[src_milestone.id];
      m[dest_milestone.id] = dest_milestone.get('name');
      T.milestones = m;

      /* when we drag a ticket with children to a different
       * milestone, we need to also update its children, and do that
       * before we update the ordering */
      pending_ops++; // placeholder until we fetch
      tkt.getChildren().fetch({
        error: function(col, resp) {
          op_done();
        },
        success: function(col, resp) {
          col.each(function (kid) {
            pending_ops++;
            var m = tkt.get('milestones');
            if (!m || (_.isArray(m) && m.length == 0)) {
              m = {};
            }
            delete m[src_milestone.id];
            m[dest_milestone.id] = dest_milestone.get('name');
            kid.save({milestones: m}, {
              error: function() {
                op_done();
              },
              success: function() {
                op_done();
              }
            });
          });
          op_done(); // remove placehold op count
        }
      });
    }

    if (need_save) {
      var S = new MTrackTicket(T);
      /* ensure that we only save the key properties we need */
      var keep = {
        id: true,
        nsident: true,
        ptid: true,
        milestones: true
      };
      for (var k in S.attributes) {
        if (!(k in keep)) {
          delete S.attributes[k];
        }
      }
      S.save(T, {
        success: function (model, resp) {
          op_done();
        }
      });
    } else {
      finalize();
    }
  },

  added: function(tkt) {
    this.addOne(tkt);
    var tickets = this.model.tickets;
    if (tkt.isNew()) {
      tkt.save(tkt.toJSON(), {
        success: function() {
          tickets.savePriorities();
        }
      });
    } else {
      tickets.savePriorities();
    }

    /* scroll it into view */
    mtrack_scroll_into_view($("#tkt-" + tkt.cid));
  },

  addOne: function(tkt) {
    var view = this;
    var id = 'tkt-' + tkt.cid;
    var el = document.getElementById(id);
    if (!el) {
      var view = new MTrackPlanningTicketView({
        model: tkt,
        rootView: view,
        id: id
      });
      var el = $(view.render().el);
      this.ul.append(el);
    }
  },

  addAll: function() {
    var view = this;
    this.model.tickets.each(function (tkt) { view.addOne(tkt) });
  }

});

MTrackUserProfileView = Backbone.View.extend({
  initialize: function (options) {
    this.template = _.template($('#user-profile-template').html());
    this.bind('change', this.render, this);
    this.render();
  },
  render: function() {
    $(this.el).html(this.template(this.model.toJSON()));
    return this;
  }
});

MTrackUserProfileEdit = Backbone.View.extend({
  initialize: function (options) {
    this.template = _.template($('#user-edit-template').html());
    this.model.bind('change', this.render, this);

    var model = this.model;
    var keys = this.model.keys;
    keys.bind('add', this.render, this);
    keys.bind('remove', this.render, this);
    var o = this.model.toJSON();
    o.pw_change = options.pw_change;
    o.privileged = options.privileged;
    $(this.el).html(this.template(o));
    if (model.isNew()) {
      new MTrackClickToEditTextField({
        model: this.model,
        srcattr: 'id',
        el: '#user-id',
        saveAfterEdit: true,
        placeholder: 'Enter username'
      });
    }
    new MTrackClickToEditTextField({
      model: this.model,
      srcattr: 'fullname',
      el: '#user-fullname',
      saveAfterEdit: true,
      placeholder: 'Enter Full Name'
    });
    new MTrackClickToEditTextField({
      model: this.model,
      srcattr: 'email',
      el: '#user-email',
      saveAfterEdit: true,
      placeholder: 'Enter email'
    });
    new MTrackClickToEditTextField({
      model: this.model,
      srcattr: 'timezone',
      el: '#user-timezone',
      saveAfterEdit: true,
      placeholder: 'Enter timezone'
    });

    if (options.privileged) {
      new MTrackSelectEditorView({
        el: "#primary-role",
        model: this.model,
        srcattr: 'role',
        saveAfterEdit: true,
        values: mtrack_roles
      });
    } else {
      new MTrackClickToEditTextField({
        model: this.model,
        srcattr: 'role',
        el: '#primary-role',
        readonly: true
      });
    }

    if (options.pw_change) {
      $('#save-password').click(function () {
        var pw1 = $('#passwd1');
        var pw2 = $('#passwd2');

        if (pw1.val() != pw2.val()) {
          alert("Passwords do not match");
          return false;
        }
        if (pw1.val() == '') {
          return false;
        }

        var m = new Backbone.Model;
        m.url = ABSWEB + 'api.php/user/' + model.id + '/password';
        m.password = pw1.val();
        m.save({password: pw1.val()}, {
          success: function() {
            pw1.val('');
            pw2.val('');
          },
          error: function(model, err) {
            alert(err);
          }
        });

        return false;
      });
    }

    this.$('#user-tabs').tabs();

    $('#user-active').change(function () {
      model.save({active: $(this).prop('checked')});
    });

    $('#add-key').click(function () {
      var nk = $('#new-key');
      var lines = nk.val().split("\n");
      var reject = [];
      nk.val('');
      _.each(lines, function (line) {
        var bits = line.split(/\s+/);
        if (bits.length != 3) {
          reject.push(line);
          return;
        }
        var key = new MTrackUserKey({id: bits[2]});
        keys.add(key);
        key.save({
          key: bits[0] + " " + bits[1]
        }, {
          success: function() {
          },
          error: function(model, err) {
            keys.remove(model);
            nk.val(nk.val() + "\n" + model.get('key') + " " + model.get('id'));
            alert("failed to save key " + model.get('id') + "\n" + err);
          }
        });
      });
    });

    var add_alias = $('#add-alias-button');
    add_alias.click(function () {
      var str = $('#add-alias').val();
      str = str.replace(/^\s+/, '');
      str = str.replace(/\s+$/, '');
      if (str.length) {
        model.save({
          aliases: _.union(model.get('aliases'), [str])
        });
      }
      $('#add-alias').val('');
    });

    this.render();
  },
  render: function() {
    $('#user-active').prop('checked', this.model.get('active'));

    var kl = $('#keys-list');
    kl.empty();
    var keys = this.model.keys;
    keys.each(function (key) {
      var li = $("<li/>");
      li.text(key.get('id'));
      var b = $('<button class="btn">X</button>');
      li.append(b);
      b.click(function () {
        if (confirm("Really delete key " + key.get('id') + "?\n" +
            "There is no undo for this action!")) {
          key.destroy();
          keys.remove(key);
        }
      });
      kl.append(li);
    });

    var al = $('#aliases-list');
    al.empty();
    var model = this.model;
    _.each(model.get('aliases'), function (name) {
      var li = $("<li/>");
      li.text(name);

      var b = $('<button class="btn">X</button>');
      li.append(b);
      b.click(function () {
        if (confirm("Really delete alias " + name + "?\n" +
            "There is no undo for this action!")) {
          model.save({
            aliases: _.without(model.get('aliases'), name)
          });
        }
      });
      kl.append(li);
      al.append(li);
    });

    var gl = $('#groups-list');
    gl.empty();
    var model = this.model;
    _.each(model.get('groups'), function (name) {
      var li = $("<li/>");
      li.text(name);
      gl.append(li);
    });

    return this;
  }
});

MTrackWatchListView = Backbone.View.extend({
  render: function() {
    var el = $(this.el);
    el.empty();
    el.append('<b>Watching</b>');
    var ul = $('<ul/>');
    el.append(ul);
    this.collection.each(function (item) {
      var li = $('<li/>');
      li.html(item.get('url'));
      ul.append(li);
    });

    return this;
  }
});

/* uses the "perms" attribute of the supplied model to render
 * an ACL editor.
 * Call the compute method to compile the new ACL ready for
 * submission to the API endpoint when you want to save the model.
 */
MTrackACLEditView = Backbone.View.extend({
  initialize: function (options) {
    this.options = options;
    this.action_map = options.action_map;
    this.template = _.template(mtrack_underscore_templates['acl-edit']);
  },
  render: function() {
    $(this.el).html(this.template({}));

    var perms = this.model.get('perms');

    /* first, compute a table with columns:
     * Entity | Cat 1 | Cat 2
     * Where Cat 1 through Cat n are the top level keys of the supplied
     * action_map */

    var tbody = $('tbody', $(this.el));

    var tr = $('thead tr', $(this.el));
    // Add columns for action groups
    var cat_order = _.keys(this.action_map).sort(
      function (a, b) {
        return b - a;
      }
    );

    /* a map for reverse engineering a permission to the appropriate
     * group in the action map */
    var reng = {};
    var rank = {};
    /* filled in with action_map and populated with the actual
     * permissions values for each level in the map.
     * eg: {SSH: ['-checkout|-commit', 'None']}
     */
    var mobj = {};
    var groups = {};

    for (var gi in cat_order) {
      var group = cat_order[gi];
      tr.append($('<th/>').text(group));

      /* let's also build up a map to help us with later work */
      var all_perms = _.keys(this.action_map[group]);
      var prohibit = {};
      _.each(this.action_map[group], function (caption, perm) {
        prohibit[perm] = "-" + perm;
      });
      var none = _.values(prohibit).join('|');
      var a = [[none, 'None']];
      var accum = [];
      var i = 0;
      _.each(this.action_map[group], function (caption, perm) {
        accum.push(perm);
        delete prohibit[perm];
        var p = _.clone(accum);
        _.each(prohibit, function (E) {
          p.push(E);
        });
        a.push([p.join('|'), caption]);
        reng[perm] = group;
        if (!(group in rank)) {
          rank[group] = {};
        }
        rank[group][perm] = i++;
      });
      mobj[group] = a;
    }

    /* A helper function that processes an ACL like this:
     * [['wez', 'checkout', 1], ['wez', 'commit', 1]]
     * and turns it into this:
     * {wez: {"SSH":['checkout', 'commit']}}
     */
    function group_actions(acl) {
      var defs = {};
      _.each(acl, function (ent) {
        var role = ent[0];
        var action = ent[1];
        var allow = ent[2];

        if (!(action in reng)) {
          return;
        }
        var group = reng[action];
        if (!allow) {
          action = '-' + action;
        }
        if (!(role in defs)) {
          defs[role] = {};
        }
        if (!(group in defs[role])) {
          defs[role][group] = [];
        }
        defs[role][group].push(action);

        if (!(role in groups)) {
          groups[role] = role;
        }
      });
      return defs;
    }
    var roledefs = group_actions(perms.acl);
    var inherited = group_actions(perms.inherited);

    /* Inheritable set may not be specified in the same terms as the
     * action_map so we need to infer it.
     * Example: we may have read|modify leaving delete unspecified.
     * We treat this as read|modify|-delete
     */
    for (var role in inherited) {
      var agroups = inherited[role];
      for (var group in agroups) {
        var actions = agroups[group];
        var highest = null;
        for (var i in actions) {
          var act = actions[i];
          if (act.charAt(0) == '-') {
            continue;;
          }
          if (highest == null || rank[group][act] > highest) {
            highest = rank[group][act];
          }
        }
        if (highest == null) {
          delete inherited[role][group];
          continue;
        }
        // Compute full value
        var comp = [];
        for (var act in rank[group]) {
          var val = rank[group][act];
          if (val <= highest) {
            comp.push(act);
          } else {
            comp.push('-' + act);
          }
        }
        inherited[role][group] = comp.join('|');
      }
    }

    function add_acl_entity(role)
    {
      // Delete role from select box
      $('option', sel).each(function () {
        if ($(this).attr('value') == role) {
          $(this).remove();
        }
      });
      // Create a row for this role
      var sp = $('<tr style="cursor:pointer"/>');
      var label = $('<span/>');
      label.text(groups[role]);
      label.attr('data-role', role);
      sp.append(
        $('<td/>')
          .html('<span style="position: absolute; margin-left: -1.3em" class="ui-icon ui-icon-arrowthick-2-n-s"></span>')
          .append(label)
      );
      tbody.append(sp);

      for (var gi in cat_order) {
        var group = cat_order[gi];
        var gsel = $('<select/>');
        gsel.data('acl.role', role);
        var data = mobj[group];
        for (var i in data) {
          var a = data[i];
          gsel.append(
            $('<option/>')
              .attr('value', a[0])
              .text(a[1])
            );
        }
        if (roledefs[role]) {
          gsel.val(roledefs[role][group].join('|'));
        }
        sp.append(
          $('<td/>')
            .append(gsel)
        );
      }
      var b = $('<button class="btn btn-mini"><i class="icon-trash"></i></button>');
      sp.append(
        $('<td/>')
          .append(b)
      );
      b.click(function () {
        sp.remove();
        sel.append(
          $('<option/>')
            .attr('value', role)
            .text(groups[role])
        );
      });
    }

    // Add fixed inherited rows
    var thead = $('thead', $(this.el));
    for (var role in inherited) {
      tr = $('<tr class="inheritedacl"/>');
      tr.append($('<td/>').text(groups[role]));
      for (var group in mobj) {
        var d = inherited[role][group];
        if (d) {
          // Good old fashioned look up (we don't have this hashed)
          for (var i in mobj[group]) {
            var ent = mobj[group][i];
            if (ent[0] == d) {
              d = ent[1];
              break;
            }
          }
          tr.append($('<td/>').text(d));
        } else {
          tr.append($('<td>(Not Specified)</td>'));
        }
      }
      thead.append(tr);
    }
    sel = $('<select/>');
    sel.append(
        $('<option/>')
        .text('Add...')
        );

    // Populate list of roles and expand titles
    $.ajax({
      url: ABSWEB + "api.php/acl/roles",
      success: function (data) {
        // Update any labels and expand the list
        // with data from the server side
        for (var id in data) {
          var label = data[id];

          groups[id] = label;
        }

        // Update any labels that may be in the table.
        // While looking, build up a list of who is in the table;
        // we don't want to add those people back to the "Add..."
        // option at the bottom.
        var already = {};
        $('span[data-role]', this.el).each(function () {
          var sp = $(this);
          var role = sp.attr('data-role');
          already[role] = role;
          sp.text(groups[role]);
        });

        // Now add the users to "Add..."
        for (var i in groups) {
          if (i in already) continue;
          var g = groups[i];
          sel.append(
              $('<option/>')
              .attr('value', i)
              .text(g)
            );
        }
      },
    });

    $(this.el).append(sel);

    /* make the tbody sortable. Note that we append the "Add..." to the table,
    * not the tbody, so that we don't allow dragging it around */
    tbody.sortable();

    for (var role in roledefs) {
      add_acl_entity(role);
    }

    sel.change(function () {
      var v = sel.val();
      if (v && v.length) {
        add_acl_entity(v);
      }
    });

    return this;
  },
  compute: function() {
    var acl = [];
    var tbody = $('tbody', this.el);
    $('select', tbody).each(function () {
      var role = $(this).data('acl.role');
      var val = $(this).val().split('|');
      for (var i in val) {
        var action = val[i];
        var allow = 1;
        if (action.substring(0, 1) == '-') {
          allow = 0;
          action = action.substring(1);
        }
        acl.push([role, action, allow]);
      }
    });
    /* suitable for doing: perms.acl = V.compute();
     * and feeding back to compatible REST APIs */
    return acl;
  }
});

MTrackRepoEditView = Backbone.View.extend({
  initialize: function(options) {
    this.options = options;
    this.template = _.template(mtrack_underscore_templates['repo-edit']);
  },
  show: function(on_success) {
    var o = this.model.toJSON();
    o.owner = o.parent;
    delete o.parent;
    o.isnew = this.model.isNew();
    o.repotypes = mtrack_repotypes_select;
    o.repotypenames = mtrack_repotypes;

    $(this.el).html(this.template(o));

    var view = this;
    $(view.el).appendTo('body');
    var dlg = $('div.modal', view.el);
    dlg.on('hidden', function () {
      $(view.el).remove();
    });

    var acleditor = null;
    if (!view.model.isNew()) {
      acleditor = new MTrackACLEditView({
        model: view.model,
        el: $('#repo-perms', dlg),
        action_map: {
          Web: {
            read: 'Browse via web UI',
          modify: 'Administer via web UI',
          delete: 'Delete repo via web UI'
          },
          SSH: {
            checkout: 'Check-out repo via SSH',
            commit: 'Commit changes to repo via SSH'
          }
        }
      });
      acleditor.render();
    }

    /* get the list of eligible projects for notifications;
     * when it arrives, update the notifications tab */
    view.projects = new MTrackProjectCollection;

    function fill_projects() {
      $('select[name=project]', dlg).each(function () {
        var sel = $(this);
        view.projects.each(function (P) {
          var opt = $('<option/>');
          opt.attr('value', P.id);
          var label = P.get('name');
          if (P.get('notifyemail')) {
            label += ' <' + P.get('notifyemail') + '>';
          }
          opt.text(label);
          sel.append(opt);
        });
        // pre-select the correct project
        sel.val(sel.attr('data-projid'));
      });

      /* on the notifications tab, update the text that says where
       * the notifications will go */
      var oproj = view.model.get('parent');
      if (oproj) {
        var m = oproj.match(/^project:(.*)$/);
        if (m) {
          /* look for the project name in our collection */
          var P = view.projects.find(function (P) {
            return P.get('shortname') == m[1];
          });
          $('b#ownername', dlg).text(P.get('name'));
          var email = P.get('notifyemail');
          if (!email) {
            email = "nowhere";
          }
          $('b#owneremail', dlg).text(email);
        }
      }
    }

    $('div.newlink button', dlg).click(function () {
      var regex = $('div.newlink input[name=regex]');
      var proj = $('div.newlink select[name=project]');

      if (!regex.val().match(/\S/)) {
        return;
      }

      /* copy this up to the links section */
      var link = $('div.newlink').clone();
      link.removeClass('newlink');
      $('button', link).remove();
      $('div.links', dlg).append(link);
      $('select[name=project]', link).val(proj.val());
      regex.val('');
    });

    view.projects.fetch({
      success: function() {
        fill_projects();
      },
      error: function (col, resp) {
        mtrack_ajax_error_to_dom(resp, $('div.modal-body', dlg));
      }
    });

    /* populate list of allowed parents */
    if (view.model.isNew()) {
      $.ajax({
        url: ABSWEB + "api.php/repo/allowed-targets",
        success: function (data) {
          var sel = $('select[name=parent]', dlg);
          _.each(data, function (value, key) {
            var opt = $('<option/>');
            opt.attr('value', key);
            opt.text(value);
            sel.append(opt);
          });
        },
      });
    }

    /* delete button */
    $('#deletebtn', dlg).click(function () {
      if ($('input[name=deleteme]', dlg).val() == view.model.get('shortname')) {
        view.model.destroy({
          success: function (model, resp) {
            dlg.modal('hide');
            on_success(null);
          },
          error: function (model, resp) {
            mtrack_ajax_error_to_dom(resp, $('div.modal-body', dlg));
          }
        });
      }
    });

    /* save button */
    $('button.btn-primary', dlg).click(function () {
      /* get fields out */
      var owner = $('select[name=parent]', dlg).val();
      var name = $('input[name=name]', dlg).val();
      var desc = $('textarea[name=description]', dlg).val();
      var typ = $('select[name=type]', dlg).val();

      var links = [];
      $('div.links div', dlg).each(function () {
        var link = {};
        var L = $(this);
        if (L.attr('data-linkid')) {
          link.id = L.attr('data-linkid');
        }
        link.regex = $('input[name=regex]', L).val();
        link.project = $('select[name=project]', L).val();
        links.push(link);
      });

      var data = {
        links: links,
        description: desc
      };

      if (view.model.isNew()) {
        data.parent = owner;
        data.shortname = name;
        data.scmtype = typ;
      }

      if (acleditor) {
        data.perms = {
          acl: acleditor.compute()
        };
      }

      view.model.save(data, {
        success: function (model, resp) {
          /* do something useful */
          dlg.modal('hide');
          on_success(model);
        },
        error: function (model, resp) {
          mtrack_ajax_error_to_dom(resp, $('div.modal-body', dlg));
        }
      });
    });

    dlg.modal('show');
  }

});

MTrackProjectEditView = Backbone.View.extend({
  initialize: function(options) {
    this.options = options;
    this.template = _.template(mtrack_underscore_templates['project-edit']);
  },
  show: function(on_success) {
    var o = this.model.toJSON();
    if (!o.name) {
      o.name = '';
    }
    if (!o.notifyemail) {
      o.notifyemail = '';
    }
    o.isnew = this.model.isNew();

    $(this.el).html(this.template(o));

    var view = this;
    $(view.el).appendTo('body');
    var dlg = $('div.modal', view.el);
    dlg.on('hidden', function () {
      $(view.el).remove();
    });

    var acleditor = null;
    var userlist = null;
    var proto_select = null;
    var next_group_id = 1;

    function make_group_tab(grpname, activate) {
      if (!grpname || !grpname.match(/\S/)) return null;
      $('input[name=newgroup]', dlg).val('');

      /* already have a tab with that name? */
      var tab = null;
      $('#project-groups ul.nav-tabs li', dlg).each(function () {
        if ($(this).attr('data-name') == grpname) {
          tab = $(this);
        }
      });

      if (tab) return tab;

      tab = $('<li/>');
      tab.attr('data-name', grpname);
      var a = $('<a/>');
      var grpid = 'group-' + next_group_id++;
      a.attr('href', '#' + grpid);
      a.attr('data-toggle', 'tab');
      a.text(grpname);
      tab.append(a);
      $('#project-groups ul.nav-tabs', dlg).append(tab);

      /* also need to create the list of people */
      var plist = $('<div class="tab-pane"/>');
      plist.attr('id', grpid);
      plist.text("Group: " + grpname);
      plist.append("<br/>");

      $('#project-groups div.tab-content', dlg).append(plist);

      var sel = proto_select.clone();
      sel.data('group-name', grpname);
      plist.append(sel);
      var groups = view.model.get('groups');
      sel.val(groups[grpname]);
      sel.css('width', '300px').chosen();

      if (activate) {
        // Activate new tab
        // would do: a.tab(); but it doesn't seem to work
        a.trigger('click');
      }

      return tab;
    }

    if (!view.model.isNew()) {
      acleditor = new MTrackACLEditView({
        model: view.model,
        el: $('#project-perms', dlg),
        action_map: {
          Admin: {
            modify: 'Administer via web UI'
          }
        }
      });
      acleditor.render();

      $.ajax({
        url: ABSWEB + "api.php/ticket/meta/users",
        success: function (data) {
          userlist = data;

          proto_select = $('<select/>');
          proto_select.attr('multiple', 'multiple');
          _.each(userlist, function (user) {
            var opt = $('<option/>');
            opt.attr('value', user.id);
            opt.text(user.label);
            proto_select.append(opt);
          });

          // now we can render the group tabs
          var groups = view.model.get('groups');
          _.each(groups, function (users, grpname) {
            make_group_tab(grpname);
          });
        }
      });
    }

    /* adding groups */
    $('button#addgroup', dlg).click(function () {
      var grpname = $('input[name=newgroup]', dlg).val();
      var tab = make_group_tab(grpname, true);
    });

    /* save button */
    $('button.btn-primary', dlg).click(function () {
      /* get fields out */
      var name = $('input[name=name]', dlg).val();
      var notifyemail = $('input[name=notifyemail]', dlg).val();

      var data = {
        name: name,
        notifyemail: notifyemail
      };

      if (view.model.isNew()) {
        var shortname = $('input[name=shortname]', dlg).val();
        data.shortname = shortname;
      }

      if (acleditor) {
        data.perms = {
          acl: acleditor.compute()
        };

        /* compute the groups */
        var groups = {};
        $('#project-groups select', dlg).each(function () {
          var sel = $(this);
          var grpname = sel.data('group-name');
          groups[grpname] = sel.val();
        });
        data.groups = groups;
      }

      view.model.save(data, {
        success: function (model, resp) {
          /* do something useful */
          dlg.modal('hide');
          on_success(model);
        },
        error: function (model, resp) {
          mtrack_ajax_error_to_dom(resp, $('div.modal-body', dlg));
        }
      });
    });

    dlg.modal('show');
  }
});

// Ticket editor
var MTrackTicketEditor = Backbone.View.extend({
  show: function(on_success) {
    var o = this.model.toJSON();
    o.isnew = this.model.isNew();
    // A clone of the model to use for editing with the existing
    // set of editors
    var dup_model = this.model.clone();
    dup_model.getAttachments().reset(this.model.getAttachments().models);

    $(this.el).html(_.template(
      mtrack_underscore_templates['ticket-edit'], o));

    var view = this;
    $(view.el).appendTo('body');
    var dlg = $('div.modal', view.el);
    var in_wiki = false;

    dlg.on('hidden', function () {
      if (!in_wiki) {
        $(view.el).remove();
        if ('hidden' in on_success) {
          on_success.hidden();
        }
      }
    });

    // Validation errors
    dup_model.bind('error', function (model, err) {
      mtrack_ajax_error_to_dom(err, $('div.modal-header', view.el));
    });

    var attach_list = $('#attach-list', dlg);
    attach_list.on('click', 'button.delattach', function() {
      var att = $(this).closest('div.attachment').data('attachment-model');
      var m = $("<div class='modal fade'><div class='modal-header'><a class='close' data-dismiss='modal'>x</a><h3>Delete Attachment?</h3></div><div class='modal-body'><p><b></b></p><p>Do you really want to delete this attachment?</p><p>You cannot undo this action!</p></div><div class='modal-footer'><button class='btn' data-dismiss='modal'>Close</button><button class='btn btn-danger'>Delete</button></div></div>");

      $('b', m).text(att.get('filename'));
      $('.btn-danger', m).click(function () {
        att.destroy();
        m.modal('hide');
      });

      m.on('hidden', function() {
        m.remove();
      });
      m.modal();

    });
    function redraw_attachment_list() {
      attach_list.empty();
      var t = _.template(mtrack_underscore_templates['attachment-item-edit']);

      dup_model.getAttachments().each(function (att) {
        var o = att.toJSON();
        if ('width' in o) {
          o.image = true;
        } else {
          o.image = false;
        }
        var d = $(t(o));
        d.data('attachment-model', att);
        attach_list.append(d);
      });
      $('.timeinterval', attach_list).timeago();
    }
    redraw_attachment_list();
    dup_model.getAttachments().bind('all', function () {
      redraw_attachment_list();
    });

    // Grab template elements and take them out of the DOM
    var tab_ul = $('ul.nav-tabs', view.el);
    var tab_hdr = $('li:first', tab_ul);
    tab_hdr.remove();
    var tab_att = $('li', tab_ul);
    tab_att.remove(); // we'll append it at the end
    var tab_content = $('div.tab-content', view.el);
    var tab_body = $('div.tab-pane:first', tab_content);
    tab_body.remove();

    var next_tab_id = 1;
    function add_group_tab(group) {
      var label;
      if (group.name == 0) {
        label = 'Details';
      } else {
        label = group.name;
      }

      var id = 'tab-' + next_tab_id++;

      var hdr = tab_hdr.clone();
      $('a', hdr).attr('href', '#' + id);
      $('a', hdr).text(label);
      tab_ul.append(hdr);

      var tab = tab_body.clone();
      tab.attr('id', id);
      tab_content.append(tab);

      if (next_tab_id == 2) {
        // Make the first one active
        $('a', hdr).trigger('click');
      }

      return tab;
    }

    function multi_editor(lcont, tab, field) {
      var label = $('<label/>');
      label.text(field.label);
      tab.append(label);
      tab.append("<br>");

      var edit = $('<textarea/>', {
        class: 'multi',
        cols: field.cols,
        rows: field.rows,
        placeholder: field.placeholder
      });
      var val = dup_model.get(field.name);
      if (val) {
        edit.val(val);
      }
      edit.on('change', function() {
        var o = {};
        o[field.name] = edit.val();
        dup_model.set(o);
      });
      lcont.remove();
      tab.append(edit);
      tab.attr('colspan', 2);
    }

    function wiki_editor(lcont, tab, field) {
      var label = $('<label/>');
      label.text(field.label);
      tab.append(label);
      tab.append("<br>");

      var edit = $('<textarea/>', {
        class: 'wiki shortwiki',
        cols: field.cols,
        rows: field.rows,
        placeholder: field.placeholder
      });
      var val = dup_model.get(field.name);
      if (val) {
        edit.val(val);
      }
      lcont.remove();
      tab.append(edit);
      var b = $('<button/>', {
        class: 'btn'
      });
      b.html('<i class="icon-pencil"></i> Edit ' + field.label + ' in wiki editor');
      tab.append(b);
      tab.attr('colspan', 2);

      dup_model.bind('change:' + field.name, function () {
        var val = dup_model.get(field.name);
        edit.val(val ? val : '');
      });
      edit.on('change', function() {
        var o = {};
        o[field.name] = edit.val();
        dup_model.set(o);
      });

      var wiki = new MTrackWikiTextAreaView({
        model: dup_model,
        wikiContext: 'ticket:',
        use_overlay: true,
        Caption: "Edit " + field.label,
        OKLabel: "Accept " + field.label,
        CancelLabel: "Abandon changes to " + field.label,
        srcattr: field.name,
        renderedattr: field.name + '_html'
      });
      wiki.bind('editstart', function () {
        in_wiki = true;
        setTimeout(function () {
          dlg.modal('hide');
        }, 1000);
      });
      wiki.bind('editend', function () {
        in_wiki = false;
        dlg.modal('show');
      });

      b.click(function () {
        var o = {};
        o[field.name] = edit.val();
        dup_model.set(o);
        wiki.edit();
      });
    }

    function text_editor(lcont, tab, field) {
      lcont.text(field.label);

      var inp = $('<input/>', {
        type: "text",
        name: field.name,
        placeholder: field.placeholder
      });
      var val = view.model.get(field.name);
      if (val) {
        inp.val(val);
      }
      inp.on('change', function() {
        var o = {};
        o[field.name] = inp.val();
        dup_model.set(o);
      });

      tab.append(inp);
    }

    function multiselect_editor(lcont, tab, field) {
      lcont.text(field.label);

      var el = $('<div/>');
      tab.append(el);
      var view = new MTrackSelectEditorView({
        el: el,
        model: dup_model,
        multiple: true,
        srcattr: field.name,
        label: field.label,
        width: '418px',
        values: field.options,
        defval: field["default"],
        placeholder: field.placeholder
      });
      view.render();
    }

    function select_editor(lcont, tab, field) {
      lcont.text(field.label);

      var el = $('<span/>');
      tab.append(el);
      var view = new MTrackSelectEditorView({
        el: el,
        model: dup_model,
        srcattr: field.name,
        label: field.label,
        width: '418px',
        values: field.options,
        defval: field["default"],
        placeholder: field.placeholder
      });
      view.render();
    }

    function ticketdeps_editor(lcont, tab, field) {
      lcont.text(field.label);

      var el = $('<span/>');
      tab.append(el);
      var view = new MTrackTicketDepEditView({
        el: el,
        model: dup_model,
        srcattr: field.name,
        label: field.label,
      });
      view.render();
    }

    function tags_editor(lcont, tab, field) {
      lcont.text(field.label);

      var el = $('<span/>');
      tab.append(el);
      var view = new MTrackTagEditView({
        el: el,
        model: dup_model,
        srcattr: field.name,
        label: field.label,
      });
      view.render();
    }

    function cc_editor(lcont, tab, field) {
      lcont.text(field.label);

      var el = $('<span/>');
      tab.append(el);
      var view = new MTrackCcEditView({
        el: el,
        model: dup_model,
        srcattr: field.name,
        label: field.label,
      });
      view.render();
    }

    var editors = {
      multi: multi_editor,
      select: select_editor,
      multiselect: multiselect_editor,
      tags: tags_editor,
      cc: cc_editor,
      ticketdeps: ticketdeps_editor,
      text: text_editor,
      wiki: wiki_editor
    };

    function add_editor(tr, tab, field)
    {
      if (field.type == 'readonly') return;

      var editor = text_editor;

      if (field.type in editors) {
        editor = editors[field.type];
      }
      tr = tr.clone();
      var lcont = $('td.fieldname', tr);
      var div = $('td.fieldvalue', tr);
      tab.append(tr);
      editor(lcont, div, field);
    }

    function add_tab_and_fields(group) {
      var tab = add_group_tab(group);

      var table = $('table', tab);
      var tr = $('tr', table);
      tr.remove();

      if (group.name == 0) {
        // Synthesize some fields
        add_editor(tr, table, {
          name: 'summary',
          label: 'Summary',
          placeholder: 'One line summary -- required!'
        });
        add_editor(tr, table, {
          name: 'status',
          label: 'Status',
          type: 'select',
          options: mtrack_ticket_states
        });
        if (dup_model.get('status') != 'closed') {
          add_editor(tr, table, {
            name: 'resolution',
            label: 'Resolution',
            placeholder: 'Resolve ticket as...',
            type: 'select',
            options: mtrack_resolutions
          });
        }
      }
      _.each(group.fields, function (field) {
        add_editor(tr, table, field);
      });
    }

    // Don't show the comment for new tickets!
    if (!view.model.isNew()) {
      add_tab_and_fields({
        name: 'Comment',
        fields: [
          {
            name: 'comment',
            label: 'Comment',
            type: 'wiki',
            placeholder: 'Something on your mind?  Share it here!',
            rows: 10,
            cols: 78
          }
        ]
      });
    }

    /* Create a tab for each category of field */
    for (var gidx in this.options.fields) {
      add_tab_and_fields(this.options.fields[gidx]);
    }
    tab_ul.append(tab_att);

    /* handle uploads */
    var uploading = false;
    $('#confirm-upload', dlg).click(function () {
      uploading = true;
      $('#upload-form', dlg).submit();
    });
    $('#upload_target', dlg).on('load', function () {
      var res = $(this).contents().find('body').text();
      try {
        res = JSON.parse(res);
        if (res.status == 'success') {
          if (uploading) {
            $('<div class="alert alert-success">' +
              '<a class="close" data-dismiss="alert">&times;</a>' +
              'Upload successful</div>').
              appendTo($('#tkt-edit-attachments', dlg));
            dup_model.getAttachments().reset(res.attachments);
          }
          $('input[type=file]', dlg).val('');
        } else {
          $('<div class="alert alert-danger">' +
            '<a class="close" data-dismiss="alert">&times;</a>' +
            res.message + '</div>').
            appendTo($('#tkt-edit-attachments', dlg));
        }
      } catch (e) {
      }
      uploading = false;
    });

    // Present the conflict resolution UI.
    // This is an alternative form that shows the changes side-by-side
    // and allows the user to pick a resolution:
    // - Accept my changes
    // - Accept their changes
    // - Cancel
    // The first two will re-display the edit dialog with the updated
    // model, the latter will cancel the edit dialog.
    function show_conflict_resolver(conflict) {
      var updated = conflict.updated;
      delete conflict.updated;
      delete conflict.description_html;
      var o = {
        nsident: dup_model.get('nsident'),
        summary: dup_model.get('summary'),
        conflict: conflict,
        updated: updated,
        ABSWEB: ABSWEB
      };
      var CD = $(_.template(mtrack_underscore_templates['ticket-conflict'], o));
      $('body').append(CD);
      $('.timeinterval', CD).timeago();

      // Fixup model so that we don't trigger a 409 on next save
      // (unless there is a further conflict!)
      dup_model.set({updated: o.updated});

      dlg.modal('hide');
      CD.modal('show');
      CD.on('hidden', function() {
        CD.remove();
      });

      $('button.mine', CD).click(function () {
        var editor = new MTrackTicketEditor({
          model: dup_model,
          fields: view.options.fields
        });
        CD.modal('hide');
        editor.show(on_success);
      });

      $('button.theirs', CD).click(function () {
        var o = {};
        for (var k in conflict) {
          var item = conflict[k];
          o[k] = item[1];
        }
        dup_model.set(o);

        var editor = new MTrackTicketEditor({
          model: dup_model,
          fields: view.options.fields
        });
        CD.modal('hide');
        editor.show(on_success);
      });
    }
    /*
    $('.modal-footer button.conflict', dlg).click(function () {
      show_conflict_resolver({
        updated: {
          who: 'otherguy',
          when: "2012-04-11T14:54:36+00:00",
          cid: "17"
        },
        summary: ['my lemons', 'your lemons']
      });
    });
     */

    // Save.  We want to apply the attributes from the dup_model to
    // the real model.
    $('.modal-footer button.btn-primary', dlg).click(function () {
      view.model.save(dup_model.attributes, {
        success: function(model) {
          if ('success' in on_success) {
            on_success.success(model);
          }
          dlg.modal('hide');
        },
        error: function (model, resp) {
          // If a conflict was detected, show some useful UI to help
          // them through it
          var is_conflict = false;

          if (_.isObject(resp)) {
            try {
              var r = JSON.parse(resp.responseText);
              if (r.code == 409) {
                is_conflict = r.extra;
              }
            } catch (e) {
            }
          }

          if (!is_conflict) {
            mtrack_ajax_error_to_dom(resp, $('div.modal-header', dlg));
            return;
          }

          show_conflict_resolver(is_conflict);
        }
      });
    });

    dlg.modal('show');
  }
});

// Ticket viewer
var MTrackTicketViewer = Backbone.View.extend({
  render: function() {
    var t = _.template(mtrack_underscore_templates['ticket-show']);
    var o = this.model.toJSON();
    $(this.el).html(t(o));
//    $('body').attr('data-target', '#tkt-nav');
//    $('body').scrollspy({offset: 30});

    var F = $('#tkt-fields');
    // Table row template
    var TR = F.find('tr');
    var table = TR.parent();
    TR.remove();

    var model = this.model;
    var view = this;

    var attach_list = $('#attach-list', this.el);
    function redraw_attachment_list() {
      attach_list.empty();
      var t = _.template(mtrack_underscore_templates['attachment-item']);
      var count = 0;

      model.getAttachments().each(function (att) {
        count++;
        var o = att.toJSON();
        if ('width' in o) {
          o.image = true;
        } else {
          o.image = false;
        }
        var d = $(t(o));
        d.data('attachment-model', att);
        attach_list.append(d);
      });
      if (count) {
        $('.timeinterval', attach_list).timeago();
        $('#attach', view.el).show();
        $('.tkt-outline a[href=#attach]').show();
      } else {
        $('#attach', view.el).hide();
        $('.tkt-outline a[href=#attach]').hide();
      }
    }
    redraw_attachment_list();
    model.getAttachments().bind('all', function () {
      redraw_attachment_list();
    });

    var change_tpl = _.template(
                        mtrack_underscore_templates['ticket-event-show']);
    var change_cont = $('#tkt-comments', this.el);

    function add_one_change(cs) {
      var d = $('<div/>');
      $(d).html(change_tpl(cs.toJSON()));
      var had_comment = false;
      var commit = false;
      _.each(cs.get('audit'), function (a) {
        if (a.label == 'Comment') {
          had_comment = true;
          if (a.value.match(/^\(In /)) {
            commit = true;
          }
        }
      });
      if (had_comment) {
        d.addClass('chg-comment');
        if (commit) {
          d.addClass('chg-commit');
        }
      } else {
        d.addClass('chg-no-comment');
      }
      d.appendTo(change_cont);
      $('.toggle-desc', d).click(function () {
        $('#' + $(this).attr('desc-id')).toggle();
        return false;
      });
      $('.timeinterval', d).timeago();
    }
    function redraw_change_list() {
      model.getChanges().each(function (cs) {
        add_one_change(cs);
      });
    }
    redraw_change_list();

    function refresh_changes() {
      var changes = model.getChanges();
      var recent = changes.at(0);

      // If a modal dialog is open, don't do any updating
      if ($('body').hasClass('modal-open')) {
        return;
      }

      if (!recent) {
        return;
      }

      changes.fetch({
        success: function (c, r) {
          if (!r.length) {
            return;
          }
          var latest = changes.at(0);
          if (latest.id == recent.id) {
            // No change
            return;
          }
          model.getAttachments().fetch();
          var base_cid = model.get('updated').cid;
          model.fetch({
            success: function (model, resp) {
              if (model.get('updated').cid == base_cid) {
                return;
              }
              /* it changed */
              model.unset('comment', {silent: true});
              view.render();
            }
          });
        }
      });
    }
    view.refresh = refresh_changes;

    var user_template = _.template(mtrack_underscore_templates['user-name']);
    function render_user(user) {
      if (!_.isObject(user)) {
        user = {
          id: user,
          label: user
        };
      }
      var o = _.clone(user);
      o.ABSWEB = ABSWEB;
      return user_template(o);
    }

    function render_user_list(users) {
      var res = [];
      _.each(users, function(user) {
        res.push(render_user(user));
      });
      return res.join(' ');
    }

    function render_change_time(item) {
      item = _.clone(item);
      item.ABSWEB = ABSWEB;
      return _.template(mtrack_underscore_templates['item-changed'], item);
    }

    var renderers = {
      'owner': render_user,
      'cc':    render_user_list,
      'created': render_change_time,
      'updated': render_change_time,
    };

    function process_field(field) {
      if (field.name == 'description') {
        return;
      }
      var val = model.get(field.name);
      if (typeof(val) == 'undefined' || val == null) {
        return;
      }
      if (_.isArray(val) && val.length == 0) {
        return;
      }
      if (typeof(val) == 'string' && val == '') {
        return;
      }
      var tr = TR.clone();
      $('td.fieldname', tr).text(field.label).attr('title', field.label);
      if (field.name in renderers) {
        $('td.fieldvalue', tr).html(renderers[field.name](val));
      } else if (field.customtemplate) {
        var o = {
          value: val,
          ABSWEB: ABSWEB
        };
        $('td.fieldvalue', tr).html(_.template(field.customtemplate, o));
      } else {
        // If we have a template defined, then use the generic template
        // based renderer.  First look to see if we have a template
        // for the specific field name, then try to fall back to a
        // generic formatter by type.
        var tplname = "ticket-field-byname-" + field.name;
        if (!(tplname in mtrack_underscore_templates)) {
          // Try by type
          tplname = null;
          if (field.customfieldtype) {
            tplname = "ticket-field-bytype-" + field.customfieldtype;
            if (!(tplname in mtrack_underscore_templates)) {
              tplname = null;
            }
          }
          if (!tplname) {
            tplname = "ticket-field-bytype-" + field.type;
          }
        }

        var render = function (a) {
          return $('<div/>').text(a).html();
        };

        if (tplname in mtrack_underscore_templates) {
          var t = _.template(mtrack_underscore_templates[tplname]);
          render = function (a) {
            /* wrap it up so that the value itself is accessible,
             * as some of the data types we have encode the ids
             * in the keys of an object and we can't iterate
             * the context without a name in the template handler */
            var o = {
              value: a,
              ABSWEB: ABSWEB
            };
            return t(o);
          };
        }
        $('td.fieldvalue', tr).html(render(val));
      }
      table.append(tr);
    }

    if (model.get('status') == 'closed') {
      process_field({name: 'resolution', label: 'Resolved'});
    } else {
      process_field({name: 'status', label: 'Status'});
    }
    if (model.get('parent')) {
      process_field({name: 'parent', label: 'Parent', type: 'ticket'});
    }

    process_field({name: 'created', label: 'Opened'});
    if (model.get('updated') &&
        model.get('updated').cid != model.get('created').cid) {
      process_field({name: 'updated', label: 'Updated'});
    }
    if (model.get('remaining')) {
      process_field({name: 'remaining', label: 'Remaining Time'});
    }

    for (var gidx in this.options.fields) {
      var group = this.options.fields[gidx];
      _.each(group.fields, process_field);
    }
    $('.timeinterval', this.el).timeago();

    $('#togglebtn', this.el).click(function () {
      $('#tkt-fields', this.el).toggle();
      return false;
    });

    // Open the ticket editor
    $('#editbtn', this.el).click(function () {
      var editor = new MTrackTicketEditor({
        model: view.model,
        fields: view.options.fields
      });
      editor.show({
        success: function (model) {
          window.location.replace(
            ABSWEB + 'ticket.php/' + model.get('nsident'));
        },
        hidden: function () {
          refresh_changes();
        }
      });
      return false;
    });

    /* operates on the result of a ticket split; if saved,
     * we're taken to the ticket page for the newly saved ticket */
    function edit_split(model, orig) {
      var editor = new MTrackTicketEditor({
        model: model,
        fields: view.options.fields
      });
      editor.show({
        success: function (model) {
          /* also add a comment on the original ticket to show that
           * it was split */
          var o = {
            id: orig.id,
            comment: "Split and created ticket #" + model.get('nsident')
          };
          var C = new MTrackTicket(o);
          C.save(o, {
            success: function () {
              /* go to that ticket page */
              window.location = ABSWEB + 'ticket.php/' + model.get('nsident');
            }
          });
        },
        hidden: function () {
          refresh_changes();
        }
      });
      return false;
    }

    function make_split_ticket() {
      var S = view.model.clone();
      console.log("cloned as", S);
      S.unset('spent');
      S.unset('remaining');
      S.unset('estimated');
      S.unset('nsident');
      S.unset('id');
      S.unset('created');
      S.unset('updated');
      S.set({children: []});
      S.set({description:
        "\nSplit from #" + view.model.get('nsident') + "\n\n---\n\n" +
        view.model.get('description')});
      // Pointless to clone it in a closed state
      if (S.get('status') == 'closed') {
        S.set({status: 'open'});
      }
      S.unset('resolution');
      return S;
    }
    $('#splitsib', this.el).click(function () {
      edit_split(make_split_ticket(), view.model);
      return false;
    });
    $('#splitchild', this.el).click(function () {
      var S = make_split_ticket();
      // New ticket is a child of the current one
      S.set({ptid: view.model.id});
      edit_split(S, view.model);
      return false;
    });

    if (view.model.isNew()) {
      var editor = new MTrackTicketEditor({
        model: view.model,
        fields: view.options.fields
      });
      editor.show({
        success: function (model) {
          window.location = ABSWEB + 'ticket.php/' + model.get('nsident');
        },
        hidden: function () {
          if (view.model.isNew()) {
            window.location = ABSWEB;
          }
        }
      });
    }

    window.mtrack_reply_comment = function (cid) {
      var c = view.model.getChanges().get(cid);
      orig_comment = view.model.get("comment");
      var comment = orig_comment || '';
      if (comment.length) {
        comment = comment + "\\n\\n";
      }
      var reason = c.get('reason');
      // cite it
      reason = reason.replace(/^(\s*)/mg, "> \$1");
      comment = comment + "Replying to [comment:" + cid + " a comment by " +
        c.get('who') + "]\\n" + reason + "\\n";
      in_reply = true;
      view.model.set({'comment': comment});

      $('#editbtn', this.el).trigger('click');

      return false;
    }

    return this;
  }
});


