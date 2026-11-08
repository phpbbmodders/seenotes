<?php
/**
 *
 * See Notes extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\seenotes\tests\event;

use phpbb\event\data;
use phpbbmodders\seenotes\event\listener;
use phpbbmodders\seenotes\manager;
use phpbbmodders\seenotes\notes_page;

/**
 * Tests \phpbbmodders\seenotes\event\listener
 */
class listener_test extends \phpbb_test_case
{
	/** @var \PHPUnit\Framework\MockObject\MockObject|\phpbb\auth\auth */
	protected $auth;

	/** @var \PHPUnit\Framework\MockObject\MockObject|\phpbb\template\template */
	protected $template;

	/** @var \PHPUnit\Framework\MockObject\MockObject|manager */
	protected $manager;

	/** @var \PHPUnit\Framework\MockObject\MockObject|notes_page */
	protected $notes_page;

	protected function setUp(): void
	{
		parent::setUp();

		$this->auth = $this->getMockBuilder('\phpbb\auth\auth')->disableOriginalConstructor()->getMock();
		$this->template = $this->getMockBuilder('\phpbb\template\template')->getMock();
		$this->manager = $this->getMockBuilder(manager::class)->disableOriginalConstructor()->getMock();
		$this->manager->method('get_label')->willReturn('SEE NOTES');
		$this->manager->method('clean_color')->willReturnArgument(0);
		$this->notes_page = $this->getMockBuilder(notes_page::class)->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return listener
	 */
	protected function get_listener()
	{
		global $phpbb_root_path, $phpEx;

		$language = $this->getMockBuilder('\phpbb\language\language')->disableOriginalConstructor()->getMock();
		$language->method('lang')->willReturnArgument(0);
		$user = $this->getMockBuilder('\phpbb\user')->disableOriginalConstructor()->getMock();
		$user->session_id = '';

		return new listener($this->auth, $language, $this->template, $user, $this->manager, $this->notes_page, $phpbb_root_path, $phpEx);
	}

	public function test_construct()
	{
		$this->assertInstanceOf('\Symfony\Component\EventDispatcher\EventSubscriberInterface', $this->get_listener());
	}

	public function test_subscribed_events()
	{
		$this->assertEquals([
			'core.user_setup',
			'core.permissions',
			'core.page_header',
			'core.memberlist_view_profile',
			'core.viewtopic_modify_post_data',
			'core.viewtopic_modify_post_row',
			'core.mcp_post_template_data',
			'core.mcp_report_template_data',
			'core.mcp_queue_approve_details_template',
			'core.mcp_topic_modify_post_data',
			'core.mcp_topic_review_modify_row',
			'core.acp_users_mode_add',
			'core.delete_user_after',
			'core.update_username',
		], array_keys(listener::getSubscribedEvents()));
	}

	public function test_permissions_are_added_in_their_own_category()
	{
		$event = new data(['categories' => [], 'permissions' => []]);
		$this->get_listener()->add_permissions($event);

		$this->assertArrayHasKey('seenotes', $event['categories']);
		$this->assertCount(11, $event['permissions']);
		$this->assertSame('seenotes', $event['permissions']['m_seenotes_note_edit']['cat']);
		$this->assertArrayHasKey('a_seenotes_settings', $event['permissions']);
	}

	public function test_post_row_unchanged_for_viewers_without_permission()
	{
		$this->manager->method('can')->willReturn(false);
		$this->manager->expects($this->never())->method('get_current_for_users');

		$event = new data(['poster_id' => 3, 'post_row' => ['POST_ID' => 1]]);
		$this->get_listener()->show_on_post($event);

		$this->assertSame(['POST_ID' => 1], $event['post_row']);
	}

	public function test_post_row_gets_the_current_reason()
	{
		$this->manager->method('can')->willReturn(true);
		$this->manager->method('get_current_for_users')->willReturn([
			3 => ['reason_title' => 'Moderation queue', 'reason_description' => 'Approve first', 'reason_color' => 'AA0000', 'note_count' => 2],
		]);
		$this->auth->method('acl_getf_global')->willReturn(true);

		// append_sid() runs its event
		global $phpbb_dispatcher;
		$phpbb_dispatcher = new \phpbb_mock_event_dispatcher();

		$event = new data(['poster_id' => 3, 'post_row' => []]);
		$this->get_listener()->show_on_post($event);

		$row = $event['post_row'];
		$this->assertTrue($row['S_SEENOTES']);
		$this->assertSame('SEE NOTES', $row['SEENOTES_LABEL']);
		$this->assertSame('Moderation queue', $row['SEENOTES_REASON']);
		$this->assertSame(2, $row['SEENOTES_COUNT']);
		$this->assertStringContainsString('mode=notes&amp;u=3', $row['U_SEENOTES']);
		$this->assertStringEndsWith('u=3', $row['U_SEENOTES_NOTES']);
	}

	public function test_post_row_without_a_current_reason_gets_only_the_button()
	{
		$this->manager->method('can')->willReturn(true);
		$this->manager->method('get_current_for_users')->willReturn([]);
		$this->auth->method('acl_getf_global')->willReturn(true);

		// append_sid() runs its event
		global $phpbb_dispatcher;
		$phpbb_dispatcher = new \phpbb_mock_event_dispatcher();

		$event = new data(['poster_id' => 3, 'post_row' => []]);
		$this->get_listener()->show_on_post($event);

		$row = $event['post_row'];
		$this->assertArrayNotHasKey('S_SEENOTES', $row);
		$this->assertStringEndsWith('u=3#seenotes-add-entry', $row['U_SEENOTES_NOTES']);
	}

	public function test_other_acp_users_modes_are_ignored()
	{
		$this->notes_page->expects($this->never())->method('handle');

		$event = new data(['mode' => 'feedback', 'user_id' => 3, 'user_row' => [], 'u_action' => 'x']);
		$this->get_listener()->acp_users_page($event);
	}

	public function test_acp_users_mode_shows_the_page()
	{
		$this->notes_page->expects($this->once())
			->method('handle')
			->with(['user_id' => 3], 'index.php?i=users&amp;mode=seenotes&amp;u=3', true);

		$event = new data(['mode' => 'seenotes', 'user_id' => 3, 'user_row' => ['user_id' => 3], 'u_action' => 'index.php?i=users&amp;mode=seenotes']);
		$this->get_listener()->acp_users_page($event);
	}
}
