# WP MCP Server (WordPress + Smart Search + Cloudinary)

WordPress plugin that exposes MCP tools over a WordPress REST endpoint so an LLM client can manage WordPress content and index Cloudinary assets into Smart Search AI.

This plugin is intended to be installed in WordPress Admin and used by an MCP-capable client.

## What This Plugin Provides

- MCP endpoint at `wp-json/wpengine/v1/mcp`
- Token-based authentication using `X-MCP-Token` header
- WordPress tools for site info, cache purge, and post create/update/read/list
- Smart Search AI indexing tools for single and bulk Cloudinary asset indexing

## Requirements

- WordPress site with admin access
- PHP environment compatible with your WordPress install
- Smart Search AI GraphQL endpoint URL and access token
- MCP client that can send JSON-RPC style requests to HTTP endpoints

## Installation

1. Copy this plugin folder into your WordPress plugins directory:
   - `wp-content/plugins/ssai-cloudinary-wpmcp`
2. In WordPress Admin, go to `Plugins`.
3. Activate `WP MCP Server`.

## Configuration (WordPress Admin)

1. Go to `Settings -> WP Engine MCP`.
2. Copy the generated MCP Access Token shown on the page.
3. Set Smart Search AI credentials:
   - Smart Search URL (GraphQL endpoint)
   - Smart Search Access Token (Bearer token)
4. Save settings.

## MCP Connection Details

- Endpoint:
  - `https://YOUR_SITE_URL/wp-json/wpengine/v1/mcp`
- Method:
  - `POST`
- Required header:
  - `X-MCP-Token: YOUR_MCP_ACCESS_TOKEN`
- Content type:
  - `application/json`

## JSON-RPC Methods

The endpoint supports these MCP methods:

- `initialize`
- `tools/list`
- `tools/call`

If an unknown method is called, the server returns JSON-RPC error code `-32601`.

## Available Tools

### Local WordPress

- `wpengine--get-current-site-info`
- `wpengine--purge-cache`

### WordPress Post Management

- `wpengine--create-post`
- `wpengine--update-post`
- `wpengine--get-post`
- `wpengine--list-posts`

### Smart Search + Cloudinary Indexing

- `wpengine--index-cloudinary-asset`
- `wpengine--bulk-index-cloudinary-assets`

## Tool Input Reference

### `wpengine--create-post`

Required:
- `title` (string)
- `content` (string)

Optional:
- `status` (`publish`, `draft`, `pending`; defaults to `publish`)
- `cloudinary_url` (string)
- `cloudinary_public_id` (string)

### `wpengine--update-post`

Required:
- `post_id` (integer)

Optional:
- `title` (string)
- `content` (string)
- `status` (string)

### `wpengine--get-post`

Required:
- `post_id` (integer)

### `wpengine--list-posts`

Optional:
- `limit` (integer, max 100, default 10)
- `status` (string, default `publish`)

### `wpengine--index-cloudinary-asset`

Required:
- `public_id` (string)
- `secure_url` (string)
- `resource_type` (string, e.g. `image`, `video`, `raw`)
- `format` (string, e.g. `jpg`, `png`, `mp4`)

Optional:
- `tags` (array of strings)

### `wpengine--bulk-index-cloudinary-assets`

Required:
- `assets` (array of asset objects)

Each asset should include:
- `public_id` (string)
- `secure_url` (string)
- `resource_type` (string)
- `format` (string)
- `tags` (array of strings, optional)

## Example Requests

Replace `YOUR_SITE_URL` and `YOUR_TOKEN`.

### Initialize

```bash
curl -X POST "https://YOUR_SITE_URL/wp-json/wpengine/v1/mcp" \
  -H "Content-Type: application/json" \
  -H "X-MCP-Token: YOUR_TOKEN" \
  -d '{
    "jsonrpc":"2.0",
    "id":1,
    "method":"initialize",
    "params":{}
  }'
```

### List Tools

```bash
curl -X POST "https://YOUR_SITE_URL/wp-json/wpengine/v1/mcp" \
  -H "Content-Type: application/json" \
  -H "X-MCP-Token: YOUR_TOKEN" \
  -d '{
    "jsonrpc":"2.0",
    "id":2,
    "method":"tools/list"
  }'
```

### Create Post with Cloudinary Image

```bash
curl -X POST "https://YOUR_SITE_URL/wp-json/wpengine/v1/mcp" \
  -H "Content-Type: application/json" \
  -H "X-MCP-Token: YOUR_TOKEN" \
  -d '{
    "jsonrpc":"2.0",
    "id":3,
    "method":"tools/call",
    "params":{
      "name":"wpengine--create-post",
      "arguments":{
        "title":"Launch Recap",
        "content":"Highlights from the event...",
        "status":"draft",
        "cloudinary_url":"https://res.cloudinary.com/demo/image/upload/sample.jpg",
        "cloudinary_public_id":"sample"
      }
    }
  }'
```

### Index a Cloudinary Asset into Smart Search

```bash
curl -X POST "https://YOUR_SITE_URL/wp-json/wpengine/v1/mcp" \
  -H "Content-Type: application/json" \
  -H "X-MCP-Token: YOUR_TOKEN" \
  -d '{
    "jsonrpc":"2.0",
    "id":4,
    "method":"tools/call",
    "params":{
      "name":"wpengine--index-cloudinary-asset",
      "arguments":{
        "public_id":"events/hero-banner",
        "secure_url":"https://res.cloudinary.com/acme/image/upload/v1/events/hero-banner.jpg",
        "resource_type":"image",
        "format":"jpg",
        "tags":["event","hero","banner"]
      }
    }
  }'
```

## Suggested End-to-End Workflow

1. Upload assets with your Cloudinary MCP integration.
2. Call `wpengine--index-cloudinary-asset` (or bulk variant) to index in Smart Search AI.
3. Use Smart Search MCP to find assets via natural language.
4. Create or update WordPress posts with the selected Cloudinary image URLs.

## Notes

- MCP access is controlled by one token stored in WordPress options.
- If Smart Search credentials are not configured, indexing tools return an error.
- Post content supports sanitized HTML (`wp_kses_post`).
