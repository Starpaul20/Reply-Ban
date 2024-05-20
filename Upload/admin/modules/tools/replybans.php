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

$page->add_breadcrumb_item($lang->reply_bans, "index.php?module=tools-replybans");

$sub_tabs['reply_bans'] = array(
	'title' => $lang->reply_bans,
	'link' => "index.php?module=tools-replybans",
	'description' => $lang->reply_bans_desc
);

if($mybb->input['action'] == "lift")
{
	$query = $db->query("
		SELECT r.*, u.username, t.subject
		FROM ".TABLE_PREFIX."replybans r
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=r.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=r.uid)
		WHERE r.rid='".$mybb->get_input('rid', MyBB::INPUT_INT)."'
	");
	$replyban = $db->fetch_array($query);

	// Does the reply ban not exist?
	if(!$replyban['rid'])
	{
		flash_message($lang->error_invalid_reply_ban, 'error');
		admin_redirect("index.php?module=tools-replybans");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=tools-replybans");
	}

	if($mybb->request_method == "post")
	{
		// Lift the reply ban
		$db->delete_query("replybans", "rid='{$replyban['rid']}'");

		// Log admin action
		log_admin_action($replyban['uid'], htmlspecialchars_uni($replyban['username']), $replyban['tid'], htmlspecialchars_uni($replyban['subject']));

		flash_message($lang->success_reply_ban_lifted, 'success');
		admin_redirect("index.php?module=tools-replybans");
	}
	else
	{
		$page->output_confirm_action("index.php?module=tools-replybans&amp;action=delete&amp;rid={$replyban['rid']}", $lang->confirm_reply_ban_lift);
	}
}

