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
	'ACL_CAT_SEENOTES'	=> 'Moderator notes',

	'ACL_M_SEENOTES_VIEW'			=> 'Can see members’ moderator notes reasons and notes',
	'ACL_M_SEENOTES_MANAGE'			=> 'Can give members a reason, set the current reason and add notes',
	'ACL_M_SEENOTES_NOTE_DELETE'	=> 'Can delete moderator notes',
	'ACL_M_SEENOTES_NOTE_EDIT'		=> 'Can edit moderator notes',
	'ACL_M_SEENOTES_ENTRY_DELETE'	=> 'Can delete a member’s reason with its notes',

	'ACL_A_SEENOTES_VIEW'			=> 'Can see members’ moderator notes reasons and notes',
	'ACL_A_SEENOTES_MANAGE'			=> 'Can give members a reason, set the current reason and add notes',
	'ACL_A_SEENOTES_NOTE_DELETE'	=> 'Can delete moderator notes',
	'ACL_A_SEENOTES_NOTE_EDIT'		=> 'Can edit moderator notes',
	'ACL_A_SEENOTES_ENTRY_DELETE'	=> 'Can delete a member’s reason with its notes',
	'ACL_A_SEENOTES_SETTINGS'		=> 'Can manage See Notes reasons and settings',
]);
