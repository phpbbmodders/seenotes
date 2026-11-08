<?php
/**
 *
 * See Notes extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\seenotes\tests;

use phpbbmodders\seenotes\manager;

/**
 * Tests the data and permission handling of \phpbbmodders\seenotes\manager
 */
class manager_test extends \phpbb_database_test_case
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \PHPUnit\Framework\MockObject\MockObject|\phpbb\auth\auth */
	protected $auth;

	/** @var \PHPUnit\Framework\MockObject\MockObject|\phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\user */
	protected $user;

	/** @var array Granted permission options */
	protected $granted = [];

	protected static function setup_extensions()
	{
		return ['phpbbmodders/seenotes'];
	}

	public function getDataSet()
	{
		return $this->createXMLDataSet(__DIR__ . '/fixtures/seenotes.xml');
	}

	protected function setUp(): void
	{
		parent::setUp();

		$this->db = $this->new_dbal();
		$this->granted = [];

		$this->auth = $this->getMockBuilder('\phpbb\auth\auth')->disableOriginalConstructor()->getMock();
		$this->auth->method('acl_get')->willReturnCallback(function ($option) {
			return in_array($option, $this->granted, true);
		});
		$this->auth->method('acl_getf_global')->willReturnCallback(function ($option) {
			return in_array($option, $this->granted, true);
		});

		$this->log = $this->getMockBuilder('\phpbb\log\log_interface')->getMock();

		$this->user = $this->getMockBuilder('\phpbb\user')->disableOriginalConstructor()->getMock();
		$this->user->data = ['user_id' => 2, 'username' => 'admin', 'is_registered' => true];
		$this->user->ip = '127.0.0.1';
	}

	/**
	 * @return manager
	 */
	protected function get_manager()
	{
		$config = new \phpbb\config\config(['seenotes_label' => 'SEE NOTES']);

		return new manager($this->auth, $config, $this->db, $this->log, $this->user, 'phpbb_seenotes_reasons', 'phpbb_seenotes_entries', 'phpbb_seenotes_notes');
	}

	public function test_moderator_or_admin_permission_allows_an_action()
	{
		$manager = $this->get_manager();
		$this->assertFalse($manager->can('view'));

		$this->granted = ['m_seenotes_view'];
		$this->assertTrue($manager->can('view'));
		$this->assertFalse($manager->can('manage'));

		$this->granted = ['a_seenotes_manage'];
		$this->assertTrue($manager->can('manage'));
	}

	public function test_guests_and_unknown_actions_are_refused()
	{
		$this->granted = ['m_seenotes_view', 'm_seenotes_settings'];
		$manager = $this->get_manager();
		$this->assertFalse($manager->can('settings'));

		$this->user->data['is_registered'] = false;
		$this->assertFalse($manager->can('view'));
	}

	public function test_clean_color()
	{
		$manager = $this->get_manager();

		$this->assertSame('00AAFF', $manager->clean_color('#00aaff'));
		$this->assertSame(manager::DEFAULT_COLOR, $manager->clean_color('red'));
		$this->assertSame(manager::DEFAULT_COLOR, $manager->clean_color('#00aaff;background:url(x)'));
	}

	public function test_active_reasons_leave_out_switched_off_ones()
	{
		$manager = $this->get_manager();

		$this->assertSame([1, 2, 3], array_keys($manager->get_reasons()));
		$this->assertSame([1, 2], array_keys($manager->get_reasons(true)));
	}

	public function test_new_reason_goes_last_and_moves()
	{
		$manager = $this->get_manager();
		$id = $manager->save_reason(['reason_title' => 'New', 'reason_description' => '', 'reason_color' => '#123abc']);

		$this->assertSame([1, 2, 3, $id], array_keys($manager->get_reasons()));
		$this->assertSame('123ABC', $manager->get_reason($id)['reason_color']);

		$this->assertTrue($manager->move_reason($id, true));
		$this->assertSame([1, 2, $id, 3], array_keys($manager->get_reasons()));
		$this->assertFalse($manager->move_reason(1, true));
	}

	public function test_current_entry_with_note_count()
	{
		$current = $this->get_manager()->get_current_for_users([3, 4]);

		$this->assertSame([3], array_keys($current));
		$this->assertSame('Moderation queue', $current[3]['reason_title']);
		$this->assertSame(2, $current[3]['note_count']);
	}

	public function test_set_current_keeps_one_current_entry()
	{
		$manager = $this->get_manager();

		$manager->set_current(3, 2);
		$entries = $manager->get_entries(3);
		$this->assertSame(0, (int) $entries[1]['entry_current']);
		$this->assertSame(1, (int) $entries[2]['entry_current']);

		$manager->set_current(3, 0);
		$this->assertSame([], $manager->get_current_for_users([3]));
	}

	public function test_add_entry_as_current()
	{
		$manager = $this->get_manager();
		$entry_id = $manager->add_entry(4, 2, true);

		$current = $manager->get_current_for_users([4]);
		$this->assertSame($entry_id, (int) $current[4]['entry_id']);
		$this->assertSame('admin', $manager->get_entry($entry_id)['entry_author_name']);
	}

	public function test_notes_newest_first_with_saved_name_for_deleted_author()
	{
		$notes = $this->get_manager()->get_notes([1]);

		$this->assertSame([2, 1], array_map('intval', array_column($notes[1], 'note_id')));
		// No users row: NULL, or '' on MSSQL, whose phpBB driver returns NULL as an empty string
		$this->assertEmpty($notes[1][0]['author_username']);
		$this->assertSame('gone', $notes[1][0]['note_author_name']);
		$this->assertSame('admin', $notes[1][1]['author_username']);
	}

	public function test_delete_entry_removes_its_notes()
	{
		$manager = $this->get_manager();
		$manager->delete_entry(1);

		$this->assertNull($manager->get_entry(1));
		$this->assertNull($manager->get_note(1));
		$this->assertNotNull($manager->get_note(3));
	}

	public function test_reason_usage_and_hard_delete()
	{
		$manager = $this->get_manager();

		$this->assertSame(['users' => 2, 'entries' => 2, 'notes' => 3], $manager->count_reason_usage(1));

		$manager->delete_reason(1);
		$this->assertNull($manager->get_reason(1));
		$this->assertSame([2], array_keys($manager->get_entries(3)));
		$this->assertSame([], $manager->get_entries(4));
		$this->assertNull($manager->get_note(3));
	}

	public function test_deleting_a_member_deletes_their_records()
	{
		$manager = $this->get_manager();
		$manager->delete_users([3]);

		$this->assertSame([], $manager->get_entries(3));
		$this->assertNull($manager->get_note(1));
		$this->assertSame([3], array_keys($manager->get_entries(4)));
	}

	public function test_renaming_updates_only_the_renamed_members_names()
	{
		$manager = $this->get_manager();

		// A deleted member (99) who had the same name as the renamed one
		$this->db->sql_query("UPDATE phpbb_seenotes_notes SET note_author_name = 'admin' WHERE note_id = 2");
		// phpBB renames the users row before core.update_username runs
		$this->db->sql_query("UPDATE phpbb_users SET username = 'boss', username_clean = 'boss' WHERE user_id = 2");

		$manager->update_username('admin', 'boss');

		$this->assertSame('boss', $manager->get_entry(1)['entry_author_name']);
		$this->assertSame('boss', $manager->get_note(1)['note_author_name']);
		$this->assertSame('admin', $manager->get_note(2)['note_author_name']);
	}

	public function test_moderator_log_has_no_forum()
	{
		$this->log->expects($this->once())
			->method('add')
			->with('mod', 2, '127.0.0.1', 'LOG_SEENOTES_NOTE_ADDED', false, ['forum_id' => 0, 'topic_id' => 0, 0 => 'Moderation queue', 1 => 'member']);

		$this->get_manager()->log_moderator('LOG_SEENOTES_NOTE_ADDED', ['Moderation queue', 'member']);
	}
}
