<?php
/**
 *
 * See Notes extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

/**
 * DO NOT CHANGE
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

// MCP Moderator notes tab and ACP Manage users page
$lang = array_merge($lang, [
	'SEENOTES_FIND_EXPLAIN'		=> 'Enter a member’s username to see the reasons and notes staff have recorded about them.',
	'SEENOTES_ENTRIES'			=> 'Reasons',
	'SEENOTES_NO_ENTRIES'		=> 'This member has no reasons.',
	'SEENOTES_NO_NOTES'			=> 'No notes under this reason yet.',
	'SEENOTES_CURRENT'			=> 'Current',
	'SEENOTES_INACTIVE'			=> 'Switched off',
	'SEENOTES_INACTIVE_EXPLAIN'	=> 'This reason is switched off. It can no longer be given, but this entry and its notes are kept.',
	'SEENOTES_ADDED_BY'			=> 'Added by %1$s on %2$s',
	'SEENOTES_NOTE_BY'			=> 'By %1$s on %2$s',
	'SEENOTES_NOTE_EDITED_BY'	=> 'Last edited by %1$s on %2$s',
	'SEENOTES_MAKE_CURRENT'		=> 'Make current',
	'SEENOTES_CLEAR_CURRENT'	=> 'Show no reason',
	'SEENOTES_CLEAR_CURRENT_EXPLAIN'	=> 'Keeps all reasons and notes, but shows nothing on the member’s profile and posts.',
	'SEENOTES_ADD_NOTE'			=> 'Add a note',
	'SEENOTES_EDIT_NOTE'		=> 'Edit note',
	'SEENOTES_DELETE_NOTE'		=> 'Delete note',
	'SEENOTES_DELETE_ENTRY'		=> 'Delete reason and notes',
	'SEENOTES_DELETE_ENTRY_CONFIRM'	=> 'Delete the reason “%s” from this member, with all its notes?',
	'SEENOTES_DELETE_NOTE_CONFIRM'	=> 'Delete this note?',

	'SEENOTES_ADD_ENTRY'			=> 'Give a reason',
	'SEENOTES_ADD_ENTRY_EXPLAIN'	=> 'Reasons are set up by administrators. Add notes under the reason once it is given.',
	'SEENOTES_REASON'				=> 'Reason',
	'SEENOTES_SELECT_REASON'		=> 'Select a reason',
	'SEENOTES_MAKE_IT_CURRENT'		=> 'Make it the current reason',
	'SEENOTES_NO_REASONS'			=> 'There are no reasons to give. Administrators can add them in ACP > Extensions > See Notes > Reasons.',

	'SEENOTES_NOTE_FOR'			=> 'Under reason',
	'SEENOTES_NOTE_TEXT'		=> 'Note',
	'SEENOTES_ENTRY_OPTION'		=> '%1$s (%2$s)',
	'SEENOTES_FORMAT_STATUS'	=> 'BBCode is %1$s, smilies are %2$s, links are %3$s.',
	'SEENOTES_ON'				=> 'on',
	'SEENOTES_OFF'				=> 'off',
	'SEENOTES_SAVE_NOTE'		=> 'Save note',

	'SEENOTES_USERNOTES'			=> 'phpBB user notes',
	'SEENOTES_USERNOTES_EXPLAIN'	=> 'The latest user notes, including warnings and bans phpBB records itself. They are not changed here.',
	'SEENOTES_USERNOTES_TOTAL'		=> [
		0	=> 'No user notes',
		1	=> '%d user note',
		2	=> '%d user notes',
	],
	'SEENOTES_USERNOTES_ALL'		=> 'See all user notes',
	'SEENOTES_NO_USERNOTES'			=> 'This member has no user notes.',

	'SEENOTES_ENTRY_ADDED'		=> 'The reason has been given.',
	'SEENOTES_CURRENT_SET'		=> 'The current reason has been changed.',
	'SEENOTES_CURRENT_CLEARED'	=> 'The member now has no current reason.',
	'SEENOTES_ENTRY_DELETED'	=> 'The reason and its notes have been deleted.',
	'SEENOTES_NOTE_ADDED'		=> 'The note has been added.',
	'SEENOTES_NOTE_EDITED'		=> 'The note has been changed.',
	'SEENOTES_NOTE_DELETED'		=> 'The note has been deleted.',

	'SEENOTES_REASON_NOT_SELECTED'	=> 'Select a reason that is switched on.',
	'SEENOTES_ENTRY_NOT_FOUND'		=> 'That reason entry does not exist for this member.',
	'SEENOTES_NOTE_NOT_FOUND'		=> 'That note does not exist for this member.',
	'SEENOTES_NOTE_EMPTY'			=> 'The note is empty.',
]);
