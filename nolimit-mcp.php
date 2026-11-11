<?php
/**
 * Plugin Name:       NoLimit MCP for WordPress
 * Description:       Self-hosted MCP server for WordPress. Your AI connects directly to your own site — no cloud relay, no account, no daily tool-call limits, no paywall. Auth via OAuth 2.1 (ChatGPT, Claude) or Application Passwords (Cursor, Claude Desktop). Posts default to draft, no raw SQL. Full-admin scope: users, plugins, themes, menus and options are manageable through the tools, exactly as wp-admin allows (post, page and comment deletes go to trash; taxonomy, user, plugin and theme deletes are permanent, as in wp-admin).
 * Version:           2.8.3
 * Author:            Built for Rizwan
 * Text Domain:       nolimit-mcp
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NLMCP_VERSION', '2.8.3' );
define( 'NLMCP_ROUTE_NAMESPACE', 'nolimit-mcp/v1' );

/* --------------------------------------------------------------------------
 * Tool definitions
 * Each tool: description, input schema, capability required, handler.
 * ------------------------------------------------------------------------ */

function nlmcp_get_tools() {
    $str    = array( 'type' => 'string' );
    $int    = array( 'type' => 'integer' );
    $bool   = array( 'type' => 'boolean' );
    $arrstr = array( 'type' => 'array', 'items' => array( 'type' => 'string' ) );
    $arrint = array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) );

    return array(
        'get_site_info' => array(
            'description' => 'Get site name, URL, WordPress version, active theme, and content counts.',
            'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_get_site_info',
        ),
        'list_posts' => array(
            'description' => 'List posts with optional search, status, category and pagination.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'search'   => $str,
                'status'   => $str,
                'category' => $int,
                'per_page' => $int,
                'page'     => $int,
            ) ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_list_posts',
        ),
        'get_post' => array(
            'description' => 'Get one post or page by numeric ID or slug, including full content, plain-text content and content length.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id'   => $int,
                'slug' => $str,
            ), 'required' => array() ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_get_post',
        ),
        'create_post' => array(
            'description' => 'Create a post. Status defaults to draft (safe default); pass "publish" only when explicitly wanted.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'title'      => $str,
                'content'    => $str,
                'excerpt'    => $str,
                'slug'       => $str,
                'status'     => $str,
                'categories' => $arrstr,
                'tags'       => $arrstr,
                'featured_media' => $int,
                'date'       => $str,
            ), 'required' => array( 'title', 'content' ) ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_create_post',
        ),
        'update_post' => array(
            'description' => 'Update a post by ID. Only provided fields change.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id'         => $int,
                'title'      => $str,
                'content'    => $str,
                'excerpt'    => $str,
                'slug'       => $str,
                'status'     => $str,
                'categories' => $arrstr,
                'tags'       => $arrstr,
                'featured_media' => $int,
                'date'       => $str,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_update_post',
        ),
        'delete_post' => array(
            'description' => 'Move a post to trash (never permanently deleted through this tool).',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'delete_posts',
            'handler'     => 'nlmcp_tool_delete_post',
        ),
        'publish_post' => array(
            'description' => 'Publish a draft post by ID.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'publish_posts',
            'handler'     => 'nlmcp_tool_publish_post',
        ),
        'list_pages' => array(
            'description' => 'List pages with optional search and pagination.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'search'   => $str,
                'status'   => $str,
                'per_page' => $int,
                'page'     => $int,
            ) ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_list_pages',
        ),
        'create_page' => array(
            'description' => 'Create a page. Status defaults to draft.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'title'   => $str,
                'content' => $str,
                'slug'    => $str,
                'status'  => $str,
            ), 'required' => array( 'title', 'content' ) ),
            'capability'  => 'edit_pages',
            'handler'     => 'nlmcp_tool_create_page',
        ),
        'update_page' => array(
            'description' => 'Update a page by ID. Only provided fields change.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id'      => $int,
                'title'   => $str,
                'content' => $str,
                'slug'    => $str,
                'status'  => $str,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'edit_pages',
            'handler'     => 'nlmcp_tool_update_page',
        ),
        'list_media' => array(
            'description' => 'List media library items with optional search and pagination.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'search'   => $str,
                'per_page' => $int,
                'page'     => $int,
            ) ),
            'capability'  => 'upload_files',
            'handler'     => 'nlmcp_tool_list_media',
        ),
        'get_media' => array(
            'description' => 'Get one media item by ID, including URL, alt text and caption.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'upload_files',
            'handler'     => 'nlmcp_tool_get_media',
        ),
        'upload_media' => array(
            'description' => 'Upload a media file from base64 data. Provide filename (with extension) and base64-encoded file bytes.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'filename' => $str,
                'data'     => $str,
                'alt'      => $str,
                'caption'  => $str,
                'title'    => $str,
            ), 'required' => array( 'filename', 'data' ) ),
            'capability'  => 'upload_files',
            'handler'     => 'nlmcp_tool_upload_media',
        ),
        'update_media' => array(
            'description' => 'Update a media item alt text, caption or title by ID.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id'      => $int,
                'alt'     => $str,
                'caption' => $str,
                'title'   => $str,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'upload_files',
            'handler'     => 'nlmcp_tool_update_media',
        ),
        'list_categories' => array(
            'description' => 'List post categories.',
            'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_list_categories',
        ),
        'create_category' => array(
            'description' => 'Create a post category.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'name' => $str,
                'slug' => $str,
            ), 'required' => array( 'name' ) ),
            'capability'  => 'manage_categories',
            'handler'     => 'nlmcp_tool_create_category',
        ),
        'update_category' => array(
            'description' => 'Update a post category by ID: name, slug, description, parent. Only provided fields change. Uses wp_update_term (WordPress native). Note: changing the slug changes the category archive URL.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id'          => $int,
                'name'        => $str,
                'slug'        => $str,
                'description' => $str,
                'parent'      => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'manage_categories',
            'handler'     => 'nlmcp_tool_update_category',
        ),
        'create_tag' => array(
            'description' => 'Create a post tag.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'name' => $str,
                'slug' => $str,
            ), 'required' => array( 'name' ) ),
            'capability'  => 'manage_categories',
            'handler'     => 'nlmcp_tool_create_tag',
        ),
        'list_tags' => array(
            'description' => 'List post tags.',
            'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_list_tags',
        ),
        'list_comments' => array(
            'description' => 'List comments with optional status filter (approve, hold, spam, trash, all).',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'status'   => $str,
                'per_page' => $int,
            ) ),
            'capability'  => 'moderate_comments',
            'handler'     => 'nlmcp_tool_list_comments',
        ),
        'approve_comment' => array(
            'description' => 'Approve a comment by ID.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'moderate_comments',
            'handler'     => 'nlmcp_tool_approve_comment',
        ),
        'trash_comment' => array(
            'description' => 'Move a comment to trash by ID.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'moderate_comments',
            'handler'     => 'nlmcp_tool_trash_comment',
        ),
        'list_users' => array(
            'description' => 'List users (id, name, slug, roles). No passwords are ever returned.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'per_page' => $int,
            ) ),
            'capability'  => 'list_users',
            'handler'     => 'nlmcp_tool_list_users',
        ),
        'get_user' => array(
            'description' => 'Get one user by ID.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'list_users',
            'handler'     => 'nlmcp_tool_get_user',
        ),
        'search_content' => array(
            'description' => 'Search posts and pages by keyword.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'query'    => $str,
                'per_page' => $int,
            ), 'required' => array( 'query' ) ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_search_content',
        ),
        'get_post_seo' => array(
            'description' => 'Get Rank Math SEO meta for a post: SEO title, meta description, focus keyword.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_get_post_seo',
        ),
        'update_post_seo' => array(
            'description' => 'Set Rank Math SEO meta for a post: SEO title, meta description, focus keyword. Only provided fields change.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id'               => $int,
                'seo_title'        => $str,
                'meta_description' => $str,
                'focus_keyword'    => $str,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_update_post_seo',
        ),
        'get_category_seo' => array(
            'description' => 'Get Rank Math SEO meta for a category: SEO title, meta description, focus keyword.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_get_category_seo',
        ),
        'update_category_seo' => array(
            'description' => 'Set Rank Math SEO meta for a category: SEO title, meta description, focus keyword. Only provided fields change. Uses update_term_meta (WordPress native).',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id'               => $int,
                'seo_title'        => $str,
                'meta_description' => $str,
                'focus_keyword'    => $str,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'manage_categories',
            'handler'     => 'nlmcp_tool_update_category_seo',
        ),
        'list_plugins' => array(
            'description' => 'List installed plugins with name, version and active status. Read-only.',
            'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            'capability'  => 'activate_plugins',
            'handler'     => 'nlmcp_tool_list_plugins',
        ),
        'list_themes' => array(
            'description' => 'List installed themes with active status. Read-only.',
            'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            'capability'  => 'switch_themes',
            'handler'     => 'nlmcp_tool_list_themes',
        ),
        'get_category' => array(
            'description' => 'Get one post category by ID: id, name, slug, post count.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'manage_categories',
            'handler'     => 'nlmcp_tool_get_category',
        ),
        'delete_category' => array(
            'description' => 'Delete a post category by ID. REFUSES if any post is still assigned to it (reports the count); it never silently reassigns posts. Reassign or remove those posts first, then delete again.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'manage_categories',
            'handler'     => 'nlmcp_tool_delete_category',
        ),
        'get_tag' => array(
            'description' => 'Get one post tag by ID: id, name, slug, post count.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'manage_categories',
            'handler'     => 'nlmcp_tool_get_tag',
        ),
        'update_tag' => array(
            'description' => 'Update a post tag by ID: name, slug, description. Only provided fields change. Uses wp_update_term (WordPress native). Note: changing the slug changes the tag archive URL.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id'          => $int,
                'name'        => $str,
                'slug'        => $str,
                'description' => $str,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'manage_categories',
            'handler'     => 'nlmcp_tool_update_tag',
        ),
        'delete_tag' => array(
            'description' => 'Delete a post tag by ID. WordPress removes the tag from all posts it was assigned to.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'manage_categories',
            'handler'     => 'nlmcp_tool_delete_tag',
        ),
        'delete_media' => array(
            'description' => 'Delete a media attachment by ID. REFUSES if the attachment is set as the featured image on any post or page (reports that post/page ID); otherwise deletes it via wp_delete_attachment (goes to trash when trash is enabled, like other deletes in this plugin).',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'upload_files',
            'handler'     => 'nlmcp_tool_delete_media',
        ),
        'delete_page' => array(
            'description' => 'Move a page to trash by ID (never permanently deleted through this tool; recoverable from trash).',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'delete_pages',
            'handler'     => 'nlmcp_tool_delete_page',
        ),
        'restore_post' => array(
            'description' => 'Restore a trashed post or page by ID (untrash via wp_untrash_post).',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'delete_posts',
            'handler'     => 'nlmcp_tool_restore_post',
        ),
        'update_comment' => array(
            'description' => 'Update a comment by ID: status (approve, hold, spam, trash) and/or content. Only provided fields change.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id'      => $int,
                'status'  => $str,
                'content' => $str,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'moderate_comments',
            'handler'     => 'nlmcp_tool_update_comment',
        ),
        'delete_comment' => array(
            'description' => 'Move a comment to trash by ID (never permanently deleted through this tool; recoverable from trash).',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'moderate_comments',
            'handler'     => 'nlmcp_tool_delete_comment',
        ),
        'update_option' => array(
            'description' => 'Update any site option by name (full-admin scope): writes exactly the value given. Requires manage_options, exactly as wp-admin does. Options like site URL, admin email or user roles change the site immediately — only call this deliberately.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'option' => $str,
                'value'  => $str,
            ), 'required' => array( 'option', 'value' ) ),
            'capability'  => 'manage_options',
            'handler'     => 'nlmcp_tool_update_option',
        ),
        'purge_cache' => array(
            'description' => 'Purge all LiteSpeed Cache caches (the plugin\'s purge-all action). Returns a clear error if LiteSpeed Cache is not active — never a fake success.',
            'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            'capability'  => 'manage_options',
            'handler'     => 'nlmcp_tool_purge_cache',
        ),
        'create_user' => array(
            'description' => 'Create a WordPress user: username, email and role are required, password is required. Returns the new user ID.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'username' => $str,
                'email'    => $str,
                'role'     => $str,
                'password' => $str,
            ), 'required' => array( 'username', 'email', 'role', 'password' ) ),
            'capability'  => 'create_users',
            'handler'     => 'nlmcp_tool_create_user',
        ),
        'update_user' => array(
            'description' => 'Update a WordPress user by ID: email, role, display name and/or password. Only provided fields change. Password changes take effect immediately.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id'           => $int,
                'email'        => $str,
                'role'         => $str,
                'display_name' => $str,
                'password'     => $str,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'edit_users',
            'handler'     => 'nlmcp_tool_update_user',
        ),
        'delete_user' => array(
            'description' => 'Delete a WordPress user by ID. reassign_to (another existing user ID) is REQUIRED: all of the deleted user posts are reassigned to that user. Refuses without a valid reassign_to.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id'          => $int,
                'reassign_to' => $int,
            ), 'required' => array( 'id', 'reassign_to' ) ),
            'capability'  => 'delete_users',
            'handler'     => 'nlmcp_tool_delete_user',
        ),
        'deactivate_plugin' => array(
            'description' => 'Deactivate an installed plugin by slug (short slug like akismet, or full path like akismet/akismet.php). REFUSES to deactivate this plugin itself (nolimit-mcp).',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'slug' => $str,
            ), 'required' => array( 'slug' ) ),
            'capability'  => 'activate_plugins',
            'handler'     => 'nlmcp_tool_deactivate_plugin',
        ),
        'install_plugin' => array(
            'description' => 'Install a plugin from wordpress.org by slug. Set activate to true to activate it right after install (default false).',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'slug'     => $str,
                'activate' => $bool,
            ), 'required' => array( 'slug' ) ),
            'capability'  => 'install_plugins',
            'handler'     => 'nlmcp_tool_install_plugin',
        ),
        'delete_plugin' => array(
            'description' => 'Delete an installed plugin by slug. REFUSES to delete this plugin itself (nolimit-mcp). If WordPress reports the plugin is still active, deactivate it first.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'slug' => $str,
            ), 'required' => array( 'slug' ) ),
            'capability'  => 'delete_plugins',
            'handler'     => 'nlmcp_tool_delete_plugin',
        ),
        'activate_theme' => array(
            'description' => 'Activate an installed theme by stylesheet slug.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'stylesheet' => $str,
            ), 'required' => array( 'stylesheet' ) ),
            'capability'  => 'switch_themes',
            'handler'     => 'nlmcp_tool_activate_theme',
        ),
        'install_theme' => array(
            'description' => 'Install a theme from wordpress.org by stylesheet slug.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'stylesheet' => $str,
            ), 'required' => array( 'stylesheet' ) ),
            'capability'  => 'install_themes',
            'handler'     => 'nlmcp_tool_install_theme',
        ),
        'delete_theme' => array(
            'description' => 'Delete an installed theme by stylesheet slug. REFUSES to delete the active theme.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'stylesheet' => $str,
            ), 'required' => array( 'stylesheet' ) ),
            'capability'  => 'delete_themes',
            'handler'     => 'nlmcp_tool_delete_theme',
        ),
        'get_theme_file' => array(
            'description' => 'Read one file inside an installed theme: theme (stylesheet slug) plus a relative file path. Only files inside that theme directory are readable; any path escaping it is refused.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'theme' => $str,
                'file'  => $str,
            ), 'required' => array( 'theme', 'file' ) ),
            'capability'  => 'edit_theme_options',
            'handler'     => 'nlmcp_tool_get_theme_file',
        ),
        'update_theme_file' => array(
            'description' => 'Write one file inside an installed theme: theme (stylesheet slug), relative file path, and full new content. Only files inside that theme directory are writable; any path escaping it is refused. Writes via WP_Filesystem.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'theme'   => $str,
                'file'    => $str,
                'content' => $str,
            ), 'required' => array( 'theme', 'file', 'content' ) ),
            'capability'  => 'edit_theme_options',
            'handler'     => 'nlmcp_tool_update_theme_file',
        ),
        'list_menus' => array(
            'description' => 'List navigation menus: id, name, slug, item count.',
            'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
            'capability'  => 'edit_theme_options',
            'handler'     => 'nlmcp_tool_list_menus',
        ),
        'create_menu' => array(
            'description' => 'Create a navigation menu by name. Returns the new menu ID.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'name' => $str,
            ), 'required' => array( 'name' ) ),
            'capability'  => 'edit_theme_options',
            'handler'     => 'nlmcp_tool_create_menu',
        ),
        'update_menu_item' => array(
            'description' => 'Create or update a menu item: menu_id plus title are required; item_id 0 (or omitted) creates a new item, an existing item_id updates it. Give url for a custom link, or object (post, page, category, post_tag) plus object_id for a content link.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'menu_id'   => $int,
                'item_id'   => $int,
                'title'     => $str,
                'url'       => $str,
                'object'    => $str,
                'object_id' => $int,
            ), 'required' => array( 'menu_id', 'title' ) ),
            'capability'  => 'edit_theme_options',
            'handler'     => 'nlmcp_tool_update_menu_item',
        ),
        'delete_menu' => array(
            'description' => 'Delete a navigation menu by ID (menu items go with it).',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'menu_id' => $int,
            ), 'required' => array( 'menu_id' ) ),
            'capability'  => 'edit_theme_options',
            'handler'     => 'nlmcp_tool_delete_menu',
        ),
        'list_revisions' => array(
            'description' => 'List revisions of a post by ID: revision id, date, author id and name, title, status.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'id' => $int,
            ), 'required' => array( 'id' ) ),
            'capability'  => 'edit_posts',
            'handler'     => 'nlmcp_tool_list_revisions',
        ),
        'get_option' => array(
            'description' => 'Read any site option by name. Returns the stored value as-is (arrays included). A missing option returns false, same as WordPress itself.',
            'inputSchema' => array( 'type' => 'object', 'properties' => array(
                'option' => $str,
            ), 'required' => array( 'option' ) ),
            'capability'  => 'manage_options',
            'handler'     => 'nlmcp_tool_get_option',
        ),
    );
}

