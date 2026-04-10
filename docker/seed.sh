#!/bin/sh
# ------------------------------------------------------------------
# Sasha Chatbot – WordPress Seed Script
# Runs inside the WP-CLI sidecar container on first boot.
# Waits for WordPress to be ready, then seeds deterministic test data.
# ------------------------------------------------------------------

set -e

echo "[seed] Waiting for WordPress database to be ready..."
MAX_TRIES=30
COUNT=0
until wp db query "SELECT 1" --skip-ssl 2>/dev/null; do
  COUNT=$((COUNT + 1))
  if [ "$COUNT" -ge "$MAX_TRIES" ]; then
    echo "[seed] ERROR: Database did not become ready in time."
    exit 1
  fi
  echo "[seed]   …waiting ($COUNT/$MAX_TRIES)"
  sleep 3
done

# ------------------------------------------------------------------
# Core install (idempotent – skips if already installed)
# ------------------------------------------------------------------
if ! wp core is-installed 2>/dev/null; then
  echo "[seed] Running WordPress core install..."
  wp core install \
    --url="http://localhost:8000" \
    --title="Sasha Coaching (Local Dev)" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=dev@localhost.test \
    --skip-email
else
  echo "[seed] WordPress already installed — skipping core install."
fi

# ------------------------------------------------------------------
# Permalink structure (required for REST API pretty URLs)
# ------------------------------------------------------------------
echo "[seed] Setting permalink structure..."
wp rewrite structure '/%postname%/' --hard

# ------------------------------------------------------------------
# Activate the chatbot plugin
# ------------------------------------------------------------------
echo "[seed] Activating sasha-chatbot plugin..."
wp plugin activate sasha-chatbot 2>/dev/null || echo "[seed]   Plugin not found or already active — will retry on next boot."

# ------------------------------------------------------------------
# Seed test page with chatbot shortcode (idempotent)
# ------------------------------------------------------------------
if ! wp post list --post_type=page --name=chatbot-test --field=ID 2>/dev/null | grep -q .; then
  echo "[seed] Creating test page with [sasha_chatbot] shortcode..."
  wp post create \
    --post_type=page \
    --post_title="Chatbot Test" \
    --post_name="chatbot-test" \
    --post_status=publish \
    --post_content='<!-- Sasha Chatbot mount point -->[sasha_chatbot]'
else
  echo "[seed] Test page 'chatbot-test' already exists — skipping."
fi

# ------------------------------------------------------------------
# Seed test user for auth-required UI state testing
# MVP: stub user for UI state testing only.
# Post-MVP: integrate with membership plugin for real role-based retrieval.
# ------------------------------------------------------------------
if ! wp user get testmember --field=ID 2>/dev/null; then
  echo "[seed] Creating test member user..."
  wp user create testmember testmember@localhost.test \
    --role=subscriber \
    --user_pass=testpassword123
else
  echo "[seed] User 'testmember' already exists — skipping."
fi

# ------------------------------------------------------------------
# Define the API key constant in wp-config.php if env var is set
# ------------------------------------------------------------------
if [ -n "$SASHA_OPENAI_API_KEY" ]; then
  if ! grep -q 'SASHA_OPENAI_API_KEY' /var/www/html/wp-config.php 2>/dev/null; then
    echo "[seed] Injecting SASHA_OPENAI_API_KEY constant into wp-config.php..."
    wp config set SASHA_OPENAI_API_KEY "$SASHA_OPENAI_API_KEY" --type=constant --quiet
  fi
fi

echo "[seed] ✅ Seed complete. Site ready at http://localhost:8000"
echo "[seed]    Admin: admin / admin"
echo "[seed]    Test member: testmember / testpassword123"
echo "[seed]    Chatbot page: http://localhost:8000/chatbot-test/"
