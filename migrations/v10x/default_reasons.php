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
 * Adds a starter set of reasons, named in the board's default language, so a
 * new board has reasons to give straight away. Administrators can rename,
 * switch off or delete each one. A board that already has reasons is left
 * alone.
 */
class default_reasons extends \phpbb\db\migration\migration
{
	/**
	 * The starter reasons: language key prefix and color, in list order. Each
	 * prefix has a name key and a _EXPLAIN description key in
	 * language/<lang>/install_seenotes.php.
	 */
	const REASONS = [
		'SEENOTES_DEFAULT_QUEUE'	=> 'AA0000',
		'SEENOTES_DEFAULT_SPAM'		=> 'C05800',
		'SEENOTES_DEFAULT_WAREZ'	=> '7B0000',
		'SEENOTES_DEFAULT_CONDUCT'	=> '8C5A00',
		'SEENOTES_DEFAULT_RETURNING'=> '6B2C91',
		'SEENOTES_DEFAULT_CONTACT'	=> '0055AA',
		'SEENOTES_DEFAULT_GENERAL'	=> '555555',
	];

	/**
	 * {@inheritdoc}
	 */
	public function effectively_installed()
	{
		$sql = 'SELECT reason_id
			FROM ' . $this->table_prefix . 'seenotes_reasons';
		$result = $this->db->sql_query_limit($sql, 1);
		$has_reasons = (bool) $this->db->sql_fetchfield('reason_id');
		$this->db->sql_freeresult($result);

		return $has_reasons;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function depends_on()
	{
		return ['\phpbbmodders\seenotes\migrations\v10x\install'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_data()
	{
		return [
			['custom', [[$this, 'add_default_reasons']]],
		];
	}

	/**
	 * Insert the starter reasons, in the board's default language when the
	 * extension ships it, otherwise in English
	 *
	 * @return void
	 */
	public function add_default_reasons()
	{
		$strings = $this->load_strings((string) $this->config['default_lang']);

		$rows = [];
		$order = 0;
		foreach (self::REASONS as $key => $color)
		{
			if (!isset($strings[$key]))
			{
				continue;
			}

			// Stored escaped, like reasons saved from the ACP form
			$rows[] = [
				'reason_title'			=> utf8_htmlspecialchars($strings[$key]),
				'reason_description'	=> isset($strings[$key . '_EXPLAIN']) ? utf8_htmlspecialchars($strings[$key . '_EXPLAIN']) : '',
				'reason_color'			=> $color,
				'reason_order'			=> ++$order,
				'reason_active'			=> 1,
			];
		}

		if ($rows)
		{
			$this->db->sql_multi_insert($this->table_prefix . 'seenotes_reasons', $rows);
		}
	}

	/**
	 * The extension's install strings in a language, falling back to English
	 *
	 * @param string $lang_iso Language folder name, such as "en" or "de"
	 * @return array Language keys and their text
	 */
	protected function load_strings($lang_iso)
	{
		$base = $this->phpbb_root_path . 'ext/phpbbmodders/seenotes/language/';
		$file = $base . 'en/install_seenotes.' . $this->php_ext;

		// Only a plain folder name, so the setting cannot point elsewhere
		if (preg_match('/\A[a-z0-9_-]+\z/i', $lang_iso) && is_file($base . $lang_iso . '/install_seenotes.' . $this->php_ext))
		{
			$file = $base . $lang_iso . '/install_seenotes.' . $this->php_ext;
		}

		$lang = [];
		include($file);

		return $lang;
	}
}
