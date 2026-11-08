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

$lang = array_merge($lang, [
	'ACP_SEENOTES_REASONS_EXPLAIN'	=> 'Moderators give members one of these reasons and keep notes under it. A switched-off reason can no longer be given, but members who have it keep it and its notes.',
	'ACP_SEENOTES_SETTINGS_EXPLAIN'	=> 'The label shown before a member’s reason, and how reasons are managed.',

	'SEENOTES_ADD_REASON'			=> 'Add reason',
	'SEENOTES_EDIT_REASON'			=> 'Edit reason',
	'SEENOTES_NO_REASONS_YET'		=> 'There are no reasons yet.',
	'SEENOTES_REASON_TITLE'			=> 'Name',
	'SEENOTES_REASON_TITLE_EXPLAIN'	=> 'Shown after the label, for example “Moderation queue”.',
	'SEENOTES_REASON_DESCRIPTION'	=> 'Description',
	'SEENOTES_REASON_DESCRIPTION_EXPLAIN'	=> 'Shown to staff when they hover over the reason.',
	'SEENOTES_REASON_DESCRIPTION_REQUIRED'	=> 'Required on this board.',
	'SEENOTES_REASON_COLOR'			=> 'Color',
	'SEENOTES_REASON_COLOR_EXPLAIN'	=> 'Used for the line next to the member’s posts.',
	'SEENOTES_STATUS'				=> 'Status',
	'SEENOTES_STATUS_ON'			=> 'On',
	'SEENOTES_STATUS_OFF'			=> 'Off',
	'SEENOTES_SWITCH_ON'			=> 'Switch on',
	'SEENOTES_SWITCH_OFF'			=> 'Switch off',
	'SEENOTES_MOVE_UP'				=> 'Move up',
	'SEENOTES_MOVE_DOWN'			=> 'Move down',
	'SEENOTES_DELETE_REASON'		=> 'Delete with all entries',

	'SEENOTES_TITLE_EMPTY'			=> 'Enter a name for the reason.',
	'SEENOTES_TEXT_TOO_LONG'		=> 'The name and description can each be at most %d characters long.',
	'SEENOTES_DESCRIPTION_REQUIRED'	=> 'Enter a description. This board requires one for each reason.',
	'SEENOTES_COLOR_INVALID'		=> 'The color must be a hex color such as #AA0000.',
	'SEENOTES_REASON_ADDED'			=> 'The reason has been added.',
	'SEENOTES_REASON_UPDATED'		=> 'The reason has been changed.',
	'SEENOTES_REASON_NOT_FOUND'		=> 'That reason does not exist.',

	'SEENOTES_HARD_DELETE_OFF'		=> 'Deleting reasons is switched off in the See Notes settings.',
	'SEENOTES_DELETE_SUMMARY'		=> 'Delete reason “%1$s”? This will permanently remove it from %2$s, along with %3$s and %4$s.',
	'SEENOTES_COUNT_USERS'			=> [
		1	=> '%d member',
		2	=> '%d members',
	],
	'SEENOTES_COUNT_ENTRIES'			=> [
		1	=> '%d entry',
		2	=> '%d entries',
	],
	'SEENOTES_COUNT_NOTES'			=> [
		1	=> '%d note',
		2	=> '%d notes',
	],
	'SEENOTES_DELETE_CONTINUE'		=> 'Continue',
	'SEENOTES_DELETE_TYPE_NAME'		=> 'To confirm, type the reason’s name: %s',
	'SEENOTES_DELETE_FINAL'			=> 'Delete this reason and all its entries',
	'SEENOTES_DELETE_NAME_MISMATCH'	=> 'The name you typed does not match the reason’s name. Nothing was deleted.',
	'SEENOTES_REASON_DELETED'		=> 'The reason and all its entries and notes have been deleted.',

	'SEENOTES_LABEL'				=> 'Label',
	'SEENOTES_LABEL_EXPLAIN'		=> 'Shown before the reason, for example “SEE NOTES: Moderation queue”. One name for the whole board.',
	'SEENOTES_LABEL_INVALID'		=> 'Enter a label of at most %d characters.',
	'SEENOTES_REQUIRE_DESCRIPTION'	=> 'Require a description for each reason',
	'SEENOTES_REQUIRE_DESCRIPTION_EXPLAIN'	=> 'Existing reasons without one must get one the next time they are edited.',
	'SEENOTES_ALLOW_HARD_DELETE'	=> 'Allow deleting a reason with all its entries',
	'SEENOTES_ALLOW_HARD_DELETE_EXPLAIN'	=> 'Adds a delete button to each reason. Deleting permanently removes the reason from every member, with all their entries and notes under it, and asks you to type the reason’s name first.',
]);
