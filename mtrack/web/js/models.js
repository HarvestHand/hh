/* vim:ts=2:sw=2:et:
 * For copyright and licensing terms, see the file named LICENSE */

// https://gist.github.com/1610397
function nestCollection(model, attributeName, nestedCollection) {
  //setup nested references
  for (var i = 0; i < nestedCollection.length; i++) {
    model.attributes[attributeName][i] = nestedCollection.at(i).attributes;
  }
  // create empty arrays if none
  nestedCollection.bind('add', function (initiative) {
    if (!model.get(attributeName)) {
      model.attributes[attributeName] = [];
    }
    model.get(attributeName).push(initiative.attributes);
  });

  nestedCollection.bind('remove', function (initiative) {
    var updateObj = {};
    updateObj[attributeName] = _.without(
                                  model.get(attributeName),
                                  initiative.attributes);
    model.set(updateObj);
  });
  return nestedCollection;
}

var MTrackProject = Backbone.Model.extend({
  defaults: {
    "shortname": '',
    "name": '',
    "notifyemail": ''
  },

  url: function() {
    if (this.isNew()) {
      return ABSWEB + "api.php/project";
    }
    return ABSWEB + "api.php/project/" + this.id;
  }
});

var MTrackProjectCollection = Backbone.Collection.extend({
  model: MTrackProject,
  url: function() {
    return ABSWEB + 'api.php/project';
  }
});

var MTrackRepo = Backbone.Model.extend({
  defaults: {
    "shortname": '',
    "description": '',
    "canDelete": false,
    "links": []
  },

  url: function() {
    if (this.isNew()) {
      return ABSWEB + "api.php/repo/properties/";
    }
    return ABSWEB + "api.php/repo/properties/" + this.id;
  }
});

var MTrackRepoList = Backbone.Collection.extend({
  model: MTrackRepo,
  url: function() {
    return ABSWEB + "api.php/repo/properties";
  }
});

var MTrackTicketChange = Backbone.Model.extend({
});

var MTrackAttachment = Backbone.Model.extend({
  url: function() {
    return ABSWEB + "api.php/attachment/" + this.id;
  }
});

var MTrackTicketAttachmentCollection = Backbone.Collection.extend({
  model: MTrackAttachment,
  ticket: null,
  url: function() {
    return ABSWEB + "api.php/ticket/" + this.ticket.id + "/attach";
  }
});

var MTrackTicketChangesCollection = Backbone.Collection.extend({
  model: MTrackTicketChange,
  ticket: null,
  comparator: function(cs) {
    return -parseInt(cs.id);
  },
  url: function() {
    return ABSWEB + "api.php/ticket/" + this.ticket.id + "/changes";
  }
});

var MTrackTicketCollection = null;

var MTrackTicket = Backbone.Model.extend({
  urlRoot: ABSWEB + "api.php/ticket",
  url: function() {
    if (this.isNew()) {
      return ABSWEB + "api.php/ticket";
    }
    return ABSWEB + "api.php/ticket/" + this.id;
  },
  defaults: {
    "nsident": null,
    "summary": null,
    "description": null,
    "classification": mtrack_ticket_defaults.classification,
    "severity": mtrack_ticket_defaults.severity,
    "priority": mtrack_ticket_defaults.priority
  },
  tktChanges: null,
  getChanges: function() {
    if (this.tktChanges == null) {
      this.tktChanges = new MTrackTicketChangesCollection;
      this.tktChanges.ticket = this;
    }
    return this.tktChanges;
  },
  tktAttachments: null,
  getAttachments: function() {
    if (this.tktAttachments == null) {
      this.tktAttachments = new MTrackTicketAttachmentCollection;
      this.tktAttachments.ticket = this;
    }
    return this.tktAttachments;
  },
  tktChildren: null,
  getChildren: function() {
    if (this.tktChildren == null) {
      this.tktChildren = new MTrackTicketCollection;
      this.tktChildren.ticket = this;
      var tkt = this;
      this.tktChildren.bind('add', function(model, col) {
        if (tkt.collection) {
          tkt.collection.trigger('childticketadd', tkt, model);
        }
        tkt.trigger('childticketadd', model);
      });
      this.tktChildren.bind('remove', function(model, col) {
        if (tkt.collection) {
          tkt.collection.trigger('childticketremove', tkt, model);
        }
        tkt.trigger('childticketremove', model);
      });
    }
    return this.tktChildren;
  },
  validate: function(attrs) {
    var m = {
      summary: [/\S/, "summary must not be empty", false],
      estimated: [/^\d*\.?\d*$/, "estimated time must be numeric", true],
      effortSpent: [/^-?\d*\.?\d*$/, "logged hours must be numeric", true]
    };
    for (var k in attrs) {
      if (k in m) {
        var pat = m[k][0];
        var reason = m[k][1];
        var nullok = m[k][2];
        if (attrs[k] == null) {
          if (nullok) continue;
          return reason;
        }
        if (attrs[k] != null && !attrs[k].match(pat)) {
          return reason;
        }
      }
    }
  }
});

