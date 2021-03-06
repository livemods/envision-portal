<?php
/**************************************************************************************
* Subs-EnvisionModules.php                                                            *
***************************************************************************************
* EnvisionPortal                                                                      *
* Community Portal Application for SMF                                                *
* =================================================================================== *
* Software by:                  EnvisionPortal (http://envisionportal.net/)           *
* Software for:                 Simple Machines Forum                                 *
* Copyright 2011 by:            EnvisionPortal (http://envisionportal.net/)           *
* Support, News, Updates at:    http://envisionportal.net/                            *
**************************************************************************************/

if (!defined('SMF'))
	die('Hacking attempt...');

/*	The following are Functions used for various default Envision Portal Modules.

	void ep_shoutbox()
		- !!!

	void LoadSmilies()
		- Load smileys into context. Checks if smileys are to come from the database,
		 also checks if user has selected a smiley set for use.

	void LoadShoutBBC()
		- Selectively load bbc buttons into context for the shoutbox to use.

	void ep_shoutbox_history()
		- Shows the history of the currently active shoutbox. Uses createList to format data.

	array list_getShouts(int start, int items_per_page, string sort, string where, array where_params, string module_id)
		- Used by createList() to return the shouts to display.
		- Also determines whether that shout is able to be moderated by the current user or not.
		- Last parameter is used for moderation of shouts.

	int list_getNumShouts(string where, array where_params)
		- Used by createList() to return the number of shouts to display

	string ep_getUrl()
		- Returns the current URL (http://localhost/ep/index.php?action=profile;u=85)

	void ep_boardNews()
		- !!!
	void ep_recentPosts()
		- !!!
	void ep_recentTopics()
		- !!!
	void ep_query_posts()
		- !!!
	void ep_topPoll()
		- !!!
	void ep_recentPoll()
		- !!!
	void ep_showPoll()
		- !!!
	void ep_boardPoll()
		- !!!
	void ep_whosOnline()
		- !!!
	void ep_changeTheme()
		- !!!
	void ep_calendarEvents($day = array(), $curMonth = integer)
		- Grabs the info. from within the $day array and outputs it at the bottom of the module.
		- returns a variable that is meant to be echo'd in order to show the holidays, events, and b-days properly.
*/

function ep_get_url()
{
	global $scripturl;

	$cur_url = $_SERVER['REQUEST_URL'];

	return $cur_url;
}

