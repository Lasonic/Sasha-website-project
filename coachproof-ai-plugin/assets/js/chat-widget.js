/**
 * CoachProof AI – Frontend Widget
 *
 * Vanilla ES6 class managing the chatbot DOM, state machine, and REST
 * communication. Communicates only through the documented REST contract
 * and stable DOM hooks.
 *
 * UI States: idle | loading | streaming | answer | auth-required | error | escalation
 * Client Events: sasha:submit | sasha:success | sasha:error | sasha:escalation
 *
 * @package CoachProofAI
 */

( function () {
    'use strict';

    class CoachProofAI {

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
         * @param {HTMLElement} rootEl – The [coachproof_chatbot] shortcode container.
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
                <button class="coachproof-chat-toggle" aria-expanded="false" aria-label="Open chat">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-circle"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
                </button>
            ` );

            // Chat window
            this.#root.insertAdjacentHTML( 'beforeend', `
                <div class="coachproof-chat-window" role="log" aria-live="polite">
                    <div class="coachproof-chat-header">
                        <div class="coachproof-chat-header-info">
                            <p class="title">Financial Advisor</p>
                            <p class="subtitle">We typically reply instantly</p>
                        </div>
                        <button class="coachproof-chat-close" aria-label="Close chat">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                    <div class="coachproof-chat-messages"></div>
                    <div class="coachproof-chat-quick-replies"></div>
                    <div class="coachproof-chat-input-area">
                        <div class="coachproof-chat-input-wrapper">
                            <input type="text"
                                   class="coachproof-chat-input"
                                   placeholder="Type your question..."
                                   aria-label="Chat message" />
                        </div>
                        <button class="coachproof-chat-send" aria-label="Send message">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-send"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                        </button>
                    </div>
                </div>
            ` );

            // Cache references
            this.$toggle   = this.#root.querySelector( '.coachproof-chat-toggle' );
            this.$window   = this.#root.querySelector( '.coachproof-chat-window' );
            this.$close    = this.#root.querySelector( '.coachproof-chat-close' );
            this.$messages = this.#root.querySelector( '.coachproof-chat-messages' );
            this.$quickRep = this.#root.querySelector( '.coachproof-chat-quick-replies' );
            this.$input    = this.#root.querySelector( '.coachproof-chat-input' );
            this.$send     = this.#root.querySelector( '.coachproof-chat-send' );

            this.#renderQuickReplies();
        }

        /* ---------------------------------------------------------------
         * Event Binding
         * --------------------------------------------------------------- */

        #bindEvents() {
            this.$toggle.addEventListener( 'click', () => this.#toggleWindow() );
            this.$close.addEventListener( 'click', () => {
                if (this.$toggle.getAttribute('aria-expanded') === 'true') {
                    this.#toggleWindow();
                }
            });

            this.$send.addEventListener( 'click', () => this.#handleSubmit() );

            this.$input.addEventListener( 'keydown', ( e ) => {
                if ( e.key === 'Enter' && !e.shiftKey ) {
                    e.preventDefault();
                    this.#handleSubmit();
                }
            } );

            this.$quickRep.addEventListener( 'click', ( e ) => {
                if ( e.target.classList.contains('coachproof-chat-quick-reply') ) {
                    const text = e.target.textContent;
                    this.#handleQuickReply(text);
                }
            });
        }

        /* ---------------------------------------------------------------
         * Quick Replies
         * --------------------------------------------------------------- */

        #renderQuickReplies() {
            const replies = [
                "What services do you offer?",
                "How do I schedule a consultation?",
                "Tell me about retirement planning"
            ];
            
            this.$quickRep.innerHTML = replies.map(r => 
                `<button class="coachproof-chat-quick-reply">${r}</button>`
            ).join('');
        }

        #handleQuickReply(text) {
            this.$quickRep.remove(); // Remove quick replies after selecting one
            
            this.$input.value = text;
            this.#handleSubmit();
        }

        /* ---------------------------------------------------------------
         * Window Toggle
         * --------------------------------------------------------------- */

        #toggleWindow() {
            const isOpen = this.$toggle.getAttribute( 'aria-expanded' ) === 'true';
            this.$toggle.setAttribute( 'aria-expanded', String( !isOpen ) );
            this.$window.classList.toggle( 'coachproof-chat-window--open', !isOpen );

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
            el.classList.add( 'coachproof-chat-msg', `coachproof-chat-msg--${type}` );
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
            el.classList.add( 'coachproof-chat-loading' );
            el.innerHTML = `
                <span class="coachproof-chat-loading__dot"></span>
                <span class="coachproof-chat-loading__dot"></span>
                <span class="coachproof-chat-loading__dot"></span>
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
        const root = document.getElementById( 'coachproof-ai' );
        if ( root ) {
            new CoachProofAI( root );
        }
    } );

} )();
