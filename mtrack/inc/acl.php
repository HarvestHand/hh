<?php # vim:ts=2:sw=2:et:
/* For licensing and copyright terms, see the file named LICENSE */

class MTrackAuthorizationException extends Exception {
  public $rights;
  function __construct($msg, $rights) {
    parent::__construct($msg);
    $this->rights = $rights;
  }
}

/* Each object in the system has an identifier, like 'ticket:XYZ' that
 * indicates the type of object as well as its own identifier.
 *
 * Each object may also have a discressionary access control list (DACL) that
 * describes what actions members of particular roles are permitted to
 * the object.  The DACL can apply either to the object itself, or be
 * a cascading (or inherited) entry that applies only to objects that are
 * children of the object in question.
 *
 * When determining whether access is permitted, the DACL is walked from
 * the object being accessed up to the root.  As soon as the allow/deny
 * status us known for a specific (role, action) combination, the search
 * stops.
 *
 * DACL entries can be explicitly ordered so that a particular user from
 * a group can be excepted from a blanket allow/deny rule that follows.
 */

class MTrackACL {
  static $cache = array();

  static public function addRootObjectAndRoles($name) {
    /* construct some roles that encapsulate read, modify, write */
    $rolebase = preg_replace('/s$/', '', $name);

    $ents = array(
        array("{$rolebase}Viewer", "read", true),
        array("{$rolebase}Editor", "read", true),
        array("{$rolebase}Editor", "modify", true),
        array("{$rolebase}Creator", "read", true),
        array("{$rolebase}Creator", "modify", true),
        array("{$rolebase}Creator", "create", true),
        array("{$rolebase}Creator", "delete", true),
        );
    MTrackACL::setACL($name, true, $ents);
    $ents = array(
        array("{$rolebase}Viewer", "read", true),
        array("{$rolebase}Editor", "read", true),
        array("{$rolebase}Creator", "read", true),
        array("{$rolebase}Creator", "modify", true),
        array("{$rolebase}Creator", "create", true),
        array("{$rolebase}Creator", "delete", true),
        );
    MTrackACL::setACL($name, false, $ents);
  }

  /* functions that we can call to determine ancestry */
  static $genealogist = array();
  static public function registerAncestry($objtype, $func) {
    self::$genealogist[$objtype] = $func;
  }

  /* returns the objectid path that leads from the root to the specified
   * object, including the object itself as the last element */
  static public function getParentPath($objectid, $steps = -1)
  {
    $path = array();
    while (strlen($objectid)) {
      if ($steps != -1 && $steps-- == 0) {
        break;
      }
      $path[] = $objectid;
      if (isset(self::$genealogist[$objectid])) {
        $func = self::$genealogist[$objectid];
        if (is_string($func)) {
          $parent = $func;
        } else {
          $parent = call_user_func($func, $objectid);
        }
        if (!$parent) break;
        $objectid = $parent;
        continue;
      }
      if (preg_match("/^(.*):([^:]+)$/", $objectid, $M)) {
        $class = $M[1];
        if (isset(self::$genealogist[$class])) {
          $func = self::$genealogist[$class];
          if (is_string($func)) {
            $parent = $func;
          } else {
            $parent = call_user_func($func, $objectid);
          }
          if (!$parent) break;
          $objectid = $parent;
          continue;
        }
        $objectid = $class;
        continue;
      }
      break;
    }
    return $path;
  }

