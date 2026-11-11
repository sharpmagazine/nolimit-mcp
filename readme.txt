=== NoLimit MCP for WordPress ===
Contributors: muse
Tags: mcp, ai, model context protocol, rest api, automation, chatgpt, oauth
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 2.8.3
Requires PHP: 7.4
License: GPLv2 or later

Self-hosted MCP server for WordPress. Works everywhere: ChatGPT (via built-in OAuth 2.1), Claude, Cursor, Windsurf. Your AI connects directly to your own site — no cloud relay, no account, no daily tool-call limits, no paywall.

== Description ==

WPVibe-style AI access, without WPVibe's limits. WPVibe routes your AI's tool calls through its own cloud servers and counts them (100/day free, then paid plans). NoLimit MCP runs entirely on your own WordPress site, so there is nothing to count and nothing to pay.

Version 2 adds a full OAuth 2.1 authorization server (Dynamic Client Registration + PKCE), so ChatGPT's custom MCP connectors can connect directly — no password pasting, no third-party relay.

= What it does =

Exposes a Streamable HTTP MCP endpoint at `/wp-json/nolimit-mcp/v1/mcp` with 60 tools:

* Site info, posts, pages, media (list, get, create, update, upload, trash, delete)
* Posts can be scheduled: create_post and update_post accept an optional `date` ("YYYY-MM-DD HH:MM:SS", site local time); a future date with publish/future status schedules the post
* Categories (list, get, create, update, delete — delete refuses while posts are assigned), tags (list, get, create, update, delete), comments (list, approve, trash, update, delete/trash)
* Pages (list, create, update, trash) and trash restore for posts/pages
* Menus (list, create, update items, delete) and post revisions (list)
* Any site option (get/set) and LiteSpeed Cache purge-all
* Users (list, get, create, update, delete with post reassignment — passwords are never returned)
* Content search
* Rank Math SEO meta for posts and categories (get/set SEO title, meta description, focus keyword)
* Plugins (list, activate, deactivate, install from wordpress.org, delete) and themes (list, activate, install, delete, read/write theme files)

= Safety rules (built in, not optional) =

* Two login methods, both verified locally: OAuth 2.1 (ChatGPT/Claude) or WordPress Application Passwords (Cursor/Claude Desktop). No credential ever leaves your site.
* New posts/pages default to draft unless publish is explicitly requested.
* Post, page and comment deletes go to trash (recoverable); taxonomy, user, plugin and theme deletes are permanent, exactly as in wp-admin.
* No raw SQL. Full-admin scope (users, plugins, themes, options, menus) is available to the authenticated admin user — the only built-in refusals are deactivating/deleting this plugin itself, and deleting the active theme.
* Every tool checks WordPress capabilities — an editor cannot do admin-only actions.
* OAuth tokens are stored only as hashes, expire after 1 hour (refreshable for 30 days), and can be revoked from Settings → NoLimit MCP.
* No usage counting anywhere in the code.

= "No limits" — honestly explained =

There are no daily caps, no accounts, no cloud relay, no paywall. The only boundaries are (1) authentication, which is mandatory — without it anyone on the internet could control your site, and (2) WordPress's own permission system. Those are locks on your own door, not usage limits.

== Installation ==

1. In wp-admin go to Plugins → Add New → Upload Plugin, upload `nolimit-mcp.zip`, then Activate. (If v1 is already installed, WordPress will ask to replace it — click Replace.)
2. Go to Settings → NoLimit MCP to see your endpoint URL.

== Connect with ChatGPT ==

1. In ChatGPT on the web go to Settings → Connectors → Create.
2. Name: NoLimit MCP. MCP server URL: your endpoint (`https://YOURSITE.com/wp-json/nolimit-mcp/v1/mcp`). Authentication: OAuth.
3. ChatGPT discovers this plugin automatically via `/.well-known/oauth-protected-resource` and `/.well-known/oauth-authorization-server`, and registers itself (DCR) — no client ID or secret to copy.
4. A WordPress login page opens: sign in and click Approve.
5. Add the connector to a chat (via the + / tools menu) and ask it to manage your site.

Note: on Business/Enterprise workspaces an admin may need to allow custom connectors first (Workspace Settings → Permissions & Roles → Connected Data → Create custom MCP connectors). If you do not see the Create button, that is why.

== Connect with Claude, Cursor, Windsurf ==

1. In WordPress go to Users → Profile → Application Passwords, create one (e.g. "MCP"), and copy the password shown once.
2. In your AI client add an MCP server of type Streamable HTTP with your endpoint URL, using HTTP Basic auth: username = your WordPress username, password = the Application Password.