/* --------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------ */

function nlmcp_clean_args( $args ) {
    if ( ! is_array( $args ) ) {
        return array();
    }
    return $args;
}

function nlmcp_text( $args, $key, $default = '' ) {
    if ( ! isset( $args[ $key ] ) || ! is_scalar( $args[ $key ] ) ) {
        return $default;
    }
    return sanitize_text_field( wp_unslash( $args[ $key ] ) );
}

function nlmcp_int( $args, $key, $default = 0 ) {
    if ( ! isset( $args[ $key ] ) ) {
        return $default;
    }
    return absint( $args[ $key ] );
}

function nlmcp_post_summary( $post ) {
    return array(
        'id'         => $post->ID,
        'title'      => get_the_title( $post ),
        'slug'       => $post->post_name,
        'status'     => $post->post_status,
        'type'       => $post->post_type,
        'date'       => $post->post_date,
        'modified'   => $post->post_modified,
        'link'       => get_permalink( $post ),
        'excerpt'    => get_the_excerpt( $post ),
        'categories' => wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) ),
        'tags'       => wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) ),
    );
}

function nlmcp_post_detail( $post ) {
    $data = nlmcp_post_summary( $post );
    $data['content']        = $post->post_content;
    $data['featured_media'] = get_post_thumbnail_id( $post->ID );
    // Plain-text + length fields: let the client verify at a glance that
    // it really received the full article (and how long it is), instead
    // of guessing from raw HTML. Also flags genuinely empty content.
    $plain = trim( wp_strip_all_tags( $post->post_content ) );
    $data['content_text']   = $plain;
    $data['content_length'] = strlen( $post->post_content );
    $data['word_count']     = '' === $plain ? 0 : count( preg_split( '/\s+/', $plain ) );
    if ( '' === $post->post_content ) {
        $data['notice'] = 'This entry has no stored content (post_content is empty). If you expected an article, the ID may belong to something else (e.g. a media/attachment ID, not a post or page).';
    }
    return $data;
}

function nlmcp_resolve_terms( $names, $taxonomy ) {
    $ids = array();
    if ( ! is_array( $names ) ) {
        return $ids;
    }
    foreach ( $names as $name ) {
        $name = sanitize_text_field( $name );
        if ( '' === $name ) {
            continue;
        }
        $term = get_term_by( 'name', $name, $taxonomy );
        if ( ! $term ) {
            $term = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
        }
        if ( $term ) {
            $ids[] = (int) $term->term_id;
        }
    }
    return $ids;
}

function nlmcp_filter_content( $content ) {
    if ( current_user_can( 'unfiltered_html' ) ) {
        return $content;
    }
    return wp_kses_post( $content );
}

function nlmcp_validate_post_date( $args ) {
    // Optional post scheduling date. Returns '' when no date is given
    // (caller behavior unchanged), otherwise a validated 'YYYY-MM-DD
    // HH:MM:SS' string in site local time. Throws on any invalid format
    // or impossible calendar date — nothing is written on failure.
    if ( ! isset( $args['date'] ) ) {
        return '';
    }
    $date = nlmcp_text( $args, 'date' );
    if ( '' === $date ) {
        return '';
    }
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date ) ) {
        throw new Exception( 'Invalid date format. Use "YYYY-MM-DD HH:MM:SS" in site local time (e.g. "2026-11-05 09:00:00").' );
    }
    $dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $date );
    $errors = DateTime::getLastErrors();
    if ( ! $dt || ! empty( $errors['warning_count'] ) || ! empty( $errors['error_count'] )
        || $dt->format( 'Y-m-d H:i:s' ) !== $date ) {
        throw new Exception( 'Invalid date "' . $date . '": not a real calendar date. Use "YYYY-MM-DD HH:MM:SS" in site local time.' );
    }
    return $date;
}

function nlmcp_post_date_is_future( $date ) {
    // Compares in the site timezone (not the PHP default timezone).
    $dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $date, wp_timezone() );
    if ( ! $dt ) {
        return false;
    }
    return $dt > new DateTime( 'now', wp_timezone() );
}

/* --------------------------------------------------------------------------
 * Tool handlers
 * ------------------------------------------------------------------------ */

function nlmcp_tool_get_site_info( $args ) {
    $theme = wp_get_theme();
    return array(
        'name'            => get_bloginfo( 'name' ),
        'description'     => get_bloginfo( 'description' ),
        'url'             => home_url(),
        'admin_email'     => get_option( 'admin_email' ),
        'wp_version'      => get_bloginfo( 'version' ),
        'theme'           => $theme ? $theme->get( 'Name' ) : '',
        'posts'           => (int) wp_count_posts( 'post' )->publish,
        'pages'           => (int) wp_count_posts( 'page' )->publish,
        'users'           => count_users(),
        'plugin_version'  => NLMCP_VERSION,
        'limits'          => 'none — self-hosted, no daily caps, no cloud relay',
    );
}

function nlmcp_tool_list_posts( $args ) {
    $q = new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => nlmcp_text( $args, 'status', 'publish' ),
        's'              => nlmcp_text( $args, 'search' ),
        'cat'            => nlmcp_int( $args, 'category' ),
        'posts_per_page' => min( max( nlmcp_int( $args, 'per_page', 20 ), 1 ), 100 ),
        'paged'          => max( nlmcp_int( $args, 'page', 1 ), 1 ),
    ) );
    $out = array();
    foreach ( $q->posts as $post ) {
        $out[] = nlmcp_post_summary( $post );
    }
    return array( 'total' => (int) $q->found_posts, 'posts' => $out );
}

function nlmcp_tool_get_post( $args ) {
    $post = null;
    // Numeric ID first.
    $id = nlmcp_int( $args, 'id' );
    if ( $id > 0 ) {
        $post = get_post( $id );
    }
    // Fallback: slug — either an explicit 'slug' argument, or a
    // non-numeric 'id' value (clients sometimes pass the slug as id).
    if ( ! $post ) {
        $slug = nlmcp_text( $args, 'slug' );
        if ( '' === $slug && isset( $args['id'] ) && ! is_numeric( $args['id'] ) ) {
            $slug = sanitize_title( $args['id'] );
        }
        if ( '' !== $slug ) {
            $post = get_page_by_path( $slug, OBJECT, 'post' );
            if ( ! $post ) {
                $post = get_page_by_path( $slug, OBJECT, 'page' );
            }
        }
    }
    if ( ! $post ) {
        throw new Exception( 'Post not found. Provide a numeric post ID or a slug (e.g. "kamilla-senjo").' );
    }
    // An attachment/menu-item/revision ID resolves to a WP_Post whose
    // content is empty — previously returned as a confusing "empty
    // response". Say so explicitly instead.
    if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
        throw new Exception( 'ID ' . $post->ID . ' is a "' . $post->post_type . '", not a post or page, so it has no article content. Use a post or page ID (or slug) instead.' );
    }
    return nlmcp_post_detail( $post );
}

function nlmcp_tool_create_post( $args ) {
    // Optional scheduling (2.8.2): a validated 'date' (site local time)
    // is stored as post_date. When the date is in the future and the
    // status is publish or future, the post ends up scheduled
    // (post_status future), per WordPress core behavior. Without 'date'
    // this function behaves exactly as before.
    $date = nlmcp_validate_post_date( $args );
    $status = nlmcp_text( $args, 'status', 'draft' );
    if ( in_array( $status, array( 'publish', 'future' ), true ) && ! current_user_can( 'publish_posts' ) ) {
        $status = 'draft';
    }
    $allowed = array( 'draft', 'publish', 'pending', 'private' );
    if ( '' !== $date ) {
        $allowed[] = 'future';
    }
    if ( ! in_array( $status, $allowed, true ) ) {
        $status = 'draft';
    }
    if ( '' !== $date && in_array( $status, array( 'publish', 'future' ), true )
        && nlmcp_post_date_is_future( $date ) ) {
        // Set explicitly: WordPress core also converts publish + a future
        // date to future, but setting it here guarantees the outcome.
        $status = 'future';
    }
    $data = array(
        'post_title'   => nlmcp_text( $args, 'title' ),
        'post_content' => nlmcp_filter_content( isset( $args['content'] ) ? wp_unslash( $args['content'] ) : '' ),
        'post_excerpt' => nlmcp_text( $args, 'excerpt' ),
        'post_name'    => sanitize_title( nlmcp_text( $args, 'slug' ) ),
        'post_status'  => $status,
        'post_type'    => 'post',
        'post_author'  => get_current_user_id(),
    );
    if ( '' !== $date ) {
        $data['post_date'] = $date;
    }
    $id = wp_insert_post( $data, true );
    if ( is_wp_error( $id ) ) {
        throw new Exception( $id->get_error_message() );
    }
    $cats = nlmcp_resolve_terms( isset( $args['categories'] ) ? $args['categories'] : array(), 'category' );
    if ( $cats ) {
        wp_set_post_categories( $id, $cats );
    }
    $tags = nlmcp_resolve_terms( isset( $args['tags'] ) ? $args['tags'] : array(), 'post_tag' );
    if ( $tags ) {
        wp_set_post_tags( $id, $tags );
    }
    if ( isset( $args['featured_media'] ) && (int) $args['featured_media'] > 0 ) {
        $fid = (int) $args['featured_media'];
        $att = get_post( $fid );
        if ( $att && 'attachment' === $att->post_type ) {
            set_post_thumbnail( $id, $fid );
        }
    }
    return nlmcp_post_detail( get_post( $id ) );
}

