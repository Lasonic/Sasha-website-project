/**
 * CoachProof AI – Frontend Widget
 *
 * Vanilla ES6 class managing a 4-step gated intake flow before entering
 * free-chat (FAQ) mode.
 *
 * Intake steps (enforced client-side AND server-side):
 *   1. collect_name_age    → name (text) + age (number)
 *   2. collect_occupation  → occupation (text)
 *   3. collect_objective   → one of three controlled buttons
 *   4. faq_mode            → normal chat input unlocked
 *
 * Local persistence: localStorage key `coachproof_session`
 * Shape: { conversation_id, current_step, lead_profile: { name, age, occupation, objective } }
 *
 * UI States: intake | loading | answer | error | escalation
 *
 * @package CoachProofAI
 */

( function () {
    'use strict';

    // ---------------------------------------------------------------
    // Constants
    // ---------------------------------------------------------------
    const STORAGE_KEY = 'coachproof_session';

    const STEPS = [
        'collect_name_age',
        'collect_occupation',
        'collect_objective',
        'faq_mode',
    ];

    const OBJECTIVES = [
        { value: 'retirement_planning_superannuation_advice', label: 'Retirement Planning & Superannuation Advice' },
        { value: 'investment_management_strategy',            label: 'Investment Management & Strategy' },
        { value: 'risk_management_insurance_advice',          label: 'Risk Management & Insurance Advice' },
    ];

    // ---------------------------------------------------------------
    // CoachProofWidget
    // ---------------------------------------------------------------
    class CoachProofWidget {

        /** @type {string} One of STEPS. */
        #step = 'collect_name_age';

        /** @type {string|null} */
        #conversationId = null;

        /** @type {{ name: string, age: number|null, occupation: string, objective: string }} */
        #leadProfile = { name: '', age: null, occupation: '', objective: '' };

        /** @type {HTMLElement} */
        #root;

        /** @type {string} */
        #restUrl;

        /** @type {string} */
        #nonce;

        /** @type {boolean} */
        #isLoading = false;

        /**
         * @param {HTMLElement} rootEl
         */
        constructor( rootEl ) {
            this.#root    = rootEl;
            this.#restUrl = rootEl.dataset.restUrl;
            this.#nonce   = rootEl.dataset.nonce;

            this.#restoreSession();
            this.#buildDOM();
            this.#bindEvents();
            this.#renderStep();
        }

        // ---------------------------------------------------------------
        // Session persistence
        // ---------------------------------------------------------------

        #restoreSession() {
            try {
                const saved = localStorage.getItem( STORAGE_KEY );
                if ( ! saved ) return;
                const session = JSON.parse( saved );
                if ( STEPS.includes( session.current_step ) ) {
                    this.#step           = session.current_step;
                    this.#conversationId = session.conversation_id || null;
                    this.#leadProfile    = Object.assign( { name: '', age: null, occupation: '', objective: '' }, session.lead_profile || {} );
                }
            } catch ( _e ) {
                // Corrupt storage — start fresh.
                localStorage.removeItem( STORAGE_KEY );
            }
        }

        #saveSession() {
            try {
                localStorage.setItem( STORAGE_KEY, JSON.stringify( {
                    conversation_id: this.#conversationId,
                    current_step:    this.#step,
                    lead_profile:    this.#leadProfile,
                } ) );
            } catch ( _e ) { /* storage full — non-fatal */ }
        }

        #clearSession() {
            localStorage.removeItem( STORAGE_KEY );
            this.#step           = 'collect_name_age';
            this.#conversationId = null;
            this.#leadProfile    = { name: '', age: null, occupation: '', objective: '' };
        }

        // ---------------------------------------------------------------
        // DOM Construction
        // ---------------------------------------------------------------

        #buildDOM() {
            this.#root.insertAdjacentHTML( 'beforeend', `
                <button class="coachproof-chat-toggle" aria-expanded="false" aria-label="Open chat">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
                </button>

                <div class="coachproof-chat-window" role="dialog" aria-label="CoachProof chat" aria-modal="true">
                    <div class="coachproof-chat-header">
                        <div class="coachproof-chat-header-info">
                            <p class="title">Financial Advisor</p>
                            <p class="subtitle">We typically reply instantly</p>
                        </div>
                        <button class="coachproof-chat-close" aria-label="Close chat">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>

                    <div class="coachproof-chat-messages" role="log" aria-live="polite"></div>

                    <!-- Intake step panels — shown one at a time -->
                    <div class="coachproof-intake-panel" id="coachproof-step-collect_name_age">
                        <p class="coachproof-intake-label">Your name</p>
                        <input id="coachproof-input-name" type="text" class="coachproof-intake-input" placeholder="First name" autocomplete="given-name" maxlength="80" />
                        <p class="coachproof-intake-label">Your age</p>
                        <input id="coachproof-input-age" type="number" class="coachproof-intake-input" placeholder="e.g. 35" min="1" max="120" />
                        <button class="coachproof-intake-next" id="coachproof-btn-name-age">Next →</button>
                        <p class="coachproof-intake-error" id="coachproof-err-name-age" aria-live="polite"></p>
                    </div>

                    <div class="coachproof-intake-panel" id="coachproof-step-collect_occupation">
                        <p class="coachproof-intake-label">Your occupation</p>
                        <input id="coachproof-input-occupation" type="text" class="coachproof-intake-input" placeholder="e.g. Engineer, Teacher, Business Owner" maxlength="100" />
                        <button class="coachproof-intake-next" id="coachproof-btn-occupation">Next →</button>
                        <p class="coachproof-intake-error" id="coachproof-err-occupation" aria-live="polite"></p>
                    </div>

                    <div class="coachproof-intake-panel" id="coachproof-step-collect_objective">
                        <p class="coachproof-intake-label">What are you looking for?</p>
                        <div class="coachproof-objective-list">
                            ${OBJECTIVES.map( o => `<button class="coachproof-objective-btn" data-value="${o.value}">${o.label}</button>` ).join( '' )}
                        </div>
                        <p class="coachproof-intake-error" id="coachproof-err-objective" aria-live="polite"></p>
                    </div>

                    <!-- FAQ (free-chat) area — shown only after intake complete -->
                    <div class="coachproof-intake-panel" id="coachproof-step-faq_mode">
                        <div class="coachproof-chat-input-area">
                            <div class="coachproof-chat-input-wrapper">
                                <input type="text"
                                       class="coachproof-chat-input"
                                       placeholder="Type your question..."
                                       aria-label="Chat message" />
                            </div>
                            <button class="coachproof-chat-send" aria-label="Send message">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            ` );

            // Cache references.
            this.$toggle     = this.#root.querySelector( '.coachproof-chat-toggle' );
            this.$window     = this.#root.querySelector( '.coachproof-chat-window' );
            this.$close      = this.#root.querySelector( '.coachproof-chat-close' );
            this.$messages   = this.#root.querySelector( '.coachproof-chat-messages' );
            this.$inputName  = this.#root.querySelector( '#coachproof-input-name' );
            this.$inputAge   = this.#root.querySelector( '#coachproof-input-age' );
            this.$btnNameAge = this.#root.querySelector( '#coachproof-btn-name-age' );
            this.$errNameAge = this.#root.querySelector( '#coachproof-err-name-age' );
            this.$inputOcc   = this.#root.querySelector( '#coachproof-input-occupation' );
            this.$btnOcc     = this.#root.querySelector( '#coachproof-btn-occupation' );
            this.$errOcc     = this.#root.querySelector( '#coachproof-err-occupation' );
            this.$errObj     = this.#root.querySelector( '#coachproof-err-objective' );
            this.$chatInput  = this.#root.querySelector( '.coachproof-chat-input' );
            this.$chatSend   = this.#root.querySelector( '.coachproof-chat-send' );
        }

        // ---------------------------------------------------------------
        // Event Binding
        // ---------------------------------------------------------------

        #bindEvents() {
            this.$toggle.addEventListener( 'click', () => this.#toggleWindow() );
            this.$close.addEventListener( 'click', () => {
                if ( this.$toggle.getAttribute( 'aria-expanded' ) === 'true' ) {
                    this.#toggleWindow();
                }
            } );

            // Step 1: name + age
            this.$btnNameAge.addEventListener( 'click', () => this.#submitNameAge() );
            this.$inputAge.addEventListener( 'keydown', e => { if ( e.key === 'Enter' ) this.#submitNameAge(); } );

            // Step 2: occupation
            this.$btnOcc.addEventListener( 'click', () => this.#submitOccupation() );
            this.$inputOcc.addEventListener( 'keydown', e => { if ( e.key === 'Enter' ) this.#submitOccupation(); } );

            // Step 3: objective (delegated to parent div)
            this.#root.querySelector( '.coachproof-objective-list' ).addEventListener( 'click', e => {
                const btn = e.target.closest( '.coachproof-objective-btn' );
                if ( btn ) this.#submitObjective( btn.dataset.value );
            } );

            // Step 4: FAQ chat
            this.$chatSend.addEventListener( 'click', () => this.#handleChatSubmit() );
            this.$chatInput.addEventListener( 'keydown', e => {
                if ( e.key === 'Enter' && ! e.shiftKey ) {
                    e.preventDefault();
                    this.#handleChatSubmit();
                }
            } );
        }

        // ---------------------------------------------------------------
        // Step Rendering
        // ---------------------------------------------------------------

        #renderStep() {
            // Hide all panels.
            this.#root.querySelectorAll( '.coachproof-intake-panel' ).forEach( el => {
                el.classList.remove( 'coachproof-intake-panel--active' );
            } );

            // Show the active panel.
            const active = this.#root.querySelector( `#coachproof-step-${this.#step}` );
            if ( active ) {
                active.classList.add( 'coachproof-intake-panel--active' );
            }

            // Restore any saved values into form fields.
            if ( this.#step === 'collect_name_age' ) {
                if ( this.#leadProfile.name ) this.$inputName.value = this.#leadProfile.name;
                if ( this.#leadProfile.age  ) this.$inputAge.value  = this.#leadProfile.age;
            }
            if ( this.#step === 'collect_occupation' && this.#leadProfile.occupation ) {
                this.$inputOcc.value = this.#leadProfile.occupation;
            }
        }

        // ---------------------------------------------------------------
        // Window Toggle
        // ---------------------------------------------------------------

        #hasShownWelcome = false;

        #toggleWindow() {
            const isOpen = this.$toggle.getAttribute( 'aria-expanded' ) === 'true';
            this.$toggle.setAttribute( 'aria-expanded', String( ! isOpen ) );
            this.$window.classList.toggle( 'coachproof-chat-window--open', ! isOpen );

            // Show welcome message on first open.
            if ( ! isOpen && ! this.#hasShownWelcome ) {
                this.#hasShownWelcome = true;

                if ( this.#step === 'faq_mode' ) {
                    // Returning user — already completed intake.
                    this.#appendMessage( `Welcome back! Feel free to ask me anything about our services.`, 'bot' );
                } else {
                    // New user — starting intake.
                    this.#appendMessage(
                        `👋 Hi there! I'm your financial advisor assistant.\n\nBefore I can recommend the best package for you, I just need a few quick details. Let's start!`,
                        'bot'
                    );
                }
            }
        }

        // ---------------------------------------------------------------
        // Intake Step Handlers
        // ---------------------------------------------------------------

        #submitNameAge() {
            const name = this.$inputName.value.trim();
            const age  = parseInt( this.$inputAge.value, 10 );

            if ( name.length < 2 || name.length > 80 ) {
                this.$errNameAge.textContent = 'Please enter your full name (2–80 characters).';
                this.$inputName.focus();
                return;
            }
            if ( isNaN( age ) || age < 1 || age > 120 ) {
                this.$errNameAge.textContent = 'Please enter a valid age (1–120).';
                this.$inputAge.focus();
                return;
            }

            this.$errNameAge.textContent = '';
            this.#leadProfile.name = name;
            this.#leadProfile.age  = age;
            this.#advanceTo( 'collect_occupation' );
        }

        #submitOccupation() {
            const occ = this.$inputOcc.value.trim();

            if ( occ.length < 1 ) {
                this.$errOcc.textContent = 'Please enter your occupation.';
                this.$inputOcc.focus();
                return;
            }

            this.$errOcc.textContent = '';
            this.#leadProfile.occupation = occ;
            this.#advanceTo( 'collect_objective' );
        }

        #submitObjective( value ) {
            const validValues = OBJECTIVES.map( o => o.value );
            if ( ! validValues.includes( value ) ) {
                this.$errObj.textContent = 'Please select one of the options above.';
                return;
            }

            this.$errObj.textContent = '';
            this.#leadProfile.objective = value;

            // Highlight selected button.
            this.#root.querySelectorAll( '.coachproof-objective-btn' ).forEach( btn => {
                btn.classList.toggle( 'coachproof-objective-btn--selected', btn.dataset.value === value );
            } );

            this.#advanceTo( 'faq_mode' );

            // Post intake completion to the backend to get the greeting/recommendation.
            this.#postIntakeComplete();
        }

        #advanceTo( step ) {
            this.#step = step;
            this.#saveSession();
            this.#renderStep();
        }

        // ---------------------------------------------------------------
        // Post intake complete — send one message to trigger recommendation
        // ---------------------------------------------------------------

        async #postIntakeComplete() {
            this.#appendMessage( 'Thanks for those details! Let me find the right package for you…', 'bot' );
            this.#setLoading( true );

            try {
                const data = await this.#post( 'I have completed the intake. Based on my profile, please recommend the most suitable coaching or training module and let me know what my options are.' );
                this.#setLoading( false );
                this.#handleServerResponse( data );
            } catch ( err ) {
                this.#setLoading( false );
                this.#appendMessage( 'Unable to reach the server. Please try again.', 'error' );
            }
        }

        // ---------------------------------------------------------------
        // FAQ Chat Submit
        // ---------------------------------------------------------------

        async #handleChatSubmit() {
            if ( this.#isLoading ) return;
            const message = this.$chatInput.value.trim();
            if ( ! message ) return;

            this.$chatInput.value = '';
            this.#appendMessage( message, 'user' );
            this.#setLoading( true );

            try {
                const data = await this.#post( message );
                this.#setLoading( false );
                this.#handleServerResponse( data );
            } catch ( err ) {
                this.#setLoading( false );
                this.#appendMessage( 'Unable to reach the server. Please check your connection.', 'error' );
            }
        }

        // ---------------------------------------------------------------
        // Server Response Handler
        // ---------------------------------------------------------------

        #handleServerResponse( data ) {
            // Persist conversation_id (Thread ID) for multi-turn.
            if ( data.conversation_id ) {
                this.#conversationId = data.conversation_id;
                this.#saveSession();
            }

            // If the server disagrees with client step, correct it.
            const actions = data.actions || {};
            if ( actions.current_step && STEPS.includes( actions.current_step ) ) {
                if ( actions.current_step !== this.#step ) {
                    this.#step = actions.current_step;
                    this.#renderStep();
                    this.#saveSession();
                }
            }

            switch ( data.ui_state ) {
                case 'intake':
                    // Server gated the response — render the prompt as a bot message.
                    this.#appendMessage( data.reply_text, 'bot' );

                    // Sync step if server corrects us.
                    if ( actions.current_step && actions.current_step !== this.#step ) {
                        this.#step = actions.current_step;
                        this.#renderStep();
                        this.#saveSession();
                    }
                    break;

                case 'answer':
                    this.#appendMessage( data.reply_text, 'bot' );
                    // Show source citations if available.
                    if ( actions.sources && actions.sources.length > 0 ) {
                        this.#appendSources( actions.sources );
                    }
                    break;

                case 'escalation':
                    this.#appendMessage( data.reply_text || 'Let me connect you with our team.', 'bot' );
                    break;

                case 'error':
                default:
                    this.#appendMessage( data.reply_text || 'Something went wrong. Please try again.', 'error' );
                    break;
            }
        }

        // ---------------------------------------------------------------
        // REST Communication
        // ---------------------------------------------------------------

        async #post( message ) {
            const response = await fetch( this.#restUrl, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   this.#nonce,
                },
                body: JSON.stringify( {
                    message,
                    conversation_id: this.#conversationId || '',
                    page_context:    window.location.pathname,
                    lead_profile:    this.#leadProfile,
                } ),
            } );

            if ( ! response.ok && response.status !== 200 ) {
                throw new Error( `HTTP ${response.status}` );
            }

            return response.json();
        }

        // ---------------------------------------------------------------
        // DOM Helpers
        // ---------------------------------------------------------------

        #appendMessage( text, type ) {
            const el = document.createElement( 'div' );
            el.classList.add( 'coachproof-chat-msg', `coachproof-chat-msg--${type}` );
            el.textContent = text;
            this.$messages.appendChild( el );
            this.$messages.scrollTop = this.$messages.scrollHeight;
        }

        #setLoading( state ) {
            this.#isLoading = state;

            if ( state ) {
                const el = document.createElement( 'div' );
                el.id = 'coachproof-loading';
                el.classList.add( 'coachproof-chat-loading' );
                el.innerHTML = `
                    <span class="coachproof-chat-loading__dot"></span>
                    <span class="coachproof-chat-loading__dot"></span>
                    <span class="coachproof-chat-loading__dot"></span>
                `;
                this.$messages.appendChild( el );
                this.$messages.scrollTop = this.$messages.scrollHeight;
            } else {
                const el = document.getElementById( 'coachproof-loading' );
                if ( el ) el.remove();
            }
        }

        /**
         * Append a source citation footer under a bot message.
         * @param {string[]} sources
         */
        #appendSources( sources ) {
            const el = document.createElement( 'div' );
            el.classList.add( 'coachproof-chat-sources' );
            el.innerHTML = `<span class="coachproof-sources-label">📄 Sources:</span> ${sources.map( s => `<span class="coachproof-source-tag">${this.#escapeHtml(s)}</span>` ).join( '' )}`;
            this.$messages.appendChild( el );
            this.$messages.scrollTop = this.$messages.scrollHeight;
        }

        /**
         * Simple HTML escape.
         * @param {string} str
         * @return {string}
         */
        #escapeHtml( str ) {
            const div = document.createElement( 'div' );
            div.textContent = str;
            return div.innerHTML;
        }
    }

    // ---------------------------------------------------------------
    // Auto-initialise on DOMContentLoaded
    // ---------------------------------------------------------------
    document.addEventListener( 'DOMContentLoaded', () => {
        const root = document.getElementById( 'coachproof-ai' );
        if ( root ) {
            new CoachProofWidget( root );
        }
    } );

} )();
