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
	'ACP_SEENOTES'			=> 'See Notes',
	'ACP_SEENOTES_REASONS'	=> 'Reasons',
	'ACP_SEENOTES_SETTINGS'	=> 'Settings',
	'ACP_USER_SEENOTES'		=> 'Moderator notes',
]);
