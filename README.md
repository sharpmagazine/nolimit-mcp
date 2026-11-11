# NoLimitMCP — WordPress MCP Server

Self-hosted MCP server for WordPress. Works everywhere: ChatGPT (via built-in OAuth 2.1), Claude, Cursor, Windsurf. Your AI connects directly to your own site — no cloud relay, no account, no daily tool-call limits, no paywall.

> **Version 2.8.3** — the same version running live on [sharpmagazine.de](https://sharpmagazine.de).

## What it does

Exposes a Streamable HTTP MCP endpoint at `/wp-json/nolimit-mcp/v1/mcp` with 60 tools:

- Site info, posts, pages, media (list, get, create, update, upload, trash, delete)
- Posts can be scheduled: `create_post` and `update_post` accept an optional `date`
- Categories, tags, comments, pages, menus, post revisions
- Any site option (get/set) and LiteSpeed Cache purge-all
- Users (list, get, create, update, delete with post reassignment — passwords are never returned)
- Content search
- Rank Math SEO meta for posts and categories (get/set SEO title, meta description, focus keyword)
- Plugins (list, activate, deactivate, install from wordpress.org, delete) and themes (list, activate, install, delete, read/write theme files)

## Safety rules (built in, not optional)

- Two login methods, both verified locally: OAuth 2.1 (ChatGPT/Claude) or WordPress Application Passwords (Cursor/Claude Desktop). No credential ever leaves your site.
- New posts/pages default to draft unless publish is explicitly requested.
- Post, page and comment deletes go to trash (recoverable).
- No raw SQL. Every tool checks WordPress capabilities.
- OAuth tokens are stored only as hashes, expire after 1 hour (refreshable for 30 days), and can be revoked from Settings → NoLimit MCP.
- No usage counting anywhere in the code.

## Installation

1. In wp-admin go to Plugins → Add New → Upload Plugin, upload `nolimit-mcp.zip`, then Activate.
2. Go to Settings → NoLimit MCP to see your endpoint URL.

(Or download `nolimit-mcp.php` + `readme.txt` from this repo, zip them into a folder named `nolimit-mcp`, and upload that zip the same way.)

## Connect with ChatGPT

1. In ChatGPT on the web go to Settings → Connectors → Create.
2. Name: NoLimit MCP. MCP server URL: your endpoint (`https://YOURSITE.com/wp-json/nolimit-mcp/v1/mcp`). Authentication: OAuth.
3. ChatGPT discovers this plugin automatically via `/.well-known/oauth-protected-resource` and `/.well-known/oauth-authorization-server`, and registers itself (DCR) — no client ID or secret to copy.
4. A WordPress login page opens: sign in and click Approve.
5. Add the connector to a chat (via the + / tools menu) and ask it to manage your site.

## Full version & support

- 💎 **Full version ($50 one-time, lifetime use, 14-day refund):** [Gumroad](https://abiharani.gumroad.com/l/etlqzn)
- 📰 Built by the team behind [Sharp Magazine](https://sharpmagazine.de) — a German-language online magazine.
- ❤️ **Support the project:** [![Donate with PayPal](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/donate/?business=hindseo09@gmail.com&currency_code=EUR)

## License

GPLv2 or later. See [LICENSE](LICENSE).
