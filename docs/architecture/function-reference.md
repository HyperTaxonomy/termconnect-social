---
title: Function Reference
parent: Architecture
nav_order: 2
---

# Function Reference
{: .no_toc }

Functions and methods of TermConnect Social by file, with each one's docblock summary, chosen by one rule used across the suite's plugins. Listed: the public API (documented in the [Developer Reference](../developer-reference.html)) and anything called from another file, which is what contributors reuse. Not listed: hook callbacks, of which the [Hook Reference](hook-reference.html) covers the registrations you can't guess from the code (search for the others by name); screen wiring (rendering, enqueueing, menu and column registration, constructors); overrides of a base class's method; and anything used only inside its own file or class, which is best read in the code. This page is generated from the code.

## Table of contents
{: .no_toc .text-delta }

1. TOC
{:toc}

---

## Includes

### Network registry (`includes/networks.php`)

Social network registry.

- `tcsoc_get_network_registry()` — Returns the built-in network definitions, keyed by slug.
- `tcsoc_get_network( string $slug )` — Returns a single network definition, or null.
- `tcsoc_network_supports( string $slug, string $taxonomy )` — Whether a network supports a given taxonomy.
- `tcsoc_taxonomies()` — The taxonomies this plugin manages.
- `tcsoc_network_icon_html( string $slug, string $dashicon, string $color = '', string $extra_class = '' )` — Returns the icon markup for a network.
- `tcsoc_allowed_html()` — `wp_kses()` allowlist for the plugin's own markup: post HTML plus the bundled SVG icons and `aria-label` on the Blogosphere's `<section>`/`<nav>`.
- `tcsoc_network_svg( string $slug )` — Returns the raw bundled SVG markup for a network, or '' if none.
- `tcsoc_network_color( string $slug )` — The official brand color for a network, used to tint its icon in the editor and the front-end panel (admin screens stay monochrome). Returns '' when none.
- `tcsoc_build_network_url( string $slug, string $id, string $taxonomy )` — Builds the external URL for a network + identifier on a given taxonomy.

### Token rendering (`includes/render.php`)

Frontend rendering of inline tokens.

- `tcsoc_mention_universe( WP_Term $term )` — The ordered selectable items for a mention token: enabled social networks plus, when the Blogosphere network is enabled, the term's WordPress author profiles (the "Local site" and any peers). Blogosphere itself is the on/off gate, not a selectable item.
- `tcsoc_link_name( int $term_id, string $slug, string $handle )` — The human display name captured for a term's link, or '' when none is available.
- `tcsoc_author_site_name( string $slug, string $fallback )` — The site name shown for a WordPress author profile. The non-configurable "Local site" link type carries a generic label (fine in the term editor), so on the front end and in the post editor we substitute this site's actual title; peers already use their admin-given name.

### Settings storage (`includes/settings.php`)

Settings storage.

- `tcsoc_get_settings()` — Returns the full settings array, with the network map normalized against the current registry (back-filling new networks at the end, dropping unknown ones).
- `tcsoc_no_link_mode()` — How to render a token (hashtag or mention) that has no enabled network links.
- `tcsoc_blogosphere_auto_append_setting()` — Whether the Blogosphere section should auto-append to hashtag archives.
- `tcsoc_get_network_states()` — Returns the ordered network map ( slug => enabled ).
- `tcsoc_get_enabled_networks( string $taxonomy = '' )` — Returns the ordered list of enabled network slugs.
- `tcsoc_is_network_enabled( string $slug )` — Whether a network is enabled.
- `tcsoc_save_settings( array $networks, bool $auto_append, string $no_link, bool $delete_on_uninstall )` — Persists the full settings: ordered network map plus display options.

### Taxonomies (`includes/taxonomies.php`)

Taxonomy registration: hashtag (primary) and mention (secondary).

- `tcsoc_token_post_types()` — Post types that may contain inline hashtags/mentions.
- `tcsoc_register_taxonomies()` — Registers the hashtag and mention taxonomies.

### Automatic term assignment (`includes/term-sync.php`)

Automatic term assignment from inline tokens.

- `tcsoc_kses_allowed_html( $tags, $context )` — Whitelists this plugin's data-tcs-* token attributes on <a> tags for the 'post' kses context.
- `tcsoc_token_attr( string $attrs, string $name )` — Reads a single attribute value from an anchor's attribute string.
- `tcsoc_get_or_create_term( string $name, string $taxonomy, int $user_id = 0 )` — Returns an existing term ID by name, creating the term if necessary.
- `tcsoc_user_matches_mention_name( int $user_id, string $name )` — Whether a mention token's text names the given user.

### Mention user links in merges (`includes/merge.php`)

What happens to merged mentions' user links (`_tcsoc_user_id`) in a HyperTaxonomy merge: they move to the target when it has no user yet (the first source's link wins), and are dropped otherwise; never kept on a redirect stub.

- `tcsoc_merge_check( $sections, $source_ids, $target_id, $taxonomy )` — `htax_merge_terms_check`: an info notice per linked source saying whether its link moves or is dropped (no choices).
- `tcsoc_before_merge( $source_ids, $target_id, $taxonomy )` / `tcsoc_after_merge( $source_ids, $target_id, $taxonomy )` — `htax_before_merge_terms` keeps the sources' links, which the merge deletes; `htax_after_merge_terms` moves one to the target with `tcsoc_set_term_user_link()`.
- `tcsoc_merge_messages( $messages )` — `htax_merge_terms_messages`: reports what happened to each link.
- `tcsoc_merge_plan( array $links, int $target_user ): array` — Whether each source's link moves (true) or is dropped (false); links to the target's own user are left out.
- `tcsoc_merge_user_links( array $term_ids ): array`, `tcsoc_merge_user_name( int $user_id ): string`, `tcsoc_merge_state(): array` (by reference) — The terms' linked users, a user's display name, and the merge in progress.