Example Claude Desktop config (`claude_desktop_config.json`):

{
  "mcpServers": {
    "wordpress": {
      "url": "https://YOURSITE.com/wp-json/nolimit-mcp/v1/mcp",
      "transport": "streamable-http",
      "auth": { "type": "basic", "username": "YOUR_WP_USERNAME", "password": "YOUR_APPLICATION_PASSWORD" }
    }
  }
}

(Exact auth fields vary by client — every client that supports Streamable HTTP + Basic auth works.)

== Frequently Asked Questions ==

= Does it really have no limits? =

Yes. No daily caps, no metering, no account, no cloud. The plugin contains no counting code at all.

= Is it safe to put on a live site? =

It is as safe as giving someone an Application Password or an OAuth approval: whoever holds it can do what that WordPress user can do, within the safety rules above. Use HTTPS, and revoke access any time from Settings → NoLimit MCP (OAuth) or Users → Profile (Application Passwords).

= Does it work with page builders (Elementor, Divi)? =

It manages content through WordPress core (posts, pages, media, meta), which page builders store as post content/meta. It does not include builder-specific visual pipelines — those can be added later.

= What if my host strips the Authorization header? =

Some Apache/CGI hosts drop it. Add this to `.htaccess` in your WordPress root:

RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

= How is this different from WPVibe? =

WPVibe = plugin + hosted cloud relay + daily call caps (100/day free). NoLimit MCP = plugin only, direct connection, no relay, no caps. v2 covers core content + Rank Math SEO + full OAuth; WPVibe's extras (builder pipelines, WP-CLI emulation, approval panels) are not included.

== Changelog ==

= 2.8.3 =
* Full-admin expansion (17 new tools, 43 -> 60): create_user, update_user, delete_user (reassign_to required); deactivate_plugin, install_plugin (wordpress.org slug, optional activate flag), delete_plugin; activate_theme, install_theme, delete_theme (refuses the active theme), get_theme_file, update_theme_file (both refuse any path escaping the theme directory; writes via WP_Filesystem); list_menus, create_menu, update_menu_item, delete_menu; list_revisions; get_option.
* update_option is now UNRESTRICTED: any option name, capability manage_options — the four-item allowlist from 2.8.2 is removed. This is the single modification to an existing tool; all other existing tools are unchanged.
* Guards: the tool REFUSES to deactivate or delete the nolimit-mcp plugin itself (self-destruction guard, works for canonical, versioned and single-file install layouts). Nothing else is refused: user passwords, plugin installs, theme file writes and option changes are all available to the authenticated admin user, exactly as wp-admin allows. Password changes are only ever invoked with the site owner explicit per-action approval — that is a usage rule, not a tool restriction.
* Generic build: the plugin contains no site-specific hardcoding (no site URL, no site-specific client ID, no site-specific slugs) — the Plugin URI header pointing at one site was removed — so the zip installs on any WordPress site.
* Widgets are the single excluded area: the block-widget system is not manageable through this tool pattern. To be added later only if the owner asks.
* Header and readme safety claims updated to match full-admin scope (the 2.8.2 claims about trash-only deletes and no user/plugin management are no longer true).

= 2.8.2 =
* New: get_category + delete_category tools. delete_category REFUSES with the assigned-post count if any post is still in the category — it never silently reassigns posts.
* New: get_tag, update_tag, delete_tag tools (tag read/update/delete by ID, mirroring the category tools).
* New: delete_media tool (refuses if the attachment is a featured image on any post/page, otherwise wp_delete_attachment).
* New: delete_page tool (trash, recoverable) and restore_post tool (untrash a post or page).
* New: update_comment tool (status approve/hold/spam/trash and/or content) and delete_comment tool (trash, not permanent delete).
* New: update_option tool, strict allowlist only: timezone_string, date_format, time_format, start_of_week. Any other option name is rejected without writing.
* New: purge_cache tool (LiteSpeed Cache purge-all; clear error — never fake success — if LiteSpeed is not active).
* New: optional `date` argument ("YYYY-MM-DD HH:MM:SS", site local time) on create_post and update_post. A future date with publish/future status schedules the post (post_status future) per WordPress core behavior; invalid dates are rejected with a clear error. Without `date`, both tools behave exactly as before.
* Deliberately NOT added: user create/update/delete or password changes, plugin install/delete/activation, theme file editing, direct database access, options outside the allowlist, anything changing site URLs, admin email, or user roles — and no Rank Math score re-save tool (the Rank Math plugin code is not available locally to verify a reliable method, so score re-save still needs the wp-admin Update click).

