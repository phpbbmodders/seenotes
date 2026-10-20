# See Notes: design

Status: **design stage, no code yet.** This document records what has been agreed so far, so it can be reviewed before work starts. Feedback goes in the design issue.

## What it does

See Notes lets moderators and administrators mark a member with a **reason** chosen from a list the board's administrators set up, and keep written **notes** under that reason. The member's current reason shows in their profile and next to their posts, but only to staff with permission to see it. Members never see it, including on their own profile.

Example: an administrator adds the reason "Moderation queue". A moderator gives a member that reason and writes a note explaining why. Other moderators then see "SEE NOTES: Moderation queue" in that member's mini-profile and can follow the link to read the notes.

It is for phpBB 3.3.19 or later, including phpBB 4.0, under the `phpbbmodders` vendor (`phpbbmodders/seenotes`).

## Why not a custom profile field

The first idea was a dropdown custom profile field. The extension keeps its own data instead, because:

- Administrators can delete or rename a custom profile field in the ACP, which would break the extension.
- A dropdown field stores the option's number, not the option. Removing or reordering an option can silently give members the wrong reason.
- A hidden profile field can only be changed by administrators in the ACP. Moderators need to change the reason.

The display still looks like a profile field.

## Reasons (ACP)

**ACP → Extensions → See Notes → Reasons**:

- Administrators add, edit and reorder reasons. The list starts empty.
- Each reason has a **description** (for example "Posts must be approved before they appear"), shown to staff when they hover over the reason, and a **color**, red by default, used for the mini-profile line. The **Require a description for each reason** setting (off by default) makes the description required: when it is on, a reason cannot be saved without one. Turning it on does not change existing reasons, but one without a description must get one the next time it is edited.
- Each reason has an **on/off switch**. Turning a reason off removes it from the list moderators pick from. Members who already have it keep their entries and notes, still shown with that reason's name. Turning it back on makes it selectable again.
- Renaming a reason renames it everywhere it is used, since entries point to the reason rather than copying its name.

**Deleting a reason with all its entries** is possible only when the setting below is on:

- Setting: **Allow deleting a reason with all its entries**, off by default.
- When it is on, each reason gets a delete button. Deleting goes through two confirm screens:
  1. "Delete reason '&lt;name&gt;'? This will permanently remove it from X users, along with Y entries and Z notes."
  2. A final screen that shows the reason's name and asks the administrator to type it, like GitHub's repository-delete confirmation. The delete button stays disabled until the text matches. The server checks the match again, so this also works with JavaScript turned off.
- The deletion is recorded in the admin log.

## Label (ACP)

**ACP → Extensions → See Notes → Settings**: the label shown before the reason, "SEE NOTES" by default. It is one name for the whole board, not one per language.

## Entries and notes

- A member can have **any number of reason entries**. Each entry records its reason, the date it was added and who added it.
- Entries are kept until someone deletes them.
- **At most one entry is current.** Staff can switch which entry is current, or set none. When none is current, nothing shows on the member's profile, but the entries and notes are still there in the MCP.
- Each entry has its own list of **notes**, like phpBB's user notes: author, date and text. Notes are plain text, shown escaped. Notes can be edited and deleted; an edited note shows who last edited it and when.
- Deleting an entry deletes its notes.
- The author's and last editor's usernames are saved with each note. While they are members, their name links to their profile. If one of them is deleted, the note keeps their name as plain text with no link, the way phpBB keeps a deleted member's name on their posts. Saved names are updated when a member is renamed (`core.update_username`).
- When the member the notes are about is deleted, their entries and notes are deleted too.

## Where it shows

To staff with the "see" permission only:

- **Profile** (`memberlist.php?mode=viewprofile`): a "&lt;label&gt;: &lt;current reason&gt; (&lt;number of notes&gt;)" row with the other profile details (name, location and so on), in the same style as those rows.
- **Mini-profile** next to posts (viewtopic): the same line next to the custom profile fields, in bold, in the reason's color (red by default).
- **MCP pages where moderators act on a post:** post details, reports and the approval queue, next to the poster's name.
- All of these show the reason's description on hover (when it has one) and link to the member's Moderator notes tab in the MCP. The number in brackets is how many notes the current entry has.

