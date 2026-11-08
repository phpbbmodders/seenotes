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
 * MCP Moderator notes tab: a "find a member" form, then the member's entries
 * and notes
 */
class main_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	/**
	 * @param string $id	Module ID
	 * @param string $mode	Module mode
	 */
	public function main($id, $mode)
	{
		global $db, $language, $phpbb_container, $phpbb_root_path, $phpEx, $request, $template;

		$language->add_lang('seenotes', 'phpbbmodders/seenotes');
		$this->tpl_name = '@phpbbmodders_seenotes/mcp_seenotes';
		$this->page_title = 'MCP_SEENOTES_NOTES';

		$user_id = $request->variable('u', 0);
		$username = $request->variable('username', '', true);

		if (!$user_id && $username === '')
		{
			$template->assign_vars([
				'S_SEENOTES_FRONT'	=> true,
				'U_SEENOTES_FIND'	=> $this->u_action,
				'U_FIND_USERNAME'	=> append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=searchuser&amp;form=mcp&amp;field=username&amp;select_single=true'),
			]);

			return;
		}

		$where = $user_id ? 'user_id = ' . (int) $user_id : "username_clean = '" . $db->sql_escape(utf8_clean_string($username)) . "'";
		$sql = 'SELECT user_id, username, user_colour
			FROM ' . USERS_TABLE . '
			WHERE ' . $where . '
				AND user_id <> ' . ANONYMOUS;
		$result = $db->sql_query($sql);
		$member = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$member)
		{
			trigger_error($language->lang('NO_USER') . '<br><br>' . $language->lang('RETURN_PAGE', '<a href="' . $this->u_action . '">', '</a>'), E_USER_WARNING);
		}

		/** @var \phpbbmodders\seenotes\notes_page $page */
		$page = $phpbb_container->get('phpbbmodders.seenotes.notes_page');
		$page->handle($member, $this->u_action . '&amp;u=' . (int) $member['user_id'], false);
	}
}
