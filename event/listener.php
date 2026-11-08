<?php
/**
 *
 * See Notes extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\seenotes\event;

use phpbb\auth\auth;
use phpbb\event\data;
use phpbb\language\language;
use phpbb\template\template;
use phpbb\user;
use phpbbmodders\seenotes\manager;
use phpbbmodders\seenotes\notes_page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * See Notes event listener
 *
 * Adds the permissions, shows a member's current reason to staff on their
 * profile, next to their posts and on the MCP pages for a post, adds the ACP
 * Manage users page, and keeps the records in step with deleted and renamed
 * members.
 */
class listener implements EventSubscriberInterface
{
	/** MCP module name */
	const MCP_MODULE = '-phpbbmodders-seenotes-mcp-main_module';

	/** @var auth */
	protected $auth;

	/** @var language */
	protected $language;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var manager */
	protected $manager;

	/** @var notes_page */
	protected $notes_page;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * @param auth $auth
	 * @param language $language
	 * @param template $template
	 * @param user $user
	 * @param manager $manager
	 * @param notes_page $notes_page
	 * @param string $root_path
	 * @param string $php_ext
	 */
	public function __construct(auth $auth, language $language, template $template, user $user, manager $manager, notes_page $notes_page, $root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->language = $language;
		$this->template = $template;
		$this->user = $user;
		$this->manager = $manager;
		$this->notes_page = $notes_page;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'							=> 'load_language',
			'core.permissions'							=> 'add_permissions',
			'core.page_header'							=> 'load_stylesheet',
			'core.memberlist_view_profile'				=> 'show_on_profile',
			'core.viewtopic_modify_post_data'			=> 'load_viewtopic_posters',
			'core.viewtopic_modify_post_row'			=> 'show_on_post',
			'core.mcp_post_template_data'				=> 'show_on_mcp_post',
			'core.mcp_report_template_data'				=> 'show_on_mcp_post',
			'core.mcp_queue_approve_details_template'	=> 'show_on_mcp_post',
			'core.mcp_topic_modify_post_data'			=> 'load_mcp_topic_posters',
			'core.mcp_topic_review_modify_row'			=> 'show_on_mcp_topic_post',
			'core.acp_users_mode_add'					=> 'acp_users_page',
			'core.delete_user_after'					=> 'delete_users',
			'core.update_username'						=> 'update_username',
		];
	}

	/**
	 * Load the strings used across the board: log entries and the reason line
	 *
	 * @param data $event
	 * @return void
	 */
	public function load_language(data $event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name'	=> 'phpbbmodders/seenotes',
			'lang_set'	=> 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Add the permissions to ACP > Permissions, in their own category
	 *
	 * @param data $event
	 * @return void
	 */
	public function add_permissions(data $event)
	{
		$this->language->add_lang('permissions_seenotes', 'phpbbmodders/seenotes');

		$categories = $event['categories'];
		$categories['seenotes'] = 'ACL_CAT_SEENOTES';
		$event['categories'] = $categories;

		$permissions = $event['permissions'];
		foreach (manager::ACTIONS as $action)
		{
			$permissions['m_seenotes_' . $action] = ['lang' => 'ACL_M_SEENOTES_' . strtoupper($action), 'cat' => 'seenotes'];
			$permissions['a_seenotes_' . $action] = ['lang' => 'ACL_A_SEENOTES_' . strtoupper($action), 'cat' => 'seenotes'];
		}
		$permissions['a_seenotes_settings'] = ['lang' => 'ACL_A_SEENOTES_SETTINGS', 'cat' => 'seenotes'];
		$event['permissions'] = $permissions;
	}

	/**
	 * Load the stylesheet for staff who may see reasons, and tell the
	 * templates which phpBB branch draws the post buttons
	 *
	 * @return void
	 */
	public function load_stylesheet()
	{
		$this->template->assign_vars([
			'S_SEENOTES_CSS'	=> $this->manager->can('view'),
			// phpBB 4.0 draws post button icons with its Icon() function
			'S_SEENOTES_PHPBB4'	=> phpbb_version_compare(PHPBB_VERSION, '4.0.0-a1', '>='),
		]);
	}

	/**
	 * Show the member's current reason with the profile details, and the
	 * link to their notes with the user statistics
	 *
	 * @param data $event
	 * @return void
	 */
	public function show_on_profile(data $event)
	{
		$user_id = (int) $event['member']['user_id'];
		$this->template->assign_vars(array_merge($this->get_display_vars($user_id), $this->get_button_vars($user_id)));
	}

	/**
	 * Read the current reasons of everyone who posted on a topic page in one
	 * query
	 *
	 * @param data $event
	 * @return void
	 */
	public function load_viewtopic_posters(data $event)
	{
		if ($this->manager->can('view') && !empty($event['user_cache']))
		{
			$this->manager->get_current_for_users(array_keys($event['user_cache']));
		}
	}

	/**
	 * Show the poster's current reason in their mini-profile, and the notes
	 * button with the post buttons
	 *
	 * @param data $event
	 * @return void
	 */
	public function show_on_post(data $event)
	{
		$poster_id = (int) $event['poster_id'];
		$vars = array_merge($this->get_display_vars($poster_id), $this->get_button_vars($poster_id));
		if ($vars)
		{
			$event['post_row'] = array_merge($event['post_row'], $vars);
		}
	}

	/**
	 * Show the poster's current reason above the post on the MCP post,
	 * report and approval queue details pages
	 *
	 * @param data $event
	 * @return void
	 */
	public function show_on_mcp_post(data $event)
	{
		if (!empty($event['post_info']['poster_id']))
		{
			$this->template->assign_vars($this->get_display_vars((int) $event['post_info']['poster_id']));
		}
	}

	/**
	 * Read the current reasons of everyone who posted on an MCP topic page in
	 * one query
	 *
	 * @param data $event
	 * @return void
	 */
	public function load_mcp_topic_posters(data $event)
	{
		if ($this->manager->can('view') && !empty($event['rowset']))
		{
			$this->manager->get_current_for_users(array_column($event['rowset'], 'poster_id'));
		}
	}

	/**
	 * Show the poster's current reason after their name in the MCP topic view
	 *
	 * @param data $event
	 * @return void
	 */
	public function show_on_mcp_topic_post(data $event)
	{
		if (empty($event['row']['poster_id']))
		{
			return;
		}

		$vars = $this->get_display_vars((int) $event['row']['poster_id']);
		if ($vars)
		{
			$event['post_row'] = array_merge($event['post_row'], $vars);
		}
	}

	/**
	 * Show the Moderator notes page in ACP > Manage users
	 *
	 * @param data $event
	 * @return void
	 */
	public function acp_users_page(data $event)
	{
		if ($event['mode'] !== 'seenotes')
		{
			return;
		}

		$this->notes_page->handle($event['user_row'], $event['u_action'] . '&amp;u=' . (int) $event['user_id'], true);
	}

	/**
	 * Delete the entries and notes about deleted members
	 *
	 * @param data $event
	 * @return void
	 */
	public function delete_users(data $event)
	{
		$this->manager->delete_users($event['user_ids']);
	}

	/**
	 * Keep saved author names up to date when a member is renamed
	 *
	 * @param data $event
	 * @return void
	 */
	public function update_username(data $event)
	{
		$this->manager->update_username($event['old_name'], $event['new_name']);
	}

	/**
	 * Template variables for a member's current reason, for viewers who may
	 * see it
	 *
	 * @param int $user_id
	 * @return array Empty when there is nothing to show
	 */
	protected function get_display_vars($user_id)
	{
		if (!$this->can_view_member($user_id))
		{
			return [];
		}

		$current = $this->manager->get_current_for_users([$user_id]);
		if (!isset($current[$user_id]))
		{
			return [];
		}

		$entry = $current[$user_id];

		return [
			'S_SEENOTES'			=> true,
			'SEENOTES_LABEL'		=> $this->manager->get_label(),
			'SEENOTES_REASON'		=> $entry['reason_title'],
			'SEENOTES_DESCRIPTION'	=> $entry['reason_description'],
			'SEENOTES_COLOR'		=> $this->manager->clean_color($entry['reason_color']),
			'SEENOTES_COUNT'		=> (int) $entry['note_count'],
			'SEENOTES_COUNT_TEXT'	=> $this->language->lang('SEENOTES_NOTE_COUNT', (int) $entry['note_count']),
			'U_SEENOTES'			=> $this->get_notes_url($user_id),
		];
	}

	/**
	 * Template variables for the notes button next to posts and the link on
	 * the profile, shown to staff whether or not the member has a reason.
	 * Without a current reason, staff who may give one land on that form.
	 *
	 * @param int $user_id
	 * @return array Empty when the viewer may not see notes or reach them
	 */
	protected function get_button_vars($user_id)
	{
		if (!$this->can_view_member($user_id))
		{
			return [];
		}

		$u_notes = $this->get_notes_url($user_id);
		if ($u_notes === '')
		{
			return [];
		}

		$current = $this->manager->get_current_for_users([$user_id]);
		if (!isset($current[$user_id]) && $this->manager->can('manage'))
		{
			$u_notes .= '#seenotes-add-entry';
		}

		return [
			'U_SEENOTES_NOTES'	=> $u_notes,
		];
	}

	/**
	 * Whether the viewer may see See Notes for this member
	 *
	 * @param int $user_id
	 * @return bool
	 */
	protected function can_view_member($user_id)
	{
		return $user_id && $user_id != ANONYMOUS && $this->manager->can('view');
	}

	/**
	 * Where the viewer can read the member's notes: the MCP for moderators,
	 * ACP > Manage users for administrators without moderator access
	 *
	 * @param int $user_id
	 * @return string Empty when the viewer can reach neither
	 */
	protected function get_notes_url($user_id)
	{
		if ($this->auth->acl_getf_global('m_'))
		{
			return append_sid("{$this->root_path}mcp.{$this->php_ext}", 'i=' . self::MCP_MODULE . '&amp;mode=notes&amp;u=' . $user_id);
		}

		if ($this->auth->acl_get('a_') && $this->auth->acl_get('a_user') && $this->auth->acl_get('a_seenotes_view'))
		{
			return append_sid("{$this->root_path}adm/index.{$this->php_ext}", 'i=users&amp;mode=seenotes&amp;u=' . $user_id, true, $this->user->session_id);
		}

		return '';
	}
}