Template events available on both 3.3.x and master: `memberlist_view_non_contact_custom_fields_after` and `viewtopic_body_postrow_custom_fields_after`. PHP events: `core.memberlist_view_profile`, `core.viewtopic_cache_user_data`, `core.viewtopic_modify_post_row`, and for the MCP pages `core.mcp_post_template_data`, `core.mcp_queue_get_posts_modify_post_row` and `core.mcp_report_template_data`. The current reasons for every poster on a topic page are loaded in one query.

## MCP

A **Moderator notes** tab in the MCP, with a "find a member" form like phpBB's User notes. Like the ACP page, its name is fixed and does not follow the label. For a member it lists their entries, newest first, with each entry's notes. From there staff with permission can:

- add an entry with a reason (active reasons only),
- set which entry is current, or none,
- add, edit and delete notes,
- delete an entry with its notes.

Below the entries, a **phpBB user notes** box shows the member's latest few phpBB user notes (read-only), with a link to the full list. phpBB's user notes stay as they are: besides notes staff type in, phpBB writes warnings, bans, account changes and other records there automatically, and other extensions use them too. The box puts both in one place so neither gets missed. A general note that does not fit a reason can go under a reason the admins create for it, such as "General".

## ACP → Manage users

A **Moderator notes** page in the Manage users form list, like Feedback and Warnings. This name is fixed: it stays "Moderator notes" whatever the label is renamed to. It shows the member's entries and notes and allows the same actions as the MCP tab, including the phpBB user notes box, for administrators with the matching permissions.

phpBB supports this on 3.3.x and master: the extension adds a hidden `acp_users` module with its own mode, which puts it in the form list, and handles it in `core.acp_users_mode_add`. One gap: `adm/style/acp_users.html` has no template event for an extension mode's content, and the event does not give access to the page's template name. How the page is drawn needs working out when coding starts. If there is no clean way, the extension will request the missing event from phpBB (a `docs/events/` file, as with the other extensions) rather than patch core.

Every change, from either page, goes into the **moderator log**: entry added, current entry changed, entry deleted, note added, note edited, note deleted.

## Permissions

Moderator permissions can be set globally or per forum. Having one in any forum is enough, since See Notes is about members, not forums. On a default board the Administrators group has no moderator role, so each moderator permission has a matching administrator permission, and either one allows the action.

| Action | Moderator permission | Administrator permission | Default |
|---|---|---|---|
| See reasons and notes | `m_seenotes_view` | `a_seenotes_view` | Standard and Full Moderator; Standard and Full Admin |
| Add entries, set the current entry, add notes | `m_seenotes_manage` | `a_seenotes_manage` | Standard and Full Moderator; Standard and Full Admin |
| Delete notes | `m_seenotes_note_delete` | `a_seenotes_note_delete` | Standard and Full Moderator; Standard and Full Admin |
| Edit notes | `m_seenotes_note_edit` | `a_seenotes_note_edit` | Full Moderator; Standard and Full Admin |
| Delete an entry with its notes | `m_seenotes_entry_delete` | `a_seenotes_entry_delete` | Full Moderator; Standard and Full Admin |
| Manage reasons and settings (ACP) | | `a_seenotes_settings` | Standard and Full Admin |

## Database

| Table | Columns |
|---|---|
| `seenotes_reasons` | `reason_id`, `reason_title`, `reason_description`, `reason_color`, `reason_order`, `reason_active` |
| `seenotes_entries` | `entry_id`, `user_id`, `reason_id`, `entry_time`, `entry_author_id`, `entry_current` |
| `seenotes_notes` | `note_id`, `entry_id`, `note_author_id`, `note_author_name`, `note_time`, `note_text`, `note_edit_time`, `note_edit_user_id`, `note_edit_user_name` |

Setting an entry as current clears the flag on the member's other entries in the same transaction.

Config: `seenotes_label` (the label), `seenotes_require_description` (off) and `seenotes_allow_hard_delete` (off).

## Standard extension parts

Same as the other phpbbmodders extensions: `ext.php` minimum version checks (phpBB 3.3.19, PHP 7.4), `extra.version-check` once a phpBB.com listing exists, the org's strict EPV workflow plus lint and tests, screen-reader text on icon-only controls, and language strings for everything shown.

## Privacy

Entries and notes are records about real people. They are shown only to staff with permission, never to the member, and they are deleted when the member is deleted. phpBB has no way to export a member's data, so the documentation will say where these records are kept and how to remove them.