MTrackTicketCollection = Backbone.Collection.extend({
  model: MTrackTicket,
  ticket: null,
  url: function () {
    return ABSWEB + "api.php/ticket/" + this.ticket.id + "/children";
  }
});

var MTrackMilestoneTicketCollection = Backbone.Collection.extend({
  model: MTrackTicket,
  milestone: null,
  /* synthesize the ordinal priority field.
   * We pretend that the tickets have a pri_ord field here,
   * then in the comparator we sneak it out of the attributes hash
   * and promote it to the ticket model itself */
  parse: function(response) {
    var i;
    for (i in response) {
      response[i].pri_ord = i;
    }
    return response;
  },
  comparator: function(tkt) {
    /* sneak the ordinal out of the attributes (see comment above parse()) */
    if ('pri_ord' in tkt.attributes) {
      tkt.pri_ord = parseInt(tkt.attributes.pri_ord);
      delete tkt.attributes.pri_ord;
      return tkt.pri_ord;
    }
    if ('pri_ord' in tkt) {
      return tkt.pri_ord;
    }
    return 0;
  },
  savePriorities: function () {
    var bulk = [];
    var collection = this;
    var i = 0;
    this.each(function(tkt) {
      tkt.pri_ord = i++;
      bulk.push(tkt.id);
    });
    var b = new Backbone.Model({tickets: bulk});
    b.url = ABSWEB + "api.php/milestone/" + this.milestone.id + "/prioritize";
    b.save();
  },
  url: function () {
    return ABSWEB + "api.php/milestone/" + this.milestone.id + "/tickets";
  }
});

var MTrackMilestone = Backbone.Model.extend({
  urlRoot: ABSWEB + "api.php/milestone",
  initialize: function (attributes) {
    this.tickets = new MTrackMilestoneTicketCollection();
    this.tickets.milestone = this;
  }
});

var MTrackMilestoneCollection = Backbone.Collection.extend({
  model: MTrackMilestone,
  url: ABSWEB + 'api.php/milestones'
});

var MTrackUserKey = Backbone.Model.extend({
  validate: function(attrs) {
    if ('key' in attrs) {
      if (!attrs.key.match(/^ssh-\S+\s+\S+$/)) {
        return "key " + attrs.key + " is not a valid ssh key";
      }
    }
  }
});

var MTrackUserKeysCollection = Backbone.Collection.extend({
  model: MTrackUserKey,
  user: null,
  url: function() {
    return ABSWEB + 'api.php/user/' + this.user.id + '/keys';
  }
});

var MTrackUser = Backbone.Model.extend({
  urlRoot: ABSWEB + "api.php/user",
  defaults: {
    "active": true,
    "role": "authenticated"
  },
  initialize: function (attributes, options) {
    this.keys = new MTrackUserKeysCollection(options ? options.keys : []);
    this.keys.user = this;
  }
});

var MTrackWatchItem = Backbone.Model.extend({
});

var MTrackWatchList = Backbone.Collection.extend({
  model: MTrackWatchItem,
  url: ABSWEB + 'api.php/watch/list'
});

var MTrackWikiAttachmentCollection = Backbone.Collection.extend({
  model: MTrackAttachment,
  WIKI: null,
  url: function() {
    return ABSWEB + "api.php/wiki/attach/" + this.WIKI.id;
  }
});

var MTrackWiki = Backbone.Model.extend({
  wikiAttachments: null,
  getAttachments: function() {
    if (this.wikiAttachments == null) {
      this.wikiAttachments = new MTrackWikiAttachmentCollection;
      this.wikiAttachments.WIKI = this;
    }
    return this.wikiAttachments;
  },
  isNew: function() {
    var c = this.previous('content');
    if (c && c.length) {
      return false;
    }
    return true;
  },
  urlRoot: ABSWEB + "api.php/wiki/page",
  url: function() {
    return ABSWEB + "api.php/wiki/page/" + this.id;
  }
});

