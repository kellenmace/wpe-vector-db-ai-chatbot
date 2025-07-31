<?php
/**
 * REST API Endpoints
 *
 * @package AIChatbot
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'AI_CHATBOT_ROUTE_NAMESPACE', 'ai-chatbot/v1' );
define( 'AI_CHATBOT_ROUTE', '/chat' );

// WordPress hooks
add_action( 'rest_api_init', 'ai_chatbot_register_rest_routes' );

/**
 * Register REST API routes.
 */
function ai_chatbot_register_rest_routes() {
    register_rest_route(
        AI_CHATBOT_ROUTE_NAMESPACE,
        AI_CHATBOT_ROUTE,
        [
            'methods'             => 'POST',
            'callback'            => 'ai_chatbot_handle_streaming_chat_request',
            'permission_callback' => '__return_true',
            'args' => [
                'messages' => [
                    'required' => true,
                    'type' => 'array',
                ],
            ],
        ]
    );
}

/**
 * Handle streaming chat REST API requests.
 *
 * @param WP_REST_Request $request The REST request.
 * @return void Streams the response directly to the client.
 */
function ai_chatbot_handle_streaming_chat_request( $request ) {
    // 1. Set SSE Headers immediately
    header( 'Content-Type: text/event-stream' );
    header( 'Cache-Control: no-cache' );
    header( 'Connection: keep-alive' );
    header( 'X-Accel-Buffering: no' ); // Disable Nginx proxy buffering
    
    // Disable all output buffering
    while ( ob_get_level() ) {
        ob_end_clean();
    }
    
    // 2. Get request data
    $body = $request->get_json_params();
    
    if ( !isset( $body['messages'] ) || empty( $body['messages'] ) ) {
        echo 'data: ' . json_encode( [ 'error' => 'No messages provided' ] ) . "\n\n";
        flush();
        exit;
    }
    
    $messages = $body['messages'];
    $latest_message = end( $messages );
    $user_message = $latest_message['text'] ?? '';
    
    if ( empty( $user_message ) ) {
        echo 'data: ' . json_encode( [ 'error' => 'Empty message' ] ) . "\n\n";
        flush();
        exit;
    }
    
    // 3. Get API key
    $api_key = ai_chatbot_get_google_api_key();
    
    if ( empty( $api_key ) ) {
        echo 'data: ' . json_encode( [ 'error' => 'API key not configured' ] ) . "\n\n";
        flush();
        exit;
    }
    
    // 4. Get context from vector database
    try {
        $context_data = ai_chatbot_get_context( $user_message );
        $context_text = ai_chatbot_format_context( $context_data );
    } catch ( Exception $e ) {
        $context_text = "Database context could not be retrieved: " . $e->getMessage();
    }
    
    // 5. Build conversation history for Gemini
    $conversation_history = ai_chatbot_build_conversation_history( $messages );
    
    // 6. Create system prompt with context
    $system_prompt = "You are a helpful AI assistant that specializes in answering questions about TV shows. " .
                    "You have access to a comprehensive TV show database through vector search. " .
                    "Below is the most relevant information from the database based on the user's query.\n\n" .
                    "IMPORTANT INSTRUCTIONS:\n" .
                    "- Use the context data below as your PRIMARY source of information\n" .
                    "- The context contains actual TV show data from the database with similarity scores\n" .
                    "- Higher similarity scores (closer to 1.0) indicate more relevant matches\n" .
                    "- If you find relevant information in the context, use it to provide detailed, accurate answers\n" .
                    "- If the context doesn't contain sufficient information for the query, you may supplement with general knowledge but clearly indicate what comes from the database vs general knowledge\n" .
                    "- When listing shows or providing specific details, prioritize information from the database context\n\n" .
                    "DATABASE CONTEXT:\n" . 
                    str_repeat( '=', 80 ) . "\n" .
                    $context_text . "\n" .
                    str_repeat( '=', 80 ) . "\n\n" .
                    "Based on the above database context and your knowledge, please provide a helpful and informative response to the user's question.\n" .
                    "Do not refer the database or mention how you retrieved the information, just answer the user's question.";
    
    // 7. Prepare the request payload for Gemini API
    $contents = [
        [
            'role' => 'user',
            'parts' => [
                [ 'text' => $system_prompt ]
            ]
        ]
    ];
    
    // Add conversation history
    foreach ( $conversation_history as $msg ) {
        $contents[] = $msg;
    }
    
    // Add current user message
    $contents[] = [
        'role' => 'user',
        'parts' => [
            [ 'text' => $user_message ]
        ]
    ];
    
    $payload = [
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 2048,
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ]
        ]
    ];
    
    // 8. Use streaming generateContent endpoint
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:streamGenerateContent?key=' . $api_key;
    
    // 9. Initialize cURL for streaming
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_POST, 1 );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $payload ) );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false ); // Don't return, we'll handle output directly
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 60 );
    curl_setopt( $ch, CURLOPT_BUFFERSIZE, 128 ); // Smaller buffer for more frequent callbacks
    
    // 10. Set the cURL write function to process the stream
    curl_setopt( $ch, CURLOPT_WRITEFUNCTION, function ( $curl, $chunk ) {
        static $buffer = '';
        
        // Add new data to buffer
        $buffer .= $chunk;
        
        // Process complete JSON objects (Gemini sends JSON objects separated by commas and brackets)
        // Look for complete JSON objects ending with }
        while ( true ) {
            // Find the start of a JSON object
            $start_pos = strpos( $buffer, '{' );
            if ( $start_pos === false ) {
                break; // No JSON object start found
            }
            
            // Find the matching closing brace
            $brace_count = 0;
            $end_pos = $start_pos;
            $in_string = false;
            $escape_next = false;
            
            for ( $i = $start_pos; $i < strlen( $buffer ); $i++ ) {
                $char = $buffer[$i];
                
                if ( $escape_next ) {
                    $escape_next = false;
                    continue;
                }
                
                if ( $char === '\\' ) {
                    $escape_next = true;
                    continue;
                }
                
                if ( $char === '"' ) {
                    $in_string = !$in_string;
                    continue;
                }
                
                if ( !$in_string ) {
                    if ( $char === '{' ) {
                        $brace_count++;
                    } elseif ( $char === '}' ) {
                        $brace_count--;
                        if ( $brace_count === 0 ) {
                            $end_pos = $i;
                            break;
                        }
                    }
                }
            }
            
            // If we found a complete JSON object
            if ( $brace_count === 0 && $end_pos > $start_pos ) {
                $json_str = substr( $buffer, $start_pos, $end_pos - $start_pos + 1 );
                $buffer = substr( $buffer, $end_pos + 1 );
                
                // Try to decode JSON
                $decoded = json_decode( $json_str, true );
                
                if ( $decoded && isset( $decoded['candidates'][0]['content']['parts'][0]['text'] ) ) {
                    $text = $decoded['candidates'][0]['content']['parts'][0]['text'];
                    
                    // Send as SSE event in the format Deep Chat expects
                    echo 'data: ' . json_encode( [ 'text' => $text ] ) . "\n\n";
                    
                    // Flush immediately
                    flush();
                }
            } else {
                // No complete JSON object found, wait for more data
                break;
            }
        }
        
        // Required by cURL: return the number of bytes processed
        return strlen( $chunk );
    } );
    
    // 11. Execute the request
    $result = curl_exec( $ch );
    
    // 12. Check for cURL errors
    if ( curl_errno( $ch ) ) {
        $error_message = curl_error( $ch );
        echo 'data: ' . json_encode( [ 'error' => 'Connection error: ' . $error_message ] ) . "\n\n";
        flush();
    }
    
    curl_close( $ch );
    
    // 13. Send completion event
    echo 'data: [DONE]' . "\n\n";
    flush();
    
    // 14. IMPORTANT: Terminate the script to prevent WordPress from adding extra content
    exit;
}