function nlmcp_tool_update_post( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $post = get_post( $id );
    if ( ! $post || 'post' !== $post->post_type ) {
        throw new Exception( 'Post not found.' );
    }
    // Optional scheduling (2.8.2): see nlmcp_tool_create_post. Without
    // 'date' this function behaves exactly as before.
    $date = nlmcp_validate_post_date( $args );
    $data = array( 'ID' => $id );
    if ( '' !== $date ) {
        $data['post_date'] = $date;
    }
    if ( isset( $args['title'] ) ) {
        $data['post_title'] = nlmcp_text( $args, 'title' );
    }
    if ( isset( $args['content'] ) ) {
        $data['post_content'] = nlmcp_filter_content( wp_unslash( $args['content'] ) );
    }
    if ( isset( $args['excerpt'] ) ) {
        $data['post_excerpt'] = nlmcp_text( $args, 'excerpt' );
    }
    if ( isset( $args['slug'] ) ) {
        $data['post_name'] = sanitize_title( nlmcp_text( $args, 'slug' ) );
    }
    if ( isset( $args['status'] ) ) {
        $status = nlmcp_text( $args, 'status' );
        if ( in_array( $status, array( 'publish', 'future' ), true ) && ! current_user_can( 'publish_posts' ) ) {
            $status = 'draft';
        }
        $allowed = array( 'draft', 'publish', 'pending', 'private' );
        if ( '' !== $date ) {
            $allowed[] = 'future';
        }
        if ( in_array( $status, $allowed, true ) ) {
            $data['post_status'] = $status;
        }
    }
    // A future date with a publish/future status schedules the post,
    // whether that status came from this call or was already set.
    if ( '' !== $date && nlmcp_post_date_is_future( $date ) ) {
        $effective = isset( $data['post_status'] ) ? $data['post_status'] : $post->post_status;
        if ( in_array( $effective, array( 'publish', 'future' ), true ) ) {
            $data['post_status'] = 'future';
        }
    }
    $updated = wp_update_post( $data, true );
    if ( is_wp_error( $updated ) ) {
        throw new Exception( $updated->get_error_message() );
    }
    if ( isset( $args['categories'] ) ) {
        wp_set_post_categories( $id, nlmcp_resolve_terms( $args['categories'], 'category' ) );
    }
    if ( isset( $args['tags'] ) ) {
        wp_set_post_tags( $id, nlmcp_resolve_terms( $args['tags'], 'post_tag' ) );
    }
    if ( isset( $args['featured_media'] ) && (int) $args['featured_media'] > 0 ) {
        $fid = (int) $args['featured_media'];
        $att = get_post( $fid );
        if ( $att && 'attachment' === $att->post_type ) {
            set_post_thumbnail( $id, $fid );
        }
    }
    return nlmcp_post_detail( get_post( $id ) );
}

function nlmcp_tool_delete_post( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $post = get_post( $id );
    if ( ! $post ) {
        throw new Exception( 'Post not found.' );
    }
    $result = wp_trash_post( $id );
    if ( ! $result ) {
        throw new Exception( 'Could not trash post.' );
    }
    return array( 'id' => $id, 'status' => 'trash' );
}

function nlmcp_tool_publish_post( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $post = get_post( $id );
    if ( ! $post ) {
        throw new Exception( 'Post not found.' );
    }
    $updated = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
    if ( is_wp_error( $updated ) ) {
        throw new Exception( $updated->get_error_message() );
    }
    return array( 'id' => $id, 'status' => 'publish', 'link' => get_permalink( $id ) );
}

function nlmcp_tool_list_pages( $args ) {
    $q = new WP_Query( array(
        'post_type'      => 'page',
        'post_status'    => nlmcp_text( $args, 'status', 'publish' ),
        's'              => nlmcp_text( $args, 'search' ),
        'posts_per_page' => min( max( nlmcp_int( $args, 'per_page', 20 ), 1 ), 100 ),
        'paged'          => max( nlmcp_int( $args, 'page', 1 ), 1 ),
    ) );
    $out = array();
    foreach ( $q->posts as $post ) {
        $out[] = nlmcp_post_summary( $post );
    }
    return array( 'total' => (int) $q->found_posts, 'pages' => $out );
}

function nlmcp_tool_create_page( $args ) {
    $status = nlmcp_text( $args, 'status', 'draft' );
    if ( 'publish' === $status && ! current_user_can( 'publish_pages' ) ) {
        $status = 'draft';
    }
    if ( ! in_array( $status, array( 'draft', 'publish', 'pending', 'private' ), true ) ) {
        $status = 'draft';
    }
    $id = wp_insert_post( array(
        'post_title'   => nlmcp_text( $args, 'title' ),
        'post_content' => nlmcp_filter_content( isset( $args['content'] ) ? wp_unslash( $args['content'] ) : '' ),
        'post_name'    => sanitize_title( nlmcp_text( $args, 'slug' ) ),
        'post_status'  => $status,
        'post_type'    => 'page',
        'post_author'  => get_current_user_id(),
    ), true );
    if ( is_wp_error( $id ) ) {
        throw new Exception( $id->get_error_message() );
    }
    return nlmcp_post_detail( get_post( $id ) );
}

function nlmcp_tool_update_page( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $post = get_post( $id );
    if ( ! $post || 'page' !== $post->post_type ) {
        throw new Exception( 'Page not found.' );
    }
    $data = array( 'ID' => $id );
    if ( isset( $args['title'] ) ) {
        $data['post_title'] = nlmcp_text( $args, 'title' );
    }
    if ( isset( $args['content'] ) ) {
        $data['post_content'] = nlmcp_filter_content( wp_unslash( $args['content'] ) );
    }
    if ( isset( $args['slug'] ) ) {
        $data['post_name'] = sanitize_title( nlmcp_text( $args, 'slug' ) );
    }
    if ( isset( $args['status'] ) ) {
        $status = nlmcp_text( $args, 'status' );
        if ( 'publish' === $status && ! current_user_can( 'publish_pages' ) ) {
            $status = 'draft';
        }
        if ( in_array( $status, array( 'draft', 'publish', 'pending', 'private' ), true ) ) {
            $data['post_status'] = $status;
        }
    }
    $updated = wp_update_post( $data, true );
    if ( is_wp_error( $updated ) ) {
        throw new Exception( $updated->get_error_message() );
    }
    return nlmcp_post_detail( get_post( $id ) );
}

function nlmcp_media_summary( $att ) {
    return array(
        'id'      => $att->ID,
        'title'   => get_the_title( $att ),
        'url'     => wp_get_attachment_url( $att->ID ),
        'mime'    => $att->post_mime_type,
        'alt'     => get_post_meta( $att->ID, '_wp_attachment_image_alt', true ),
        'caption' => $att->post_excerpt,
        'date'    => $att->post_date,
    );
}

function nlmcp_tool_list_media( $args ) {
    $q = new WP_Query( array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        's'              => nlmcp_text( $args, 'search' ),
        'posts_per_page' => min( max( nlmcp_int( $args, 'per_page', 20 ), 1 ), 100 ),
        'paged'          => max( nlmcp_int( $args, 'page', 1 ), 1 ),
    ) );
    $out = array();
    foreach ( $q->posts as $att ) {
        $out[] = nlmcp_media_summary( $att );
    }
    return array( 'total' => (int) $q->found_posts, 'media' => $out );
}

function nlmcp_tool_get_media( $args ) {
    $att = get_post( nlmcp_int( $args, 'id' ) );
    if ( ! $att || 'attachment' !== $att->post_type ) {
        throw new Exception( 'Media not found.' );
    }
    return nlmcp_media_summary( $att );
}

function nlmcp_tool_upload_media( $args ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $filename = sanitize_file_name( nlmcp_text( $args, 'filename' ) );
    if ( '' === $filename ) {
        throw new Exception( 'Filename is required.' );
    }
    $raw = isset( $args['data'] ) ? $args['data'] : '';
    if ( '' === $raw ) {
        throw new Exception( 'Base64 data is required.' );
    }
    $bytes = base64_decode( $raw, true );
    if ( false === $bytes ) {
        throw new Exception( 'Invalid base64 data.' );
    }
    $upload = wp_upload_bits( $filename, null, $bytes );
    if ( ! empty( $upload['error'] ) ) {
        throw new Exception( $upload['error'] );
    }
    $filetype = wp_check_filetype( $upload['file'] );
    $att_id = wp_insert_attachment( array(
        'post_title'   => nlmcp_text( $args, 'title', pathinfo( $filename, PATHINFO_FILENAME ) ),
        'post_content' => '',
        'post_excerpt' => nlmcp_text( $args, 'caption' ),
        'post_mime_type' => $filetype['type'],
        'post_status'  => 'inherit',
    ), $upload['file'] );
    if ( is_wp_error( $att_id ) ) {
        throw new Exception( $att_id->get_error_message() );
    }
    $meta = wp_generate_attachment_metadata( $att_id, $upload['file'] );
    wp_update_attachment_metadata( $att_id, $meta );
    if ( isset( $args['alt'] ) ) {
        update_post_meta( $att_id, '_wp_attachment_image_alt', sanitize_text_field( wp_unslash( $args['alt'] ) ) );
    }
    return nlmcp_media_summary( get_post( $att_id ) );
}

function nlmcp_tool_update_media( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $att = get_post( $id );
    if ( ! $att || 'attachment' !== $att->post_type ) {
        throw new Exception( 'Media not found.' );
    }
    $data = array( 'ID' => $id );
    if ( isset( $args['title'] ) ) {
        $data['post_title'] = nlmcp_text( $args, 'title' );
    }
    if ( isset( $args['caption'] ) ) {
        $data['post_excerpt'] = nlmcp_text( $args, 'caption' );
    }
    $updated = wp_update_post( $data, true );
    if ( is_wp_error( $updated ) ) {
        throw new Exception( $updated->get_error_message() );
    }
    if ( isset( $args['alt'] ) ) {
        update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( wp_unslash( $args['alt'] ) ) );
    }
    return nlmcp_media_summary( get_post( $id ) );
}

function nlmcp_tool_list_categories( $args ) {
    $terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
    $out = array();
    foreach ( $terms as $t ) {
        $out[] = array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count );
    }
    return array( 'categories' => $out );
}

function nlmcp_tool_create_category( $args ) {
    $name = nlmcp_text( $args, 'name' );
    if ( '' === $name ) {
        throw new Exception( 'Category name is required.' );
    }
    $result = wp_insert_term( $name, 'category', array( 'slug' => sanitize_title( nlmcp_text( $args, 'slug' ) ) ) );
    if ( is_wp_error( $result ) ) {
        throw new Exception( $result->get_error_message() );
    }
    $term = get_term( $result['term_id'], 'category' );
    return array( 'id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug );
}

function nlmcp_tool_update_category( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $term = get_term( $id, 'category' );
    if ( ! $term || is_wp_error( $term ) ) {
        throw new Exception( 'Category not found.' );
    }
    $update = array();
    if ( isset( $args['name'] ) ) {
        $name = nlmcp_text( $args, 'name' );
        if ( '' === $name ) {
            throw new Exception( 'Category name must not be empty.' );
        }
        $update['name'] = $name;
    }
    if ( isset( $args['slug'] ) ) {
        $update['slug'] = sanitize_title( nlmcp_text( $args, 'slug' ) );
    }
    if ( isset( $args['description'] ) && is_scalar( $args['description'] ) ) {
        $update['description'] = wp_kses_post( wp_unslash( $args['description'] ) );
    }
    if ( isset( $args['parent'] ) ) {
        $parent = absint( $args['parent'] );
        if ( 0 !== $parent ) {
            $parent_term = get_term( $parent, 'category' );
            if ( ! $parent_term || is_wp_error( $parent_term ) ) {
                throw new Exception( 'Parent category not found.' );
            }
        }
        $update['parent'] = $parent;
    }
    if ( empty( $update ) ) {
        throw new Exception( 'Nothing to update: provide at least one of name, slug, description, parent.' );
    }
    $result = wp_update_term( $id, 'category', $update );
    if ( is_wp_error( $result ) ) {
        throw new Exception( $result->get_error_message() );
    }
    $term = get_term( $result['term_id'], 'category' );
    return array(
        'id'          => $term->term_id,
        'name'        => $term->name,
        'slug'        => $term->slug,
        'description' => $term->description,
        'parent'      => (int) $term->parent,
        'count'       => (int) $term->count,
        'link'        => get_term_link( $term ),
    );
}

function nlmcp_tool_create_tag( $args ) {
    $name = nlmcp_text( $args, 'name' );
    if ( '' === $name ) {
        throw new Exception( 'Tag name is required.' );
    }
    $result = wp_insert_term( $name, 'post_tag', array( 'slug' => sanitize_title( nlmcp_text( $args, 'slug' ) ) ) );
    if ( is_wp_error( $result ) ) {
        throw new Exception( $result->get_error_message() );
    }
    $term = get_term( $result['term_id'], 'post_tag' );
    return array( 'id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug );
}

function nlmcp_tool_list_tags( $args ) {
    $terms = get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false ) );
    $out = array();
    foreach ( $terms as $t ) {
        $out[] = array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count );
    }
    return array( 'tags' => $out );
}

