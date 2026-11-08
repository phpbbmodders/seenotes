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

use phpbb\language\language;
use phpbb\log\log_interface;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;

/**
 * A member's entries and notes, with the forms to change them; shared by the
 * MCP Moderator notes tab and the ACP Manage users page
 */
class notes_page
{
	/** Form key name */
	const FORM_KEY = 'phpbbmodders_seenotes_notes';

	/** phpBB user notes shown in the read-only box */
	const USER_NOTES_LIMIT = 5;

	/** @var language */
	protected $language;

	/** @var log_interface */
	protected $log;

	/** @var request_interface */
	protected $request;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var manager */
	protected $manager;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/** @var array The member the page is about */
	protected $member;

	/** @var string Page URL, with the member */
	protected $u_action;

	/** @var bool Shown in the ACP */
	protected $in_acp;

	/**
	 * @param language $language
	 * @param log_interface $log
	 * @param request_interface $request
	 * @param template $template
	 * @param user $user
	 * @param manager $manager
	 * @param string $root_path
	 * @param string $php_ext
	 */
	public function __construct(language $language, log_interface $log, request_interface $request, template $template, user $user, manager $manager, $root_path, $php_ext)
	{
		$this->language = $language;
		$this->log = $log;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->manager = $manager;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Run the requested action, then show the member's entries and notes
	 *
	 * @param array $member Users table row of the member
	 * @param string $u_action Page URL, including the member's ID
	 * @param bool $in_acp Shown in the ACP rather than the MCP
	 * @return void
	 */
	public function handle(array $member, $u_action, $in_acp)
	{
		$this->member = $member;
		$this->u_action = $u_action;
		$this->in_acp = $in_acp;

		// acp/common holds the phpBB user-note log strings, as on core's own notes page
		$this->language->add_lang(['posting', 'mcp', 'acp/common']);
		$this->language->add_lang('seenotes', 'phpbbmodders/seenotes');

		if (!$this->manager->can('view'))
		{
			$this->error('NOT_AUTHORISED');
		}

		add_form_key(self::FORM_KEY);

		$errors = [];
		$editor_text = null;
		$action = $this->request->variable('action', '');

		switch ($action)
		{
			case 'add_entry':
				$this->add_entry();
			break;

			case 'set_current':
				$this->set_current();
			break;

			case 'delete_entry':
				$this->delete_entry();
			break;

			case 'add_note':
			case 'edit_note':
				$editor_text = $this->save_note($action === 'edit_note', $errors);
			break;

			case 'delete_note':
				$this->delete_note();
			break;
		}

		$this->display($errors, $editor_text);
	}

	/**
	 * Give the member a reason
	 *
	 * @return void
	 */
	protected function add_entry()
	{
		$this->require_post_permission('manage');

		$reason_id = $this->request->variable('reason_id', 0);
		$reasons = $this->manager->get_reasons(true);
		if (!isset($reasons[$reason_id]))
		{
			$this->error('SEENOTES_REASON_NOT_SELECTED');
		}

		$current = (bool) $this->request->variable('entry_current', false);
		$this->manager->add_entry((int) $this->member['user_id'], $reason_id, $current);
		$this->manager->log_moderator('LOG_SEENOTES_ENTRY_ADDED', [$reasons[$reason_id]['reason_title'], $this->member['username']]);

		$this->success('SEENOTES_ENTRY_ADDED');
	}

	/**
	 * Make one of the member's entries current, or none
	 *
	 * @return void
	 */
	protected function set_current()
	{
		$this->require_post_permission('manage');

		$entry_id = $this->request->variable('entry_id', 0);
		$entry = null;
		if ($entry_id)
		{
			$entry = $this->get_member_entry($entry_id);
		}

		$this->manager->set_current((int) $this->member['user_id'], $entry_id);

		if ($entry)
		{
			$this->manager->log_moderator('LOG_SEENOTES_CURRENT_SET', [$entry['reason_title'], $this->member['username']]);
		}
		else
		{
			$this->manager->log_moderator('LOG_SEENOTES_CURRENT_CLEARED', [$this->member['username']]);
		}

		$this->success($entry ? 'SEENOTES_CURRENT_SET' : 'SEENOTES_CURRENT_CLEARED');
	}

	/**
	 * Delete one of the member's entries with its notes, after confirming
	 *
	 * @return void
	 */
	protected function delete_entry()
	{
		$this->require_permission('entry_delete');

		$entry_id = $this->request->variable('entry_id', 0);
		$entry = $this->get_member_entry($entry_id);

		if (confirm_box(true))
		{
			$this->manager->delete_entry($entry_id);
			$this->manager->log_moderator('LOG_SEENOTES_ENTRY_DELETED', [$entry['reason_title'], $this->member['username']]);

			$this->success('SEENOTES_ENTRY_DELETED');
		}

		confirm_box(false, $this->language->lang('SEENOTES_DELETE_ENTRY_CONFIRM', $entry['reason_title']), build_hidden_fields([
			'u'			=> (int) $this->member['user_id'],
			'action'	=> 'delete_entry',
			'entry_id'	=> $entry_id,
		]));

		redirect($this->u_action_raw());
	}

	/**
	 * Add a note under one of the member's entries, or change a note
	 *
	 * @param bool $edit Change an existing note
	 * @param array $errors Filled with errors to show above the form
	 * @return string|null Text to put back in the form, null to use the default
	 */
	protected function save_note($edit, array &$errors)
	{
		$this->require_permission($edit ? 'note_edit' : 'manage');

		$note = null;
		if ($edit)
		{
			$note = $this->get_member_note($this->request->variable('note_id', 0));
		}

		if (!$this->request->is_set_post('submit_note'))
		{
			// Opening the form to edit a note
			return null;
		}

		if (!check_form_key(self::FORM_KEY))
		{
			$this->error('FORM_INVALID');
		}

		$text = $this->request->variable('message', '', true);

		if ($edit)
		{
			$entry = ['reason_title' => $note['reason_title']];
		}
		else
		{
			$entry = $this->get_member_entry($this->request->variable('entry_id', 0));
		}

		$parsed = $this->manager->parse_note($text);
		if ($parsed['errors'])
		{
			$errors = array_map([$this->language, 'lang'], $parsed['errors']);

			return $text;
		}

		if ($edit)
		{
			$this->manager->edit_note((int) $note['note_id'], $parsed);
			$this->manager->log_moderator('LOG_SEENOTES_NOTE_EDITED', [$entry['reason_title'], $this->member['username']]);

			$this->success('SEENOTES_NOTE_EDITED');
		}

		$this->manager->add_note((int) $entry['entry_id'], $parsed);
		$this->manager->log_moderator('LOG_SEENOTES_NOTE_ADDED', [$entry['reason_title'], $this->member['username']]);

		$this->success('SEENOTES_NOTE_ADDED');
	}

	/**
	 * Delete one of the member's notes, after confirming
	 *
	 * @return void
	 */
	protected function delete_note()
	{
		$this->require_permission('note_delete');

		$note_id = $this->request->variable('note_id', 0);
		$note = $this->get_member_note($note_id);

		if (confirm_box(true))
		{
			$this->manager->delete_note($note_id);
			$this->manager->log_moderator('LOG_SEENOTES_NOTE_DELETED', [$note['reason_title'], $this->member['username']]);

			$this->success('SEENOTES_NOTE_DELETED');
		}

		confirm_box(false, $this->language->lang('SEENOTES_DELETE_NOTE_CONFIRM'), build_hidden_fields([
			'u'			=> (int) $this->member['user_id'],
			'action'	=> 'delete_note',
			'note_id'	=> $note_id,
		]));

		redirect($this->u_action_raw());
	}

	/**
	 * Show the member's entries and notes, the forms, and the latest phpBB
	 * user notes
	 *
	 * @param array $errors Errors to show above the note form
	 * @param string|null $editor_text Text to put back in the note form
	 * @return void
	 */
	protected function display(array $errors, $editor_text)
	{
		$user_id = (int) $this->member['user_id'];
		$entries = $this->manager->get_entries($user_id);
		$notes = $this->manager->get_notes(array_keys($entries));

		$can_manage = $this->manager->can('manage');
		$can_edit = $this->manager->can('note_edit');
		$can_delete_note = $this->manager->can('note_delete');
		$can_delete_entry = $this->manager->can('entry_delete');

		$current_entry_id = 0;
		foreach ($entries as $entry_id => $entry)
		{
			$entry_notes = isset($notes[$entry_id]) ? $notes[$entry_id] : [];
			if ($entry['entry_current'])
			{
				$current_entry_id = $entry_id;
			}

			$this->template->assign_block_vars('seenotes_entries', [
				'ID'			=> $entry_id,
				'REASON'		=> $entry['reason_title'],
				'DESCRIPTION'	=> $entry['reason_description'],
				'COLOR'			=> $this->manager->clean_color($entry['reason_color']),
				'AUTHOR'		=> $this->username($entry['entry_author_id'], $entry['username'], $entry['user_colour'], $entry['entry_author_name']),
				'TIME'			=> $this->user->format_date($entry['entry_time']),
				'NOTE_COUNT'	=> $this->language->lang('SEENOTES_NOTE_COUNT', count($entry_notes)),
				'S_CURRENT'		=> (bool) $entry['entry_current'],
				'S_ACTIVE'		=> (bool) $entry['reason_active'],
				'U_ADD_NOTE'	=> $this->u_action . '&amp;entry_id=' . $entry_id . '#seenotes-editor',
				'U_DELETE'		=> $this->u_action . '&amp;action=delete_entry&amp;entry_id=' . $entry_id,
			]);

			foreach ($entry_notes as $note)
			{
				$this->template->assign_block_vars('seenotes_entries.notes', [
					'ID'		=> (int) $note['note_id'],
					'TEXT'		=> generate_text_for_display($note['note_text'], $note['note_bbcode_uid'], $note['note_bbcode_bitfield'], (int) $note['note_bbcode_options']),
					'AUTHOR'	=> $this->username($note['note_author_id'], $note['author_username'], $note['author_colour'], $note['note_author_name']),
					'TIME'		=> $this->user->format_date($note['note_time']),
					'EDITED'	=> $note['note_edit_time'] ? $this->language->lang(
						'SEENOTES_NOTE_EDITED_BY',
						$this->username($note['note_edit_user_id'], $note['editor_username'], $note['editor_colour'], $note['note_edit_user_name']),
						$this->user->format_date($note['note_edit_time'])
					) : '',
					'U_EDIT'	=> $this->u_action . '&amp;action=edit_note&amp;note_id=' . (int) $note['note_id'] . '#seenotes-editor',
					'U_DELETE'	=> $this->u_action . '&amp;action=delete_note&amp;note_id=' . (int) $note['note_id'],
				]);
			}
		}

		$this->assign_editor($entries, $current_entry_id, $editor_text, $can_manage, $can_edit);

		if ($can_manage)
		{
			foreach ($this->manager->get_reasons(true) as $reason_id => $reason)
			{
				$this->template->assign_block_vars('seenotes_reasons', [
					'ID'			=> $reason_id,
					'TITLE'			=> $reason['reason_title'],
					'DESCRIPTION'	=> $reason['reason_description'],
				]);
			}
		}

		$this->assign_user_notes($user_id);

		$this->template->assign_vars([
			'S_SEENOTES_PAGE'				=> true,
			'SEENOTES_MEMBER'				=> get_username_string('full', $user_id, $this->member['username'], $this->member['user_colour']),
			'SEENOTES_ERROR'				=> implode('<br>', $errors),
			'S_SEENOTES_CAN_MANAGE'			=> $can_manage,
			'S_SEENOTES_CAN_EDIT'			=> $can_edit,
			'S_SEENOTES_CAN_DELETE_NOTE'	=> $can_delete_note,
			'S_SEENOTES_CAN_DELETE_ENTRY'	=> $can_delete_entry,
			'S_SEENOTES_HAS_CURRENT'		=> $current_entry_id > 0,
			'U_SEENOTES_ACTION'				=> $this->u_action,
			'S_SEENOTES_HIDDEN_USER'		=> build_hidden_fields(['u' => $user_id]),
		]);
	}

	/**
	 * Fill the note form: a new note under a chosen entry, or a note being
	 * edited
	 *
	 * @param array $entries The member's entries
	 * @param int $current_entry_id
	 * @param string|null $editor_text Text sent back after an error
	 * @param bool $can_manage
	 * @param bool $can_edit
	 * @return void
	 */
	protected function assign_editor(array $entries, $current_entry_id, $editor_text, $can_manage, $can_edit)
	{
		$edit_note = null;
		if ($this->request->variable('action', '') === 'edit_note' && $can_edit)
		{
			$edit_note = $this->get_member_note($this->request->variable('note_id', 0));
		}

		if (!$entries || (!$edit_note && !$can_manage))
		{
			return;
		}

		if ($edit_note)
		{
			$text = $editor_text !== null ? $editor_text : generate_text_for_edit($edit_note['note_text'], $edit_note['note_bbcode_uid'], (int) $edit_note['note_bbcode_options'])['text'];
			$selected = (int) $edit_note['entry_id'];
		}
		else
		{
			$text = $editor_text !== null ? $editor_text : '';
			$selected = $this->request->variable('entry_id', 0);
			if (!isset($entries[$selected]))
			{
				$selected = $current_entry_id ?: (int) key($entries);
			}

			foreach ($entries as $entry_id => $entry)
			{
				$this->template->assign_block_vars('seenotes_editor_entries', [
					'ID'		=> $entry_id,
					'TITLE'		=> $this->language->lang('SEENOTES_ENTRY_OPTION', $entry['reason_title'], $this->user->format_date($entry['entry_time'], false, true)),
					'S_SELECTED'=> $entry_id === $selected,
				]);
			}
		}

		$settings = $this->manager->get_editor_settings();
		if ($settings['bbcode'])
		{
			if (!function_exists('display_custom_bbcodes'))
			{
				include($this->root_path . 'includes/functions_display.' . $this->php_ext);
			}
			display_custom_bbcodes();
		}

		$this->template->assign_vars([
			'S_SEENOTES_EDITOR'		=> true,
			'S_SEENOTES_EDITING'	=> $edit_note !== null,
			'SEENOTES_EDIT_NOTE_ID'	=> $edit_note ? (int) $edit_note['note_id'] : 0,
			'SEENOTES_EDITOR_TEXT'	=> $text,
			'S_BBCODE_ALLOWED'		=> $settings['bbcode'],
			'S_BBCODE_QUOTE'		=> $settings['quote'],
			'S_BBCODE_IMG'			=> $settings['img'],
			'S_BBCODE_FLASH'		=> $settings['flash'],
			'S_LINKS_ALLOWED'		=> $settings['urls'],
			'SEENOTES_FORMAT_STATUS'=> $this->language->lang('SEENOTES_FORMAT_STATUS',
				$this->language->lang($settings['bbcode'] ? 'SEENOTES_ON' : 'SEENOTES_OFF'),
				$this->language->lang($settings['smilies'] ? 'SEENOTES_ON' : 'SEENOTES_OFF'),
				$this->language->lang($settings['urls'] ? 'SEENOTES_ON' : 'SEENOTES_OFF')
			),
		]);
	}

	/**
	 * The member's latest phpBB user notes, read-only
	 *
	 * @param int $user_id
	 * @return void
	 */
	protected function assign_user_notes($user_id)
	{
		$rows = $this->log->get_logs('user', true, self::USER_NOTES_LIMIT, 0, 0, 0, $user_id);
		$total = (int) $this->log->get_log_count();

		foreach ($rows as $row)
		{
			$this->template->assign_block_vars('seenotes_usernotes', [
				'REPORT_BY'	=> $row['username_full'],
				'REPORT_AT'	=> $this->user->format_date($row['time']),
				'ACTION'	=> $row['action'],
			]);
		}

		if ($this->in_acp)
		{
			$u_user_notes = append_sid("{$this->root_path}adm/index.{$this->php_ext}", 'i=users&amp;mode=feedback&amp;u=' . $user_id);
		}
		else
		{
			$u_user_notes = append_sid("{$this->root_path}mcp.{$this->php_ext}", 'i=notes&amp;mode=user_notes&amp;u=' . $user_id);
		}

		$this->template->assign_vars([
			'SEENOTES_USERNOTES_TOTAL'	=> $this->language->lang('SEENOTES_USERNOTES_TOTAL', $total),
			'U_SEENOTES_USERNOTES'		=> $u_user_notes,
		]);
	}

	/**
	 * A username that links to the profile while the member exists, or the
	 * saved name as plain text after they were deleted
	 *
	 * @param int $user_id
	 * @param string|null $username Current username, null when deleted
	 * @param string|null $colour
	 * @param string $saved_name Name saved with the record (escaped)
	 * @return string
	 */
	protected function username($user_id, $username, $colour, $saved_name)
	{
		if ($username === null || $username === '')
		{
			return $saved_name;
		}

		return get_username_string('full', (int) $user_id, $username, (string) $colour);
	}

	/**
	 * An entry of this member, or an error
	 *
	 * @param int $entry_id
	 * @return array
	 */
	protected function get_member_entry($entry_id)
	{
		$entry = $entry_id ? $this->manager->get_entry($entry_id) : null;
		if (!$entry || (int) $entry['user_id'] !== (int) $this->member['user_id'])
		{
			$this->error('SEENOTES_ENTRY_NOT_FOUND');
		}

		return $entry;
	}

	/**
	 * A note under one of this member's entries, or an error
	 *
	 * @param int $note_id
	 * @return array
	 */
	protected function get_member_note($note_id)
	{
		$note = $note_id ? $this->manager->get_note($note_id) : null;
		if (!$note || (int) $note['user_id'] !== (int) $this->member['user_id'])
		{
			$this->error('SEENOTES_NOTE_NOT_FOUND');
		}

		return $note;
	}

	/**
	 * Stop unless the current user has the permission
	 *
	 * @param string $action
	 * @return void
	 */
	protected function require_permission($action)
	{
		if (!$this->manager->can($action))
		{
			$this->error('NOT_AUTHORISED');
		}
	}

	/**
	 * Stop unless the request is a valid form post and the current user has
	 * the permission
	 *
	 * @param string $action
	 * @return void
	 */
	protected function require_post_permission($action)
	{
		$this->require_permission($action);

		if (!$this->request->is_set_post('action') || !check_form_key(self::FORM_KEY))
		{
			$this->error('FORM_INVALID');
		}
	}

	/**
	 * The page URL without HTML-escaped ampersands, for redirects
	 *
	 * @return string
	 */
	protected function u_action_raw()
	{
		return str_replace('&amp;', '&', $this->u_action);
	}

	/**
	 * Show a success message with a link back to the page
	 *
	 * @param string $message Language key
	 * @return void
	 */
	protected function success($message)
	{
		if ($this->in_acp)
		{
			trigger_error($this->language->lang($message) . adm_back_link($this->u_action));
		}

		meta_refresh(3, $this->u_action);
		trigger_error($this->language->lang($message) . '<br><br>' . $this->language->lang('RETURN_PAGE', '<a href="' . $this->u_action . '">', '</a>'));
	}

	/**
	 * Show an error with a link back to the page
	 *
	 * @param string $message Language key
	 * @return void
	 */
	protected function error($message)
	{
		if ($this->in_acp)
		{
			trigger_error($this->language->lang($message) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		trigger_error($this->language->lang($message) . '<br><br>' . $this->language->lang('RETURN_PAGE', '<a href="' . $this->u_action . '">', '</a>'), E_USER_WARNING);
	}
}
