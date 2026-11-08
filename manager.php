<?php
/**
 *
 * See Notes extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\seenotes;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\log\log_interface;
use phpbb\user;

/**
 * Reads and changes reasons, entries and notes, checks who may see and change
 * them, and logs each change
 *
 * Titles, descriptions and saved usernames are stored the way phpBB stores
 * request input: HTML-escaped. Note text is stored by phpBB's text formatter.
 */
class manager
{
	/** Longest reason title or description, in characters */
	const TEXT_MAX_LENGTH = 255;

	/** Reason color used when none is set */
	const DEFAULT_COLOR = 'AA0000';

	/** Actions with their own permission */
	const ACTIONS = ['view', 'manage', 'note_delete', 'note_edit', 'entry_delete'];

	/** @var auth */
	protected $auth;

	/** @var config */
	protected $config;

	/** @var driver_interface */
	protected $db;

	/** @var log_interface */
	protected $log;

	/** @var user */
	protected $user;

	/** @var string */
	protected $reasons_table;

	/** @var string */
	protected $entries_table;

	/** @var string */
	protected $notes_table;

	/** @var array Current entries already read, by user ID (false when none) */
	protected $current_cache = [];

	/**
	 * @param auth $auth
	 * @param config $config
	 * @param driver_interface $db
	 * @param log_interface $log
	 * @param user $user
	 * @param string $reasons_table
	 * @param string $entries_table
	 * @param string $notes_table
	 */
	public function __construct(auth $auth, config $config, driver_interface $db, log_interface $log, user $user, $reasons_table, $entries_table, $notes_table)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->log = $log;
		$this->user = $user;
		$this->reasons_table = $reasons_table;
		$this->entries_table = $entries_table;
		$this->notes_table = $notes_table;
	}

	/**
	 * Whether the current user may take an action: the moderator permission
	 * in any forum, or the matching administrator permission
	 *
	 * @param string $action One of self::ACTIONS
	 * @return bool
	 */
	public function can($action)
	{
		if (!in_array($action, self::ACTIONS, true) || empty($this->user->data['is_registered']))
		{
			return false;
		}

		return (bool) $this->auth->acl_getf_global('m_seenotes_' . $action) || (bool) $this->auth->acl_get('a_seenotes_' . $action);
	}

	/**
	 * The label shown before the reason
	 *
	 * @return string
	 */
	public function get_label()
	{
		$label = (string) $this->config['seenotes_label'];

		return $label !== '' ? $label : 'SEE NOTES';
	}

	/**
	 * A reason color as six hex digits, or the default when it is not valid
	 *
	 * @param string $color
	 * @return string
	 */
	public function clean_color($color)
	{
		$color = strtoupper(ltrim(trim((string) $color), '#'));

		return preg_match('/\A[0-9A-F]{6}\z/', $color) ? $color : self::DEFAULT_COLOR;
	}

	/* ------------------------------------------------------------------ */
	/* Reasons                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * All reasons in their set order
	 *
	 * @param bool $active_only Leave out reasons that are switched off
	 * @return array Rows by reason ID
	 */
	public function get_reasons($active_only = false)
	{
		$sql = 'SELECT *
			FROM ' . $this->reasons_table .
			($active_only ? ' WHERE reason_active = 1' : '') . '
			ORDER BY reason_order ASC, reason_id ASC';
		$result = $this->db->sql_query($sql);

		$reasons = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$reasons[(int) $row['reason_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $reasons;
	}

	/**
	 * One reason
	 *
	 * @param int $reason_id
	 * @return array|null
	 */
	public function get_reason($reason_id)
	{
		$sql = 'SELECT *
			FROM ' . $this->reasons_table . '
			WHERE reason_id = ' . (int) $reason_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	/**
	 * Add a reason at the end of the list, or change one
	 *
	 * @param array $data reason_title, reason_description, reason_color
	 * @param int $reason_id 0 to add a reason
	 * @return int The reason's ID
	 */
	public function save_reason(array $data, $reason_id = 0)
	{
		$sql_ary = [
			'reason_title'			=> (string) $data['reason_title'],
			'reason_description'	=> (string) $data['reason_description'],
			'reason_color'			=> $this->clean_color($data['reason_color']),
		];

		if ($reason_id)
		{
			$sql = 'UPDATE ' . $this->reasons_table . '
				SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
				WHERE reason_id = ' . (int) $reason_id;
			$this->db->sql_query($sql);

			return (int) $reason_id;
		}

		$sql = 'SELECT MAX(reason_order) AS max_order
			FROM ' . $this->reasons_table;
		$result = $this->db->sql_query($sql);
		$max_order = (int) $this->db->sql_fetchfield('max_order');
		$this->db->sql_freeresult($result);

		$sql_ary['reason_order'] = $max_order + 1;
		$sql_ary['reason_active'] = 1;

		$this->db->sql_query('INSERT INTO ' . $this->reasons_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

		return (int) $this->db->sql_nextid();
	}

	/**
	 * Move a reason one place up or down the list
	 *
	 * @param int $reason_id
	 * @param bool $up
	 * @return bool Whether it moved
	 */
	public function move_reason($reason_id, $up)
	{
		$ids = array_keys($this->get_reasons());
		$position = array_search((int) $reason_id, $ids, true);
		if ($position === false)
		{
			return false;
		}

		$swap = $up ? $position - 1 : $position + 1;
		if (!isset($ids[$swap]))
		{
			return false;
		}

		list($ids[$position], $ids[$swap]) = [$ids[$swap], $ids[$position]];

		// Renumber the whole list, which also repairs equal order values
		$this->db->sql_transaction('begin');
		foreach ($ids as $order => $id)
		{
			$sql = 'UPDATE ' . $this->reasons_table . '
				SET reason_order = ' . ($order + 1) . '
				WHERE reason_id = ' . (int) $id;
			$this->db->sql_query($sql);
		}
		$this->db->sql_transaction('commit');

		return true;
	}

	/**
	 * Switch a reason on or off
	 *
	 * @param int $reason_id
	 * @param bool $active
	 * @return void
	 */
	public function set_reason_active($reason_id, $active)
	{
		$sql = 'UPDATE ' . $this->reasons_table . '
			SET reason_active = ' . ($active ? 1 : 0) . '
			WHERE reason_id = ' . (int) $reason_id;
		$this->db->sql_query($sql);
	}

	/**
	 * How many members, entries and notes use a reason
	 *
	 * @param int $reason_id
	 * @return array users, entries, notes
	 */
	public function count_reason_usage($reason_id)
	{
		$sql = 'SELECT COUNT(DISTINCT user_id) AS users, COUNT(entry_id) AS entries
			FROM ' . $this->entries_table . '
			WHERE reason_id = ' . (int) $reason_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$sql = 'SELECT COUNT(n.note_id) AS notes
			FROM ' . $this->notes_table . ' n, ' . $this->entries_table . ' e
			WHERE n.entry_id = e.entry_id
				AND e.reason_id = ' . (int) $reason_id;
		$result = $this->db->sql_query($sql);
		$notes = (int) $this->db->sql_fetchfield('notes');
		$this->db->sql_freeresult($result);

		return [
			'users'		=> (int) $row['users'],
			'entries'	=> (int) $row['entries'],
			'notes'		=> $notes,
		];
	}

	/**
	 * Delete a reason with all its entries and their notes
	 *
	 * @param int $reason_id
	 * @return void
	 */
	public function delete_reason($reason_id)
	{
		$entry_ids = $this->get_entry_ids('reason_id = ' . (int) $reason_id);

		$this->db->sql_transaction('begin');
		$this->delete_entry_rows($entry_ids);
		$this->db->sql_query('DELETE FROM ' . $this->reasons_table . ' WHERE reason_id = ' . (int) $reason_id);
		$this->db->sql_transaction('commit');
	}

	/* ------------------------------------------------------------------ */
	/* Entries                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * A member's entries, newest first, with their reason and author
	 *
	 * @param int $user_id
	 * @return array Rows by entry ID
	 */
	public function get_entries($user_id)
	{
		$sql_ary = [
			'SELECT'	=> 'e.*, r.reason_title, r.reason_description, r.reason_color, r.reason_active, u.username, u.user_colour',
			'FROM'		=> [$this->entries_table => 'e'],
			'LEFT_JOIN'	=> [
				[
					'FROM'	=> [$this->reasons_table => 'r'],
					'ON'	=> 'r.reason_id = e.reason_id',
				],
				[
					'FROM'	=> [USERS_TABLE => 'u'],
					'ON'	=> 'u.user_id = e.entry_author_id',
				],
			],
			'WHERE'		=> 'e.user_id = ' . (int) $user_id,
			'ORDER_BY'	=> 'e.entry_time DESC, e.entry_id DESC',
		];
		$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $sql_ary));

		$entries = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$entries[(int) $row['entry_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $entries;
	}

	/**
	 * One entry
	 *
	 * @param int $entry_id
	 * @return array|null
	 */
	public function get_entry($entry_id)
	{
		$sql = 'SELECT e.*, r.reason_title
			FROM ' . $this->entries_table . ' e
			LEFT JOIN ' . $this->reasons_table . ' r
				ON (r.reason_id = e.reason_id)
			WHERE e.entry_id = ' . (int) $entry_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	/**
	 * Give a member a reason
	 *
	 * @param int $user_id
	 * @param int $reason_id
	 * @param bool $current Make it the member's current entry
	 * @return int The entry's ID
	 */
	public function add_entry($user_id, $reason_id, $current)
	{
		$sql_ary = [
			'user_id'			=> (int) $user_id,
			'reason_id'			=> (int) $reason_id,
			'entry_time'		=> time(),
			'entry_author_id'	=> (int) $this->user->data['user_id'],
			'entry_author_name'	=> (string) $this->user->data['username'],
			'entry_current'		=> 0,
		];

		$this->db->sql_transaction('begin');
		$this->db->sql_query('INSERT INTO ' . $this->entries_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
		$entry_id = (int) $this->db->sql_nextid();

		if ($current)
		{
			$this->set_current($user_id, $entry_id);
		}
		$this->db->sql_transaction('commit');

		return $entry_id;
	}

	/**
	 * Make one of a member's entries current, or none
	 *
	 * @param int $user_id
	 * @param int $entry_id 0 for none
	 * @return void
	 */
	public function set_current($user_id, $entry_id)
	{
		$this->db->sql_transaction('begin');

		$sql = 'UPDATE ' . $this->entries_table . '
			SET entry_current = 0
			WHERE user_id = ' . (int) $user_id;
		$this->db->sql_query($sql);

		if ($entry_id)
		{
			$sql = 'UPDATE ' . $this->entries_table . '
				SET entry_current = 1
				WHERE user_id = ' . (int) $user_id . '
					AND entry_id = ' . (int) $entry_id;
			$this->db->sql_query($sql);
		}

		$this->db->sql_transaction('commit');

		unset($this->current_cache[(int) $user_id]);
	}

	/**
	 * Delete an entry with its notes
	 *
	 * @param int $entry_id
	 * @return void
	 */
	public function delete_entry($entry_id)
	{
		$this->db->sql_transaction('begin');
		$this->delete_entry_rows([(int) $entry_id]);
		$this->db->sql_transaction('commit');
	}

	/**
	 * The current entry of each member that has one, with its reason and
	 * number of notes; read once per member
	 *
	 * @param array $user_ids
	 * @return array Rows by user ID, members without a current entry left out
	 */
	public function get_current_for_users(array $user_ids)
	{
		$user_ids = array_unique(array_map('intval', $user_ids));
		$missing = array_values(array_diff($user_ids, array_keys($this->current_cache)));

		if ($missing)
		{
			foreach ($missing as $user_id)
			{
				$this->current_cache[$user_id] = false;
			}

			$sql = 'SELECT e.entry_id, e.user_id, r.reason_title, r.reason_description, r.reason_color
				FROM ' . $this->entries_table . ' e, ' . $this->reasons_table . ' r
				WHERE r.reason_id = e.reason_id
					AND e.entry_current = 1
					AND ' . $this->db->sql_in_set('e.user_id', $missing);
			$result = $this->db->sql_query($sql);

			$by_entry = [];
			while ($row = $this->db->sql_fetchrow($result))
			{
				$row['note_count'] = 0;
				$by_entry[(int) $row['entry_id']] = (int) $row['user_id'];
				$this->current_cache[(int) $row['user_id']] = $row;
			}
			$this->db->sql_freeresult($result);

			if ($by_entry)
			{
				$sql = 'SELECT entry_id, COUNT(note_id) AS note_count
					FROM ' . $this->notes_table . '
					WHERE ' . $this->db->sql_in_set('entry_id', array_keys($by_entry)) . '
					GROUP BY entry_id';
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					$this->current_cache[$by_entry[(int) $row['entry_id']]]['note_count'] = (int) $row['note_count'];
				}
				$this->db->sql_freeresult($result);
			}
		}

		$current = [];
		foreach ($user_ids as $user_id)
		{
			if ($this->current_cache[$user_id] !== false)
			{
				$current[$user_id] = $this->current_cache[$user_id];
			}
		}

		return $current;
	}

	/* ------------------------------------------------------------------ */
	/* Notes                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * The notes of some entries, newest first, with their author and editor
	 *
	 * @param array $entry_ids
	 * @return array Lists of rows by entry ID
	 */
	public function get_notes(array $entry_ids)
	{
		if (!$entry_ids)
		{
			return [];
		}

		$sql_ary = [
			'SELECT'	=> 'n.*, a.username AS author_username, a.user_colour AS author_colour, ed.username AS editor_username, ed.user_colour AS editor_colour',
			'FROM'		=> [$this->notes_table => 'n'],
			'LEFT_JOIN'	=> [
				[
					'FROM'	=> [USERS_TABLE => 'a'],
					'ON'	=> 'a.user_id = n.note_author_id',
				],
				[
					'FROM'	=> [USERS_TABLE => 'ed'],
					'ON'	=> 'ed.user_id = n.note_edit_user_id',
				],
			],
			'WHERE'		=> $this->db->sql_in_set('n.entry_id', array_map('intval', $entry_ids)),
			'ORDER_BY'	=> 'n.note_time DESC, n.note_id DESC',
		];
		$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $sql_ary));

		$notes = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$notes[(int) $row['entry_id']][] = $row;
		}
		$this->db->sql_freeresult($result);

		return $notes;
	}

	/**
	 * One note, with the member its entry is about
	 *
	 * @param int $note_id
	 * @return array|null
	 */
	public function get_note($note_id)
	{
		$sql = 'SELECT n.*, e.user_id, r.reason_title
			FROM ' . $this->notes_table . ' n
			INNER JOIN ' . $this->entries_table . ' e
				ON (e.entry_id = n.entry_id)
			LEFT JOIN ' . $this->reasons_table . ' r
				ON (r.reason_id = e.reason_id)
			WHERE n.note_id = ' . (int) $note_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	/**
	 * Parse note text with the board's private message settings
	 *
	 * @param string $text Text as typed, from request->variable(..., true)
	 * @return array text, uid, bitfield, options, errors (untranslated keys or messages)
	 */
	public function parse_note($text)
	{
		$uid = $bitfield = '';
		$options = 0;

		$allow_bbcode = $this->config['allow_bbcode'] && $this->config['auth_bbcode_pm'];
		$allow_smilies = $this->config['allow_smilies'] && $this->config['auth_smilies_pm'];
		$allow_urls = (bool) $this->config['allow_post_links'];

		$errors = [];
		if (trim($text) === '')
		{
			$errors[] = 'SEENOTES_NOTE_EMPTY';
		}

		$warnings = generate_text_for_storage(
			$text,
			$uid,
			$bitfield,
			$options,
			$allow_bbcode,
			$allow_urls,
			$allow_smilies,
			(bool) $this->config['auth_img_pm'],
			(bool) $this->config['auth_flash_pm'],
			true,
			$allow_urls,
			'pm'
		);

		return [
			'text'		=> $text,
			'uid'		=> $uid,
			'bitfield'	=> $bitfield,
			'options'	=> (int) $options,
			'errors'	=> array_merge($errors, (array) $warnings),
		];
	}

	/**
	 * The BBCode, smilies and links settings notes are written with
	 *
	 * @return array bbcode, smilies, urls, img, flash, quote
	 */
	public function get_editor_settings()
	{
		$bbcode = $this->config['allow_bbcode'] && $this->config['auth_bbcode_pm'];

		return [
			'bbcode'	=> (bool) $bbcode,
			'smilies'	=> $this->config['allow_smilies'] && $this->config['auth_smilies_pm'],
			'urls'		=> (bool) $this->config['allow_post_links'],
			'img'		=> $bbcode && $this->config['auth_img_pm'],
			'flash'		=> $bbcode && $this->config['auth_flash_pm'],
			'quote'		=> (bool) $bbcode,
		];
	}

	/**
	 * Add a note under an entry
	 *
	 * @param int $entry_id
	 * @param array $parsed From parse_note()
	 * @return int The note's ID
	 */
	public function add_note($entry_id, array $parsed)
	{
		$sql_ary = [
			'entry_id'				=> (int) $entry_id,
			'note_author_id'		=> (int) $this->user->data['user_id'],
			'note_author_name'		=> (string) $this->user->data['username'],
			'note_time'				=> time(),
			'note_text'				=> $parsed['text'],
			'note_bbcode_uid'		=> $parsed['uid'],
			'note_bbcode_bitfield'	=> $parsed['bitfield'],
			'note_bbcode_options'	=> (int) $parsed['options'],
		];

		$this->db->sql_query('INSERT INTO ' . $this->notes_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
		$this->current_cache = [];

		return (int) $this->db->sql_nextid();
	}

	/**
	 * Change a note's text, recording who edited it and when
	 *
	 * @param int $note_id
	 * @param array $parsed From parse_note()
	 * @return void
	 */
	public function edit_note($note_id, array $parsed)
	{
		$sql_ary = [
			'note_text'				=> $parsed['text'],
			'note_bbcode_uid'		=> $parsed['uid'],
			'note_bbcode_bitfield'	=> $parsed['bitfield'],
			'note_bbcode_options'	=> (int) $parsed['options'],
			'note_edit_time'		=> time(),
			'note_edit_user_id'		=> (int) $this->user->data['user_id'],
			'note_edit_user_name'	=> (string) $this->user->data['username'],
		];

		$sql = 'UPDATE ' . $this->notes_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
			WHERE note_id = ' . (int) $note_id;
		$this->db->sql_query($sql);
	}

	/**
	 * Delete a note
	 *
	 * @param int $note_id
	 * @return void
	 */
	public function delete_note($note_id)
	{
		$this->db->sql_query('DELETE FROM ' . $this->notes_table . ' WHERE note_id = ' . (int) $note_id);
		$this->current_cache = [];
	}

	/* ------------------------------------------------------------------ */
	/* Members                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Delete the entries and notes about deleted members
	 *
	 * @param array $user_ids
	 * @return void
	 */
	public function delete_users(array $user_ids)
	{
		if (!$user_ids)
		{
			return;
		}

		$entry_ids = $this->get_entry_ids($this->db->sql_in_set('user_id', array_map('intval', $user_ids)));

		$this->db->sql_transaction('begin');
		$this->delete_entry_rows($entry_ids);
		$this->db->sql_transaction('commit');
	}

	/**
	 * Keep the saved names of a renamed member up to date
	 *
	 * @param string $old_name
	 * @param string $new_name
	 * @return void
	 */
	public function update_username($old_name, $new_name)
	{
		$updates = [
			[$this->entries_table, 'entry_author_name'],
			[$this->notes_table, 'note_author_name'],
			[$this->notes_table, 'note_edit_user_name'],
		];

		foreach ($updates as $update)
		{
			list($table, $column) = $update;

			$sql = 'UPDATE ' . $table . "
				SET $column = '" . $this->db->sql_escape($new_name) . "'
				WHERE $column = '" . $this->db->sql_escape($old_name) . "'";
			$this->db->sql_query($sql);
		}
	}

	/* ------------------------------------------------------------------ */
	/* Logs                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Add an entry to the moderator log
	 *
	 * @param string $operation Language key
	 * @param array $data Values for the language key
	 * @return void
	 */
	public function log_moderator($operation, array $data)
	{
		$this->log->add('mod', (int) $this->user->data['user_id'], $this->user->ip, $operation, false, array_merge(['forum_id' => 0, 'topic_id' => 0], array_values($data)));
	}

	/**
	 * Add an entry to the admin log
	 *
	 * @param string $operation Language key
	 * @param array $data Values for the language key
	 * @return void
	 */
	public function log_admin($operation, array $data = [])
	{
		$this->log->add('admin', (int) $this->user->data['user_id'], $this->user->ip, $operation, false, array_values($data));
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Entry IDs matching a condition
	 *
	 * @param string $where SQL condition on the entries table
	 * @return array
	 */
	protected function get_entry_ids($where)
	{
		$sql = 'SELECT entry_id
			FROM ' . $this->entries_table . '
			WHERE ' . $where;
		$result = $this->db->sql_query($sql);

		$entry_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$entry_ids[] = (int) $row['entry_id'];
		}
		$this->db->sql_freeresult($result);

		return $entry_ids;
	}

	/**
	 * Delete entries and their notes; the caller holds the transaction
	 *
	 * @param array $entry_ids
	 * @return void
	 */
	protected function delete_entry_rows(array $entry_ids)
	{
		if (!$entry_ids)
		{
			return;
		}

		$this->db->sql_query('DELETE FROM ' . $this->notes_table . ' WHERE ' . $this->db->sql_in_set('entry_id', $entry_ids));
		$this->db->sql_query('DELETE FROM ' . $this->entries_table . ' WHERE ' . $this->db->sql_in_set('entry_id', $entry_ids));
		$this->current_cache = [];
	}
}