if(!$mybb->input['action'])
{
	$page->output_header($lang->reply_bans);

	$page->output_nav_tabs($sub_tabs, 'reply_bans');

	$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if(!$perpage)
	{
		if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
		{
			$mybb->settings['threadsperpage'] = 20;
		}
		
		$perpage = $mybb->settings['threadsperpage'];
	}

	$where = 'WHERE 1=1';

	// Searching for entries by a particular user
	if($mybb->get_input('uid') > 0)
	{
		$where .= " AND r.uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";
	}

	// Searching for entries in a specific thread
	if($mybb->get_input('tid') > 0)
	{
		$where .= " AND r.tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'";
	}

	// Order?
	switch($mybb->get_input('sortby'))
	{
		case "username":
			$sortby = "u.username";
			break;
		case "thread":
			$sortby = "t.subject";
			break;
		case "lifted":
			$sortby = "r.lifted";
			break;
		default:
			$sortby = "r.dateline";
	}
	$order = $mybb->get_input('order');
	if($order != "asc")
	{
		$order = "desc";
	}

	$query = $db->query("
		SELECT COUNT(r.dateline) AS count
		FROM ".TABLE_PREFIX."replybans r
		{$where}
	");
	$rescount = $db->fetch_field($query, "count");

	// Figure out if we need to display multiple pages.
	if($mybb->get_input('page') != "last")
	{
		$pagecnt = $mybb->get_input('page', MyBB::INPUT_INT);
	}

	$postcount = (int)$rescount;
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->get_input('page') == "last")
	{
		$pagecnt = $pages;
	}

	if($pagecnt > $pages)
	{
		$pagecnt = 1;
	}

	if($pagecnt)
	{
		$start = ($pagecnt-1) * $perpage;
	}
	else
	{
		$start = 0;
		$pagecnt = 1;
	}

	$table = new Table;
	$table->construct_header($lang->username, array('width' => '15%'));
	$table->construct_header($lang->thread, array("class" => "align_center", 'width' => '25%'));
	$table->construct_header($lang->date_banned_on, array("class" => "align_center", 'width' => '15%'));
	$table->construct_header($lang->lifted_on, array("class" => "align_center", 'width' => '15%'));
	$table->construct_header($lang->reason, array("class" => "align_center", 'width' => '20%'));
	$table->construct_header($lang->action, array("class" => "align_center", 'width' => '100'));

	$query = $db->query("
		SELECT r.*, u.username, u.usergroup, u.displaygroup, t.subject
		FROM ".TABLE_PREFIX."replybans r
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=r.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=r.tid)
		{$where}
		ORDER BY {$sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($replyban = $db->fetch_array($query))
	{
		$trow = alt_trow();
		$replyban['dateline'] = my_date('relative', $replyban['dateline']);

		if($replyban['lifted'] == 0)
		{
			$replyban['lifted'] = $lang->permanently;
		}
		else
		{
			$replyban['lifted'] = my_date('relative', $replyban['lifted']);
		}

		if($replyban['username'])
		{
			$username = format_name(htmlspecialchars_uni($replyban['username']), $replyban['usergroup'], $replyban['displaygroup']);
			$replyban['profilelink'] = build_profile_link($username, $replyban['uid'], "_blank");
		}
		else
		{
			$username = $replyban['profilelink'] = $replyban['username'] = htmlspecialchars_uni($lang->na_deleted);
		}

		$replyban['subject'] = htmlspecialchars_uni($replyban['subject']);
		$replyban['reason'] = htmlspecialchars_uni($replyban['reason']);

		$table->construct_cell($replyban['profilelink']);
		$table->construct_cell("<a href=\"../".get_thread_link($replyban['tid'])."\" target=\"_blank\">{$replyban['subject']}</a>", array("class" => "align_center"));
		$table->construct_cell($replyban['dateline'], array("class" => "align_center"));
		$table->construct_cell($replyban['lifted'], array("class" => "align_center"));
		$table->construct_cell($replyban['reason'], array("class" => "align_center"));
		$table->construct_cell("<a href=\"index.php?module=tools-replybans&amp;action=lift&amp;rid={$replyban['rid']}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_reply_ban_lift}')\">{$lang->lift_ban}</a>", array("class" => "align_center", "width" => '90'));
		$table->construct_row();
	}

	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_replybans, array("colspan" => "6"));
		$table->construct_row();
	}

	$table->output($lang->reply_bans);

	// Do we need to construct the pagination?
	if($rescount > $perpage)
	{
		echo draw_admin_pagination($pagecnt, $perpage, $rescount, "index.php?module=tools-replybans&amp;perpage=$perpage&amp;uid={$mybb->input['uid']}&amp;tid={$mybb->input['tid']}&amp;sortby={$mybb->input['sortby']}&amp;order={$order}")."<br />";
	}

	// Fetch filter options
	$sortbysel[$mybb->get_input('sortby')] = "selected=\"selected\"";
	$ordersel[$mybb->get_input('order')] = "selected=\"selected\"";

	$user_options[''] = $lang->all_users;
	$user_options['0'] = '----------';

	$query = $db->query("
		SELECT DISTINCT r.uid, u.username
		FROM ".TABLE_PREFIX."replybans r
		LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid)
		ORDER BY u.username ASC
	");
	while($user = $db->fetch_array($query))
	{
		// Deleted Users
		if(!$user['username'])
		{
			$user['username'] = htmlspecialchars_uni($lang->na_deleted);
		}

		$selected = '';
		if($mybb->get_input('uid') == $user['uid'])
		{
			$selected = "selected=\"selected\"";
		}
		$user_options[$user['uid']] = htmlspecialchars_uni($user['username']);
	}

	$thread_options[''] = $lang->all_threads;
	$thread_options['0'] = '----------';
	
	$query2 = $db->query("
		SELECT DISTINCT r.tid, t.subject
		FROM ".TABLE_PREFIX."replybans r
		LEFT JOIN ".TABLE_PREFIX."threads t ON (r.tid=t.tid)
		ORDER BY t.subject ASC
	");
	while($thread = $db->fetch_array($query2))
	{
		// Deleted Threads
		if(!$thread['subject'])
		{
			$thread['subject'] = htmlspecialchars_uni($lang->na_deleted);
		}

		$thread_options[$thread['tid']] = $thread['subject'];
	}

	$sort_by = array(
		'dateline' => $lang->date_banned_on,
		'lifted' => $lang->lifted_on,
		'username' => $lang->username,
		'thread' => $lang->thread_subject
	);

	$order_array = array(
		'asc' => $lang->asc,
		'desc' => $lang->desc
	);

	$form = new Form("index.php?module=tools-replybans", "post");
	$form_container = new FormContainer($lang->filter_reply_bans);
	$form_container->output_row($lang->username.":", "", $form->generate_select_box('uid', $user_options, $mybb->get_input('uid'), array('id' => 'uid')), 'uid');
	$form_container->output_row($lang->thread.":", "", $form->generate_select_box('tid', $thread_options, $mybb->get_input('tid'), array('id' => 'tid')), 'tid');
	$form_container->output_row($lang->sort_by, "", $form->generate_select_box('sortby', $sort_by, $mybb->get_input('sortby'), array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('order', $order_array, $order, array('id' => 'order'))." {$lang->order}", 'order');
	$form_container->output_row($lang->results_per_page, "", $form->generate_numeric_field('perpage', $perpage, array('id' => 'perpage', 'min' => 1)), 'perpage');

	$form_container->end();
	$buttons[] = $form->generate_submit_button($lang->filter_reply_bans);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}