function ep_boardNews($board, $limit)
{
	global $scripturl, $smcFunc, $modSettings;

	$request = $smcFunc['db_query']('', '
		SELECT b.id_board
		FROM {db_prefix}boards AS b
		WHERE b.id_board = {int:current_board}
			AND {query_see_board}
		LIMIT 1',
		array(
			'current_board' => $board,
		)
	);

	if ($smcFunc['db_num_rows']($request) == 0)
		return array();

	list ($board) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT id_first_msg
		FROM {db_prefix}topics
		WHERE id_board = {int:current_board}' . ($modSettings['postmod_active'] ? '
			AND approved = {int:is_approved}' : '') . '
		ORDER BY id_first_msg DESC
		LIMIT ' . $limit,
		array(
			'current_board' => $board,
			'is_approved' => 1,
		)
	);

	$posts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$posts[] = $row['id_first_msg'];
	$smcFunc['db_free_result']($request);

	if (empty($posts))
		return array();

	$request = $smcFunc['db_query']('', '
		SELECT
			m.subject, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
			t.id_topic, m.id_member, mg.online_color
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
		WHERE t.id_first_msg IN ({array_int:post_list})
		ORDER BY t.id_first_msg DESC
		LIMIT ' . count($posts),
		array(
			'post_list' => $posts,
		)
	);

	$return = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$return[] = array(
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
			'poster' => !empty($row['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
			'color_poster' => !empty($row['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '"><span style="color: ' . $row['online_color'] . ';">' . $row['poster_name'] . '</span></a>' : $row['poster_name'],
			'is_last' => false,
		);
	}

	$smcFunc['db_free_result']($request);

	if (empty($return))
		return $return;

	$return[count($return) - 1]['is_last'] = true;

	return $return;
}

function ep_recent_posts($num_recent = 8, $exclude_boards = null, $include_boards = null)
{
	global $context, $settings, $scripturl, $txt, $db_prefix, $user_info;
	global $modSettings, $smcFunc;

	// Excluding certain boards...
	if ($exclude_boards === null && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0)
		$exclude_boards = array($modSettings['recycle_board']);
	else
		$exclude_boards = empty($exclude_boards) ? array() : (is_array($exclude_boards) ? $exclude_boards : array($exclude_boards));

	// What about including certain boards - note we do some protection here as pre-2.0 didn't have this parameter.
	if (is_array($include_boards) || (int) $include_boards === $include_boards)
		$include_boards = is_array($include_boards) ? $include_boards : array($include_boards);
	elseif ($include_boards != null)
		$include_boards = array();

	// Let's restrict the query boys (and girls)
	$query_where = '
		m.id_msg >= {int:min_message_id}
		' . (empty($exclude_boards) ? '' : '
		AND b.id_board NOT IN ({array_int:exclude_boards})') . '
		' . ($include_boards === null ? '' : '
		AND b.id_board IN ({array_int:include_boards})') . '
		AND {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
		AND m.approved = {int:is_approved}' : '');

	$query_where_params = array(
		'is_approved' => 1,
		'include_boards' => $include_boards === null ? '' : $include_boards,
		'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
		'min_message_id' => $modSettings['maxMsgID'] - 25 * min($num_recent, 5),
	);

	// Pass to this simpleton of a function...
	return ep_query_posts($query_where, $query_where_params, $num_recent, 'm.id_msg DESC', true);
}

function ep_recent_topics($num_recent = 8, $exclude_boards = null, $include_boards = null)
{
	global $context, $settings, $scripturl, $txt, $db_prefix, $user_info;
	global $modSettings, $smcFunc;

	if ($exclude_boards === null && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0)
		$exclude_boards = array($modSettings['recycle_board']);
	else
		$exclude_boards = empty($exclude_boards) ? array() : (is_array($exclude_boards) ? $exclude_boards : array($exclude_boards));

	// Only some boards?.
	if (is_array($include_boards) || (int) $include_boards === $include_boards)
		$include_boards = is_array($include_boards) ? $include_boards : array($include_boards);
	elseif ($include_boards != null)
		$include_boards = array();

	$stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'moved', 'recycled', 'wireless');
	$icon_sources = array();
	foreach ($stable_icons as $icon)
		$icon_sources[$icon] = 'images_url';

	// Find all the posts in distinct topics.  Newer ones will have higher IDs.
	$request = $smcFunc['db_query']('substring', '
		SELECT
			t.id_topic, b.id_board, b.name AS board_name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE t.id_last_msg >= {int:min_message_id}' . (empty($exclude_boards) ? '' : '
			AND b.id_board NOT IN ({array_int:exclude_boards})') . '' . (empty($include_boards) ? '' : '
			AND b.id_board IN ({array_int:include_boards})') . '
			AND {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}
			AND ml.approved = {int:is_approved}' : '') . '
		ORDER BY t.id_last_msg DESC
		LIMIT ' . $num_recent,
		array(
			'include_boards' => empty($include_boards) ? '' : $include_boards,
			'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
			'min_message_id' => $modSettings['maxMsgID'] - 35 * min($num_recent, 5),
			'is_approved' => 1,
		)
	);

	$topics = array();
	$boards = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$topics[] = $row['id_topic'];
		$boards[$row['id_topic']] = $row;
	}

	// Find all the posts in distinct topics.  Newer ones will have higher IDs.
	$request = $smcFunc['db_query']('substring', '
		SELECT
			mf.poster_time, mf.subject, ml.id_topic, mf.id_member, ml.id_msg, t.num_replies, t.num_views, mg.online_color,
			IFNULL(mem.real_name, mf.poster_name) AS poster_name, ' . ($user_info['is_guest'] ? '1 AS is_read, 0 AS new_from' : '
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) >= ml.id_msg_modified AS is_read,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from') . ', SUBSTRING(mf.body, 1, 384) AS body, mf.smileys_enabled, mf.icon
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mf.id_member)' . (!$user_info['is_guest'] ? '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' : '') . '
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
		WHERE t.id_topic IN ({array_int:topic_list})',
		array(
			'current_member' => $user_info['id'],
			'topic_list' => $topics,
		)
	);
	$posts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['body'] = strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']), array('<br />' => '&#10;')));
		if ($smcFunc['strlen']($row['body']) > 128)
			$row['body'] = $smcFunc['substr']($row['body'], 0, 128) . '...';

		// Censor the subject.
		censorText($row['subject']);
		censorText($row['body']);

		if (empty($modSettings['messageIconChecks_disable']) && !isset($icon_sources[$row['icon']]))
			$icon_sources[$row['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['icon'] . '.gif') ? 'images_url' : 'default_images_url';

		// Build the array.
		$posts[] = array(
			'board' => array(
				'id' => $boards[$row['id_topic']]['id_board'],
				'name' => $boards[$row['id_topic']]['board_name'],
				'href' => $scripturl . '?board=' . $boards[$row['id_topic']]['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $boards[$row['id_topic']]['id_board'] . '.0">' . $boards[$row['id_topic']]['board_name'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>',
				'color_link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . (!empty($row['online_color']) ? '<span style="color: ' . $row['online_color'] . ';">' . $row['poster_name'] . '</span>' : $row['poster_name']) . '</a>',
			),
			'subject' => $row['subject'],
			'replies' => $row['num_replies'],
			'views' => $row['num_views'],
			'short_subject' => shorten_subject($row['subject'], 25),
			'preview' => $row['body'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#new',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#new" rel="nofollow">' . $row['subject'] . '</a>',
			'is_new' => empty($row['is_read']),
			'new_from' => $row['new_from'],
			'icon' => '<img src="' . $settings[$icon_sources[$row['icon']]] . '/post/' . $row['icon'] . '.gif" align="middle" alt="' . $row['icon'] . '" border="0" />',
		);
	}

	return $posts;
}

function ep_query_posts($query_where, $query_where_params = array(), $query_limit = '', $query_order = 'm.id_msg DESC', $limit_body = false)
{
	global $context, $settings, $scripturl, $txt, $db_prefix, $user_info;
	global $modSettings, $smcFunc;

	// Find all the posts. Newer ones will have higher IDs.
	$request = $smcFunc['db_query']('substring', '
		SELECT
			m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg, m.id_board, b.name AS board_name, mg.online_color,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, ' . ($user_info['is_guest'] ? '1 AS is_read, 0 AS new_from' : '
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) >= m.id_msg_modified AS is_read,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from') . ', ' . ($limit_body ? 'SUBSTRING(m.body, 1, 384) AS body' : 'm.body') . ', m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (!$user_info['is_guest'] ? '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = m.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = m.id_board AND lmr.id_member = {int:current_member})' : '') . '
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
		WHERE ' . $query_where . '
		ORDER BY ' . $query_order . '
		' . ($query_limit == '' ? '' : 'LIMIT ' . $query_limit),
		array_merge($query_where_params, array(
			'current_member' => $user_info['id'],
		))
	);
	$posts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);
		$preview = strip_tags(strtr($row['body'], array('<br />' => '&#10;')));

		// Censor it!
		censorText($row['subject']);
		censorText($row['body']);

		// Build the array.
		$posts[] = array(
			'id' => $row['id_msg'],
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>',
				'color_link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . (!empty($row['online_color']) ? '<span style="color: ' . $row['online_color'] . ';">' . $row['poster_name'] . '</span>' : $row['poster_name']) . '</a>',
			),
			'subject' => $row['subject'],
			'short_subject' => shorten_subject($row['subject'], 25),
			'preview' => $smcFunc['strlen']($preview) > 128 ? $smcFunc['substr']($preview, 0, 128) . '...' : $preview,
			'body' => $row['body'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#new',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
			'is_new' => empty($row['is_read']),
			'new_from' => $row['new_from'],
		);
	}

	return $posts;
}

