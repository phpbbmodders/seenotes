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
 * ACP pages: the list of reasons, and the settings
 */
class main_module
{
	/** Form key name */
	const FORM_KEY = 'phpbbmodders_seenotes_acp';

	/** Longest label, in characters */
	const LABEL_MAX_LENGTH = 255;

	public $u_action;
	public $tpl_name;
	public $page_title;

	/** @var \phpbbmodders\seenotes\manager */
	protected $manager;

	/**
	 * @param string $id	Module ID
	 * @param string $mode	Module mode
	 */
	public function main($id, $mode)
	{
		global $language, $phpbb_container;

		$language->add_lang('acp_seenotes', 'phpbbmodders/seenotes');
		$this->manager = $phpbb_container->get('phpbbmodders.seenotes.manager');

		add_form_key(self::FORM_KEY);

		if ($mode === 'settings')
		{
			$this->settings();
			return;
		}

		$this->reasons();
	}

	/**
	 * The label and the settings
	 *
	 * @return void
	 */
	protected function settings()
	{
		global $config, $language, $request, $template;

		$this->tpl_name = '@phpbbmodders_seenotes/acp_seenotes_settings';
		$this->page_title = 'ACP_SEENOTES_SETTINGS';

		if ($request->is_set_post('submit'))
		{
			$this->check_form();

			$label = trim($request->variable('seenotes_label', '', true));
			if ($label === '' || utf8_strlen($label) > self::LABEL_MAX_LENGTH)
			{
				$this->error($language->lang('SEENOTES_LABEL_INVALID', self::LABEL_MAX_LENGTH));
			}

			$config->set('seenotes_label', $label);
			$config->set('seenotes_require_description', (int) $request->variable('seenotes_require_description', false));
			$config->set('seenotes_allow_hard_delete', (int) $request->variable('seenotes_allow_hard_delete', false));

			$this->manager->log_admin('LOG_SEENOTES_SETTINGS');
			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'U_ACTION'						=> $this->u_action,
			'SEENOTES_LABEL'				=> $this->manager->get_label(),
			'SEENOTES_LABEL_MAX_LENGTH'		=> self::LABEL_MAX_LENGTH,
			'S_SEENOTES_REQUIRE_DESCRIPTION'=> !empty($config['seenotes_require_description']),
			'S_SEENOTES_ALLOW_HARD_DELETE'	=> !empty($config['seenotes_allow_hard_delete']),
		]);
	}

	/**
	 * The list of reasons, and the forms to add, change, move, switch and
	 * delete them
	 *
	 * @return void
	 */
	protected function reasons()
	{
		global $config, $language, $request, $template;

		$this->tpl_name = '@phpbbmodders_seenotes/acp_seenotes_reasons';
		$this->page_title = 'ACP_SEENOTES_REASONS';

		$action = $request->variable('action', '');
		$reason_id = $request->variable('id', 0);

		switch ($action)
		{
			case 'add':
			case 'edit':
				$this->edit_reason($action === 'edit' ? $reason_id : 0);
			return;

			case 'move_up':
			case 'move_down':
			case 'enable':
			case 'disable':
				$this->quick_action($action, $reason_id);
			break;

			case 'delete':
			case 'delete_confirm':
			case 'delete_final':
				$this->delete_reason($action, $reason_id);
			return;
		}

		$hash = generate_link_hash('seenotes_reasons');
		foreach ($this->manager->get_reasons() as $id => $reason)
		{
			$template->assign_block_vars('reasons', [
				'TITLE'			=> $reason['reason_title'],
				'DESCRIPTION'	=> $reason['reason_description'],
				'COLOR'			=> $this->manager->clean_color($reason['reason_color']),
				'S_ACTIVE'		=> (bool) $reason['reason_active'],
				'U_EDIT'		=> $this->u_action . '&amp;action=edit&amp;id=' . $id,
				'U_MOVE_UP'		=> $this->u_action . '&amp;action=move_up&amp;id=' . $id . '&amp;hash=' . $hash,
				'U_MOVE_DOWN'	=> $this->u_action . '&amp;action=move_down&amp;id=' . $id . '&amp;hash=' . $hash,
				'U_TOGGLE'		=> $this->u_action . '&amp;action=' . ($reason['reason_active'] ? 'disable' : 'enable') . '&amp;id=' . $id . '&amp;hash=' . $hash,
				'U_DELETE'		=> $this->u_action . '&amp;action=delete&amp;id=' . $id,
			]);
		}

		$template->assign_vars([
			'S_SEENOTES_LIST'			=> true,
			'S_SEENOTES_HARD_DELETE'	=> !empty($config['seenotes_allow_hard_delete']),
			'U_ACTION'					=> $this->u_action,
			'U_SEENOTES_ADD'			=> $this->u_action . '&amp;action=add',
		]);
	}

	/**
	 * The form to add or change a reason
	 *
	 * @param int $reason_id 0 to add a reason
	 * @return void
	 */
	protected function edit_reason($reason_id)
	{
		global $config, $language, $request, $template;

		$reason = [
			'reason_title'			=> '',
			'reason_description'	=> '',
			'reason_color'			=> \phpbbmodders\seenotes\manager::DEFAULT_COLOR,
		];

		if ($reason_id)
		{
			$reason = $this->get_reason_or_error($reason_id);
		}

		$errors = [];
		if ($request->is_set_post('submit'))
		{
			$this->check_form();

			$reason['reason_title'] = trim($request->variable('reason_title', '', true));
			$reason['reason_description'] = trim($request->variable('reason_description', '', true));
			$color = $request->variable('reason_color', '');

			if ($reason['reason_title'] === '')
			{
				$errors[] = $language->lang('SEENOTES_TITLE_EMPTY');
			}

			$max = \phpbbmodders\seenotes\manager::TEXT_MAX_LENGTH;
			if (utf8_strlen(htmlspecialchars_decode($reason['reason_title'], ENT_COMPAT)) > $max || utf8_strlen(htmlspecialchars_decode($reason['reason_description'], ENT_COMPAT)) > $max)
			{
				$errors[] = $language->lang('SEENOTES_TEXT_TOO_LONG', $max);
			}

			if ($reason['reason_description'] === '' && !empty($config['seenotes_require_description']))
			{
				$errors[] = $language->lang('SEENOTES_DESCRIPTION_REQUIRED');
			}

			if (!preg_match('/\A#?[0-9A-Fa-f]{6}\z/', $color))
			{
				$errors[] = $language->lang('SEENOTES_COLOR_INVALID');
			}
			$reason['reason_color'] = $this->manager->clean_color($color);

			if (!$errors)
			{
				$this->manager->save_reason($reason, $reason_id);
				$this->manager->log_admin($reason_id ? 'LOG_SEENOTES_REASON_EDITED' : 'LOG_SEENOTES_REASON_ADDED', [$reason['reason_title']]);

				trigger_error($language->lang($reason_id ? 'SEENOTES_REASON_UPDATED' : 'SEENOTES_REASON_ADDED') . adm_back_link($this->u_action));
			}
		}

		$template->assign_vars([
			'S_SEENOTES_EDIT'			=> true,
			'S_SEENOTES_ADDING'			=> !$reason_id,
			'S_SEENOTES_REQUIRE_DESCRIPTION'=> !empty($config['seenotes_require_description']),
			'SEENOTES_ERROR'			=> implode('<br>', $errors),
			'REASON_TITLE'				=> $reason['reason_title'],
			'REASON_DESCRIPTION'		=> $reason['reason_description'],
			'REASON_COLOR'				=> $this->manager->clean_color($reason['reason_color']),
			'REASON_MAX_LENGTH'			=> \phpbbmodders\seenotes\manager::TEXT_MAX_LENGTH,
			'U_ACTION'					=> $this->u_action . '&amp;action=' . ($reason_id ? 'edit&amp;id=' . $reason_id : 'add'),
			'U_BACK'					=> $this->u_action,
		]);
	}

	/**
	 * Move a reason, or switch it on or off, from a link in the list
	 *
	 * @param string $action move_up, move_down, enable or disable
	 * @param int $reason_id
	 * @return void
	 */
	protected function quick_action($action, $reason_id)
	{
		global $language, $request;

		if (!check_link_hash($request->variable('hash', ''), 'seenotes_reasons'))
		{
			$this->error($language->lang('FORM_INVALID'));
		}

		$reason = $this->get_reason_or_error($reason_id);

		switch ($action)
		{
			case 'move_up':
			case 'move_down':
				$this->manager->move_reason($reason_id, $action === 'move_up');
			break;

			case 'enable':
			case 'disable':
				$this->manager->set_reason_active($reason_id, $action === 'enable');
				$this->manager->log_admin($action === 'enable' ? 'LOG_SEENOTES_REASON_ENABLED' : 'LOG_SEENOTES_REASON_DISABLED', [$reason['reason_title']]);
			break;
		}
	}

	/**
	 * Delete a reason with all its entries: a screen with what will be
	 * removed, a screen where the reason's name is typed, then the deletion
	 *
	 * @param string $action delete, delete_confirm or delete_final
	 * @param int $reason_id
	 * @return void
	 */
	protected function delete_reason($action, $reason_id)
	{
		global $config, $language, $request, $template;

		if (empty($config['seenotes_allow_hard_delete']))
		{
			$this->error($language->lang('SEENOTES_HARD_DELETE_OFF'));
		}

		$reason = $this->get_reason_or_error($reason_id);
		$usage = $this->manager->count_reason_usage($reason_id);

		if ($request->is_set_post('cancel'))
		{
			redirect(str_replace('&amp;', '&', $this->u_action));
		}

		if ($action !== 'delete')
		{
			$this->check_form();
		}

		if ($action === 'delete_final')
		{
			$typed = trim($request->variable('confirm_name', '', true));
			if ($typed !== $reason['reason_title'])
			{
				$this->error($language->lang('SEENOTES_DELETE_NAME_MISMATCH'));
			}

			$this->manager->delete_reason($reason_id);
			$this->manager->log_admin('LOG_SEENOTES_REASON_DELETED', [$reason['reason_title'], $usage['users'], $usage['entries'], $usage['notes']]);

			trigger_error($language->lang('SEENOTES_REASON_DELETED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'S_SEENOTES_DELETE_STEP1'	=> $action === 'delete',
			'S_SEENOTES_DELETE_STEP2'	=> $action === 'delete_confirm',
			'REASON_TITLE'				=> $reason['reason_title'],
			'SEENOTES_DELETE_SUMMARY'	=> $language->lang('SEENOTES_DELETE_SUMMARY',
				$reason['reason_title'],
				$language->lang('SEENOTES_COUNT_USERS', $usage['users']),
				$language->lang('SEENOTES_COUNT_ENTRIES', $usage['entries']),
				$language->lang('SEENOTES_COUNT_NOTES', $usage['notes'])
			),
			'U_ACTION'					=> $this->u_action . '&amp;action=' . ($action === 'delete' ? 'delete_confirm' : 'delete_final') . '&amp;id=' . $reason_id,
		]);
	}

	/**
	 * A reason, or an error
	 *
	 * @param int $reason_id
	 * @return array
	 */
	protected function get_reason_or_error($reason_id)
	{
		global $language;

		$reason = $this->manager->get_reason($reason_id);
		if ($reason === null)
		{
			$this->error($language->lang('SEENOTES_REASON_NOT_FOUND'));
		}

		return $reason;
	}

	/**
	 * Stop when the form key is not valid
	 *
	 * @return void
	 */
	protected function check_form()
	{
		global $language;

		if (!check_form_key(self::FORM_KEY))
		{
			$this->error($language->lang('FORM_INVALID'));
		}
	}

	/**
	 * Show an error with a link back to the list
	 *
	 * @param string $message
	 * @return void
	 */
	protected function error($message)
	{
		trigger_error($message . adm_back_link($this->u_action), E_USER_WARNING);
	}
}
