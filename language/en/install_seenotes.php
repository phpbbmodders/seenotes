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
	'SEENOTES_NOT_ENABLEABLE'	=> 'See Notes could not be enabled. It needs phpBB 3.3.19 or later and PHP 8.3.36 or later.',

	// Starter reasons, added once when the extension is first enabled. They
	// are stored in the board's default language and can then be edited in
	// the ACP like any other reason.
	'SEENOTES_DEFAULT_QUEUE'				=> 'Moderation queue',
	'SEENOTES_DEFAULT_QUEUE_EXPLAIN'		=> 'Posts must be approved before they appear',
	'SEENOTES_DEFAULT_SPAM'					=> 'Spam watch',
	'SEENOTES_DEFAULT_SPAM_EXPLAIN'			=> 'Check links and signatures in new posts',
	'SEENOTES_DEFAULT_WAREZ'				=> 'Warez',
	'SEENOTES_DEFAULT_WAREZ_EXPLAIN'		=> 'Has posted or asked for pirated software, cracks or serials; check links and attachments',
	'SEENOTES_DEFAULT_CONDUCT'				=> 'Conduct',
	'SEENOTES_DEFAULT_CONDUCT_EXPLAIN'		=> 'Earlier problems with how they treat other members',
	'SEENOTES_DEFAULT_RETURNING'			=> 'Possible returning member',
	'SEENOTES_DEFAULT_RETURNING_EXPLAIN'	=> 'May be a banned member back under a new name; compare IPs and emails',
	'SEENOTES_DEFAULT_CONTACT'				=> 'Staff in contact',
	'SEENOTES_DEFAULT_CONTACT_EXPLAIN'		=> 'A staff member is talking to them by PM; check the notes before stepping in',
	'SEENOTES_DEFAULT_GENERAL'				=> 'General',
	'SEENOTES_DEFAULT_GENERAL_EXPLAIN'		=> 'Notes that do not fit another reason',
]);