  /* computes the overall ACL as it applies to someone that belongs to the
   * indicated set of roles. */
  static public function computeACL($objectid, $role_list)
  {
    $key = $objectid . '~' . join('~', $role_list);

    if (isset(self::$cache[$key])) {
      return self::$cache[$key];
    }

    /* we calculate the path to the object from its parent, and pull
     * out all ACL entries on those objects that match the provided
     * role list, ordering from the object up to the root.
     */

    $rlist = array();
    $db = MTrackDB::get();
    foreach ($role_list as $r => $rname) {
      $rlist[] = $db->quote($r);
    }
    // Always want the special wildcard 'everybody' entry
    $rlist[] = $db->quote('*');
    $role_list = join(',', $rlist);

    $actions = array();

    $oidlist = array();
    $path = self::getParentPath($objectid);
    foreach ($path as $oid) {
      $oidlist[] = $db->quote($oid);
    }
    $oidlist = join(',', $oidlist);

    $sql = <<<SQL
select objectid as id, action, cascade, allow
from
  acl
where
  role in ($role_list)
  and objectid in ($oidlist)
order by
  cascade desc,
  seq asc
SQL
    ;

#    echo $sql;

    # Collect the results and index by objectid; we'll need to walk over
    # them in path order
    $res_by_oid = array();

    foreach (MTrackDB::q($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $res_by_oid[$row['id']][] = $row;
    }
    foreach ($path as $oid) {
      if (!isset($res_by_oid[$oid])) continue;
      foreach ($res_by_oid[$oid] as $row) {

        if ($row['id'] == $objectid && $row['cascade']) {
          /* ignore items below the object of interest */
          continue;
        }

        if (!isset($actions[$row['action']])) {
          $actions[$row['action']] = $row['allow'] ? true : false;
        }
      }
    }

    self::$cache[$key] = $actions;

    return $actions;
  }

  /* Entries is an array of [role, action, allow] tuples in the order
   * that they should be checked.
   * If cascade is true, then these entries will replace the
   * inheritable set, otherwise they will replace the entries
   * on the object.
   * If entries is an empty array, or not an array, then the appropriate
   * ACL will be removed.
   */
  static public function setACL($object, $cascade, $entries)
  {
    self::$cache = array();

    $cascade = (int)$cascade;
    MTrackDB::q('delete from acl where objectid = ? and cascade = ?',
      $object, $cascade);
    $seq = 0;
    if (is_array($entries)) {
      foreach ($entries as $ent) {
        if (isset($ent['role'])) {
          $role = $ent['role'];
          $action = $ent['action'];
          $allow = $ent['allow'];
        } else {
          list($role, $action, $allow) = $ent;
        }
        MTrackDB::q('insert into acl (objectid, cascade, seq, role,
              action, allow) values (?, ?, ?, ?, ?, ?)',
            $object, $cascade, $seq++,
            $role, $action, (int)$allow);
      }
    }
  }

  /* Obtains the ACL entries for the specified object.
   * If cascade is true, it will return the inheritable ACL.
   */
  static public function getACL($object, $cascade)
  {
    return MTrackDB::q('select role, action, allow from acl
      where objectid = ? and cascade = ? order by seq',
      $object, (int)$cascade)->fetchAll(PDO::FETCH_ASSOC);
  }

  static public function hasAllRights($object, $rights)
  {
    if (MTrackAuth::getUserClass() == 'admin') {
      return true;
    }
    if (!is_array($rights)) {
      $rights = array($rights);
    }
    if (!count($rights)) {
      throw new Exception("can't have all of no rights");
    }
    $acl = self::computeACL($object, MTrackAuth::getGroups());
#    echo "ACL: $object<br>";
#    var_dump($rights);
#    echo "<br>";
#    var_dump($acl);
#    echo "<br>";

    foreach ($rights as $action) {
      if (!isset($acl[$action]) || !$acl[$action]) {
        return false;
      }
    }
    return true;
  }

  static public function hasAnyRights($object, $rights)
  {
    if (MTrackAuth::getUserClass() == 'admin') {
      return true;
    }
    if (!is_array($rights)) {
      $rights = array($rights);
    }
    if (!count($rights)) {
      throw new Exception("can't have any of no rights");
    }
    $acl = self::computeACL($object, MTrackAuth::getGroups());

    $ok = false;
    foreach ($rights as $action) {
      if (isset($acl[$action]) && $acl[$action]) {
        $ok = true;
      }
    }
    return $ok;
  }

  static public function requireAnyRights($object, $rights)
  {
    if (!self::hasAnyRights($object, $rights)) {
      throw new MTrackAuthorizationException("Not authorized", $rights);
    }
  }

