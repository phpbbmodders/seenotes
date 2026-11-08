<?php
/**
 *
 * See Notes extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\seenotes\migrations\v10x;

/**
 * Adds the reasons, entries and notes tables, the settings, the permissions,
 * and the ACP, MCP and ACP Manage users pages
 */
class install extends \phpbb\db\migration\migration
{
	/**
	 * {@inheritdoc}
	 */
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'seenotes_reasons');
	}

	/**
	 * {@inheritdoc}
	 */
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_schema()
	{
		return [
			'add_tables' => [
				// The reasons administrators set up
				$this->table_prefix . 'seenotes_reasons' => [
					'COLUMNS' => [
						'reason_id'				=> ['UINT', null, 'auto_increment'],
						'reason_title'			=> ['VCHAR_UNI', ''],
						'reason_description'	=> ['VCHAR_UNI', ''],
						'reason_color'			=> ['VCHAR:6', 'AA0000'],
						'reason_order'			=> ['UINT', 0],
						'reason_active'			=> ['BOOL', 1],
					],
					'PRIMARY_KEY'	=> 'reason_id',
					'KEYS'			=> [
						'reason_order'	=> ['INDEX', 'reason_order'],
					],
				],
				// A reason given to a member; at most one per member is current
				$this->table_prefix . 'seenotes_entries' => [
					'COLUMNS' => [
						'entry_id'			=> ['UINT', null, 'auto_increment'],
						'user_id'			=> ['UINT', 0],
						'reason_id'			=> ['UINT', 0],
						'entry_time'		=> ['TIMESTAMP', 0],
						'entry_author_id'	=> ['UINT', 0],
						'entry_author_name'	=> ['VCHAR_UNI', ''],
						'entry_current'		=> ['BOOL', 0],
					],
					'PRIMARY_KEY'	=> 'entry_id',
					'KEYS'			=> [
						'user_id'		=> ['INDEX', 'user_id'],
						'reason_id'		=> ['INDEX', 'reason_id'],
						'user_current'	=> ['INDEX', ['user_id', 'entry_current']],
					],
				],
				// Notes under an entry, stored the way phpBB stores private messages
				$this->table_prefix . 'seenotes_notes' => [
					'COLUMNS' => [
						'note_id'				=> ['UINT', null, 'auto_increment'],
						'entry_id'				=> ['UINT', 0],
						'note_author_id'		=> ['UINT', 0],
						'note_author_name'		=> ['VCHAR_UNI', ''],
						'note_time'				=> ['TIMESTAMP', 0],
						'note_text'				=> ['MTEXT_UNI', ''],
						'note_bbcode_uid'		=> ['VCHAR:8', ''],
						'note_bbcode_bitfield'	=> ['VCHAR:255', ''],
						'note_bbcode_options'	=> ['UINT:11', 7],
						'note_edit_time'		=> ['TIMESTAMP', 0],
						'note_edit_user_id'		=> ['UINT', 0],
						'note_edit_user_name'	=> ['VCHAR_UNI', ''],
					],
					'PRIMARY_KEY'	=> 'note_id',
					'KEYS'			=> [
						'entry_id'	=> ['INDEX', 'entry_id'],
					],
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'seenotes_notes',
				$this->table_prefix . 'seenotes_entries',
				$this->table_prefix . 'seenotes_reasons',
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_data()
	{
		$data = [
			// The label shown before the reason
			['config.add', ['seenotes_label', 'SEE NOTES']],
			// Reasons must have a description (0 no, 1 yes)
			['config.add', ['seenotes_require_description', 0]],
			// Reasons can be deleted with all their entries (0 no, 1 yes)
			['config.add', ['seenotes_allow_hard_delete', 0]],
		];

		// Moderator permissions, global and per forum like phpBB's own; having
		// one in any forum is enough
		$moderator_permissions = [
			'm_seenotes_view'			=> ['ROLE_MOD_STANDARD', 'ROLE_MOD_FULL'],
			'm_seenotes_manage'			=> ['ROLE_MOD_STANDARD', 'ROLE_MOD_FULL'],
			'm_seenotes_note_delete'	=> ['ROLE_MOD_STANDARD', 'ROLE_MOD_FULL'],
			'm_seenotes_note_edit'		=> ['ROLE_MOD_FULL'],
			'm_seenotes_entry_delete'	=> ['ROLE_MOD_FULL'],
		];

		foreach ($moderator_permissions as $permission => $roles)
		{
			$data[] = ['permission.add', [$permission, true]];
			$data[] = ['permission.add', [$permission, false]];
			$data = array_merge($data, $this->set_in_roles($permission, $roles));
		}

		// Matching administrator permissions, since the Administrators group
		// has no moderator role by default
		$admin_permissions = [
			'a_seenotes_view',
			'a_seenotes_manage',
			'a_seenotes_note_delete',
			'a_seenotes_note_edit',
			'a_seenotes_entry_delete',
			'a_seenotes_settings',
		];

		foreach ($admin_permissions as $permission)
		{
			$data[] = ['permission.add', [$permission, true]];
			$data = array_merge($data, $this->set_in_roles($permission, ['ROLE_ADMIN_STANDARD', 'ROLE_ADMIN_FULL']));
		}

		return array_merge($data, [
			['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_SEENOTES']],
			['module.add', ['acp', 'ACP_SEENOTES', [
				'module_basename'	=> '\phpbbmodders\seenotes\acp\main_module',
				'modes'				=> ['reasons', 'settings'],
			]]],

			// A page in ACP > Manage users' form list, handled through
			// core.acp_users_mode_add
			['module.add', ['acp', 'ACP_CAT_USERS', [
				'module_basename'	=> 'acp_users',
				'module_langname'	=> 'ACP_USER_SEENOTES',
				'module_mode'		=> 'seenotes',
				'module_auth'		=> 'ext_phpbbmodders/seenotes && acl_a_user && acl_a_seenotes_view',
				'module_display'	=> 0,
			]]],

			['module.add', ['mcp', 0, 'MCP_SEENOTES']],
			['module.add', ['mcp', 'MCP_SEENOTES', [
				'module_basename'	=> '\phpbbmodders\seenotes\mcp\main_module',
				'modes'				=> ['notes'],
			]]],
		]);
	}

	/**
	 * Steps that give a permission to roles that exist on this board
	 *
	 * @param string $permission
	 * @param array $roles
	 * @return array
	 */
	protected function set_in_roles($permission, array $roles)
	{
		$steps = [];

		foreach ($roles as $role)
		{
			$steps[] = ['if', [
				['permission.role_exists', [$role]],
				['permission.permission_set', [$role, $permission]],
			]];
		}

		return $steps;
	}
}
