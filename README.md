# See Notes

Lets moderators mark a member with a staff-only reason from a list the administrators set, and keep dated notes under each reason.

> **Status: design stage.** There is no code yet. The planned behavior is in [`docs/design.md`](docs/design.md).

## Planned features

- Administrators set up the list of reasons in the ACP, and can switch each one on or off.
- Moderators give a member one or more reason entries, each with its own notes, and choose which one is current.
- The current reason shows in the member's profile and mini-profile, only to staff with permission.
- Separate permissions for seeing, managing, editing notes, deleting notes and deleting entries.
- Every change goes into the moderator log.

## Requirements

- phpBB 3.3.19 or later, including phpBB 4.0
- PHP 7.4 or later (phpBB 4.0 itself needs PHP 8.2 or later)

## Contributing

Contributions are welcome!

- **Bug reports**: [Open an issue](https://github.com/phpbbmodders/seenotes/issues).
- **Everything else** (questions, feature requests, ideas, general discussion): [Use Discussions](https://github.com/orgs/phpbbmodders/discussions), or the [community forum](https://www.phpbbmodders.com/community/).
- Pull requests are welcome for bug fixes or discussed features.

## Acknowledgments

- Design written with [Claude](https://www.anthropic.com/claude).

## License

This extension is licensed under the **GNU General Public License v2.0**.

See [license.txt](license.txt) for more information.