function ep_topPoll()
{
	// Just use recentPoll, no need to duplicate code...
	return ep_recentPoll(true);
}

// Show the most recently posted poll.
function ep_recentPoll($topPollInstead = false)
{
	global $db_prefix, $txt, $settings, $boardurl, $user_info, $context, $smcFunc, $modSettings;

	$boardsAllowed = boardsAllowedTo('poll_view');

	if (empty($boardsAllowed))
		return array();

	$request = $smcFunc['db_query']('', '
		SELECT p.id_poll, p.question, t.id_topic, p.max_votes, p.guest_vote, p.hide_results, p.expire_time
		FROM {db_prefix}polls AS p
			INNER JOIN {db_prefix}topics AS t ON (t.id_poll = p.id_poll' . ($modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') . ')
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)' . ($topPollInstead ? '
			INNER JOIN {db_prefix}poll_choices AS pc ON (pc.id_poll = p.id_poll)' : '') . '
			LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_poll = p.id_poll AND lp.id_member > {int:no_member} AND lp.id_member = {int:current_member})
		WHERE p.voting_locked = {int:voting_opened}
			AND {query_wanna_see_board}' . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_allowed_list})' : '') . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_enable}' : '') . '
		ORDER BY ' . ($topPollInstead ? 'pc.votes' : 'p.id_poll') . ' DESC
		LIMIT 1',
		array(
			'current_member' => $user_info['id'],
			'boards_allowed_list' => $boardsAllowed,
			'is_approved' => 1,
			'guest_vote_allowed' => 1,
			'no_member' => 0,
			'voting_opened' => 0,
			'no_expiration' => 0,
			'current_time' => time(),
			'recycle_enable' => $modSettings['recycle_board'],
		)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// This user has voted on all the polls.
	if ($row === false)
		return array();

	// If this is a guest who's voted we'll throw ourselves to show poll to show the results.
	if ($user_info['is_guest'] && ($row['guest_vote'] || (isset($_COOKIE['guest_poll_vote']) && in_array($row['id_poll'], explode(', ', $_COOKIE['guest_poll_vote'])))))
		return ep_showPoll($row['id_topic']);

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(DISTINCT id_member)
		FROM {db_prefix}log_polls
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	list ($total) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT id_choice, label, votes
		FROM {db_prefix}poll_choices
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	$options = array();
	while ($rowChoice = $smcFunc['db_fetch_assoc']($request))
	{
		censorText($rowChoice['label']);

		$options[$rowChoice['id_choice']] = array($rowChoice['label'], $rowChoice['votes']);
	}
	$smcFunc['db_free_result']($request);

	// Can they view it?
	$is_expired = !empty($row['expire_time']) && $row['expire_time'] < time();
	$allow_view_results = allowedTo('moderate_board') || $row['hide_results'] == 0 || $is_expired;

	$return = array(
		'id' => $row['id_poll'],
		'image' => 'poll',
		'question' => $row['question'],
		'total_votes' => $total,
		'is_locked' => false,
		'topic' => $row['id_topic'],
		'allow_view_results' => $allow_view_results,
		'options' => array()
	);

	// Calculate the percentages and bar lengths...
	$divisor = $return['total_votes'] == 0 ? 1 : $return['total_votes'];
	foreach ($options as $i => $option)
	{
		$bar = floor(($option[1] * 100) / $divisor);
		$return['options'][$i] = array(
			'id' => 'options-' . ($topPollInstead ? 'top-' : 'recent-') . $i,
			'percent' => $bar,
			'votes' => $option[1],
			'bar' => '<div class="bar" style="width: ' . $bar . '%;"><div></div></div>',
			'option' => parse_bbc($option[0]),
			'vote_button' => '<input type="' . ($row['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" id="options-' . ($topPollInstead ? 'top-' : 'recent-') . $i . '" value="' . $i . '" class="check" />'
		);
	}

	$return['allowed_warning'] = $row['max_votes'] > 1 ? sprintf($txt['poll_options6'], min(count($options), $row['max_votes'])) : '';

	return $return;
}

function ep_showPoll($topic = null)
{
	global $db_prefix, $txt, $settings, $boardurl, $user_info, $context, $smcFunc, $modSettings;

	$boardsAllowed = boardsAllowedTo('poll_view');

	if (empty($boardsAllowed))
		return array();

	if ($topic === null && isset($_REQUEST['ssi_topic']))
		$topic = (int) $_REQUEST['ssi_topic'];
	else
		$topic = (int) $topic;

	$request = $smcFunc['db_query']('', '
		SELECT
			p.id_poll, p.question, p.voting_locked, p.hide_results, p.expire_time, p.max_votes, p.guest_vote, b.id_board
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE t.id_topic = {int:current_topic}
			AND {query_see_board}' . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_allowed_see})' : '') . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : '') . '
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'boards_allowed_see' => $boardsAllowed,
			'is_approved' => 1,
		)
	);

	// Either this topic has no poll, or the user cannot view it.
	if ($smcFunc['db_num_rows']($request) == 0)
		return array();

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Check if they can vote.
	if (!empty($row['expire_time']) && $row['expire_time'] < time())
		$allow_vote = false;
	elseif ($user_info['is_guest'] && $row['guest_vote'] && (!isset($_COOKIE['guest_poll_vote']) || !in_array($row['id_poll'], explode(', ', $_COOKIE['guest_poll_vote']))))
		$allow_vote = true;
	elseif ($user_info['is_guest'])
		$allow_vote = false;
	elseif (!empty($row['voting_locked']) || !allowedTo('poll_vote', $row['id_board']))
		$allow_vote = false;
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}log_polls
			WHERE id_poll = {int:current_poll}
				AND id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'current_poll' => $row['id_poll'],
			)
		);
		$allow_vote = $smcFunc['db_num_rows']($request) == 0;
		$smcFunc['db_free_result']($request);
	}

	// Can they view?
	$is_expired = !empty($row['expire_time']) && $row['expire_time'] < time();
	$allow_view_results = allowedTo('moderate_board') || $row['hide_results'] == 0 || ($row['hide_results'] == 1 && !$allow_vote) || $is_expired;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(DISTINCT id_member)
		FROM {db_prefix}log_polls
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	list ($total) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT id_choice, label, votes
		FROM {db_prefix}poll_choices
		WHERE id_poll = {int:current_poll}',
		array(
			'current_poll' => $row['id_poll'],
		)
	);
	$options = array();
	$total_votes = 0;
	while ($rowChoice = $smcFunc['db_fetch_assoc']($request))
	{
		censorText($rowChoice['label']);

		$options[$rowChoice['id_choice']] = array($rowChoice['label'], $rowChoice['votes']);
		$total_votes += $rowChoice['votes'];
	}
	$smcFunc['db_free_result']($request);

	$return = array(
		'id' => $row['id_poll'],
		'image' => empty($pollinfo['voting_locked']) ? 'poll' : 'locked_poll',
		'question' => $row['question'],
		'total_votes' => $total,
		'is_locked' => !empty($pollinfo['voting_locked']),
		'allow_vote' => $allow_vote,
		'allow_view_results' => $allow_view_results,
		'topic' => $topic
	);

	// Calculate the percentages and bar lengths...
	$divisor = $total_votes == 0 ? 1 : $total_votes;
	foreach ($options as $i => $option)
	{
		$bar = floor(($option[1] * 100) / $divisor);
		$return['options'][$i] = array(
			'id' => 'options-' . $i,
			'percent' => $bar,
			'votes' => $option[1],
			'bar' => '<div class="bar" style="width: ' . $bar . '%;"><div></div></div>',
			'option' => parse_bbc($option[0]),
			'vote_button' => '<input type="' . ($row['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" id="options-' . $i . '" value="' . $i . '" class="check" />'
		);
	}

	$return['allowed_warning'] = $row['max_votes'] > 1 ? sprintf($txt['poll_options6'], min(count($options), $row['max_votes'])) : '';

	return $return;
}

function ep_whosOnline()
{
	global $user_info, $txt, $sourcedir, $settings, $modSettings;

	require_once($sourcedir . '/Subs-MembersOnline.php');
	$membersOnlineOptions = array(
		'show_hidden' => allowedTo('moderate_forum'),
		'sort' => 'log_time',
		'reverse_sort' => true,
	);
	$return = getMembersOnlineStats($membersOnlineOptions);

	return $return + array(
		'users' => $return['users_online'],
		'guests' => $return['num_guests'],
		'hidden' => $return['num_users_hidden'],
		'buddies' => $return['num_buddies'],
		'num_users' => $return['num_users_online'],
		'total_users' => $return['num_users_online'] + $return['num_guests'] + $return['num_spiders'],
	);
}

// Changes the theme data for the user when a theme from the theme module is selected.
function ep_changeTheme($members, $theme_data)
{
	global $user_info;

	// Are they a user?
	if (!$user_info['is_guest'])
	{
		// Update their data.
		updateMemberData($members, $theme_data);

		$cur_url = ep_getUrl();

		// And refresh the page!
		redirectexit($cur_url);
	}
	// They are a guest, so redirect theme to the theme id.
	else
		redirectexit((isset($_POST['ep_theme']) && $_POST['ep_theme'] > 0) ? 'theme=' . (int) $_POST['ep_theme'] : '');
}

function ep_calendar_getinfo($calData, $today, $calOptions, $curCalInfo, $rtl)
{
	global $sourcedir;

	$i = 0;
	$calCount = count($calData);

	foreach ($calData as $key => $value)
	{
		if ($i == 0)
		{
			if ($rtl)
				$high_date = strftime('%Y-%m-%d', mktime(0, 0, 0, $calData[$key]['month'] == 12 ? 1 : $calData[$key]['month'] + 1, 0, $calData[$key]['month'] == 12 ? ($calData[$key]['year'] + 1) : $calData[$key]['year']));
			else
				$low_date = strftime('%Y-%m-%d', mktime(0, 0, 0, $calData[$key]['month'], 1, $calData[$key]['year']));
		}
		elseif ($i == $calCount - 1)
		{
			if ($rtl)
				$low_date = strftime('%Y-%m-%d', mktime(0, 0, 0, $calData[$key]['month'], 1, $calData[$key]['year']));
			else
				$high_date = strftime('%Y-%m-%d', mktime(0, 0, 0, $calData[$key]['month'] == 12 ? 1 : $calData[$key]['month'] + 1, 0, $calData[$key]['month'] == 12 ? ($calData[$key]['year'] + 1) : $calData[$key]['year']));
		}
		$i++;
	}

	// If only showing 1 month, set the high and low dates.
	if (empty($high_date))
		$high_date = strftime('%Y-%m-%d', mktime(0, 0, 0, $today['month'] == 12 ? 1 : $today['month'] + 1, 0, $today['month'] == 12 ? ($today['year'] + 1) : $today['year']));

	if (empty($low_date))
		$low_date = strftime('%Y-%m-%d', mktime(0, 0, 0, $today['month'], 1, $today['year']));

	// Are we showing events/holidays/birthdays?
	if (!empty($curCalInfo['show_options']))
	{
		foreach ($curCalInfo['show_options'] as $opt => $type)
		{
			$type = (int) $type;

			switch ($type)
			{
				case 1:
					$show_holidays = true;
					break;
				case 2:
					$show_birthdays = true;
					break;
				default:
					$show_events = true;
					break;
			}
		}
	}
	else
	{
		$show_birthdays = false;
		$show_holidays = false;
		$show_events = false;
	}

	// Still need this file for birthdays and events ONLY!
	require_once($sourcedir . '/Subs-Calendar.php');

	// Hitting the database 3 times MAX here no matter how many months/years, that's all she wrote!
	$calEvents = array(
		'bdays' => $show_birthdays ? getBirthdayRange($low_date, $high_date) : array(),
		'events' => $show_events ? getEventRange($low_date, $high_date) : array(),
		'holidays' => $show_holidays ? epCalendarGetHolidayRange($low_date, $high_date) : array(),
	);

	// Now pass it to the function to get all of the months we need!
	$yearMonthInfo = array();
	foreach ($calData as $key => $month)
		$yearMonthInfo[$calData[$key]['year']][$calData[$key]['month']] = ep_calendar_GetMonthData($calData[$key]['month'], $calData[$key]['year'], $calOptions, $calEvents);

	return $yearMonthInfo;
}

// Slight Modification to SMF's getCalendarGrid function minus the crazy amount of hits to the database!
function ep_calendar_GetMonthData($month, $year, $calendarOptions, $calEvents)
{
	global $scripturl, $modSettings;

	// Eventually this is what we'll be returning.
	$calendarGrid = array(
		'week_days' => array(),
		'weeks' => array(),
		'short_day_titles' => !empty($calendarOptions['short_day_titles']),
		'current_month' => $month,
		'current_year' => $year,
		'show_next_prev' => !empty($calendarOptions['show_next_prev']),
		'show_week_links' => !empty($calendarOptions['show_week_links']),
		'previous_calendar' => array(
			'year' => $month == 1 ? $year - 1 : $year,
			'month' => $month == 1 ? 12 : $month - 1,
			'disabled' => $modSettings['cal_minyear'] > ($month == 1 ? $year - 1 : $year),
		),
		'next_calendar' => array(
			'year' => $month == 12 ? $year + 1 : $year,
			'month' => $month == 12 ? 1 : $month + 1,
			'disabled' => $modSettings['cal_maxyear'] < ($month == 12 ? $year + 1 : $year),
		),
		//!!! Better tweaks?
		'size' => isset($calendarOptions['size']) ? $calendarOptions['size'] : 'large',
	);

	// Get todays date.
	$today = getTodayInfo();

	// Get information about this month.
	$month_info = array(
		'first_day' => array(
			'day_of_week' => (int) strftime('%w', mktime(0, 0, 0, $month, 1, $year)),
			'week_num' => (int) strftime('%U', mktime(0, 0, 0, $month, 1, $year)),
			'date' => strftime('%Y-%m-%d', mktime(0, 0, 0, $month, 1, $year)),
		),
		'last_day' => array(
			'day_of_month' => (int) strftime('%d', mktime(0, 0, 0, $month == 12 ? 1 : $month + 1, 0, $month == 12 ? $year + 1 : $year)),
			'date' => strftime('%Y-%m-%d', mktime(0, 0, 0, $month == 12 ? 1 : $month + 1, 0, $month == 12 ? $year + 1 : $year)),
		),
		'first_day_of_year' => (int) strftime('%w', mktime(0, 0, 0, 1, 1, $year)),
		'first_day_of_next_year' => (int) strftime('%w', mktime(0, 0, 0, 1, 1, $year + 1)),
	);

	// The number of days the first row is shifted to the right for the starting day.
	$nShift = $month_info['first_day']['day_of_week'];

	$calendarOptions['start_day'] = empty($calendarOptions['start_day']) ? 0 : (int) $calendarOptions['start_day'];

	// Starting any day other than Sunday means a shift...
	if (!empty($calendarOptions['start_day']))
	{
		$nShift -= $calendarOptions['start_day'];
		if ($nShift < 0)
			$nShift = 7 + $nShift;
	}

	// Number of rows required to fit the month.
	$nRows = floor(($month_info['last_day']['day_of_month'] + $nShift) / 7);
	if (($month_info['last_day']['day_of_month'] + $nShift) % 7)
		$nRows++;

	// Days of the week taking into consideration that they may want it to start on any day.
	$count = $calendarOptions['start_day'];
	for ($i = 0; $i < 7; $i++)
	{
		$calendarGrid['week_days'][] = $count;
		$count++;
		if ($count == 7)
			$count = 0;
	}

	// An adjustment value to apply to all calculated week numbers.
	if (!empty($calendarOptions['show_week_num']))
	{
		if ($calendarOptions['start_day'] === 0)
			$nWeekAdjust = $month_info['first_day_of_year'] === 0 ? 0 : 1;
		else
			$nWeekAdjust = $calendarOptions['start_day'] > $month_info['first_day_of_year'] && $month_info['first_day_of_year'] !== 0 ? 2 : 1;

		if ($month_info['first_day']['day_of_week'] < $calendarOptions['start_day'] || $month_info['first_day_of_year'] > 4)
			$nWeekAdjust--;
	}
	else
		$nWeekAdjust = 0;

	// Iterate through each week.
	$calendarGrid['weeks'] = array();
	for ($nRow = 0; $nRow < $nRows; $nRow++)
	{
		// Start off the week - and don't let it go above 52, since that's the number of weeks in a year.
		$calendarGrid['weeks'][$nRow] = array(
			'days' => array(),
			'number' => $month_info['first_day']['week_num'] + $nRow + $nWeekAdjust
		);
		// Handle the dreaded "week 53", it can happen, but only once in a blue moon ;)
		if ($calendarGrid['weeks'][$nRow]['number'] == 53 && $nShift != 4 && $month_info['first_day_of_next_year'] < 4)
			$calendarGrid['weeks'][$nRow]['number'] = 1;

		// And figure out all the days.
		for ($nCol = 0; $nCol < 7; $nCol++)
		{
			$nDay = ($nRow * 7) + $nCol - $nShift + 1;

			if ($nDay < 1 || $nDay > $month_info['last_day']['day_of_month'])
				$nDay = 0;

			$date = sprintf('%04d-%02d-%02d', $year, $month, $nDay);
			// $date = strftime('%Y-%m-%d', mktime(0, 0, 0, $month, $nDay, $year));

			$calendarGrid['weeks'][$nRow]['days'][$nCol] = array(
				'day' => $nDay,
				'date' => $date,
				'is_today' => $date == $today['date'],
				'is_first_day' => !empty($calendarOptions['show_week_num']) && (($month_info['first_day']['day_of_week'] + $nDay - 1) % 7 == $calendarOptions['start_day']),
				'holidays' => !empty($calEvents['holidays'][$date]) ? $calEvents['holidays'][$date] : array(),
				'events' => !empty($calEvents['events'][$date]) ? $calEvents['events'][$date] : array(),
				'birthdays' => !empty($calEvents['bdays'][$date]) ? $calEvents['bdays'][$date] : array()
			);
		}
	}

	// Set the previous and the next month's links.
	$calendarGrid['previous_calendar']['href'] = $scripturl . '?action=calendar;year=' . $calendarGrid['previous_calendar']['year'] . ';month=' . $calendarGrid['previous_calendar']['month'];
	$calendarGrid['next_calendar']['href'] = $scripturl . '?action=calendar;year=' . $calendarGrid['next_calendar']['year'] . ';month=' . $calendarGrid['next_calendar']['month'];

	return $calendarGrid;
}

function ep_calendarEvents($day = array(), $curMonth, $curYear, $curCalOptions)
{
	global $context, $scripturl, $txt;

	$echo = '';

	if (empty($day['holidays']) && empty($day['birthdays']) && empty($day['events']) && !$day['is_today'])
		return $echo;
	elseif (empty($day['holidays']) && empty($day['birthdays']) && empty($day['events']))
	{
		$echo = '
				<div style="text-align: center;" class="smalltext" id="' . $curCalOptions['unique_id'] . '_' . $day['date'] . '">' . $txt['ep_nocal_found'] . '</div>';

		return $echo;
	}

	$echo .= '
				<ul class="reset smalltext" id="' . $curCalOptions['unique_id'] . '_' . $day['date'] . '" ' . (!$day['is_today'] ? ' style="display: none;"' : '') . '>';

	// No padding to the first option in the list.
	$xOpt = 0;

	foreach($curCalOptions['show_options'] as $option => $type)
	{
		$type = (int) $type;

		// HOLIDAYS
		if ($type == 1)
		{
			if (!empty($day['holidays']))
			{
				$echo .= '
							<li><div style="text-align: center;' . (!empty($xOpt) ? ' padding-top: 4px;' : '') . '"><strong>- ' . $txt['ep_holidays'] . ' -</strong></div></li>';



				foreach ($day['holidays'] as $key => $holiday)
					$echo .= '
							<li><div style="padding-left: 10px;"><img src="'.  $context['ep_icon_url'] . '/cal.png" alt="" /> ' . $holiday  .'</div></li>';
			}
		}
		// BIRTHDAYS
		elseif ($type == 2)
		{

			if (!empty($day['birthdays']))
			{
				$echo .= '
							<li><div style="text-align: center;' . (!empty($xOpt) ? ' padding-top: 4px;' : '') . '"><strong>- ' . $txt['ep_bdays'] . ' -</strong></div></li>';

				foreach ($day['birthdays'] as $member)
					$echo .= '
							<li><div style="padding-left: 10px;"><img src="' . $context['ep_icon_url'] . '/cake.png" alt="" /> <a href="' . $scripturl . '?action=profile;u=' . $member['id'] . '">' . $member['name'] . (isset($member['age']) ? ' (' . $member['age'] . ')' : '') . '</a></div></li>';
			}
		}
		// EVENTS
		else
		{
			if (!empty($day['events']))
			{
					$echo .= '
							<li><div style="text-align: center;' . (!empty($xOpt) ? ' padding-top: 4px;' : '') . '"><strong>- ' . $txt['ep_events'] . ' -</strong></div></li>';

				foreach ($day['events'] as $event)
					$echo .= '
							<li><div style="padding-left: 10px;"><img src="' .  $context['ep_icon_url'] . '/cal-event.png" alt="" /> ' . $event['link'] . '</div></li>';
			}
		}
		$xOpt++;
	}
	$echo .= '
				</ul>';
	return $echo;
}

// Get all holidays within the given time range.
function epCalendarGetHolidayRange($low_date, $high_date)
{
	global $smcFunc;

	$low_year = (int) substr($low_date, 0, 4);
	$high_year = (int) substr($high_date, 0, 4);
	$diff_year = $high_year - $low_year;

	// Get the lowest and highest dates for "all years".
	if ($low_year != $high_year)
	{
		if ($diff_year > 1)
			// More than 1 year difference, we need them all.
			$allyear_part = 'event_date BETWEEN {date:all_year_jan} AND {date:all_year_dec}';
		else
			// This would work for a difference of 1 year ONLY!
			$allyear_part = 'event_date BETWEEN {date:all_year_low} AND {date:all_year_dec}
				OR event_date BETWEEN {date:all_year_jan} AND {date:all_year_high}';
	}
	else
		$allyear_part = 'event_date BETWEEN {date:all_year_low} AND {date:all_year_high}';

	// Find some holidays... ;).
	$result = $smcFunc['db_query']('', '
		SELECT event_date, YEAR(event_date) AS year, title
		FROM {db_prefix}calendar_holidays
		WHERE event_date BETWEEN {date:low_date} AND {date:high_date}
			OR ' . $allyear_part,
		array(
			'low_date' => $low_date,
			'high_date' => $high_date,
			'all_year_low' => '0004' . substr($low_date, 4),
			'all_year_high' => '0004' . substr($high_date, 4),
			'all_year_jan' => '0004-01-01',
			'all_year_dec' => '0004-12-31',
		)
	);
	$holidays = array();
	$event_year = $low_year;

	// We need to assign the correct year for all years, no matter what the month is!
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		if ($low_year != $high_year)
		{
			// Need to SET all 0004 Holidays for each year since these are only called once!
			if (substr($row['event_date'], 0, 4) == '0004')
			{
				// Add it to each year!
				for ($i = 0; $i <= $diff_year; $i++)
					$holidays[$event_year + $i . substr($row['event_date'], 4)][] = $row['title'];
			}
			else
				$holidays[$row['event_date']][] = $row['title'];
		}
		else
			$holidays[$event_year . substr($row['event_date'], 4)][] = $row['title'];
	}
	$smcFunc['db_free_result']($result);

	return $holidays;
}

?>