/**
 * Build conversation history for Gemini API format.
 *
 * @param array $messages The message history.
 * @return array Formatted conversation history.
 */
function ai_chatbot_build_conversation_history($messages) {
    $history = [];
    
    // Skip the last message as it's handled separately
    $message_count = count($messages);
    if ($message_count <= 1) {
        return $history;
    }
    
    for ($i = 0; $i < $message_count - 1; $i++) {
        $message = $messages[$i];
        $role = isset($message['role']) ? $message['role'] : 'user';
        $text = isset($message['text']) ? $message['text'] : '';
        
        if (empty($text)) {
            continue;
        }
        
        // Convert role to Gemini format
        $gemini_role = ($role === 'ai' || $role === 'assistant') ? 'model' : 'user';
        
        $history[] = [
            'role' => $gemini_role,
            'parts' => [
                ['text' => $text]
            ]
        ];
    }
    
    return $history;
}

/**
 * Format context data for use in the prompt.
 *
 * @param array $context_data The context data from vector search.
 * @return string Formatted context text.
 */
function ai_chatbot_format_context($context_data) {
    if (!isset($context_data['data']['similarity']['docs']) || empty($context_data['data']['similarity']['docs'])) {
        return "No specific TV show information found in the database.";
    }
    
    $context_parts = [];
    $docs = $context_data['data']['similarity']['docs'];
    
    // Process all available documents (the API controls how many are returned)
    foreach ($docs as $index => $doc) {
        if (isset($doc['data']) && !empty($doc['data'])) {
            $score = isset($doc['score']) ? $doc['score'] : 0;
            
            // Include all results but note their relevance scores
            $content = $doc['data'];
            
            // If the data is an array or object, convert it to readable text
            if (is_array($content)) {
                $content = ai_chatbot_array_to_text($content);
            } elseif (is_object($content)) {
                $content = ai_chatbot_object_to_text($content);
            }
            
            // Clean up any HTML tags if present
            $content = wp_strip_all_tags($content);
            
            // Remove extra whitespace and normalize
            $content = preg_replace('/\s+/', ' ', trim($content));
            
            // Skip very short or empty content
            if (strlen($content) < 20) {
                continue;
            }
            
            // Limit content length but keep it substantial
            if (strlen($content) > 1500) {
                $content = substr($content, 0, 1500) . '...';
            }
            
            $context_parts[] = "Document " . ($index + 1) . " (Similarity Score: " . round($score, 3) . "):\n" . $content;
            
            // Limit to top 5 results to avoid overwhelming the prompt
            if (count($context_parts) >= 5) {
                break;
            }
        }
    }
    
    if (empty($context_parts)) {
        return "No TV show information could be extracted from the database results.";
    }
    
    $formatted_context = implode("\n\n" . str_repeat('-', 60) . "\n\n", $context_parts);
    
    // Add summary info
    $total_docs = count($docs);
    $used_docs = count($context_parts);
    $summary = "Retrieved {$used_docs} relevant documents out of {$total_docs} total results from the TV show database:\n\n";
    
    $final_context = $summary . $formatted_context;
    
    return $final_context;
}

