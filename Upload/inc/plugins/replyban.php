<?php
/**
 * Reply Ban
 * Copyright 2015 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(defined('THIS_SCRIPT'))
{
	if(THIS_SCRIPT == 'showthread.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'showthread_replybanlink,showthread_replybannotice';
	}

	if(THIS_SCRIPT == 'moderation.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'moderation_replyban,moderation_replyban_no_bans,moderation_replyban_liftlist,moderation_replyban_bit';
	}
}

// Tell MyBB when to run the hooks
$plugins->add_hook("moderation_start", "replyban_run");
$plugins->add_hook("showthread_start", "replyban_showthread");
$plugins->add_hook("postbit", "replyban_postbit");
$plugins->add_hook("showthread_end", "replyban_quickreply");
$plugins->add_hook("newreply_start", "replyban_reply");
$plugins->add_hook("newreply_do_newreply_start", "replyban_reply");
$plugins->add_hook("editpost_action_start", "replyban_reply");
$plugins->add_hook("editpost_do_editpost_start", "replyban_reply");
$plugins->add_hook("xmlhttp_edit_post_end", "replyban_xmlhttp");
$plugins->add_hook("class_moderation_delete_thread_start", "replyban_delete_thread");
$plugins->add_hook("class_moderation_merge_threads", "replyban_merge_thread");
$plugins->add_hook("task_usercleanup", "replyban_lift");
$plugins->add_hook("datahandler_user_delete_content", "replyban_delete");

$plugins->add_hook("admin_user_users_merge_commit", "replyban_merge");
$plugins->add_hook("admin_tools_menu_logs", "replyban_admin_menu");
$plugins->add_hook("admin_tools_action_handler", "replyban_admin_action_handler");
$plugins->add_hook("admin_tools_permissions", "replyban_admin_permissions");
$plugins->add_hook("admin_tools_get_admin_log_action", "replyban_admin_adminlog");

// The information that shows up on the plugin manager
function replyban_info()
{
	global $lang;
	$lang->load("replyban", true);

	return array(
		"name"				=> $lang->replyban_info_name,
		"description"		=> $lang->replyban_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.5",
		"codename"			=> "replyban",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is installed.
function replyban_install()
{
	global $db;
	replyban_uninstall();
	$collation = $db->build_create_table_collation();

	switch($db->type)
	{
		case "pgsql":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."replybans (
				rid serial,
				uid int NOT NULL default '0',
				tid int NOT NULL default '0',
				dateline numeric(30,0) NOT NULL default '0',
				lifted numeric(30,0) NOT NULL default '0',
				reason varchar(240) NOT NULL default '',
				PRIMARY KEY (rid)
			);");
			break;
		case "sqlite":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."replybans (
				rid INTEGER PRIMARY KEY,
				uid int NOT NULL default '0',
				tid int NOT NULL default '0',
				dateline int NOT NULL default '0',
				lifted int NOT NULL default '0',
				reason varchar(240) NOT NULL default ''
			);");
			break;
		default:
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."replybans (
				rid int unsigned NOT NULL auto_increment,
				uid int unsigned NOT NULL default '0',
				tid int unsigned NOT NULL default '0',
				dateline int unsigned NOT NULL default '0',
				lifted int unsigned NOT NULL default '0',
				reason varchar(240) NOT NULL default '',
				PRIMARY KEY (rid)
			) ENGINE=MyISAM{$collation};");
			break;
	}
}

// Checks to make sure plugin is installed
function replyban_is_installed()
{
	global $db;
	if($db->table_exists("replybans"))
	{
		return true;
	}
	return false;
}

// This function runs when the plugin is uninstalled.
function replyban_uninstall()
{
	global $db;

	if($db->table_exists("replybans"))
	{
		$db->drop_table("replybans");
	}
}

// This function runs when the plugin is activated.
function replyban_activate()
{
	global $db;

	// Insert templates
	$insert_array = array(
		'title'		=> 'moderation_replyban',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->reply_bans_for}</title>
{$headerinclude}
</head>
<body>
{$header}
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead" colspan="4"><strong>{$lang->reply_bans_for}</strong></td>
	</tr>
	<tr>
		<td class="tcat" width="25%"><span class="smalltext"><strong>{$lang->username}</strong></span></td>
		<td class="tcat" width="35%"><span class="smalltext"><strong>{$lang->reason}</strong></span></td>
		<td class="tcat" width="30%" align="center"><span class="smalltext"><strong>{$lang->expires_on}</strong></span></td>
		<td class="tcat" width="10%" align="center"><span class="smalltext"><strong>{$lang->options}</strong></span></td>
	</tr>
	{$ban_bit}
</table>
<br />
<form action="moderation.php" method="post">
	<input type="hidden" name="action" value="do_replyban" />
	<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
	<input type="hidden" name="tid" value="{$tid}" />
	<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
		<tr>
			<td class="thead" colspan="2"><strong>{$lang->ban_user_from_replying}</strong></td>
		</tr>
		<tr>
			<td class="trow1" width="25%"><strong>{$lang->username}:</strong></td>
			<td class="trow1" width="75%"><input type="text" class="textbox" name="username" id="username" size="25" /></td>
		</tr>
		<tr>
			<td class="trow2" width="25%"><strong>{$lang->ban_reason}:</strong></td>
			<td class="trow2" width="75%"><textarea name="reason" cols="60" rows="4" maxlength="200"></textarea></td>
		</tr>
		<tr>
			<td class="trow1" width="25%"><strong>{$lang->ban_lift_on}:</strong></td>
			<td class="trow1" width="75%"><select name="liftban">{$liftlist}</select></td>
		</tr>
	</table>
	<br />
	<div align="center">
		<input type="submit" class="button" name="submit" value="{$lang->ban_user}" />
	</div>
</form>
{$footer}
<link rel="stylesheet" href="{$mybb->asset_url}/jscripts/select2/select2.css">
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/select2/select2.min.js"></script>
<script type="text/javascript">
<!--
if(use_xmlhttprequest == "1")
{
	MyBB.select2();
	$("#username").select2({
		placeholder: "{$lang->search_user}",
		minimumInputLength: 2,
		multiple: false,
		ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
			url: "xmlhttp.php?action=get_users",
			dataType: \'json\',
			data: function (term, page) {
				return {
					query: term, // search term
				};
			},
			results: function (data, page) { // parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to alter remote JSON data
				return {results: data};
			}
		},
		initSelection: function(element, callback) {
			var value = $(element).val();
			if (value !== "") {
				callback({
					id: value,
					text: value
				});
			}
		},
	});
}
// -->
</script>
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'moderation_replyban_bit',
		'template'	=> $db->escape_string('<tr>
	<td class="{$alt_bg}">{$ban[\'username\']}</td>
	<td class="{$alt_bg}">{$ban[\'reason\']}</td>
	<td class="{$alt_bg}" align="center">{$ban[\'lifted\']}</td>
	<td class="{$alt_bg}" align="center"><a href="moderation.php?action=liftreplyban&amp;rid={$ban[\'rid\']}&amp;my_post_key={$mybb->post_code}"><strong>{$lang->lift_ban}</strong></a></td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'moderation_replyban_no_bans',
		'template'	=> $db->escape_string('<tr>
	<td class="trow1" colspan="4" align="center">{$lang->no_bans}</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'moderation_replyban_liftlist',
		'template'	=> $db->escape_string('<option value="{$time}"{$selected}>{$title}{$thattime}</option>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'showthread_replybanlink',
		'template'	=> $db->escape_string(' | <a href="moderation.php?action=replyban&amp;tid={$thread[\'tid\']}">{$lang->reply_bans}</a>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'showthread_replybannotice',
		'template'	=> $db->escape_string('<div class="red_alert"><strong>{$lang->error_banned_from_replying}</strong> {$lang->reason}: {$replybanreason}<br />{$lang->ban_will_be_lifted}: {$replybanlift}</div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("showthread", "#".preg_quote('{$threadnoteslink}')."#i", '{$threadnoteslink}{$replybanlink}');
	find_replace_templatesets("showthread", "#".preg_quote('{$header}')."#i", '{$header}{$replybannotice}');

	change_admin_permission('tools', 'replybans');
}

// This function runs when the plugin is deactivated.
function replyban_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('moderation_replyban','moderation_replyban_bit','moderation_replyban_no_bans','moderation_replyban_liftlist','showthread_replybanlink','showthread_replybannotice')");

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("showthread", "#".preg_quote('{$replybanlink}')."#i", '', 0);
	find_replace_templatesets("showthread", "#".preg_quote('{$replybannotice}')."#i", '', 0);

	change_admin_permission('tools', 'replybans', -1);
}

// Reply Ban moderation page
function replyban_run()
{
	global $db, $mybb, $lang, $templates, $theme, $headerinclude, $header, $footer, $parser;
	$lang->load("replyban");

	if($mybb->input['action'] != "replyban" && $mybb->input['action'] != "do_replyban" && $mybb->input['action'] != "liftreplyban")
	{
		return;
	}

	if($mybb->input['action'] == "replyban")
	{
		$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
		$thread = get_thread($tid);

		if(!is_moderator($thread['fid'], "canmanagethreads"))
		{
			error_no_permission();
		}

		if(!$thread['tid'])
		{
			error($lang->error_invalidthread);
		}

		$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
		$lang->reply_bans_for = $lang->sprintf($lang->reply_bans_for, $thread['subject']);

		check_forum_password($thread['fid']);

		build_forum_breadcrumb($thread['fid']);
		add_breadcrumb($thread['subject'], get_thread_link($thread['tid']));
		add_breadcrumb($lang->reply_bans);

		$ban_bit = '';
		$query = $db->query("
			SELECT r.*, u.username, u.usergroup, u.displaygroup
			FROM ".TABLE_PREFIX."replybans r
			LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid)
			WHERE r.tid='{$thread['tid']}'
			ORDER BY r.dateline DESC
		");
		while($ban = $db->fetch_array($query))
		{
			$ban['reason'] = htmlspecialchars_uni($ban['reason']);
			$ban['username'] = format_name(htmlspecialchars_uni($ban['username']), $ban['usergroup'], $ban['displaygroup']);
			$ban['username'] = build_profile_link($ban['username'], $ban['uid']);

			if($ban['lifted'] == 0)
			{
				$ban['lifted'] = $lang->permanent;
			}
			else
			{
				$ban['lifted'] = my_date('relative', $ban['lifted'], '', 2);
			}

			$alt_bg = alt_trow();
			eval("\$ban_bit .= \"".$templates->get("moderation_replyban_bit")."\";");
		}

		if(!$ban_bit)
		{
			eval("\$ban_bit = \"".$templates->get("moderation_replyban_no_bans")."\";");
		}

		// Generate the banned times dropdown
		$liftlist = '';
		$bantimes = fetch_ban_times();
		foreach($bantimes as $time => $title)
		{
			$selected = '';
			if(isset($banned['bantime']) && $banned['bantime'] == $time)
			{
				$selected = " selected=\"selected\"";
			}

			$thattime = '';
			if($time != '---')
			{
				$dateline = TIME_NOW;
				if(isset($banned['dateline']))
				{
					$dateline = $banned['dateline'];
				}

				$thatime = my_date("D, jS M Y @ g:ia", ban_date2timestamp($time, $dateline));
				$thattime = " ({$thatime})";
			}

			eval("\$liftlist .= \"".$templates->get("moderation_replyban_liftlist")."\";");
		}

		eval("\$replyban = \"".$templates->get("moderation_replyban")."\";");
		output_page($replyban);
	}

	if($mybb->input['action'] == "do_replyban" && $mybb->request_method == "post")
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
		$thread = get_thread($tid);

		if(!is_moderator($thread['fid'], "canmanagethreads"))
		{
			error_no_permission();
		}

		if(!$thread['tid'])
		{
			error($lang->error_invalidthread);
		}

		$user = get_user_by_username($mybb->input['username'], array('fields' => array('username')));

		if(empty($user['uid']))
		{
			error($lang->error_invaliduser);
		}

		$mybb->input['reason'] = $mybb->get_input('reason');
		if(!trim($mybb->input['reason']))
		{
			error($lang->error_missing_reason);
		}

		$query = $db->simple_select('replybans', 'rid', "uid='{$user['uid']}' AND tid='{$thread['tid']}'");
		$existingreplyban = $db->fetch_field($query, 'rid');

		if($existingreplyban > 0)
		{
			error($lang->error_alreadybanned);
		}

		if($mybb->get_input('liftban') == '---')
		{
			$lifted = 0;
		}
		else
		{
			$lifted = ban_date2timestamp($mybb->get_input('liftban'), 0);
		}

		$reason = my_substr($mybb->input['reason'], 0, 240);

		$insert_array = array(
			'uid' => $user['uid'],
			'tid' => $thread['tid'],
			'dateline' => TIME_NOW,
			'reason' => $db->escape_string($reason),
			'lifted' => $db->escape_string($lifted)
		);
		$db->insert_query('replybans', $insert_array);

		log_moderator_action(array("tid" => $thread['tid'], "fid" => $thread['fid'], "uid" => $user['uid'], "username" => $user['username']), $lang->user_reply_banned);

		moderation_redirect("moderation.php?action=replyban&tid={$thread['tid']}", $lang->redirect_user_banned_replying);
	}

	if($mybb->input['action'] == "liftreplyban")
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$rid = $mybb->get_input('rid', MyBB::INPUT_INT);
		$query = $db->simple_select("replybans", "*", "rid='{$rid}'");
		$ban = $db->fetch_array($query);

		if(!$ban['rid'])
		{
			error($lang->error_invalidreplyban);
		}

		$thread = get_thread($ban['tid']);
		$user = get_user($ban['uid']);

		if(!$thread['tid'])
		{
			error($lang->error_invalidthread);
		}

		if(!is_moderator($thread['fid'], "canmanagethreads"))
		{
			error_no_permission();
		}

		$db->delete_query("replybans", "rid='{$ban['rid']}'");

		log_moderator_action(array("tid" => $thread['tid'], "fid" => $thread['fid'], "uid" => $user['uid'], "username" => $user['username']), $lang->user_reply_banned_lifted);

		moderation_redirect("moderation.php?action=replyban&tid={$thread['tid']}", $lang->redirect_reply_ban_lifted);
	}
	exit;
}

// Link to reply bans on show thread/Query to see if user is reply banned (to remove postbit buttons)/Show ban notice in thread
function replyban_showthread()
{
	global $db, $mybb, $lang, $templates, $replybanlink, $fid, $thread, $existingreplyban, $replybannotice;
	$lang->load("replyban");

	$replybanlink = '';
	if(is_moderator($fid, "canmanagethreads"))
	{
		eval('$replybanlink = "'.$templates->get('showthread_replybanlink').'";');
	}

	$query = $db->simple_select('replybans', 'rid, reason, lifted', "uid='{$mybb->user['uid']}' AND tid='{$thread['tid']}'");
	$existingreplyban = $db->fetch_array($query);

	$replybannotice = '';
	if(!empty($existingreplyban) && $existingreplyban['rid'] > 0)
	{
		$replybanlift = $lang->banned_lifted_never;
		$replybanreason = htmlspecialchars_uni($existingreplyban['reason']);

		if($existingreplyban['lifted'] > 0)
		{
			$replybanlift = my_date('normal', $existingreplyban['lifted']);
		}

		if(empty($replybanreason))
		{
			$replybanreason = $lang->unknown;
		}

		if(empty($replybanlift))
		{
			$replybanlift = $lang->unknown;
		}

		eval('$replybannotice = "'.$templates->get('showthread_replybannotice').'";');
	}
}

// Remove postbit buttons if reply banned
function replyban_postbit($post)
{
	global $existingreplyban;

	if(!empty($existingreplyban) && $existingreplyban['rid'] > 0)
	{
		$post['button_edit'] = $post['button_quickdelete'] = $post['button_multiquote'] = $post['button_quote'] = '';
	}

	return $post;
}

// Remove quick reply box if reply banned
function replyban_quickreply()
{
	global $quickreply, $newreply, $existingreplyban;

	if(!empty($existingreplyban) && $existingreplyban['rid'] > 0)
	{
		$quickreply = $newreply = '';
	}
}

// Check to see if user is banned from replying
function replyban_reply()
{
	global $db, $mybb, $lang, $thread;
	$lang->load("replyban");

	$query = $db->simple_select('replybans', 'rid, reason', "uid='{$mybb->user['uid']}' AND tid='{$thread['tid']}'");
	$existingreplyban = $db->fetch_array($query);

	if($existingreplyban['rid'] > 0)
	{
		$existingreplyban['reason'] = htmlspecialchars_uni($existingreplyban['reason']);
		$lang->error_banned_from_replying_reason = $lang->sprintf($lang->error_banned_from_replying_reason, $existingreplyban['reason']);

		error($lang->error_banned_from_replying_reason);
	}
}

// Error if quick editing is used
function replyban_xmlhttp()
{
	global $db, $mybb, $lang, $post;
	$lang->load("replyban");

	$query = $db->simple_select('replybans', 'rid', "uid='{$mybb->user['uid']}' AND tid='{$post['tid']}'");
	$existingreplyban = $db->fetch_field($query, 'rid');

	if($existingreplyban['rid'] > 0)
	{
		xmlhttp_error($lang->error_banned_from_replying);
	}
}

// Delete reply bans if thread is deleted
function replyban_delete_thread($tid)
{
	global $db;

	$db->delete_query("replybans", "tid='{$tid}'");

	return $tid;
}

// Update tid if threads are merged
function replyban_merge_thread($arguments)
{
	global $db;
	$sqlarray = array(
		"tid" => "{$arguments['tid']}",
	);
	$db->update_query("replybans", $sqlarray, "tid='{$arguments['mergetid']}'");

	return $arguments;
}

// Lift old reply bans
function replyban_lift(&$task)
{
	global $db;

	$query = $db->simple_select("replybans", "rid", "lifted!=0 AND lifted<".TIME_NOW);
	while($replyban = $db->fetch_array($query))
	{
		$db->delete_query("replybans", "rid='{$replyban['rid']}'");
	}
}

// Delete reply bans if user is deleted
function replyban_delete($delete)
{
	global $db;

	$db->delete_query('replybans', 'uid IN('.$delete->delete_uids.')');

	return $delete;
}

// Update reply bans if users are merged
function replyban_merge()
{
	global $db, $source_user, $destination_user;

	$uid = array(
		"uid" => $destination_user['uid']
	);
	$db->update_query("replybans", $uid, "uid='{$source_user['uid']}'");
}

// Admin CP reply ban page
function replyban_admin_menu($sub_menu)
{
	global $lang;
	$lang->load("tools_replybans");

	$sub_menu['130'] = array('id' => 'replybans', 'title' => $lang->reply_bans, 'link' => 'index.php?module=tools-replybans');

	return $sub_menu;
}

function replyban_admin_action_handler($actions)
{
	$actions['replybans'] = array('active' => 'replybans', 'file' => 'replybans.php');

	return $actions;
}

function replyban_admin_permissions($admin_permissions)
{
	global $lang;
	$lang->load("tools_replybans");

	$admin_permissions['replybans'] = $lang->can_manage_reply_bans;

	return $admin_permissions;
}

// Admin Log display
function replyban_admin_adminlog($plugin_array)
{
	global $lang;
	$lang->load("tools_replybans");

	return $plugin_array;
}