  static public function requireAllRights($object, $rights)
  {
    if (!self::hasAllRights($object, $rights)) {
      throw new MTrackAuthorizationException("Not authorized", $rights);
    }
  }

  /* computes the ACL object suitable for including in the JSON
   * object returned (and settable) via the REST API.
   * This is used by the various views to create ACL editors.
   */
  static public function computeACLObject($objectid) {
    $o = new stdclass;
    $o->acl = array();
    $o->inherited = array();

    foreach (self::getACL($objectid, 0) as $ent) {
      $o->acl[] = array($ent['role'], $ent['action'], (int)$ent['allow']);
    }

    $path = self::getParentPath($objectid, 2);
    if (count($path) == 2) {
      foreach (self::getACL($path[1], 1) as $ent) {
        $o->inherited[] = array($ent['role'], $ent['action'], (int)$ent['allow']);
      }
    }

    return $o;
  }

  static function rest_roles($method, $uri, $captures) {
    MTrackAPI::checkAllowed($method, 'GET');

    /* avoid leaking information to anonymous users */
    $me = MTrackAuth::whoami();
    if ($me == 'anonymous' || MTrackAuth::getUserClass() == 'anonymous') {
      $o = new stdclass;
      return $o;
    }

    $groups = MTrackAuth::enumGroups();
    /* merge in users */
    foreach (MTrackDB::q(
        'select userid, fullname from userinfo where active = 1')
        ->fetchAll() as $row) {
      if (isset($groups[$row[0]])) continue;
      if (strlen($row[1])) {
        $disp = "$row[0] - $row[1]";
      } else {
        $disp = $row[0];
      }
      $groups[$row[0]] = $disp;
    }
    if (!isset($groups['*'])) {
      $groups['*'] = '(Everybody)';
    }
    return $groups;
  }

