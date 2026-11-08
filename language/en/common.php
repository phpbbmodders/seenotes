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

// Strings loaded on every page: the reason line and log entries
$lang = array_merge($lang, [
	'SEENOTES_NOTE_COUNT'	=> [
		0	=> 'No notes',
		1	=> '%d note',
		2	=> '%d notes',
	],
	'SEENOTES_OPEN_NOTES'	=> 'Read the moderator notes about this member',

	'LOG_SEENOTES_ENTRY_ADDED'		=> '<strong>Gave a member a See Notes reason</strong><br>» %1$s, to %2$s',
	'LOG_SEENOTES_CURRENT_SET'		=> '<strong>Changed a member’s current See Notes reason</strong><br>» %1$s, for %2$s',
	'LOG_SEENOTES_CURRENT_CLEARED'	=> '<strong>Cleared a member’s current See Notes reason</strong><br>» %s',
	'LOG_SEENOTES_ENTRY_DELETED'	=> '<strong>Deleted a member’s See Notes reason with its notes</strong><br>» %1$s, for %2$s',
	'LOG_SEENOTES_NOTE_ADDED'		=> '<strong>Added a See Notes note</strong><br>» %1$s, for %2$s',
	'LOG_SEENOTES_NOTE_EDITED'		=> '<strong>Edited a See Notes note</strong><br>» %1$s, for %2$s',
	'LOG_SEENOTES_NOTE_DELETED'		=> '<strong>Deleted a See Notes note</strong><br>» %1$s, for %2$s',

	'LOG_SEENOTES_REASON_ADDED'		=> '<strong>Added a See Notes reason</strong><br>» %s',
	'LOG_SEENOTES_REASON_EDITED'	=> '<strong>Changed a See Notes reason</strong><br>» %s',
	'LOG_SEENOTES_REASON_ENABLED'	=> '<strong>Switched on a See Notes reason</strong><br>» %s',
	'LOG_SEENOTES_REASON_DISABLED'	=> '<strong>Switched off a See Notes reason</strong><br>» %s',
	'LOG_SEENOTES_REASON_DELETED'	=> '<strong>Deleted a See Notes reason with all its entries</strong><br>» %1$s (members: %2$d, entries: %3$d, notes: %4$d)',
	'LOG_SEENOTES_SETTINGS'			=> '<strong>Changed the See Notes settings</strong>',
]);