function nlmcp_tool_list_comments( $args ) {
    $status = nlmcp_text( $args, 'status', 'all' );
    $comments = get_comments( array(
        'status' => $status,
        'number' => min( max( nlmcp_int( $args, 'per_page', 20 ), 1 ), 100 ),
    ) );
    $out = array();
    foreach ( $comments as $c ) {
        $out[] = array(
            'id'      => (int) $c->comment_ID,
            'post_id' => (int) $c->comment_post_ID,
            'author'  => $c->comment_author,
            'content' => $c->comment_content,
            'status'  => wp_get_comment_status( $c ),
            'date'    => $c->comment_date,
        );
    }
    return array( 'comments' => $out );
}

function nlmcp_tool_approve_comment( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $comment = get_comment( $id );
    if ( ! $comment ) {
        throw new Exception( 'Comment not found.' );
    }
    wp_set_comment_status( $id, 'approve' );
    return array( 'id' => $id, 'status' => 'approved' );
}

function nlmcp_tool_trash_comment( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $comment = get_comment( $id );
    if ( ! $comment ) {
        throw new Exception( 'Comment not found.' );
    }
    wp_trash_comment( $id );
    return array( 'id' => $id, 'status' => 'trash' );
}

function nlmcp_user_summary( $user ) {
    return array(
        'id'    => $user->ID,
        'name'  => $user->display_name,
        'slug'  => $user->user_nicename,
        'email' => $user->user_email,
        'roles' => $user->roles,
    );
}

function nlmcp_tool_list_users( $args ) {
    $users = get_users( array( 'number' => min( max( nlmcp_int( $args, 'per_page', 20 ), 1 ), 100 ) ) );
    $out = array();
    foreach ( $users as $u ) {
        $out[] = nlmcp_user_summary( $u );
    }
    return array( 'users' => $out );
}

function nlmcp_tool_get_user( $args ) {
    $user = get_user_by( 'id', nlmcp_int( $args, 'id' ) );
    if ( ! $user ) {
        throw new Exception( 'User not found.' );
    }
    return nlmcp_user_summary( $user );
}

function nlmcp_tool_search_content( $args ) {
    $q = new WP_Query( array(
        'post_type'      => array( 'post', 'page' ),
        'post_status'    => array( 'publish', 'draft', 'pending' ),
        's'              => nlmcp_text( $args, 'query' ),
        'posts_per_page' => min( max( nlmcp_int( $args, 'per_page', 20 ), 1 ), 100 ),
    ) );
    $out = array();
    foreach ( $q->posts as $post ) {
        $out[] = nlmcp_post_summary( $post );
    }
    return array( 'total' => (int) $q->found_posts, 'results' => $out );
}

function nlmcp_tool_get_post_seo( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $post = get_post( $id );
    if ( ! $post ) {
        throw new Exception( 'Post not found.' );
    }
    return array(
        'id'               => $id,
        'seo_title'        => get_post_meta( $id, 'rank_math_title', true ),
        'meta_description' => get_post_meta( $id, 'rank_math_description', true ),
        'focus_keyword'    => get_post_meta( $id, 'rank_math_focus_keyword', true ),
    );
}

function nlmcp_tool_update_post_seo( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $post = get_post( $id );
    if ( ! $post ) {
        throw new Exception( 'Post not found.' );
    }
    if ( isset( $args['seo_title'] ) ) {
        update_post_meta( $id, 'rank_math_title', sanitize_text_field( wp_unslash( $args['seo_title'] ) ) );
    }
    if ( isset( $args['meta_description'] ) ) {
        update_post_meta( $id, 'rank_math_description', sanitize_text_field( wp_unslash( $args['meta_description'] ) ) );
    }
    if ( isset( $args['focus_keyword'] ) ) {
        update_post_meta( $id, 'rank_math_focus_keyword', sanitize_text_field( wp_unslash( $args['focus_keyword'] ) ) );
    }
    return nlmcp_tool_get_post_seo( array( 'id' => $id ) );
}

// Rank Math stores category SEO in term meta under the same keys it uses
// for posts, so the category SEO tools mirror the post SEO tools exactly.
function nlmcp_tool_get_category_seo( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $term = get_term( $id, 'category' );
    if ( ! $term || is_wp_error( $term ) ) {
        throw new Exception( 'Category not found.' );
    }
    return array(
        'id'               => $id,
        'seo_title'        => get_term_meta( $id, 'rank_math_title', true ),
        'meta_description' => get_term_meta( $id, 'rank_math_description', true ),
        'focus_keyword'    => get_term_meta( $id, 'rank_math_focus_keyword', true ),
    );
}

function nlmcp_tool_update_category_seo( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $term = get_term( $id, 'category' );
    if ( ! $term || is_wp_error( $term ) ) {
        throw new Exception( 'Category not found.' );
    }
    if ( isset( $args['seo_title'] ) ) {
        update_term_meta( $id, 'rank_math_title', sanitize_text_field( wp_unslash( $args['seo_title'] ) ) );
    }
    if ( isset( $args['meta_description'] ) ) {
        update_term_meta( $id, 'rank_math_description', sanitize_text_field( wp_unslash( $args['meta_description'] ) ) );
    }
    if ( isset( $args['focus_keyword'] ) ) {
        update_term_meta( $id, 'rank_math_focus_keyword', sanitize_text_field( wp_unslash( $args['focus_keyword'] ) ) );
    }
    return nlmcp_tool_get_category_seo( array( 'id' => $id ) );
}

function nlmcp_tool_list_plugins( $args ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $all = get_plugins();
    $out = array();
    foreach ( $all as $file => $data ) {
        $out[] = array(
            'file'    => $file,
            'name'    => $data['Name'],
            'version' => $data['Version'],
            'active'  => is_plugin_active( $file ),
        );
    }
    return array( 'plugins' => $out );
}

function nlmcp_tool_list_themes( $args ) {
    $themes = wp_get_themes();
    $active = get_stylesheet();
    $out = array();
    foreach ( $themes as $slug => $theme ) {
        $out[] = array(
            'slug'    => $slug,
            'name'    => $theme->get( 'Name' ),
            'version' => $theme->get( 'Version' ),
            'active'  => ( $slug === $active ),
        );
    }
    return array( 'themes' => $out );
}

/* --------------------------------------------------------------------------
 * New tools in 2.8.2 — categories/tags, media, pages, trash/restore,
 * comments, allowlisted options, cache purge. Same patterns as above:
 * nlmcp_int / nlmcp_text helpers, WordPress native functions only,
 * exceptions on any failure, capability enforced by the dispatcher.
 * ------------------------------------------------------------------------ */

function nlmcp_term_summary( $term ) {
    return array(
        'id'    => (int) $term->term_id,
        'name'  => $term->name,
        'slug'  => $term->slug,
        'count' => (int) $term->count,
    );
}

function nlmcp_tool_get_category( $args ) {
    $term = get_term( nlmcp_int( $args, 'id' ), 'category' );
    if ( ! $term || is_wp_error( $term ) ) {
        throw new Exception( 'Category not found.' );
    }
    return nlmcp_term_summary( $term );
}

function nlmcp_tool_delete_category( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $term = get_term( $id, 'category' );
    if ( ! $term || is_wp_error( $term ) ) {
        throw new Exception( 'Category not found.' );
    }
    // Guard: never silently reassign or orphan posts. The caller must
    // reassign or remove the posts first, then delete again.
    $count = (int) $term->count;
    if ( $count > 0 ) {
        throw new Exception( 'Refusing to delete category "' . $term->name . '" (ID ' . $id . '): it still has ' . $count . ' post(s) assigned. Reassign or remove those posts first; this tool never silently reassigns posts.' );
    }
    $result = wp_delete_term( $id, 'category' );
    if ( is_wp_error( $result ) || ! $result ) {
        throw new Exception( is_wp_error( $result ) ? $result->get_error_message() : 'Could not delete category.' );
    }
    return array( 'id' => $id, 'status' => 'deleted' );
}

function nlmcp_tool_get_tag( $args ) {
    $term = get_term( nlmcp_int( $args, 'id' ), 'post_tag' );
    if ( ! $term || is_wp_error( $term ) ) {
        throw new Exception( 'Tag not found.' );
    }
    return nlmcp_term_summary( $term );
}

function nlmcp_tool_update_tag( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $term = get_term( $id, 'post_tag' );
    if ( ! $term || is_wp_error( $term ) ) {
        throw new Exception( 'Tag not found.' );
    }
    $update = array();
    if ( isset( $args['name'] ) ) {
        $name = nlmcp_text( $args, 'name' );
        if ( '' === $name ) {
            throw new Exception( 'Tag name must not be empty.' );
        }
        $update['name'] = $name;
    }
    if ( isset( $args['slug'] ) ) {
        $update['slug'] = sanitize_title( nlmcp_text( $args, 'slug' ) );
    }
    if ( isset( $args['description'] ) && is_scalar( $args['description'] ) ) {
        $update['description'] = wp_kses_post( wp_unslash( $args['description'] ) );
    }
    if ( empty( $update ) ) {
        throw new Exception( 'Nothing to update: provide at least one of name, slug, description.' );
    }
    $result = wp_update_term( $id, 'post_tag', $update );
    if ( is_wp_error( $result ) ) {
        throw new Exception( $result->get_error_message() );
    }
    $term = get_term( $result['term_id'], 'post_tag' );
    return array(
        'id'          => $term->term_id,
        'name'        => $term->name,
        'slug'        => $term->slug,
        'description' => $term->description,
        'count'       => (int) $term->count,
        'link'        => get_term_link( $term ),
    );
}

function nlmcp_tool_delete_tag( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $term = get_term( $id, 'post_tag' );
    if ( ! $term || is_wp_error( $term ) ) {
        throw new Exception( 'Tag not found.' );
    }
    $result = wp_delete_term( $id, 'post_tag' );
    if ( is_wp_error( $result ) || ! $result ) {
        throw new Exception( is_wp_error( $result ) ? $result->get_error_message() : 'Could not delete tag.' );
    }
    return array( 'id' => $id, 'status' => 'deleted' );
}

function nlmcp_tool_delete_media( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $att = get_post( $id );
    if ( ! $att || 'attachment' !== $att->post_type ) {
        throw new Exception( 'Media not found.' );
    }
    // Guard: never delete an attachment that is a featured image
    // somewhere — deleting it would leave a broken featured image.
    $users = new WP_Query( array(
        'post_type'      => array( 'post', 'page' ),
        'post_status'    => 'any',
        'meta_key'       => '_thumbnail_id',
        'meta_value'     => $id,
        'fields'         => 'ids',
        'posts_per_page' => 1,
    ) );
    if ( ! empty( $users->posts ) ) {
        throw new Exception( 'Refusing to delete media ID ' . $id . ': it is set as the featured image on post/page ID ' . (int) $users->posts[0] . '. Remove it as featured image first, then delete again.' );
    }
    $deleted = wp_delete_attachment( $id );
    if ( ! $deleted ) {
        throw new Exception( 'Could not delete media.' );
    }
    // wp_delete_attachment trashes when trash is enabled, deletes
    // permanently otherwise — report whichever actually happened.
    $after = get_post( $id );
    $status = ( $after && 'trash' === $after->post_status ) ? 'trash' : 'deleted';
    return array( 'id' => $id, 'status' => $status );
}

function nlmcp_tool_delete_page( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $post = get_post( $id );
    if ( ! $post || 'page' !== $post->post_type ) {
        throw new Exception( 'Page not found.' );
    }
    $result = wp_trash_page( $id );
    if ( ! $result ) {
        throw new Exception( 'Could not trash page.' );
    }
    return array( 'id' => $id, 'status' => 'trash' );
}

function nlmcp_tool_restore_post( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $post = get_post( $id );
    if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
        throw new Exception( 'Post or page not found.' );
    }
    $result = wp_untrash_post( $id );
    if ( ! $result ) {
        throw new Exception( 'Could not restore post or page (is it in trash?).' );
    }
    $restored = get_post( $id );
    return array( 'id' => $id, 'status' => $restored ? $restored->post_status : 'restored' );
}

function nlmcp_tool_update_comment( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $comment = get_comment( $id );
    if ( ! $comment ) {
        throw new Exception( 'Comment not found.' );
    }
    $changed = false;
    if ( isset( $args['status'] ) ) {
        $status = nlmcp_text( $args, 'status' );
        if ( ! in_array( $status, array( 'approve', 'hold', 'spam', 'trash' ), true ) ) {
            throw new Exception( 'Invalid comment status "' . $status . '". Use one of: approve, hold, spam, trash.' );
        }
        wp_set_comment_status( $id, $status );
        $changed = true;
    }
    if ( isset( $args['content'] ) && is_scalar( $args['content'] ) ) {
        $updated = wp_update_comment( array(
            'comment_ID'      => $id,
            'comment_content' => wp_kses_post( wp_unslash( $args['content'] ) ),
        ) );
        if ( ! $updated ) {
            throw new Exception( 'Could not update comment content.' );
        }
        $changed = true;
    }
    if ( ! $changed ) {
        throw new Exception( 'Nothing to update: provide at least one of status, content.' );
    }
    $comment = get_comment( $id );
    return array(
        'id'      => $id,
        'status'  => wp_get_comment_status( $comment ),
        'content' => $comment->comment_content,
    );
}

function nlmcp_tool_delete_comment( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $comment = get_comment( $id );
    if ( ! $comment ) {
        throw new Exception( 'Comment not found.' );
    }
    wp_trash_comment( $id );
    return array( 'id' => $id, 'status' => 'trash' );
}