/**
 * Convert array data to readable text.
 *
 * @param array $data The array data.
 * @return string Readable text.
 */
function ai_chatbot_array_to_text($data) {
    $text_parts = [];
    
    foreach ($data as $key => $value) {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_PRETTY_PRINT);
        }
        
        $text_parts[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
    }
    
    return implode("\n", $text_parts);
}

/**
 * Convert object data to readable text.
 *
 * @param object $data The object data.
 * @return string Readable text.
 */
function ai_chatbot_object_to_text($data) {
    return json_encode($data, JSON_PRETTY_PRINT);
}

/**
 * Get context from Smart Search API.
 *
 * @param string $message Search message.
 * @return array Context response.
 */
function ai_chatbot_get_context( $message ) {
    $url = ai_chatbot_get_smart_search_url();
    $access_token = ai_chatbot_get_smart_search_access_token();

    if ( ! $url || ! $access_token ) {
        throw new Exception( 'Smart Search URL or access token not configured' );
    }

    // GraphQL query using the correct schema
    $query = 'query GetContext($message: String!, $field: String!) {
        similarity(
            input: {
                nearest: {
                    text: $message,
                    field: $field
                }
            }) {
            total
            docs {
                id
                data
                score
            }
        }
    }';

    $variables = [
        'message' => $message,
        'field' => 'post_content'
    ];

    $body = [
        'query' => $query,
        'variables' => $variables
    ];

    $response = wp_remote_post( $url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token
        ],
        'body' => wp_json_encode( $body ),
        'timeout' => 30
    ] );

    if ( is_wp_error( $response ) ) {
        throw new Exception( 'Failed to fetch context: ' . $response->get_error_message() );
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    if ( $response_code !== 200 ) {
        $error_body = wp_remote_retrieve_body( $response );
        throw new Exception( 'Vector DB API returned status ' . $response_code . ': ' . $error_body );
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        throw new Exception( 'Invalid JSON response from Smart Search API: ' . json_last_error_msg() );
    }

    // Check for GraphQL errors
    if ( isset( $data['errors'] ) && ! empty( $data['errors'] ) ) {
        $error_messages = array_map( function( $error ) {
            return $error['message'] ?? 'Unknown GraphQL error';
        }, $data['errors'] );
        throw new Exception( 'GraphQL errors: ' . implode( ', ', $error_messages ) );
    }

    return $data;
}
