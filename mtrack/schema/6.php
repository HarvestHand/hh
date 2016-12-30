<?php # vim:ts=2:sw=2:et:

# Add forking permissions
$ents = MTrackACL::getACL('Browser', false);
$ents[] = array('BrowserCreator', 'fork', true);
$ents[] = array('BrowserForker', 'fork', true);
$ents[] = array('BrowserForker', 'read', true);
MTrackACL::setACL('Browser', false, $ents);