function nlmcp_tool_update_option( $args ) {
    // Full-admin scope (v2.8.3): ANY option name. Capability
    // manage_options is enforced by the dispatcher, exactly as wp-admin
    // requires. This tool writes exactly what it is given — the caller
    // is responsible for only changing options deliberately.
    $option = nlmcp_text( $args, 'option' );
    if ( '' === $option ) {
        throw new Exception( 'An option name is required.' );
    }
    if ( ! isset( $args['value'] ) ) {
        throw new Exception( 'A value is required.' );
    }
    $value = $args['value'];
    if ( is_string( $value ) || is_array( $value ) ) {
        $value = wp_unslash( $value );
    }
    update_option( $option, $value );
    return array( 'option' => $option, 'value' => get_option( $option ) );
}

function nlmcp_tool_purge_cache( $args ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    // Never a fake success: if LiteSpeed Cache is not active, or its
    // purge hook is not registered, say so and purge nothing.
    if ( ! is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
        throw new Exception( 'LiteSpeed Cache is not active on this site. Nothing was purged.' );
    }
    if ( ! has_action( 'litespeed_purge_all' ) ) {
        throw new Exception( 'LiteSpeed Cache is active, but its purge-all hook is not registered. Nothing was purged.' );
    }
    do_action( 'litespeed_purge_all' );
    return array( 'purged' => true, 'method' => 'litespeed_purge_all' );
}

/* --------------------------------------------------------------------------
 * Full-admin tools (v2.8.3): users, plugins, themes, menus, revisions,
 * unrestricted options. Capabilities are enforced by the dispatcher,
 * exactly as wp-admin enforces them. The ONLY built-in refusals are:
 * deactivating/deleting this plugin itself, and deleting the active theme.
 * Everything else is available to the authenticated admin user, as in
 * wp-admin. Password changes are only ever invoked with the site owner
 * explicit per-action approval — that is a usage rule, not a tool limit.
 * ------------------------------------------------------------------------ */

function nlmcp_resolve_plugin_file( $slug ) {
    $slug = trim( $slug );
    if ( '' === $slug ) {
        return false;
    }
    if ( false !== strpos( $slug, '/' ) ) {
        return $slug;
    }
    $plugins = get_plugins();
    foreach ( $plugins as $file => $data ) {
        if ( $slug === dirname( $file ) ) {
            return $file;
        }
    }
    return false;
}

function nlmcp_is_self_plugin( $file ) {
    // Covers every install layout: canonical folder (nolimit-mcp/), a
    // versioned folder (nolimit-mcp-2.8.3/), or a single-file install.
    if ( $file === plugin_basename( __FILE__ ) ) {
        return true;
    }
    $base = basename( $file );
    $dir = dirname( $file );
    if ( 'nolimit-mcp.php' === $base && ( '.' === $dir || 0 === strpos( $dir, 'nolimit-mcp' ) ) ) {
        return true;
    }
    return false;
}

function nlmcp_require_admin_includes( $files ) {
    foreach ( $files as $rel ) {
        $path = ABSPATH . $rel;
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
}

function nlmcp_theme_file_path( $stylesheet, $rel ) {
    $theme = wp_get_theme( $stylesheet );
    if ( ! $theme || ! $theme->exists() ) {
        throw new Exception( 'Theme not found: ' . $stylesheet );
    }
    $root = get_theme_root( $stylesheet );
    $dir = rtrim( $root, '/' ) . '/' . $stylesheet;
    $real_dir = realpath( $dir );
    if ( false === $real_dir ) {
        throw new Exception( 'Theme directory not found.' );
    }
    $rel = trim( str_replace( '\\', '/', $rel ), '/' );
    if ( '' === $rel ) {
        throw new Exception( 'A relative file path is required.' );
    }
    // Lexical normalisation: resolve . and .. without touching the disk,
    // so a path escaping the theme directory is caught even for files
    // that do not exist yet (writes).
    $parts = array();
    foreach ( explode( '/', $rel ) as $seg ) {
        if ( '' === $seg || '.' === $seg ) {
            continue;
        }
        if ( '..' === $seg ) {
            if ( empty( $parts ) ) {
                throw new Exception( 'File path escapes the theme directory. Refused.' );
            }
            array_pop( $parts );
            continue;
        }
        $parts[] = $seg;
    }
    if ( empty( $parts ) ) {
        throw new Exception( 'A relative file path is required.' );
    }
    $clean = implode( '/', $parts );
    $full = $real_dir . '/' . $clean;
    if ( 0 !== strpos( $full, $real_dir . '/' ) ) {
        throw new Exception( 'File path escapes the theme directory. Refused.' );
    }
    return array( $theme, $real_dir, $full, $clean );
}

function nlmcp_tool_create_user( $args ) {
    $username = sanitize_user( nlmcp_text( $args, 'username' ) );
    $email = sanitize_email( nlmcp_text( $args, 'email' ) );
    $role = sanitize_key( nlmcp_text( $args, 'role' ) );
    $password = isset( $args['password'] ) && is_scalar( $args['password'] ) ? (string) wp_unslash( $args['password'] ) : '';
    if ( '' === $username ) {
        throw new Exception( 'A username is required.' );
    }
    if ( ! is_email( $email ) ) {
        throw new Exception( 'A valid email is required.' );
    }
    if ( null === get_role( $role ) ) {
        throw new Exception( 'Unknown role: ' . $role );
    }
    if ( '' === $password ) {
        throw new Exception( 'A password is required.' );
    }
    $id = wp_insert_user( array(
        'user_login' => $username,
        'user_email' => $email,
        'role'       => $role,
        'user_pass'  => $password,
    ) );
    if ( is_wp_error( $id ) ) {
        throw new Exception( $id->get_error_message() );
    }
    return array( 'id' => (int) $id, 'username' => $username, 'email' => $email, 'role' => $role );
}

function nlmcp_tool_update_user( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $user = get_user_by( 'id', $id );
    if ( ! $user ) {
        throw new Exception( 'User not found.' );
    }
    $update = array( 'ID' => $id );
    if ( isset( $args['email'] ) ) {
        $email = sanitize_email( nlmcp_text( $args, 'email' ) );
        if ( ! is_email( $email ) ) {
            throw new Exception( 'A valid email is required.' );
        }
        $update['user_email'] = $email;
    }
    if ( isset( $args['role'] ) ) {
        $role = sanitize_key( nlmcp_text( $args, 'role' ) );
        if ( null === get_role( $role ) ) {
            throw new Exception( 'Unknown role: ' . $role );
        }
        $update['role'] = $role;
    }
    if ( isset( $args['display_name'] ) ) {
        $update['display_name'] = nlmcp_text( $args, 'display_name' );
    }
    if ( isset( $args['password'] ) ) {
        $pw = is_scalar( $args['password'] ) ? (string) wp_unslash( $args['password'] ) : '';
        if ( '' === $pw ) {
            throw new Exception( 'Password must not be empty.' );
        }
        $update['user_pass'] = $pw;
    }
    if ( count( $update ) < 2 ) {
        throw new Exception( 'Nothing to update: provide at least one of email, role, display_name, password.' );
    }
    $result = wp_update_user( $update );
    if ( is_wp_error( $result ) ) {
        throw new Exception( $result->get_error_message() );
    }
    $fresh = get_userdata( $id );
    return array(
        'id'           => $id,
        'username'     => $fresh->user_login,
        'email'        => $fresh->user_email,
        'roles'        => $fresh->roles,
        'display_name' => $fresh->display_name,
    );
}

function nlmcp_tool_delete_user( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $user = get_user_by( 'id', $id );
    if ( ! $user ) {
        throw new Exception( 'User not found.' );
    }
    if ( ! isset( $args['reassign_to'] ) ) {
        throw new Exception( 'reassign_to (another user ID) is REQUIRED. Refusing to delete without it.' );
    }
    $reassign = nlmcp_int( $args, 'reassign_to' );
    if ( $reassign <= 0 || ! get_user_by( 'id', $reassign ) ) {
        throw new Exception( 'reassign_to must be an existing user ID.' );
    }
    if ( $reassign === $id ) {
        throw new Exception( 'reassign_to must be a different user.' );
    }
    $ok = wp_delete_user( $id, $reassign );
    if ( ! $ok ) {
        throw new Exception( 'Could not delete user.' );
    }
    return array( 'id' => $id, 'status' => 'deleted', 'reassigned_to' => $reassign );
}

function nlmcp_tool_deactivate_plugin( $args ) {
    nlmcp_require_admin_includes( array( 'wp-admin/includes/plugin.php' ) );
    $slug = nlmcp_text( $args, 'slug' );
    $file = nlmcp_resolve_plugin_file( $slug );
    if ( false === $file ) {
        throw new Exception( 'Plugin not found: ' . $slug );
    }
    if ( nlmcp_is_self_plugin( $file ) ) {
        throw new Exception( 'Refusing to deactivate this plugin itself (nolimit-mcp): doing so would kill the tool mid-task.' );
    }
    if ( ! is_plugin_active( $file ) ) {
        return array( 'plugin' => $file, 'status' => 'already_inactive' );
    }
    deactivate_plugins( $file );
    return array( 'plugin' => $file, 'status' => 'deactivated' );
}

function nlmcp_tool_install_plugin( $args ) {
    nlmcp_require_admin_includes( array(
        'wp-admin/includes/plugin.php',
        'wp-admin/includes/plugin-install.php',
        'wp-admin/includes/class-wp-upgrader.php',
        'wp-admin/includes/class-automatic-upgrader-skin.php',
        'wp-admin/includes/file.php',
    ) );
    $slug = sanitize_key( nlmcp_text( $args, 'slug' ) );
    if ( '' === $slug ) {
        throw new Exception( 'A wordpress.org plugin slug is required.' );
    }
    $activate = ! empty( $args['activate'] );
    $api = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'download_link' => true ) ) );
    if ( is_wp_error( $api ) ) {
        throw new Exception( $api->get_error_message() );
    }
    if ( empty( $api->download_link ) ) {
        throw new Exception( 'No download link found for plugin: ' . $slug );
    }
    $upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
    $result = $upgrader->install( $api->download_link );
    if ( is_wp_error( $result ) ) {
        throw new Exception( $result->get_error_message() );
    }
    if ( ! $result ) {
        throw new Exception( 'Plugin install failed.' );
    }
    $file = $upgrader->plugin_info();
    if ( '' === (string) $file ) {
        $file = nlmcp_resolve_plugin_file( $slug );
    }
    $activated = false;
    if ( $activate && $file ) {
        $act = activate_plugin( $file );
        if ( is_wp_error( $act ) ) {
            throw new Exception( 'Installed, but activation failed: ' . $act->get_error_message() );
        }
        $activated = true;
    }
    return array( 'slug' => $slug, 'plugin' => $file, 'activated' => $activated );
}

function nlmcp_tool_delete_plugin( $args ) {
    nlmcp_require_admin_includes( array( 'wp-admin/includes/plugin.php' ) );
    $slug = nlmcp_text( $args, 'slug' );
    $file = nlmcp_resolve_plugin_file( $slug );
    if ( false === $file ) {
        throw new Exception( 'Plugin not found: ' . $slug );
    }
    if ( nlmcp_is_self_plugin( $file ) ) {
        throw new Exception( 'Refusing to delete this plugin itself (nolimit-mcp): doing so would kill the tool mid-task.' );
    }
    $result = delete_plugins( array( $file ) );
    if ( is_wp_error( $result ) ) {
        throw new Exception( $result->get_error_message() );
    }
    if ( empty( $result ) ) {
        throw new Exception( 'Could not delete plugin. If it is still active, deactivate it first.' );
    }
    return array( 'plugin' => $file, 'status' => 'deleted' );
}

function nlmcp_tool_activate_theme( $args ) {
    $stylesheet = sanitize_key( nlmcp_text( $args, 'stylesheet' ) );
    $theme = wp_get_theme( $stylesheet );
    if ( ! $theme || ! $theme->exists() ) {
        throw new Exception( 'Theme not found: ' . $stylesheet );
    }
    switch_theme( $stylesheet );
    return array( 'stylesheet' => $stylesheet, 'name' => $theme->get( 'Name' ), 'active' => true );
}

function nlmcp_tool_install_theme( $args ) {
    nlmcp_require_admin_includes( array(
        'wp-admin/includes/theme.php',
        'wp-admin/includes/class-wp-upgrader.php',
        'wp-admin/includes/class-automatic-upgrader-skin.php',
        'wp-admin/includes/file.php',
    ) );
    $slug = sanitize_key( nlmcp_text( $args, 'stylesheet' ) );
    if ( '' === $slug ) {
        throw new Exception( 'A wordpress.org theme slug is required.' );
    }
    $api = themes_api( 'theme_information', array( 'slug' => $slug, 'fields' => array( 'download_link' => true ) ) );
    if ( is_wp_error( $api ) ) {
        throw new Exception( $api->get_error_message() );
    }
    if ( empty( $api->download_link ) ) {
        throw new Exception( 'No download link found for theme: ' . $slug );
    }
    $upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
    $result = $upgrader->install( $api->download_link );
    if ( is_wp_error( $result ) ) {
        throw new Exception( $result->get_error_message() );
    }
    if ( ! $result ) {
        throw new Exception( 'Theme install failed.' );
    }
    $theme = wp_get_theme( $slug );
    if ( ! $theme || ! $theme->exists() ) {
        throw new Exception( 'Theme installed, but it is not visible to WordPress afterwards.' );
    }
    return array( 'stylesheet' => $slug, 'name' => $theme->get( 'Name' ), 'installed' => true );
}

