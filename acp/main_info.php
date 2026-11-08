<?php
/**
 *
 * See Notes extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\seenotes\acp;

/**
 * ACP module information
 */
class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\phpbbmodders\seenotes\acp\main_module',
			'title'		=> 'ACP_SEENOTES',
			'modes'		=> [
				'reasons'	=> [
					'title'	=> 'ACP_SEENOTES_REASONS',
					'auth'	=> 'ext_phpbbmodders/seenotes && acl_a_seenotes_settings',
					'cat'	=> ['ACP_SEENOTES'],
				],
				'settings'	=> [
					'title'	=> 'ACP_SEENOTES_SETTINGS',
					'auth'	=> 'ext_phpbbmodders/seenotes && acl_a_seenotes_settings',
					'cat'	=> ['ACP_SEENOTES'],
				],
			],
		];
	}
}
