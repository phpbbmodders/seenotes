<?php
/**
 *
 * See Notes extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\seenotes\mcp;

/**
 * MCP module information
 */
class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\phpbbmodders\seenotes\mcp\main_module',
			'title'		=> 'MCP_SEENOTES',
			'modes'		=> [
				'notes'	=> [
					'title'	=> 'MCP_SEENOTES_NOTES',
					'auth'	=> 'ext_phpbbmodders/seenotes && (aclf_m_seenotes_view || acl_a_seenotes_view)',
					'cat'	=> ['MCP_SEENOTES'],
				],
			],
		];
	}
}