function nlmcp_tool_delete_theme( $args ) {
    nlmcp_require_admin_includes( array( 'wp-admin/includes/theme.php' ) );
    $stylesheet = sanitize_key( nlmcp_text( $args, 'stylesheet' ) );
    $theme = wp_get_theme( $stylesheet );
    if ( ! $theme || ! $theme->exists() ) {
        throw new Exception( 'Theme not found: ' . $stylesheet );
    }
    if ( get_stylesheet() === $stylesheet ) {
        throw new Exception( 'Refusing to delete the active theme: ' . $stylesheet );
    }
    $result = delete_theme( $stylesheet );
    if ( is_wp_error( $result ) ) {
        throw new Exception( $result->get_error_message() );
    }
    if ( ! $result ) {
        throw new Exception( 'Could not delete theme.' );
    }
    return array( 'stylesheet' => $stylesheet, 'status' => 'deleted' );
}

function nlmcp_tool_get_theme_file( $args ) {
    $theme_arg = sanitize_key( nlmcp_text( $args, 'theme' ) );
    $file_arg = isset( $args['file'] ) && is_scalar( $args['file'] ) ? (string) wp_unslash( $args['file'] ) : '';
    list( $theme, $dir, $full, $rel ) = nlmcp_theme_file_path( $theme_arg, $file_arg );
    if ( ! is_file( $full ) ) {
        throw new Exception( 'File not found in theme: ' . $rel );
    }
    $content = file_get_contents( $full );
    if ( false === $content ) {
        throw new Exception( 'Could not read file: ' . $rel );
    }
    return array( 'theme' => $theme->get_stylesheet(), 'file' => $rel, 'content' => $content );
}

function nlmcp_tool_update_theme_file( $args ) {
    $theme_arg = sanitize_key( nlmcp_text( $args, 'theme' ) );
    $file_arg = isset( $args['file'] ) && is_scalar( $args['file'] ) ? (string) wp_unslash( $args['file'] ) : '';
    list( $theme, $dir, $full, $rel ) = nlmcp_theme_file_path( $theme_arg, $file_arg );
    if ( ! isset( $args['content'] ) || ! is_scalar( $args['content'] ) ) {
        throw new Exception( 'File content is required.' );
    }
    $content = (string) wp_unslash( $args['content'] );
    $parent = dirname( $full );
    if ( ! is_dir( $parent ) ) {
        throw new Exception( 'Parent directory does not exist inside the theme: ' . $rel );
    }
    nlmcp_require_admin_includes( array( 'wp-admin/includes/file.php' ) );
    global $wp_filesystem;
    if ( ! $wp_filesystem ) {
        WP_Filesystem();
    }
    if ( ! $wp_filesystem ) {
        throw new Exception( 'Could not initialise the WordPress filesystem.' );
    }
    $ok = $wp_filesystem->put_contents( $full, $content, FS_CHMOD_FILE );
    if ( ! $ok ) {
        throw new Exception( 'Could not write file: ' . $rel );
    }
    return array( 'theme' => $theme->get_stylesheet(), 'file' => $rel, 'written' => true, 'bytes' => strlen( $content ) );
}

function nlmcp_tool_list_menus( $args ) {
    $menus = wp_get_nav_menus();
    $out = array();
    foreach ( $menus as $m ) {
        $out[] = array(
            'id'    => (int) $m->term_id,
            'name'  => $m->name,
            'slug'  => $m->slug,
            'count' => (int) $m->count,
        );
    }
    return array( 'menus' => $out );
}

function nlmcp_tool_create_menu( $args ) {
    $name = nlmcp_text( $args, 'name' );
    if ( '' === $name ) {
        throw new Exception( 'A menu name is required.' );
    }
    $id = wp_create_nav_menu( $name );
    if ( is_wp_error( $id ) ) {
        throw new Exception( $id->get_error_message() );
    }
    return array( 'id' => (int) $id, 'name' => $name );
}

function nlmcp_tool_update_menu_item( $args ) {
    $menu_id = nlmcp_int( $args, 'menu_id' );
    $menu = wp_get_nav_menu_object( $menu_id );
    if ( ! $menu ) {
        throw new Exception( 'Menu not found.' );
    }
    $item_id = nlmcp_int( $args, 'item_id' );
    $title = isset( $args['title'] ) ? nlmcp_text( $args, 'title' ) : '';
    if ( '' === $title ) {
        throw new Exception( 'An item title is required.' );
    }
    $data = array( 'menu-item-title' => $title );
    if ( isset( $args['url'] ) && '' !== nlmcp_text( $args, 'url' ) ) {
        $data['menu-item-type'] = 'custom';
        $data['menu-item-url'] = esc_url_raw( nlmcp_text( $args, 'url' ) );
    } elseif ( isset( $args['object'] ) && isset( $args['object_id'] ) ) {
        $object = sanitize_key( nlmcp_text( $args, 'object' ) );
        $object_id = nlmcp_int( $args, 'object_id' );
        if ( 'post' === $object || 'page' === $object ) {
            $data['menu-item-type'] = 'post_type';
            $data['menu-item-object'] = $object;
            $data['menu-item-object-id'] = $object_id;
        } elseif ( 'category' === $object || 'post_tag' === $object ) {
            $data['menu-item-type'] = 'taxonomy';
            $data['menu-item-object'] = $object;
            $data['menu-item-object-id'] = $object_id;
        } else {
            throw new Exception( 'object must be post, page, category or post_tag (or pass url for a custom link).' );
        }
    } elseif ( 0 === $item_id ) {
        throw new Exception( 'A new menu item needs url (custom link) or object + object_id.' );
    }
    $result = wp_update_nav_menu_item( $menu_id, $item_id, $data );
    if ( is_wp_error( $result ) ) {
        throw new Exception( $result->get_error_message() );
    }
    if ( ! $result ) {
        throw new Exception( 'Could not save menu item.' );
    }
    return array( 'menu_id' => $menu_id, 'item_id' => (int) $result );
}

function nlmcp_tool_delete_menu( $args ) {
    $menu_id = nlmcp_int( $args, 'menu_id' );
    $menu = wp_get_nav_menu_object( $menu_id );
    if ( ! $menu ) {
        throw new Exception( 'Menu not found.' );
    }
    $ok = wp_delete_nav_menu( $menu_id );
    if ( is_wp_error( $ok ) ) {
        throw new Exception( $ok->get_error_message() );
    }
    if ( ! $ok ) {
        throw new Exception( 'Could not delete menu.' );
    }
    return array( 'menu_id' => $menu_id, 'status' => 'deleted' );
}

function nlmcp_tool_list_revisions( $args ) {
    $id = nlmcp_int( $args, 'id' );
    $post = get_post( $id );
    if ( ! $post ) {
        throw new Exception( 'Post not found.' );
    }
    $revs = wp_get_post_revisions( $id );
    $out = array();
    foreach ( $revs as $rev ) {
        $author = get_userdata( $rev->post_author );
        $out[] = array(
            'id'        => (int) $rev->ID,
            'date'      => $rev->post_date,
            'author_id' => (int) $rev->post_author,
            'author'    => $author ? $author->display_name : '',
            'title'     => $rev->post_title,
            'status'    => $rev->post_status,
        );
    }
    return array( 'post_id' => $id, 'revisions' => $out );
}

function nlmcp_tool_get_option( $args ) {
    $option = nlmcp_text( $args, 'option' );
    if ( '' === $option ) {
        throw new Exception( 'An option name is required.' );
    }
    return array( 'option' => $option, 'value' => get_option( $option ) );
}

/* --------------------------------------------------------------------------
 * Authentication — WordPress Application Passwords, verified locally.
 * No cloud, no account, no third party ever sees the credential.
 * ------------------------------------------------------------------------ */

function nlmcp_get_auth_header( $request ) {
    // Some hosts (Apache CGI/FastCGI) do not pass the Authorization header
    // to PHP via the REST layer. Check every source before giving up.
    $auth = $request->get_header( 'authorization' );
    if ( $auth ) {
        return $auth;
    }
    if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) && '' !== $_SERVER['HTTP_AUTHORIZATION'] ) {
        return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
    }
    if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) && '' !== $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) {
        return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
    }
    if ( function_exists( 'apache_request_headers' ) ) {
        $headers = apache_request_headers();
        if ( is_array( $headers ) ) {
            if ( isset( $headers['Authorization'] ) ) {
                return $headers['Authorization'];
            }
            if ( isset( $headers['authorization'] ) ) {
                return $headers['authorization'];
            }
        }
    }
    return '';
}

function nlmcp_authenticate( $request ) {
    // OAuth 2.1 Bearer token (ChatGPT, Claude, any OAuth MCP client).
    $auth_header = nlmcp_get_auth_header( $request );
    if ( $auth_header && 0 === stripos( $auth_header, 'Bearer ' ) ) {
        $token  = substr( $auth_header, 7 );
        $tokens = get_option( 'nlmcp_oauth_tokens', array() );
        $rec    = isset( $tokens[ hash( 'sha256', $token ) ] ) ? $tokens[ hash( 'sha256', $token ) ] : null;
        if ( $rec && $rec['expires'] > time() ) {
            $user = get_user_by( 'id', $rec['user_id'] );
            if ( $user ) {
                wp_set_current_user( $user->ID );
                return $user;
            }
        }
        return false;
    }

    // Already logged in via cookie (e.g. testing from wp-admin).
    $current = wp_get_current_user();
    if ( $current && $current->ID ) {
        return $current;
    }

    $auth = nlmcp_get_auth_header( $request );
    if ( ! $auth || 0 !== stripos( $auth, 'Basic ' ) ) {
        return false;
    }
    $decoded = base64_decode( substr( $auth, 6 ), true );
    if ( false === $decoded || false === strpos( $decoded, ':' ) ) {
        return false;
    }
    list( $username, $password ) = explode( ':', $decoded, 2 );

    if ( ! function_exists( 'wp_authenticate_application_password' ) ) {
        return false;
    }
    $user = wp_authenticate_application_password( null, $username, $password );
    if ( ! $user ) {
        return false;
    }
    wp_set_current_user( $user->ID );
    return $user;
}

/* --------------------------------------------------------------------------
 * MCP protocol handler (Streamable HTTP, JSON-RPC 2.0)
 * ------------------------------------------------------------------------ */

/* Diagnostic: remember the last few initialize handshakes (no secrets:
 * only the requested protocol version, Accept header and client name).
 * Shown on the Settings page so a failed client handshake can be
 * diagnosed from evidence instead of guesses. */
function nlmcp_record_handshake( $requested_version, $request ) {
    $entry = array(
        'time'    => current_time( 'mysql' ),
        'version' => $requested_version,
        'accept'  => (string) $request->get_header( 'Accept' ),
        'ua'      => (string) $request->get_header( 'User-Agent' ),
    );
    $list = get_option( 'nlmcp_handshakes', array() );
    if ( ! is_array( $list ) ) {
        $list = array();
    }
    array_unshift( $list, $entry );
    update_option( 'nlmcp_handshakes', array_slice( $list, 0, 5 ), false );
}

function nlmcp_record_tool_call( $tool, $args, $result, $request ) {
    // Witness log: proves what the server actually returned for a given
    // client call (tool, id/slug, result size). No content bodies, no
    // secrets — only sizes and identifiers, so a "client got empty"
    // claim can be checked against server evidence.
    $idpart = '';
    if ( isset( $args['id'] ) ) {
        $idpart = 'id=' . $args['id'];
    } elseif ( isset( $args['slug'] ) ) {
        $idpart = 'slug=' . $args['slug'];
    }
    $entry = array(
        'time'         => current_time( 'mysql' ),
        'tool'         => $tool,
        'args'         => $idpart,
        'result_bytes' => strlen( (string) wp_json_encode( $result ) ),
        'ua'           => (string) $request->get_header( 'User-Agent' ),
    );
    $list = get_option( 'nlmcp_tool_calls', array() );
    if ( ! is_array( $list ) ) {
        $list = array();
    }
    array_unshift( $list, $entry );
    update_option( 'nlmcp_tool_calls', array_slice( $list, 0, 10 ), false );
}

