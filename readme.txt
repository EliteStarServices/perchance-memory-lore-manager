=== Perchance Memory Manager ===
Contributors: your-name
Tags: perchance, memory, markdown, text, admin
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A starter WordPress plugin for uploading, cleaning, reorganizing, and exporting Perchance AI chat memory files.

== Description ==

This plugin provides a v1 admin tool for:
- Uploading .txt or .md memory files
- Parsing sections such as Characters, Organizations, Locations, Technology / Systems, Relationships, NSFW, Notes, and New Entries
- Performing basic deduplication and cleanup
- Preserving Notes and (AI:) instructions
- Exporting cleaned content as Markdown or plain text

== Installation ==

1. Create a folder named `perchance-memory-manager`
2. Place all plugin files in that folder
3. Zip the folder
4. Upload through WordPress Plugins > Add New > Upload Plugin
5. Activate the plugin
6. Open "Perchance Memory" in wp-admin

== Frequently Asked Questions ==

= Does this support JSON? =

No. This starter version is focused on Markdown/text output for Perchance-style memory files.

= Does it support very large files? =

It depends on your server PHP limits. For larger files, increase:
- upload_max_filesize
- post_max_size
- memory_limit
- max_execution_time

= Does it preserve (AI:) notes? =

Yes. Lines beginning with `(AI:)` in Notes/New Entries are preserved.

== Changelog ==

= 0.1.0 =
* Initial starter version.