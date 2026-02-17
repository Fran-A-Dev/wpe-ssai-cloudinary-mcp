<?php
/**
 * Plugin Name: WP MCP Server
 * Plugin URI: https://github.com/Fran-A-Dev/wpe-ssai-cloudinary-mcp
 * Description: WordPress, Cloudinary and Smart Search AI management via MCP 
 * Version: 2.0.0
 * Author: Fran Agulto
 * License: GPL v2 or later
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Main plugin class
class WPEngine_MCP_Server_Simple {

    private $smart_search_url;
    private $smart_search_token;
    private $mcp_access_token;

    public function __construct() {
        add_action( 'init', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

        // Load Smart Search AI credentials from options
        $this->smart_search_url = get_option( 'wpengine_mcp_smart_search_url', '' );
        $this->smart_search_token = get_option( 'wpengine_mcp_smart_search_token', '' );

        // Load or generate MCP access token
        $this->mcp_access_token = get_option( 'wpengine_mcp_access_token', '' );
        if ( empty( $this->mcp_access_token ) ) {
            $this->mcp_access_token = wp_generate_password( 32, false );
            update_option( 'wpengine_mcp_access_token', $this->mcp_access_token );
        }
    }

    public function init() {
        // Register REST API endpoint
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Add admin settings page
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
    }

    public function register_rest_routes() {
        register_rest_route( 'wpengine/v1', '/mcp', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_mcp_request' ],
            'permission_callback' => [ $this, 'check_permissions' ],
        ]);
    }

    public function check_permissions( $request ) {
        // Simple token-based authentication
        // Check for X-MCP-Token header
        $token = $request->get_header( 'x-mcp-token' );

        if ( empty( $token ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Authentication required. Please provide X-MCP-Token header.' ),
                [ 'status' => 401 ]
            );
        }

        // Verify token matches stored token
        if ( $token !== $this->mcp_access_token ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Invalid authentication token.' ),
                [ 'status' => 401 ]
            );
        }

        return true;
    }

    public function handle_mcp_request( $request ) {
        $body = $request->get_json_params();
        $method = $body['method'] ?? '';
        $id = $body['id'] ?? 1; // Default to 1 if no ID provided

        // Handle MCP methods
        $result = null;
        switch ( $method ) {
            case 'initialize':
                $result = $this->initialize( $body['params'] ?? [] );
                break;
            case 'tools/list':
                $result = $this->list_tools();
                break;
            case 'tools/call':
                $result = $this->call_tool( $body['params'] ?? [] );
                break;
            default:
                // Return JSON-RPC 2.0 error
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32601,
                        'message' => 'Method not found: ' . $method
                    ]
                ];
        }

        // Return JSON-RPC 2.0 success response
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result
        ];
    }

    private function initialize( $params ) {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => (object) []
            ],
            'serverInfo' => [
                'name' => 'WP Engine MCP Server',
                'version' => '2.0.0'
            ]
        ];
    }

    private function list_tools() {
        return [
            'tools' => [
                // Local WordPress tools
                [
                    'name' => 'wpengine--get-current-site-info',
                    'description' => 'Get information about the current WordPress site',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => []
                    ]
                ],
                [
                    'name' => 'wpengine--purge-cache',
                    'description' => 'Clear the local WordPress cache',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => []
                    ]
                ],

                // WordPress Post Management tools
                [
                    'name' => 'wpengine--create-post',
                    'description' => 'Create a new WordPress post with optional Cloudinary image',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => 'Post title'
                            ],
                            'content' => [
                                'type' => 'string',
                                'description' => 'Post content (HTML allowed)'
                            ],
                            'status' => [
                                'type' => 'string',
                                'description' => 'Post status: publish, draft, pending',
                                'default' => 'publish'
                            ],
                            'cloudinary_url' => [
                                'type' => 'string',
                                'description' => 'Cloudinary image URL to embed in post (optional)'
                            ],
                            'cloudinary_public_id' => [
                                'type' => 'string',
                                'description' => 'Cloudinary public_id to store as post meta (optional)'
                            ]
                        ],
                        'required' => ['title', 'content']
                    ]
                ],
                [
                    'name' => 'wpengine--update-post',
                    'description' => 'Update an existing WordPress post',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'post_id' => [
                                'type' => 'integer',
                                'description' => 'WordPress post ID'
                            ],
                            'title' => [
                                'type' => 'string',
                                'description' => 'Post title (optional)'
                            ],
                            'content' => [
                                'type' => 'string',
                                'description' => 'Post content (optional)'
                            ],
                            'status' => [
                                'type' => 'string',
                                'description' => 'Post status (optional)'
                            ]
                        ],
                        'required' => ['post_id']
                    ]
                ],
                [
                    'name' => 'wpengine--get-post',
                    'description' => 'Get details of a specific WordPress post',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'post_id' => [
                                'type' => 'integer',
                                'description' => 'WordPress post ID'
                            ]
                        ],
                        'required' => ['post_id']
                    ]
                ],
                [
                    'name' => 'wpengine--list-posts',
                    'description' => 'List WordPress posts',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Number of posts to return',
                                'default' => 10
                            ],
                            'status' => [
                                'type' => 'string',
                                'description' => 'Filter by status: publish, draft, any',
                                'default' => 'publish'
                            ]
                        ]
                    ]
                ],

                // Smart Search AI Indexing tools
                [
                    'name' => 'wpengine--index-cloudinary-asset',
                    'description' => 'Index a Cloudinary asset into Smart Search AI for natural language search',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'public_id' => [
                                'type' => 'string',
                                'description' => 'Cloudinary public_id (unique identifier)'
                            ],
                            'secure_url' => [
                                'type' => 'string',
                                'description' => 'Cloudinary secure URL'
                            ],
                            'resource_type' => [
                                'type' => 'string',
                                'description' => 'Resource type: image, video, raw'
                            ],
                            'format' => [
                                'type' => 'string',
                                'description' => 'File format: jpg, png, mp4, etc.'
                            ],
                            'tags' => [
                                'type' => 'array',
                                'description' => 'Array of tags for searchability (optional)',
                                'items' => [
                                    'type' => 'string'
                                ]
                            ]
                        ],
                        'required' => ['public_id', 'secure_url', 'resource_type', 'format']
                    ]
                ],
                [
                    'name' => 'wpengine--bulk-index-cloudinary-assets',
                    'description' => 'Index multiple Cloudinary assets into Smart Search AI in one request',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'assets' => [
                                'type' => 'array',
                                'description' => 'Array of Cloudinary assets to index',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'public_id' => ['type' => 'string'],
                                        'secure_url' => ['type' => 'string'],
                                        'resource_type' => ['type' => 'string'],
                                        'format' => ['type' => 'string'],
                                        'tags' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string']
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'required' => ['assets']
                    ]
                ]
            ]
        ];
    }

    private function call_tool( $params ) {
        $tool_name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        switch ( $tool_name ) {
            // Local WordPress tools
            case 'wpengine--get-current-site-info':
                return $this->get_current_site_info();
            case 'wpengine--purge-cache':
                return $this->purge_cache();

            // WordPress Post Management tools
            case 'wpengine--create-post':
                return $this->create_post( $arguments );
            case 'wpengine--update-post':
                return $this->update_post( $arguments );
            case 'wpengine--get-post':
                return $this->get_post( $arguments );
            case 'wpengine--list-posts':
                return $this->list_posts( $arguments );

            // Smart Search AI Indexing tools
            case 'wpengine--index-cloudinary-asset':
                return $this->index_cloudinary_asset( $arguments );
            case 'wpengine--bulk-index-cloudinary-assets':
                return $this->bulk_index_cloudinary_assets( $arguments );

            default:
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Unknown tool: ' . $tool_name
                        ]
                    ]
                ];
        }
    }

    // Local WordPress methods
    private function get_current_site_info() {
        $info = sprintf(
            "Site: %s\nURL: %s\nDescription: %s\nWordPress Version: %s\nAdmin Email: %s",
            get_bloginfo('name'),
            get_site_url(),
            get_bloginfo('description'),
            get_bloginfo('version'),
            get_option('admin_email')
        );

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $info
                ]
            ]
        ];
    }

    private function purge_cache() {
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Cache purged successfully for ' . get_bloginfo('name')
                ]
            ]
        ];
    }

    // WordPress Post Management methods
    private function create_post( $arguments ) {
        $title = $arguments['title'] ?? '';
        $content = $arguments['content'] ?? '';
        $status = $arguments['status'] ?? 'publish';
        $cloudinary_url = $arguments['cloudinary_url'] ?? '';
        $cloudinary_public_id = $arguments['cloudinary_public_id'] ?? '';

        if ( empty( $title ) || empty( $content ) ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: title and content are required'
                    ]
                ]
            ];
        }

        // If Cloudinary URL is provided, embed it in content
        if ( ! empty( $cloudinary_url ) ) {
            $content = '<img src="' . esc_url( $cloudinary_url ) . '" alt="' . esc_attr( $title ) . '" class="cloudinary-image" />' . "\n\n" . $content;
        }

        $post_data = [
            'post_title' => sanitize_text_field( $title ),
            'post_content' => wp_kses_post( $content ),
            'post_status' => in_array( $status, ['publish', 'draft', 'pending'] ) ? $status : 'publish',
            'post_type' => 'post'
        ];

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error creating post: ' . $post_id->get_error_message()
                    ]
                ]
            ];
        }

        // Store Cloudinary metadata
        if ( ! empty( $cloudinary_public_id ) ) {
            update_post_meta( $post_id, 'cloudinary_public_id', sanitize_text_field( $cloudinary_public_id ) );
        }
        if ( ! empty( $cloudinary_url ) ) {
            update_post_meta( $post_id, 'cloudinary_url', esc_url_raw( $cloudinary_url ) );
        }

        $post_url = get_permalink( $post_id );
        $text = "Post created successfully!\n\n";
        $text .= "• Title: {$title}\n";
        $text .= "• ID: {$post_id}\n";
        $text .= "• Status: {$status}\n";
        $text .= "• URL: {$post_url}\n";

        if ( ! empty( $cloudinary_url ) ) {
            $text .= "• Cloudinary Image: Embedded\n";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text
                ]
            ]
        ];
    }

    private function update_post( $arguments ) {
        $post_id = $arguments['post_id'] ?? 0;

        if ( empty( $post_id ) ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: post_id is required'
                    ]
                ]
            ];
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: Post with ID {$post_id} not found"
                    ]
                ]
            ];
        }

        $post_data = [ 'ID' => $post_id ];

        if ( isset( $arguments['title'] ) ) {
            $post_data['post_title'] = sanitize_text_field( $arguments['title'] );
        }

        if ( isset( $arguments['content'] ) ) {
            $post_data['post_content'] = wp_kses_post( $arguments['content'] );
        }

        if ( isset( $arguments['status'] ) ) {
            $post_data['post_status'] = $arguments['status'];
        }

        $result = wp_update_post( $post_data );

        if ( is_wp_error( $result ) ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error updating post: ' . $result->get_error_message()
                    ]
                ]
            ];
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Post {$post_id} updated successfully!"
                ]
            ]
        ];
    }

    private function get_post( $arguments ) {
        $post_id = $arguments['post_id'] ?? 0;

        if ( empty( $post_id ) ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: post_id is required'
                    ]
                ]
            ];
        }

        $post = get_post( $post_id );

        if ( ! $post ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: Post with ID {$post_id} not found"
                    ]
                ]
            ];
        }

        $cloudinary_public_id = get_post_meta( $post_id, 'cloudinary_public_id', true );
        $cloudinary_url = get_post_meta( $post_id, 'cloudinary_url', true );

        $text = "Post Details:\n\n";
        $text .= "• ID: {$post->ID}\n";
        $text .= "• Title: {$post->post_title}\n";
        $text .= "• Status: {$post->post_status}\n";
        $text .= "• Date: {$post->post_date}\n";
        $text .= "• URL: " . get_permalink( $post_id ) . "\n";

        if ( ! empty( $cloudinary_public_id ) ) {
            $text .= "• Cloudinary Public ID: {$cloudinary_public_id}\n";
        }
        if ( ! empty( $cloudinary_url ) ) {
            $text .= "• Cloudinary URL: {$cloudinary_url}\n";
        }

        $text .= "\nContent:\n" . wp_trim_words( $post->post_content, 50 );

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text
                ]
            ]
        ];
    }

    private function list_posts( $arguments ) {
        $limit = $arguments['limit'] ?? 10;
        $status = $arguments['status'] ?? 'publish';

        $args = [
            'posts_per_page' => min( $limit, 100 ),
            'post_status' => $status,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $posts = get_posts( $args );

        if ( empty( $posts ) ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "No posts found with status: {$status}"
                    ]
                ]
            ];
        }

        $count = count( $posts );
        $text = "Found {$count} posts:\n\n";

        foreach ( $posts as $post ) {
            $text .= "• {$post->post_title} (ID: {$post->ID})\n";
            $text .= "  - Status: {$post->post_status}\n";
            $text .= "  - Date: {$post->post_date}\n";
            $text .= "  - URL: " . get_permalink( $post->ID ) . "\n\n";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text
                ]
            ]
        ];
    }

    // Smart Search AI Indexing methods
    private function make_smart_search_request( $query, $variables ) {
        if ( empty( $this->smart_search_url ) || empty( $this->smart_search_token ) ) {
            return new WP_Error( 'no_credentials', 'Smart Search AI credentials not configured in settings' );
        }

        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->smart_search_token
            ],
            'body' => json_encode([
                'query' => $query,
                'variables' => $variables
            ]),
            'timeout' => 30
        ];

        $response = wp_remote_request( $this->smart_search_url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );

        if ( isset( $decoded['errors'] ) ) {
            $error_message = $decoded['errors'][0]['message'] ?? 'Unknown GraphQL error';
            return new WP_Error( 'graphql_error', 'Smart Search AI Error: ' . $error_message );
        }

        if ( $status_code >= 400 ) {
            return new WP_Error( 'api_error', 'Smart Search AI API Error', ['status_code' => $status_code] );
        }

        return $decoded;
    }

    private function index_cloudinary_asset( $arguments ) {
        $public_id = $arguments['public_id'] ?? '';
        $secure_url = $arguments['secure_url'] ?? '';
        $resource_type = $arguments['resource_type'] ?? '';
        $format = $arguments['format'] ?? '';
        $tags = $arguments['tags'] ?? [];

        if ( empty( $public_id ) || empty( $secure_url ) || empty( $resource_type ) || empty( $format ) ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: public_id, secure_url, resource_type, and format are required'
                    ]
                ]
            ];
        }

        // Create document ID with cloudinary prefix
        $document_id = 'cloudinary:' . $public_id;

        // Build document data
        $document_data = [
            'cloudinary_public_id' => $public_id,
            'cloudinary_url' => $secure_url,
            'resource_type' => $resource_type,
            'format' => $format,
            'post_type' => 'cloudinary_asset', // For filtering
            'tags' => is_array( $tags ) ? implode( ', ', $tags ) : $tags
        ];

        $mutation = '
            mutation CreateIndexDocument($input: DocumentInput!) {
                index(input: $input) {
                    success
                    code
                    message
                    document {
                        id
                        data
                    }
                }
            }
        ';

        $variables = [
            'input' => [
                'id' => $document_id,
                'data' => $document_data,
                'meta' => [
                    'system' => 'WP Engine MCP Server',
                    'action' => 'index-cloudinary-asset',
                    'source' => get_site_url()
                ]
            ]
        ];

        $result = $this->make_smart_search_request( $mutation, $variables );

        if ( is_wp_error( $result ) ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: ' . $result->get_error_message()
                    ]
                ]
            ];
        }

        $success = $result['data']['index']['success'] ?? false;
        $message = $result['data']['index']['message'] ?? 'Unknown result';

        if ( $success ) {
            $text = "Cloudinary asset indexed successfully into Smart Search AI!\n\n";
            $text .= "• Document ID: {$document_id}\n";
            $text .= "• Public ID: {$public_id}\n";
            $text .= "• Resource Type: {$resource_type}\n";
            $text .= "• Format: {$format}\n";
            $text .= "• Status: {$message}\n";
        } else {
            $text = "Indexing failed: {$message}";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text
                ]
            ]
        ];
    }

    private function bulk_index_cloudinary_assets( $arguments ) {
        $assets = $arguments['assets'] ?? [];

        if ( empty( $assets ) || ! is_array( $assets ) ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: assets array is required'
                    ]
                ]
            ];
        }

        $documents = [];

        foreach ( $assets as $asset ) {
            $public_id = $asset['public_id'] ?? '';
            $secure_url = $asset['secure_url'] ?? '';
            $resource_type = $asset['resource_type'] ?? '';
            $format = $asset['format'] ?? '';
            $tags = $asset['tags'] ?? [];

            if ( empty( $public_id ) || empty( $secure_url ) ) {
                continue; // Skip invalid assets
            }

            $documents[] = [
                'id' => 'cloudinary:' . $public_id,
                'data' => [
                    'cloudinary_public_id' => $public_id,
                    'cloudinary_url' => $secure_url,
                    'resource_type' => $resource_type,
                    'format' => $format,
                    'post_type' => 'cloudinary_asset',
                    'tags' => is_array( $tags ) ? implode( ', ', $tags ) : $tags
                ]
            ];
        }

        if ( empty( $documents ) ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: No valid assets to index'
                    ]
                ]
            ];
        }

        $mutation = '
            mutation CreateBulkIndexDocuments($input: BulkIndexInput!) {
                bulkIndex(input: $input) {
                    code
                    success
                    documents {
                        id
                    }
                }
            }
        ';

        $variables = [
            'input' => [
                'documents' => $documents,
                'meta' => [
                    'system' => 'WP Engine MCP Server',
                    'action' => 'bulk-index-cloudinary-assets',
                    'source' => get_site_url()
                ]
            ]
        ];

        $result = $this->make_smart_search_request( $mutation, $variables );

        if ( is_wp_error( $result ) ) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: ' . $result->get_error_message()
                    ]
                ]
            ];
        }

        $success = $result['data']['bulkIndex']['success'] ?? false;
        $indexed_count = count( $result['data']['bulkIndex']['documents'] ?? [] );

        if ( $success ) {
            $text = "Bulk indexing successful!\n\n";
            $text .= "• Assets Indexed: {$indexed_count}\n";
            $text .= "• Status: All Cloudinary assets are now searchable via Smart Search AI\n";
        } else {
            $text = "Bulk indexing failed";
        }

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text
                ]
            ]
        ];
    }

    // Admin interface
    public function add_admin_menu() {
        add_options_page(
            'WP Engine MCP Settings',
            'WP Engine MCP',
            'manage_options',
            'wpengine-mcp-simple-settings',
            [ $this, 'admin_page' ]
        );
    }

    public function admin_page() {
        if ( isset( $_POST['submit'] ) && check_admin_referer( 'wpengine_mcp_settings' ) ) {
            update_option( 'wpengine_mcp_smart_search_url', esc_url_raw( $_POST['smart_search_url'] ) );
            update_option( 'wpengine_mcp_smart_search_token', sanitize_text_field( $_POST['smart_search_token'] ) );

            $this->smart_search_url = get_option( 'wpengine_mcp_smart_search_url' );
            $this->smart_search_token = get_option( 'wpengine_mcp_smart_search_token' );

            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }

        $smart_search_url = get_option( 'wpengine_mcp_smart_search_url', '' );
        $smart_search_token = get_option( 'wpengine_mcp_smart_search_token', '' );
        ?>
        <div class="wrap">
            <h1>WP Engine MCP Server Settings</h1>
            <p>Configure Smart Search AI credentials for MCP integration with Cloudinary.</p>

            <div class="notice notice-info" style="padding: 15px; margin: 20px 0;">
                <h2 style="margin-top: 0;">MCP Client Authentication</h2>
                <p><strong>MCP Endpoint:</strong> <code><?php echo esc_html( home_url( '/wp-json/wpengine/v1/mcp' ) ); ?></code></p>
                <p><strong>Access Token:</strong></p>
                <p>
                    <input type="text" value="<?php echo esc_attr( $this->mcp_access_token ); ?>" readonly="readonly"
                           style="width: 100%; max-width: 500px; font-family: monospace; padding: 8px;"
                           onclick="this.select();" />
                    <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $this->mcp_access_token ); ?>'); alert('Token copied!');">
                        Copy Token
                    </button>
                </p>
                <p class="description">
                    Add this token to your .env.local file:<br>
                    <code>WORDPRESS_MCP_TOKEN=<?php echo esc_html( $this->mcp_access_token ); ?></code>
                </p>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field( 'wpengine_mcp_settings' ); ?>

                <h2>Smart Search AI Credentials</h2>
                <p>Find these in your WordPress Admin → Smart Search → Settings.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">Smart Search URL</th>
                        <td>
                            <input type="url" name="smart_search_url" value="<?php echo esc_attr( $smart_search_url ); ?>" class="regular-text" placeholder="https://..." />
                            <p class="description">Smart Search GraphQL endpoint URL</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Smart Search Access Token</th>
                        <td>
                            <input type="password" name="smart_search_token" value="<?php echo esc_attr( $smart_search_token ); ?>" class="regular-text" />
                            <p class="description">Smart Search API access token (Bearer token)</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2>Available MCP Tools</h2>

            <h3>WordPress Post Management</h3>
            <ul>
                <li><strong>wpengine--create-post</strong> - Create blog posts with Cloudinary images</li>
                <li><strong>wpengine--update-post</strong> - Update existing posts</li>
                <li><strong>wpengine--get-post</strong> - Get post details</li>
                <li><strong>wpengine--list-posts</strong> - List WordPress posts</li>
            </ul>

            <h3>Smart Search AI + Cloudinary Integration</h3>
            <ul>
                <li><strong>wpengine--index-cloudinary-asset</strong> - Index single Cloudinary asset for AI search</li>
                <li><strong>wpengine--bulk-index-cloudinary-assets</strong> - Index multiple Cloudinary assets</li>
            </ul>

            <h3>Local WordPress Management</h3>
            <ul>
                <li><strong>wpengine--get-current-site-info</strong> - Get current site information</li>
                <li><strong>wpengine--purge-cache</strong> - Clear local cache</li>
            </ul>

            <h3>MCP Endpoint</h3>
            <p><strong>Endpoint:</strong> <code><?php echo home_url( '/wp-json/wpengine/v1/mcp' ); ?></code></p>
            <p><strong>Authentication:</strong> Basic Auth with WordPress username and application password</p>

            <h3>Demo Workflow</h3>
            <ol>
                <li>Upload images to Cloudinary (via Cloudinary MCP)</li>
                <li>Index Cloudinary assets into Smart Search AI (wpengine--index-cloudinary-asset)</li>
                <li>Search for images using natural language (via Smart Search MCP)</li>
                <li>Create WordPress posts with found images (wpengine--create-post)</li>
            </ol>
        </div>
        <?php
    }

    public function activate() {
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new WPEngine_MCP_Server_Simple();