function nlmcp_handle_mcp( $request ) {
    // GET = discovery info, not an MCP call.
    if ( 'GET' === $request->get_method() ) {
        return rest_ensure_response( array(
            'name'     => 'nolimit-mcp',
            'version'  => NLMCP_VERSION,
            'endpoint' => rest_url( NLMCP_ROUTE_NAMESPACE . '/mcp' ),
            'tools'    => count( nlmcp_get_tools() ),
            'limits'   => 'none',
            // Diagnostic only: whether ANY Authorization header reached PHP
            // on this request (boolean, never the value). Used to detect
            // hosts that strip the header before it reaches WordPress.
            'auth_header_seen' => ( '' !== nlmcp_get_auth_header( $request ) ),
        ) );
    }

    $user = nlmcp_authenticate( $request );
    if ( ! $user ) {
        // RFC 9728: point clients at the protected-resource metadata so
        // they can discover the OAuth server from the 401 alone.
        $resp = new WP_REST_Response( array(
            'jsonrpc' => '2.0',
            'id'      => null,
            'error'   => array(
                'code'    => -32001,
                'message' => 'Unauthorized. Send HTTP Basic auth with a WordPress username and Application Password (Users > Profile > Application Passwords).',
            ),
        ), 401 );
        $resp->header( 'WWW-Authenticate', 'Bearer realm="nolimit-mcp", resource_metadata="' . home_url( '/.well-known/oauth-protected-resource' ) . '"' );
        return $resp;
    }

    $body = $request->get_json_params();
    if ( ! is_array( $body ) || ! isset( $body['method'] ) ) {
        return new WP_REST_Response( array(
            'jsonrpc' => '2.0',
            'id'      => isset( $body['id'] ) ? $body['id'] : null,
            'error'   => array( 'code' => -32600, 'message' => 'Invalid Request: expected a JSON-RPC object with a method.' ),
        ), 400 );
    }

    $method = $body['method'];
    $id     = isset( $body['id'] ) ? $body['id'] : null;
    $params = isset( $body['params'] ) && is_array( $body['params'] ) ? $body['params'] : array();

    // Notifications get no response body at all (204, empty).
    if ( 0 === strpos( $method, 'notifications/' ) ) {
        return new WP_REST_Response( '', 204 );
    }

    $ok = function ( $result ) use ( $id ) {
        return rest_ensure_response( array(
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ) );
    };
    $err = function ( $code, $message ) use ( $id ) {
        return rest_ensure_response( array(
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => array( 'code' => $code, 'message' => $message ),
        ) );
    };

    if ( 'initialize' === $method ) {
        // Protocol negotiation: our implementation is version-agnostic,
        // so echo ANY well-formed date-style version the client asks for.
        // Restricting the echo to a short allowlist made newer clients
        // (which send a version we had not listed yet) fall back to an
        // old version they no longer accept, breaking action discovery.
        $requested = isset( $params['protocolVersion'] ) ? sanitize_text_field( $params['protocolVersion'] ) : '';
        $version = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $requested ) ? $requested : '2024-11-05';
        nlmcp_record_handshake( $requested, $request );
        return $ok( array(
            'protocolVersion' => $version,
            'capabilities'    => array( 'tools' => new stdClass() ),
            'serverInfo'      => array( 'name' => 'nolimit-mcp', 'version' => NLMCP_VERSION ),
            'instructions'    => 'WordPress content management tools. New posts default to draft unless publish is explicitly requested; deletes go to trash. Use get_site_info first to confirm the site.',
        ) );
    }

    if ( 'ping' === $method ) {
        return $ok( new stdClass() );
    }

    if ( 'tools/list' === $method ) {
        $tools = array();
        foreach ( nlmcp_get_tools() as $name => $def ) {
            $tools[] = array(
                'name'        => $name,
                'description' => $def['description'],
                'inputSchema' => $def['inputSchema'],
            );
        }
        return $ok( array( 'tools' => $tools ) );
    }

    if ( 'tools/call' === $method ) {
        $name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
        $args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
        $defs = nlmcp_get_tools();

        if ( ! isset( $defs[ $name ] ) ) {
            return $ok( array(
                'content' => array( array( 'type' => 'text', 'text' => 'Unknown tool: ' . $name ) ),
                'isError' => true,
            ) );
        }
        $def = $defs[ $name ];

        if ( ! current_user_can( $def['capability'] ) ) {
            return $ok( array(
                'content' => array( array( 'type' => 'text', 'text' => 'Forbidden: your user lacks the capability "' . $def['capability'] . '" for this tool.' ) ),
                'isError' => true,
            ) );
        }

        try {
            $result = call_user_func( $def['handler'], $args );
            nlmcp_record_tool_call( $name, $args, $result, $request );
            // Modern clients (2025-06-18+) prefer structuredContent: the
            // raw result object alongside the text block. Some clients
            // mishandle very long text blocks (large HTML articles);
            // giving them the parsed object as well removes a whole
            // class of client-side parsing failures.
            return $ok( array(
                'content' => array( array(
                    'type' => 'text',
                    'text' => wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
                ) ),
                'structuredContent' => $result,
            ) );
        } catch ( Exception $e ) {
            return $ok( array(
                'content' => array( array( 'type' => 'text', 'text' => 'Error: ' . $e->getMessage() ) ),
                'isError' => true,
            ) );
        }
    }

    return $err( -32601, 'Method not found: ' . $method );
}

add_action( 'rest_api_init', function () {
    register_rest_route( NLMCP_ROUTE_NAMESPACE, '/mcp', array(
        'methods'             => array( 'GET', 'POST' ),
        'callback'            => 'nlmcp_handle_mcp',
        'permission_callback' => '__return_true',
    ) );
} );

/* --------------------------------------------------------------------------
 * Admin settings page — endpoint + connection instructions.
 * ------------------------------------------------------------------------ */

add_action( 'admin_menu', function () {
    add_options_page(
        'NoLimit MCP',
        'NoLimit MCP',
        'manage_options',
        'nolimit-mcp',
        'nlmcp_render_settings_page'
    );
} );

function nlmcp_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Forbidden.' );
    }

    // Revoke all OAuth access.
    if ( isset( $_POST['nlmcp_revoke_all'] ) && check_admin_referer( 'nlmcp_revoke_all' ) ) {
        delete_option( 'nlmcp_oauth_tokens' );
        delete_option( 'nlmcp_oauth_refresh' );
        delete_option( 'nlmcp_oauth_codes' );
        echo '<div class="notice notice-success"><p>All OAuth access revoked. Clients must sign in again.</p></div>';
    }

    $endpoint = rest_url( NLMCP_ROUTE_NAMESPACE . '/mcp' );
    $count    = count( nlmcp_get_tools() );
    $tokens   = get_option( 'nlmcp_oauth_tokens', array() );
    $active   = 0;
    foreach ( $tokens as $t ) {
        if ( $t['expires'] > time() ) {
            $active++;
        }
    }
    ?>
    <div class="wrap">
        <h1>NoLimit MCP for WordPress</h1>
        <p>Self-hosted MCP server. Your AI connects <strong>directly to this site</strong> — no cloud relay, no account, no daily tool-call limits, no paywall.</p>
        <h2>Endpoint</h2>
        <p><code style="font-size:15px;"><?php echo esc_html( $endpoint ); ?></code></p>
        <p>Tools available: <strong><?php echo esc_html( $count ); ?></strong> (posts, pages, media, categories, tags, comments, users, search, Rank Math SEO, plugins/themes read-only).</p>
        <h2>Connect with ChatGPT (OAuth — no password to paste)</h2>
        <ol>
            <li>In ChatGPT on the web go to <strong>Settings &rarr; Connectors &rarr; Create</strong>.</li>
            <li>Name: <strong>NoLimit MCP</strong>. Under MCP server URL paste the endpoint above. Authentication: <strong>OAuth</strong>.</li>
            <li>ChatGPT discovers this plugin automatically (via .well-known metadata) and registers itself — no client ID or secret to copy.</li>
            <li>A WordPress login page opens: sign in with your WordPress account and click <strong>Approve</strong>.</li>
            <li>Add the connector to a chat (via the + / tools menu) and ask it to manage your site.</li>
        </ol>
        <p><em>Note: on Business/Enterprise workspaces an admin may need to allow custom connectors first (Workspace Settings &rarr; Permissions &amp; Roles &rarr; Connected Data &rarr; Create custom MCP connectors). If you do not see the Create button, that is why.</em></p>
        <h2>Connect with Claude, Cursor, Windsurf (Application Password)</h2>
        <ol>
            <li>In WordPress go to <strong>Users &rarr; Profile &rarr; Application Passwords</strong> and create one (name it e.g. "MCP"). Copy the password shown once.</li>
            <li>In your AI client add an MCP server of type <strong>Streamable HTTP</strong> with the endpoint above, using HTTP Basic auth: username = your WP username, password = the Application Password.</li>
            <li>Never use your main login password here — Application Passwords can be revoked any time from the same Profile page.</li>
        </ol>
        <h2>OAuth access</h2>
        <p>Active OAuth tokens right now: <strong><?php echo esc_html( $active ); ?></strong></p>
        <form method="post">
            <?php wp_nonce_field( 'nlmcp_revoke_all' ); ?>
            <input type="submit" name="nlmcp_revoke_all" class="button" value="Revoke all OAuth access">
        </form>
        <h2>Recent client handshakes (diagnostic, no secrets stored)</h2>
        <?php
        $shakes = get_option( 'nlmcp_handshakes', array() );
        if ( ! is_array( $shakes ) || empty( $shakes ) ) {
            echo '<p>No initialize handshake recorded yet.</p>';
        } else {
            echo '<ul>';
            foreach ( $shakes as $s ) {
                echo '<li>' . esc_html( $s['time'] ) . ' — protocolVersion: <code>' . esc_html( $s['version'] ) . '</code> — Accept: <code>' . esc_html( $s['accept'] ) . '</code> — UA: <code>' . esc_html( $s['ua'] ) . '</code></li>';
            }
            echo '</ul>';
        }
        ?>
        <h2>Recent tool calls (diagnostic: what the server actually returned)</h2>
        <?php
        $calls = get_option( 'nlmcp_tool_calls', array() );
        if ( ! is_array( $calls ) || empty( $calls ) ) {
            echo '<p>No tool call recorded yet.</p>';
        } else {
            echo '<ul>';
            foreach ( $calls as $c ) {
                echo '<li>' . esc_html( $c['time'] ) . ' — tool: <code>' . esc_html( $c['tool'] ) . '</code> ' . esc_html( $c['args'] ) . ' — result bytes: <strong>' . esc_html( $c['result_bytes'] ) . '</strong> — UA: <code>' . esc_html( $c['ua'] ) . '</code></li>';
            }
            echo '</ul>';
        }
        ?>
        <h2>Safety rules built in</h2>
        <ul>
            <li>New posts/pages default to <strong>draft</strong> unless publish is explicitly requested.</li>
            <li>Deletes go to <strong>trash</strong>, never permanent.</li>
            <li>No raw SQL, no arbitrary PHP execution, no user creation/deletion, no plugin install/activation.</li>
            <li>Every tool checks your WordPress capabilities — an editor cannot do admin-only actions.</li>
            <li>No usage counting anywhere in the code. The only "limits" are your own server resources and WordPress permissions.</li>
        </ul>
    </div>
    <?php
}

/* --------------------------------------------------------------------------
 * OAuth 2.1 authorization server (for ChatGPT + any OAuth MCP client)
 * Dynamic Client Registration (RFC 7591) + PKCE (RFC 7636, S256/plain).
 * Tokens are stored only as SHA-256 hashes. No cloud involved.
 * ------------------------------------------------------------------------ */

function nlmcp_oauth_opt( $key ) {
    $v = get_option( $key, array() );
    return is_array( $v ) ? $v : array();
}

function nlmcp_b64url_encode( $bytes ) {
    return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
}

function nlmcp_oauth_clients() {
    return nlmcp_oauth_opt( 'nlmcp_oauth_clients' );
}

function nlmcp_oauth_issuer() {
    // RFC 8414: the issuer MUST match the base URL the client used to
    // retrieve the metadata (no trailing slash). A trailing slash here
    // makes strict clients (e.g. ChatGPT) reject discovery with
    // "Couldn't discover OAuth settings".
    return untrailingslashit( home_url( '/' ) );
}

function nlmcp_oauth_authorize_url() {
    return home_url( '/nlmcp-authorize/' );
}

function nlmcp_add_rewrite_rules() {
    add_rewrite_rule( '^\.well-known/oauth-authorization-server/?$', 'index.php?nlmcp_wellknown=auth', 'top' );
    add_rewrite_rule( '^\.well-known/oauth-protected-resource/?$', 'index.php?nlmcp_wellknown=resource', 'top' );
    add_rewrite_rule( '^nlmcp-authorize/?$', 'index.php?nlmcp_oauth=authorize', 'top' );
}

add_action( 'init', 'nlmcp_add_rewrite_rules' );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'nlmcp_wellknown';
    $vars[] = 'nlmcp_oauth';
    return $vars;
} );

// Runs before redirect_canonical so POSTs and .well-known URLs are not redirected away.
add_action( 'template_redirect', 'nlmcp_handle_wellknown_and_authorize', 9 );

function nlmcp_oauth_auth_metadata() {
    return array(
        'issuer'                                => nlmcp_oauth_issuer(),
        'authorization_endpoint'                => nlmcp_oauth_authorize_url(),
        'token_endpoint'                        => rest_url( NLMCP_ROUTE_NAMESPACE . '/oauth/token' ),
        'registration_endpoint'                 => rest_url( NLMCP_ROUTE_NAMESPACE . '/oauth/register' ),
        'response_types_supported'              => array( 'code' ),
        'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
        'code_challenge_methods_supported'      => array( 'S256', 'plain' ),
        'token_endpoint_auth_methods_supported' => array( 'client_secret_basic', 'client_secret_post', 'none' ),
        'scopes_supported'                      => array( 'mcp' ),
    );
}

function nlmcp_oauth_resource_metadata() {
    return array(
        'resource'               => rest_url( NLMCP_ROUTE_NAMESPACE . '/mcp' ),
        'authorization_servers'  => array( nlmcp_oauth_issuer() ),
        'scopes_supported'       => array( 'mcp' ),
        'bearer_methods_supported' => array( 'header' ),
    );
}

// Early interceptor: answer .well-known + authorize URLs directly on init,
// BEFORE WordPress rewrite matching. This guarantees NoLimit MCP's OAuth
// discovery wins even when another MCP/OAuth plugin registers competing
// rewrite rules for the same .well-known paths (rule order otherwise
// depends on plugin load order). Rewrite rules remain as a fallback.
add_action( 'init', 'nlmcp_early_intercept', 1 );

function nlmcp_early_intercept() {
    if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
        return;
    }
    $path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
    if ( ! is_string( $path ) ) {
        return;
    }
    $path = '/' . trim( $path, '/' );
    if ( '/.well-known/oauth-authorization-server' === $path ) {
        wp_send_json( nlmcp_oauth_auth_metadata() );
    }
    if ( '/.well-known/oauth-protected-resource' === $path ) {
        wp_send_json( nlmcp_oauth_resource_metadata() );
    }
    if ( '/nlmcp-authorize' === $path ) {
        nlmcp_oauth_authorize_page();
    }
}

