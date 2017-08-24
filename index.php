<?php

/*
 *  BBBmw - BigBlueButton Middleware
 *  Copyright (C) 2012 François Kooman <fkooman@tuxed.net>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once ('vendor/autoload.php');
require_once ('config.php');
require_once ('utils.php');

try {
	if (!isset ($config) || !is_array($config)){
		throw new Exception("broken or missing configuration file?");
	}
	date_default_timezone_set(getConfig($config, 'time_zone', FALSE, 'Europe/Amsterdam'));

	if (getConfig($config, 'ssl_only', FALSE, FALSE)) {
		// only allow SSL connections
		if (!isset ($_SERVER['HTTPS']) || empty ($_SERVER['HTTPS'])) {
			throw new Exception("only available through secure connection");
		} else {
			/* support HTTP Strict Transport Security */
			if(getConfig($config, 'ssl_hsts', FALSE, FALSE)) {
				header('Strict-Transport-Security: max-age=3600');
			}
		}
	}

	$smarty = new Smarty();
	$smarty->template_dir = 'tpl';
	$smarty->compile_dir = 'tpl_c';

	/*
	 * Format can be any of these:
	 * w = web (default)
	 * g = gadget (for OpenSocial gadget)
	 * x = gadget XML (for loading in OpenSocial container)
	 */
	$fmt = getRequest('fmt', FALSE, 'w');
	if(!in_array($fmt, array('w','g','x'))) {
		throw new Exception("requesting invalid format");
	}


	if($fmt == 'x') {
		/* XML gadget template */
		$script_url = getProtocol() . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'];
		$thumbnail_url = getProtocol() . $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . DIRECTORY_SEPARATOR . "i" . DIRECTORY_SEPARATOR . "bigbluebutton.png";
		$smarty->assign('script_url', $script_url);
		$smarty->assign('thumbnail_url', $thumbnail_url);
		/* Disable Caching */
		header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
		header("Content-Type: text/xml");
		$smarty->display('x.tpl');
		exit(0);
	}

	$authType = getConfig($config, 'auth_type', TRUE);
	$groupType = getConfig($config, 'group_type', TRUE);
	$api_url = getConfig($config, 'bbb_api_url', TRUE);
	$salt = getConfig($config, 'bbb_salt', TRUE);

	$logout_url = getProtocol() . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

	require_once ("lib/Auth/Auth.class.php");
	require_once ("lib/$authType/$authType.class.php");
	require_once ("lib/Groups/Groups.class.php");
	require_once ("lib/$groupType/$groupType.class.php");
	require_once ("lib/CRUDStorage/CRUDStorage.class.php");
	require_once ("lib/FileCRUDStorage/FileCRUDStorage.class.php");

	if (!isset ($auth) || empty ($auth)) {
		$auth = new $authType ($config);
	}

	if (!$auth->isLoggedIn()) {
		$auth->login();
	}

	$groups = new $groupType ($config, $auth);
	$storage = new FileCRUDStorage( getcwd() . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . "data.php");
	$action = getRequest('action', FALSE, FALSE);
	$groupContext = getRequest('groupContext', FALSE, FALSE);

	$notification = NULL;
	switch($action) {
		case "create":
			$name = getRequest('name', TRUE);
			$welcome = getRequest('welcome', TRUE);
			$grps = getRequest('groups', FALSE, array());

			/* store conference in local database */
			$confdata = array('moderator' => $auth->getUserId(), 'moderatorDN' => $auth->getUserDisplayName(), 'name' => $name, 'groups' => $grps );
			$confId = $storage->createEntry('conference', $confdata);

			/* create conference in BigBlueButton */
			$uname = urlencode($name);
			$uwelcome = urlencode($welcome);
			$ulogout_url = urlencode($logout_url);
			/* voice bridge should have a number between 70000 and 79999 according to docs */
			$vb = mt_rand(70000,79999);
			$call = "name=$uname&meetingID=$confId&welcome=$uwelcome&logoutURL=$ulogout_url&voiceBridge=$vb&record=true";
			$checksum = sha1("create".$call.$salt);
			$result = getContents("$api_url/create?$call&checksum=$checksum");
			$xml = new SimpleXMLElement($result);
			$confdata['attendeePW'] = (string)$xml->attendeePW;
			$confdata['moderatorPW'] = (string)$xml->moderatorPW;
			$storage->updateEntry('conference', $confId, $confdata);
			$notification = 'conference created';
			logHandler($notification);
			break;

		case "end":
			$id = getRequest('id', TRUE);
			$entry = $storage->readEntry('conference', $id);
			$moderator = $entry['moderator'] === $auth->getUserId();
			if($moderator) {
				$pass = $entry['moderatorPW'];
				$call = "meetingID=$id&password=$pass";
				$checksum = sha1("end".$call.$salt);
				$result = getContents("$api_url/end?$call&checksum=$checksum");
				$storage->deleteEntry('conference', $id);
				$notification = 'conference ended';
				logHandler($notification);
			}else {
				throw new Exception('not a moderator');
			}
			break;

		case "join":
			$id = getRequest('id', TRUE);
			$entry = $storage->readEntry('conference', $id);
			$moderator = $entry['moderator'] === $auth->getUserId();
			if($moderator) {
				$pass = $entry['moderatorPW'];
			}else {
				$pass = $entry['attendeePW'];
			}
			$uname = urlencode($auth->getUserDisplayName());
			$call = "fullName=$uname&meetingID=$id&password=$pass";
			$checksum = sha1("join".$call.$salt);
			header("Location: $api_url/join?$call&checksum=$checksum");
			break;

		default:
			// fall through, no action specified so just show conferences
	}

	$conferences = $storage->listEntries('conference');

	/* filter out only my group's conferences, conferences currently
	 * active and limited to the group context if that was specified
	 */
	$grps = $groups->getUserGroups();
	foreach($conferences as $cid => $cinfo) {
		$intersect = array_intersect(array_keys($grps), array_values($cinfo['groups']));
		/* if logged in user's groups are not part of the conference, unset it */
		if(empty($intersect)) {
			unset($conferences[$cid]);
		}else {
			/* if groupContext is specified and group is not specified with conference, unset it */
			if($groupContext !== FALSE && !in_array($groupContext, $intersect)) {
				unset($conferences[$cid]);
			} else {
				/* for the remaining ones, check that it is actually still active in server */
				/* FIXME: actually also remove them from the data store if they expired */
				$pass = $cinfo['moderatorPW'];
				$call = "meetingID=$cid&password=$pass";
				$checksum = sha1("getMeetingInfo".$call.$salt);
				$result = getContents("$api_url/getMeetingInfo?$call&checksum=$checksum");
				$xml = new SimpleXMLElement($result);
				$mk = (string)$xml->messageKey;
				if($mk === "notFound") {
					unset($conferences[$cid]);
				} else {
					$conferences[$cid]['isRunning'] = (string)$xml->running;
					$conferences[$cid]['participantCount'] = (string)$xml->participantCount;
				}
			}
		}
	}

    $restricted = FALSE;
    $restrictToOrganizations = getConfig($config, 'restrict_create_org', FALSE, array());
    if(is_array($restrictToOrganizations) && !empty($restrictToOrganizations)) {
        // non empty array, so possibly restricted!
        $schacHomeOrganization = $auth->getUserOrganization();
        if(!in_array($schacHomeOrganization, $restrictToOrganizations)) {
            $restricted = TRUE;
        }
    }

    $smarty->assign('restricted', $restricted);

    /* Web and Gadget template */
	$smarty->assign('userId', $auth->getUserId());
	$smarty->assign('userDisplayName', $auth->getUserDisplayName());
	$smarty->assign('conferences', $conferences);
	$smarty->assign('userGroups', $grps);
	$smarty->assign('notification', $notification);
	$smarty->assign('groupContext', $groupContext);

	/* allow iframe to set cookies on IE */
	header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
	$smarty->display($fmt.'.tpl');
} catch(Exception $e) {
	die("Error: " . $e->getMessage());
}
?>