= 2.8.1 =
* New: update_category tool (rename, re-slug, edit description, re-parent a post category by ID — only provided fields change, via wp_update_term, the WordPress native function).
* New: update_category_seo + get_category_seo tools (Rank Math SEO title, meta description and focus keyword for categories, via update_term_meta / get_term_meta — the same meta keys Rank Math itself uses, mirroring update_post_seo / get_post_seo). Category fixes can now be applied and read back through MCP exactly like post SEO fixes, instead of by hand in wp-admin.

= 2.8.0 =
* New: create_tag tool (create post tags by name, mirroring create_category) — previously new tag names passed to create_post/update_post were silently dropped when they did not exist yet.
* New: featured_media parameter on create_post and update_post — set the post thumbnail (featured image) by attachment ID, validated as a real attachment before it is set.

= 2.7.0 =
* tools/call now also returns structuredContent (the raw result object) alongside the text block, for modern MCP clients that prefer parsed structured output over parsing long text blocks (e.g. large HTML articles).
* Settings page now logs recent tool calls (tool, id/slug, result size in bytes, client name — no content bodies, no secrets), so a "client got empty" claim can be checked against server evidence.

= 2.6.0 =
* get_post robustness (reported "empty response" for full-content reads): get_post now also accepts a slug (explicit 'slug' argument, or a non-numeric 'id'), returns plain-text content_text plus content_length and word_count so the client can verify it received the full article, warns explicitly when stored content is genuinely empty, and rejects non-post/page IDs (attachments, menu items) with a clear error naming the type instead of a confusing empty result.

= 2.5.0 =
* Fixed ChatGPT tools not loading ("action discovery failed"): five no-parameter tools (get_site_info, list_categories, list_tags, list_plugins, list_themes) emitted inputSchema.properties as a JSON array [] instead of an object {} — invalid JSON Schema that strict clients reject, killing the whole tools list. Properties are now emitted as a proper empty object.

= 2.4.0 =
* Fixed ChatGPT "Authentication succeeded, action discovery failed": initialize now echoes ANY well-formed date-style protocol version the client requests (previously only three listed versions were echoed, and newer clients fell back to an old version they no longer accept).
* Notifications now get a truly empty 204 response (previously a 202 with a null body, which strict clients can misread).
* Settings page now shows recent client handshakes (requested version, Accept header, client name — no secrets) for evidence-based debugging.

= 2.3.0 =
* Fixed ChatGPT discovery ("Couldn't discover OAuth settings"): issuer is now emitted without a trailing slash, matching the metadata retrieval URL exactly as RFC 8414 requires (strict clients rejected the trailing-slash issuer).
* 401 responses from the MCP endpoint now include a WWW-Authenticate header pointing at the protected-resource metadata (RFC 9728), so clients can discover the OAuth server from the 401 alone.

= 2.2.0 =
* Protocol negotiation: initialize now answers with the client's requested MCP protocol version when it is a known version (2024-11-05, 2025-03-26, 2025-06-18), so strict clients do not disconnect over a version mismatch.
* Authorization header is now read from every available source (REST header, HTTP_AUTHORIZATION, REDIRECT_HTTP_AUTHORIZATION, apache_request_headers), so hosts that strip the header in one place no longer break Bearer/Basic auth.
* GET discovery now reports auth_header_seen (boolean) to diagnose header stripping.

= 2.1.0 =
* OAuth discovery now answers directly on init (early interceptor), so NoLimit MCP's .well-known metadata always wins even when another MCP/OAuth plugin (e.g. WPVibe, WP Direct Bridge) registers competing rewrite rules for the same paths. This fixes "Unknown OAuth client" in ChatGPT when another OAuth server shadows the site.
* Rewrite rules kept as a fallback.

= 2.0.0 =
* Full OAuth 2.1 authorization server: Dynamic Client Registration, PKCE (S256/plain), authorization + token + refresh flow, .well-known discovery — ChatGPT custom connectors now connect directly.
* Bearer token auth on the MCP endpoint; Application Password auth kept for Claude/Cursor.
* initialize now returns server instructions.
* Settings page: ChatGPT steps, Claude/Cursor steps, active token count, revoke-all button.
* Activation flushes rewrite rules for .well-known and authorize URLs.

= 1.0.0 =
* First release: 27 MCP tools, Application Password auth, draft-first safety, Rank Math SEO tools, settings page.