function nlmcp_handle_wellknown_and_authorize() {
    $wellknown = get_query_var( 'nlmcp_wellknown' );
    if ( 'auth' === $wellknown ) {
        wp_send_json( nlmcp_oauth_auth_metadata() );
    }
    if ( 'resource' === $wellknown ) {
        wp_send_json( nlmcp_oauth_resource_metadata() );
    }
    if ( 'authorize' === get_query_var( 'nlmcp_oauth' ) ) {
        nlmcp_oauth_authorize_page();
    }
}

function nlmcp_oauth_redirect_error( $redirect_uri, $error, $state ) {
    $args = array( 'error' => $error );
    if ( '' !== $state ) {
        $args['state'] = $state;
    }
    wp_redirect( esc_url_raw( add_query_arg( $args, $redirect_uri ) ) );
    exit;
}

function nlmcp_oauth_authorize_page() {
    $get = array();
    foreach ( array( 'client_id', 'redirect_uri', 'response_type', 'state', 'code_challenge', 'code_challenge_method', 'scope' ) as $k ) {
        $get[ $k ] = isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
    }
    $post = array();
    foreach ( array( 'client_id', 'redirect_uri', 'state', 'code_challenge', 'code_challenge_method', 'scope', 'decision' ) as $k ) {
        $post[ $k ] = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
    }
    $is_post = 'POST' === $_SERVER['REQUEST_METHOD'];
    $params  = $is_post ? $post : $get;

    $clients = nlmcp_oauth_clients();
    $client  = isset( $clients[ $params['client_id'] ] ) ? $clients[ $params['client_id'] ] : null;
    $redirect_uri = $params['redirect_uri'];
    $state   = $params['state'];

    if ( ! $client || ! in_array( $redirect_uri, $client['redirect_uris'], true ) ) {
        wp_die( 'Invalid OAuth client or redirect URI.', 'NoLimit MCP', array( 'response' => 400 ) );
    }

    // POST = approval decision.
    if ( $is_post ) {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'nlmcp_oauth_approve' ) ) {
            wp_die( 'Security check failed. Please go back and try again.', 'NoLimit MCP', array( 'response' => 403 ) );
        }
        if ( 'deny' === $params['decision'] ) {
            nlmcp_oauth_redirect_error( $redirect_uri, 'access_denied', $state );
        }
        $code = wp_generate_password( 40, false );
        $codes = nlmcp_oauth_opt( 'nlmcp_oauth_codes' );
        $codes[ $code ] = array(
            'client_id'      => $params['client_id'],
            'user_id'        => get_current_user_id(),
            'redirect_uri'   => $redirect_uri,
            'challenge'      => $params['code_challenge'],
            'method'         => $params['code_challenge_method'] ? $params['code_challenge_method'] : 'plain',
            'expires'        => time() + 600,
        );
        update_option( 'nlmcp_oauth_codes', $codes, false );
        $args = array( 'code' => $code );
        if ( '' !== $state ) {
            $args['state'] = $state;
        }
        wp_redirect( esc_url_raw( add_query_arg( $args, $redirect_uri ) ) );
        exit;
    }

    // GET = show approval form. Must be logged in first.
    if ( 'code' !== $get['response_type'] ) {
        nlmcp_oauth_redirect_error( $redirect_uri, 'unsupported_response_type', $state );
    }
    $current = get_current_user_id();
    if ( ! $current ) {
        $back = add_query_arg( $_GET, nlmcp_oauth_authorize_url() );
        wp_redirect( wp_login_url( $back ) );
        exit;
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( 'Your WordPress user cannot use the MCP tools (edit_posts capability required).', 'NoLimit MCP', array( 'response' => 403 ) );
    }
    $client_name = $client['name'] ? $client['name'] : 'An AI client';
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Approve access — NoLimit MCP</title></head>
    <body style="font-family:sans-serif;max-width:560px;margin:60px auto;padding:0 20px;">
    <h1>Allow <?php echo esc_html( $client_name ); ?> to manage this site?</h1>
    <p>This AI client wants to use the NoLimit MCP tools on <strong><?php echo esc_html( home_url() ); ?></strong> as user <strong><?php echo esc_html( wp_get_current_user()->display_name ); ?></strong>. It will be able to do what that WordPress user can do (posts, pages, media, comments, SEO). You can revoke access any time from Settings &rarr; NoLimit MCP.</p>
    <form method="post">
        <?php wp_nonce_field( 'nlmcp_oauth_approve' ); ?>
        <input type="hidden" name="client_id" value="<?php echo esc_attr( $get['client_id'] ); ?>">
        <input type="hidden" name="redirect_uri" value="<?php echo esc_attr( $get['redirect_uri'] ); ?>">
        <input type="hidden" name="state" value="<?php echo esc_attr( $get['state'] ); ?>">
        <input type="hidden" name="code_challenge" value="<?php echo esc_attr( $get['code_challenge'] ); ?>">
        <input type="hidden" name="code_challenge_method" value="<?php echo esc_attr( $get['code_challenge_method'] ); ?>">
        <input type="hidden" name="scope" value="<?php echo esc_attr( $get['scope'] ); ?>">
        <button type="submit" name="decision" value="approve" style="padding:10px 24px;font-size:16px;cursor:pointer;">Approve</button>
        <button type="submit" name="decision" value="deny" style="padding:10px 24px;font-size:16px;cursor:pointer;margin-left:10px;">Deny</button>
    </form>
    </body></html>
    <?php
    exit;
}

/* --- REST: client registration + token exchange --- */

add_action( 'rest_api_init', function () {
    register_rest_route( NLMCP_ROUTE_NAMESPACE, '/oauth/register', array(
        'methods'             => array( 'POST' ),
        'callback'            => 'nlmcp_oauth_register',
        'permission_callback' => '__return_true',
    ) );
    register_rest_route( NLMCP_ROUTE_NAMESPACE, '/oauth/token', array(
        'methods'             => array( 'POST' ),
        'callback'            => 'nlmcp_oauth_token',
        'permission_callback' => '__return_true',
    ) );
} );

function nlmcp_oauth_register( $request ) {
    $body = $request->get_json_params();
    if ( ! is_array( $body ) ) {
        $body = $request->get_params();
    }
    $redirects = isset( $body['redirect_uris'] ) && is_array( $body['redirect_uris'] ) ? $body['redirect_uris'] : array();
    $clean = array();
    foreach ( $redirects as $u ) {
        $u = esc_url_raw( $u );
        if ( '' === $u ) {
            continue;
        }
        $host = wp_parse_url( $u, PHP_URL_HOST );
        $scheme = wp_parse_url( $u, PHP_URL_SCHEME );
        if ( 'https' === $scheme || 'localhost' === $host || '127.0.0.1' === $host ) {
            $clean[] = $u;
        }
    }
    if ( empty( $clean ) ) {
        return new WP_REST_Response( array( 'error' => 'invalid_redirect_uri', 'error_description' => 'At least one https (or localhost) redirect_uri is required.' ), 400 );
    }
    $auth_method = isset( $body['token_endpoint_auth_method'] ) ? sanitize_text_field( $body['token_endpoint_auth_method'] ) : 'client_secret_basic';
    if ( ! in_array( $auth_method, array( 'client_secret_basic', 'client_secret_post', 'none' ), true ) ) {
        $auth_method = 'client_secret_basic';
    }
    $client_id = 'nlmcp_' . wp_generate_password( 20, false );
    $secret = null;
    $record = array(
        'name'          => isset( $body['client_name'] ) ? sanitize_text_field( $body['client_name'] ) : '',
        'redirect_uris' => array_values( array_unique( $clean ) ),
        'auth_method'   => $auth_method,
        'secret_hash'   => null,
        'created'       => time(),
    );
    if ( 'none' !== $auth_method ) {
        $secret = wp_generate_password( 40, false );
        $record['secret_hash'] = hash( 'sha256', $secret );
    }
    $clients = nlmcp_oauth_clients();
    $clients[ $client_id ] = $record;
    update_option( 'nlmcp_oauth_clients', $clients, false );

    $out = array(
        'client_id'                => $client_id,
        'client_id_issued_at'      => time(),
        'redirect_uris'            => $record['redirect_uris'],
        'grant_types'              => array( 'authorization_code', 'refresh_token' ),
        'response_types'           => array( 'code' ),
        'token_endpoint_auth_method' => $auth_method,
    );
    if ( null !== $secret ) {
        $out['client_secret'] = $secret;
        $out['client_secret_expires_at'] = 0;
    }
    return rest_ensure_response( $out );
}

function nlmcp_oauth_issue_tokens( $user_id, $client_id ) {
    $access  = 'nlmcp_at_' . wp_generate_password( 40, false );
    $refresh = 'nlmcp_rt_' . wp_generate_password( 40, false );
    $tokens = nlmcp_oauth_opt( 'nlmcp_oauth_tokens' );
    $tokens[ hash( 'sha256', $access ) ] = array(
        'user_id'   => $user_id,
        'client_id' => $client_id,
        'expires'   => time() + 3600,
        'scope'     => 'mcp',
    );
    update_option( 'nlmcp_oauth_tokens', $tokens, false );
    $refreshes = nlmcp_oauth_opt( 'nlmcp_oauth_refresh' );
    $refreshes[ hash( 'sha256', $refresh ) ] = array(
        'user_id'   => $user_id,
        'client_id' => $client_id,
        'expires'   => time() + 30 * DAY_IN_SECONDS,
    );
    update_option( 'nlmcp_oauth_refresh', $refreshes, false );
    return array(
        'access_token'  => $access,
        'token_type'    => 'Bearer',
        'expires_in'    => 3600,
        'refresh_token' => $refresh,
        'scope'         => 'mcp',
    );
}

function nlmcp_oauth_token( $request ) {
    $params = $request->get_params();
    $grant  = isset( $params['grant_type'] ) ? sanitize_text_field( $params['grant_type'] ) : '';

    // Client authentication: Basic header, or body client_id + client_secret.
    $client_id = isset( $params['client_id'] ) ? sanitize_text_field( $params['client_id'] ) : '';
    $client_secret = isset( $params['client_secret'] ) ? $params['client_secret'] : '';
    $auth_header = nlmcp_get_auth_header( $request );
    if ( $auth_header && 0 === stripos( $auth_header, 'Basic ' ) ) {
        $decoded = base64_decode( substr( $auth_header, 6 ), true );
        if ( $decoded && false !== strpos( $decoded, ':' ) ) {
            list( $client_id, $client_secret ) = explode( ':', $decoded, 2 );
        }
    }
    $clients = nlmcp_oauth_clients();
    $client  = isset( $clients[ $client_id ] ) ? $clients[ $client_id ] : null;
    if ( ! $client ) {
        return new WP_REST_Response( array( 'error' => 'invalid_client' ), 401 );
    }
    if ( 'none' !== $client['auth_method'] ) {
        if ( '' === $client_secret || ! hash_equals( (string) $client['secret_hash'], hash( 'sha256', $client_secret ) ) ) {
            return new WP_REST_Response( array( 'error' => 'invalid_client' ), 401 );
        }
    }

    if ( 'authorization_code' === $grant ) {
        $code = isset( $params['code'] ) ? sanitize_text_field( $params['code'] ) : '';
        $redirect_uri = isset( $params['redirect_uri'] ) ? esc_url_raw( $params['redirect_uri'] ) : '';
        $verifier = isset( $params['code_verifier'] ) ? $params['code_verifier'] : '';
        $codes = nlmcp_oauth_opt( 'nlmcp_oauth_codes' );
        $rec = isset( $codes[ $code ] ) ? $codes[ $code ] : null;
        if ( ! $rec || $rec['expires'] < time() || $rec['client_id'] !== $client_id || $rec['redirect_uri'] !== $redirect_uri ) {
            return new WP_REST_Response( array( 'error' => 'invalid_grant' ), 400 );
        }
        // PKCE check.
        if ( '' !== $rec['challenge'] ) {
            if ( 'S256' === $rec['method'] ) {
                $expected = nlmcp_b64url_encode( hash( 'sha256', $verifier, true ) );
                if ( ! hash_equals( $rec['challenge'], $expected ) ) {
                    return new WP_REST_Response( array( 'error' => 'invalid_grant', 'error_description' => 'PKCE verification failed.' ), 400 );
                }
            } elseif ( ! hash_equals( $rec['challenge'], $verifier ) ) {
                return new WP_REST_Response( array( 'error' => 'invalid_grant', 'error_description' => 'PKCE verification failed.' ), 400 );
            }
        }
        unset( $codes[ $code ] );
        update_option( 'nlmcp_oauth_codes', $codes, false );
        return rest_ensure_response( nlmcp_oauth_issue_tokens( $rec['user_id'], $client_id ) );
    }

    if ( 'refresh_token' === $grant ) {
        $rt = isset( $params['refresh_token'] ) ? $params['refresh_token'] : '';
        $refreshes = nlmcp_oauth_opt( 'nlmcp_oauth_refresh' );
        $rec = isset( $refreshes[ hash( 'sha256', $rt ) ] ) ? $refreshes[ hash( 'sha256', $rt ) ] : null;
        if ( ! $rec || $rec['expires'] < time() || $rec['client_id'] !== $client_id ) {
            return new WP_REST_Response( array( 'error' => 'invalid_grant' ), 400 );
        }
        unset( $refreshes[ hash( 'sha256', $rt ) ] );
        update_option( 'nlmcp_oauth_refresh', $refreshes, false );
        return rest_ensure_response( nlmcp_oauth_issue_tokens( $rec['user_id'], $client_id ) );
    }

    return new WP_REST_Response( array( 'error' => 'unsupported_grant_type' ), 400 );
}

/* --- Activation: flush rewrite rules so .well-known + authorize URLs work --- */

function nlmcp_activate() {
    nlmcp_add_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'nlmcp_activate' );

function nlmcp_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'nlmcp_deactivate' );
