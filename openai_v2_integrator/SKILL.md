---
name: openai-responses-integrator
description: Use this skill whenever the user asks to integrate, build, or troubleshoot the OpenAI Responses API connection within their PHP WordPress plugin. This covers sending user messages via the /v1/responses endpoint, handling the normalized response, and implementing the ChatProviderInterface pattern. Use this skill for any OpenAI API work, even if the user says "chatbot API" or "AI provider" without naming the specific endpoint.
---

# OpenAI Responses API Integrator

You are the authoritative expert for integrating the OpenAI Responses API in PHP within a WordPress plugin context. Your job is to generate accurate, robust code that correctly uses the Responses API endpoint and fits cleanly behind a provider interface pattern.

## Why Responses API (not Assistants V2)

The Assistants API V2 requires managing Threads, Messages, Runs, and polling loops — unnecessary complexity for an MVP that only needs single-turn public guidance. The Responses API is a simpler, stateless request/response pattern. One POST, one answer. This is the right tool for the job.

## Core Rules & Constraints

1. **Correct Endpoint:** All requests go to `POST https://api.openai.com/v1/responses`. Do NOT use `/v1/chat/completions` or `/v1/assistants`.
2. **Provider Interface Pattern:** All OpenAI-specific code must implement a `ChatProviderInterface` with a single normalized method: `send_message( $message, $context ): ChatResult`. The rest of the plugin never touches OpenAI directly.
3. **WordPress HTTP Functions:** Use `wp_remote_post()` for external HTTP calls. Always check for `WP_Error` before decoding JSON.
4. **API Key Security:** Fetch the API key with this precedence:
   - `SASHA_OPENAI_API_KEY` constant (defined in `wp-config.php` or environment)
   - `get_option( 'sasha_chatbot_api_key' )` as fallback
   - Never log, echo, or expose the key in any response.
5. **Normalized Response:** Always return a `ChatResult` value object containing: `reply_text`, `ui_state`, `delivery_mode`, `conversation_id`, `error_code`, `trace_id`. The provider maps OpenAI's raw response into this shape.
6. **Error Handling:** On provider failure (timeout, rate limit, bad key, malformed response), return a `ChatResult` with `ui_state: 'error'`, a safe `error_code`, and a unique `trace_id` for server-side log correlation. Never expose raw OpenAI error messages to the user.

## Request Shape

```php
$body = array(
    'model'        => $model,        // e.g. 'gpt-4.1'
    'instructions'  => $system_prompt, // system-level coaching instructions
    'input'        => $user_message,  // the visitor's chat message
);

$response = wp_remote_post( 'https://api.openai.com/v1/responses', array(
    'headers' => array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
    ),
    'body'    => wp_json_encode( $body ),
    'timeout' => $timeout,
) );
```

## Response Mapping

Map the OpenAI response into a normalized `ChatResult`:

```php
$data = json_decode( wp_remote_retrieve_body( $response ), true );

// The Responses API returns the output in $data['output']
$reply_text = $data['output'][0]['content'][0]['text'] ?? '';

return new ChatResult(
    reply_text    : $reply_text,
    ui_state      : 'answer',
    delivery_mode : 'single',
    conversation_id : $data['id'] ?? wp_generate_uuid4(),
    error_code    : null,
    trace_id      : $trace_id,
);
```

## What NOT To Do

- Do NOT create Threads or Runs. Those belong to the Assistants API, which we are not using.
- Do NOT poll for status. The Responses API returns the complete answer in a single HTTP response.
- Do NOT implement streaming unless the backend configuration explicitly requests it. MVP transport is single-response.
- Do NOT hardcode the model. Read it from the plugin settings.
