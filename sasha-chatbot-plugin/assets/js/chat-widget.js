/**
 * Sasha Chatbot – Frontend Widget
 *
 * Vanilla ES6 class managing the chatbot DOM, state machine, and REST
 * communication. Communicates only through the documented REST contract
 * and stable DOM hooks.
 *
 * UI States: idle | loading | streaming | answer | auth-required | error | escalation
 * Client Events: sasha:submit | sasha:success | sasha:error | sasha:escalation
 *
 * @package SashaChatbot
 */

( function () {
    'use strict';

    class SashaChatbot {

        /** @type {string} Current UI state. */
        #state = 'idle';

        /** @type {string|null} Active conversation ID for multi-turn. */
        #conversationId = null;

        /** @type {HTMLElement} Root container rendered by the shortcode. */
        #root;

        /** @type {string} REST endpoint URL (from data-rest-url). */
        #restUrl;

        /** @type {string} WP REST nonce (from data-nonce). */
        #nonce;

        /**
         * Initialise the chatbot on the mount-point container.
         *
         * @param {HTMLElement} rootEl – The [sasha_chatbot] shortcode container.
         */
        constructor( rootEl ) {
            this.#root    = rootEl;
            this.#restUrl = rootEl.dataset.restUrl;
            this.#nonce   = rootEl.dataset.nonce;

            this.#buildDOM();
            this.#bindEvents();
            this.#setState( 'idle' );
        }

        /* ---------------------------------------------------------------
         * DOM Construction
         * --------------------------------------------------------------- */

        #buildDOM() {
            // Toggle button
            this.#root.insertAdjacentHTML( 'beforeend', `
                <button class="sasha-chat-toggle" aria-expanded="false" aria-label="Open chat">
                    <span class="sasha-chat-toggle__icon--open">💬</span>
                    <span class="sasha-chat-toggle__icon--close">✕</span>
                </button>
            ` );

            // Chat window
            this.#root.insertAdjacentHTML( 'beforeend', `
                <div class="sasha-chat-window" role="log" aria-live="polite">
                    <div class="sasha-chat-header">Sasha Coaching</div>
                    <div class="sasha-chat-messages"></div>
                    <div class="sasha-chat-input-area">
                        <input type="text"
                               class="sasha-chat-input"
                               placeholder="Ask me anything..."
                               aria-label="Chat message" />
                        <button class="sasha-chat-send" aria-label="Send message">➤</button>
                    </div>
                </div>
            ` );

            // Cache references
            this.$toggle   = this.#root.querySelector( '.sasha-chat-toggle' );
            this.$window   = this.#root.querySelector( '.sasha-chat-window' );
            this.$messages = this.#root.querySelector( '.sasha-chat-messages' );
            this.$input    = this.#root.querySelector( '.sasha-chat-input' );
            this.$send     = this.#root.querySelector( '.sasha-chat-send' );
        }

        /* ---------------------------------------------------------------
         * Event Binding
         * --------------------------------------------------------------- */

        #bindEvents() {
            this.$toggle.addEventListener( 'click', () => this.#toggleWindow() );

            this.$send.addEventListener( 'click', () => this.#handleSubmit() );

            this.$input.addEventListener( 'keydown', ( e ) => {
                if ( e.key === 'Enter' && !e.shiftKey ) {
                    e.preventDefault();
                    this.#handleSubmit();
                }
            } );
        }

        /* ---------------------------------------------------------------
         * Window Toggle
         * --------------------------------------------------------------- */

        #toggleWindow() {
            const isOpen = this.$toggle.getAttribute( 'aria-expanded' ) === 'true';
            this.$toggle.setAttribute( 'aria-expanded', String( !isOpen ) );
            this.$window.classList.toggle( 'sasha-chat-window--open', !isOpen );

            if ( !isOpen ) {
                this.$input.focus();
            }
        }

        /* ---------------------------------------------------------------
         * State Machine
         * --------------------------------------------------------------- */

        /**
         * Transition to a new UI state.
         *
         * @param {string} newState
         */
        #setState( newState ) {
            this.#state = newState;
            this.#root.setAttribute( 'data-ui-state', newState );

            const isInteractive = ( newState === 'idle' || newState === 'answer' );
            this.$input.disabled = !isInteractive;
            this.$send.disabled  = !isInteractive;
        }

        /* ---------------------------------------------------------------
         * Submit Handler
         * --------------------------------------------------------------- */

        async #handleSubmit() {
            const message = this.$input.value.trim();
            if ( !message || this.#state === 'loading' ) return;

            // Render user message and clear input.
            this.#appendMessage( message, 'user' );
            this.$input.value = '';

            // Dispatch client event.
            this.#root.dispatchEvent( new CustomEvent( 'sasha:submit', { detail: { message } } ) );

            // Transition to loading.
            this.#setState( 'loading' );
            const $loading = this.#showLoading();

            try {
                const response = await fetch( this.#restUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce':   this.#nonce,
                    },
                    body: JSON.stringify( {
                        message,
                        conversation_id: this.#conversationId || '',
                        page_context:    window.location.pathname,
                    } ),
                } );

                // Remove loading indicator.
                $loading.remove();

                const data = await response.json();

                // Persist conversation_id for multi-turn.
                if ( data.conversation_id ) {
                    this.#conversationId = data.conversation_id;
                }

                // Route based on the ui_state from the backend contract.
                switch ( data.ui_state ) {
                    case 'answer':
                        this.#appendMessage( data.reply_text, 'bot' );
                        this.#setState( 'answer' );
                        this.#root.dispatchEvent( new CustomEvent( 'sasha:success', { detail: data } ) );
                        break;

                    case 'auth-required':
                        this.#appendMessage(
                            data.reply_text || 'Please sign in to access this content.',
                            'auth'
                        );
                        this.#setState( 'auth-required' );
                        break;

                    case 'escalation':
                        this.#appendMessage(
                            data.reply_text || 'Let me connect you with a real person.',
                            'bot'
                        );
                        this.#setState( 'escalation' );
                        this.#root.dispatchEvent( new CustomEvent( 'sasha:escalation', { detail: data } ) );
                        break;

                    case 'error':
                    default:
                        this.#appendMessage(
                            data.reply_text || 'Something went wrong. Please try again.',
                            'error'
                        );
                        this.#setState( 'error' );
                        this.#root.dispatchEvent( new CustomEvent( 'sasha:error', { detail: data } ) );
                        break;
                }

            } catch ( err ) {
                // Network-level failure.
                $loading.remove();
                this.#appendMessage( 'Unable to reach the server. Please check your connection.', 'error' );
                this.#setState( 'error' );
                this.#root.dispatchEvent( new CustomEvent( 'sasha:error', { detail: { error: err.message } } ) );
            }

            // After rendering, re-enable input for subsequent messages.
            if ( this.#state === 'answer' || this.#state === 'error' ) {
                this.#setState( 'idle' );
            }
        }

        /* ---------------------------------------------------------------
         * DOM Helpers
         * --------------------------------------------------------------- */

        /**
         * Append a message bubble to the chat.
         *
         * @param {string} text
         * @param {'user'|'bot'|'error'|'auth'} type
         */
        #appendMessage( text, type ) {
            const el = document.createElement( 'div' );
            el.classList.add( 'sasha-chat-msg', `sasha-chat-msg--${type}` );
            el.textContent = text;
            this.$messages.appendChild( el );
            this.$messages.scrollTop = this.$messages.scrollHeight;
        }

        /**
         * Show the loading dots indicator.
         *
         * @returns {HTMLElement} The loading element (caller removes it).
         */
        #showLoading() {
            const el = document.createElement( 'div' );
            el.classList.add( 'sasha-chat-loading' );
            el.innerHTML = `
                <span class="sasha-chat-loading__dot"></span>
                <span class="sasha-chat-loading__dot"></span>
                <span class="sasha-chat-loading__dot"></span>
            `;
            this.$messages.appendChild( el );
            this.$messages.scrollTop = this.$messages.scrollHeight;
            return el;
        }
    }

    /* -------------------------------------------------------------------
     * Auto-initialise on DOMContentLoaded
     * ------------------------------------------------------------------- */
    document.addEventListener( 'DOMContentLoaded', () => {
        const root = document.getElementById( 'sasha-chatbot' );
        if ( root ) {
            new SashaChatbot( root );
        }
    } );

} )();