  /* helper for generating an ACL editor.
   * As parameters, takes an objectid indicating the object being edited,
   * and an action map which breaks down tasks into groups.
   * Each group consists of a set of permissions, starting with the least
   * permissive in that group, through to most permissive.
   * Each group will be rendered as a select box, and a synthetic "none"
   * entry will be generated for the group that explicitly excludes each
   * of the other permission levels in that group.
   *
   * The form element that is generated will contain a JSON representation
   * of an "ents" array that can be passed to setACL().
   */
  static public function renderACLForm($formprefix, $objectid, $map) {
    $ident = preg_replace("/[^a-zA-Z]/", '', $formprefix);
    $entities = array();
    $groups = MTrackAuth::enumGroups();
    /* merge in users */
    foreach (MTrackDB::q('select userid, fullname from userinfo where active = 1')
        ->fetchAll() as $row) {
      if (isset($groups[$row[0]])) continue;
      if (strlen($row[1])) {
        $disp = "$row[0] - $row[1]";
      } else {
        $disp = $row[0];
      }
      $groups[$row[0]] = $disp;
    }
    if (!isset($groups['*'])) {
      $groups['*'] = '(Everybody)';
    }

    // Encode the map into an object
    $mobj = new stdClass;

    $reng = array();
    $rank = array();

    foreach ($map as $group => $actions) {
      // Each subsequent action in a group implies access greater than
      // the item that preceeds it

      $all_perms = array_keys($actions);
      $prohibit = array();
      foreach ($all_perms as $p) {
        $prohibit[$p] = "-$p";
      }
      $none = join('|', $prohibit);
      $a = array();
      $a[] = array($none, 'None');
      $accum = array();
      $i = 0;
      foreach ($actions as $perm => $label) {
        $accum[] = $perm;
        unset($prohibit[$perm]);
        $p = join('|', array_merge($accum, $prohibit));
        $a[] = array($p, $label);
        /* save this for reverse engineering the right group in the current
         * ACL data */
        $reng[$perm] = $group;
        $rank[$group][$perm] = $i++;
      }
      $mobj->{$group} = $a;
    }
    $mobj = json_encode($mobj);

    $roledefs = new stdclass;
    $acl = self::getACL($objectid, 0);
    foreach ($acl as $ent) {
      $group = $reng[$ent['action']];
      $act = ($ent['allow'] ? '' : '-') . $ent['action'];
      $roledefs->{$ent['role']}->{$group}[] = $act;

      if (!isset($groups[$ent['role']])) {
        $groups[$ent['role']] = $ent['role'];
      }
    }
    $roledefs = json_encode($roledefs);

    /* let's figure out the inherited ACL */
    $path = self::getParentPath($objectid, 2);
    $inherited = new stdclass;
    if (count($path) == 2) {
      $pacl = self::getACL($path[1], 1);
      foreach ($pacl as $ent) {
        // Not relevant per the specified action map
        if (!isset($reng[$ent['action']])) continue;

        $group = $reng[$ent['action']];
        $act = ($ent['allow'] ? '' : '-') . $ent['action'];
        $inherited->{$ent['role']}->{$group}[] = $act;

        if (!isset($groups[$ent['role']])) {
          $groups[$ent['role']] = $ent['role'];
        }
      }

      // Inheritable set may not be specified directly in
      // the same terms as the action_map, so we need to infer it
      // Example: we may have read|modify leaving delete unspecified.
      // We treat this as read|modify|-delete
      foreach ($inherited as $role => $agroups) {
        foreach ($agroups as $group => $actions) {
          $highest = null;
          foreach ($actions as $act) {
            if ($act[0] == '-') continue;
            if ($highest === null || $rank[$group][$act] > $highest) {
              $highest = $rank[$group][$act];
              $hact = $act;
            }
          }
          if ($highest === null) {
            unset($inherited->{$role}->{$group});
            continue;
          }
          // Compute full value
          $comp = array();
          foreach ($rank[$group] as $act => $i) {
            if ($i <= $highest) {
              $comp[] = $act;
            } else {
              $comp[] = "-$act";
            }
          }
          $inherited->{$role}->{$group} = join('|', $comp);
        }
      }
    }
    $inherited = json_encode($inherited);

    //var_dump($acl);

    $groups = json_encode($groups);
    $cat_order = json_encode(array_keys($map));

    echo <<<HTML
<div class='permissioneditor'>
<p>
  <b>Permissions</b>
</p>
<p>
  <em>Select "Add" to define permissions for an entity.
    The first matching permission is taken as definitive,
    so if a given user belongs to multiple groups and matches
    multiple permission rows, the first is taken.  You may
    drag to re-order permissions.
  </em>
</p>
<p>
  <em>Permissions inherited from the parent of this object are
  shown as non-editable entries at the top of the list. You may
  override them by adding your own explicit entry.</em>
</p>
<br>
<input type='hidden' id='$formprefix' name='$formprefix'>
<table id='acl$ident'>
  <thead>
    <tr>
      <th>Entity</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>
<script>
$(document).ready(function () {
  var cat_order = $cat_order;
  var groups = $groups;
  var roledefs = $roledefs;
  var inherited = $inherited;
  var mobj = $mobj;
  var disp = $('#acl$ident');
  var tbody = $('tbody', disp);
  var sel;
  var field = $('#$formprefix');

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
    sp.append(
      $('<td/>')
        .html('<span style="position: absolute; margin-left: -1.3em" class="ui-icon ui-icon-arrowthick-2-n-s"></span>')
        .append(groups[role])
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
    var b = $('<button>x</button>');
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

  var tr = $('thead tr', disp);
  // Add columns for action groups
  for (var gi in cat_order) {
    var group = cat_order[gi];
    tr.append($('<th/>').text(group));
  }
  // Add fixed inherited rows
  var thead = $('thead', disp);
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

  for (var i in groups) {
    var g = groups[i];
    sel.append(
      $('<option/>')
        .attr('value', i)
        .text(g)
    );
  }
  disp.append(sel);
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

  field.parents('form:first').submit(function () {
    var acl = [];
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
    field.val(JSON.stringify(acl));
  });
});
</script>
</div>
HTML;

  }
}

MTrackAPI::register('/acl/roles', 'MTrackACL::rest_roles');

