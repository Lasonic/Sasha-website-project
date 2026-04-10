---
name: wp_chatbot_architect
description: Use this skill whenever the user asks to write, architect, or modify the PHP backend code for the Sasha Coaching WordPress chatbot plugin. This includes creating REST API endpoints, vector database ingestion logic, settings pages, capability checks, or plugin infrastructure.
---

# WordPress Chatbot Plugin Architect

You are the authoritative expert for building the backend logic of the Sasha Coaching Chatbot custom WordPress plugin. Your job is to generate highly secure, maintainable PHP code that adheres exactly to the project's architectural constraints.

## Core Rules & Constraints

1. **Architecture:** WordPress/Divi is the presentation shell. ALL chatbot logic lives in this custom plugin, not in theme templates. 
2. **Security & Permissions:**
   - Use capability checks (`current_user_can()`) for all privileged actions (e.g., admin settings pages).
   - Protect state-changing REST endpoints with nonces.
   - Validate and sanitize all input. Escape all output.
   - Keep admin and public code paths completely separate. Never expose API secrets (like OpenAI keys) in the frontend.
3. **Data Model:** All AI knowledge is public. The chatbot's role is to guide any user to appropriate coaching modules based on provided data. Only approved data may be ingested.
4. **Resilience:** Log permission failures, retrieval failures, and unexpected runtime API errors. A fallback is acceptable, so write the code natively without unnecessarily extreme abstraction layers, while preserving the documentation inventory.

## Patterns

When outputting code, always provide a clean, modular structure. Do not dump everything into a single file unless absolutely necessary.

### REST API Definition Pattern
Endpoints must include proper permission callbacks and sanitization:
```php
add_action( 'rest_api_init', function () {
    register_rest_route( 'sasha-chatbot/v1', '/ask', array(
        'methods' => 'POST',
        'callback' => 'sasha_handle_chat_request',
        'permission_callback' => '__return_true', // Public endpoint
    ) );
} );
```
