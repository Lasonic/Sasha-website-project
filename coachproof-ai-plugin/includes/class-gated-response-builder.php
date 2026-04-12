<?php
/**
 * Gated Response Builder
 *
 * The single control point that enforces the intake gate.
 *
 * Flow:
 *   1. Receive the validated request payload (includes lead_profile snapshot from client).
 *   2. Re-validate intake completeness server-side — never trust client state alone.
 *   3. If intake incomplete → return an intake-prompt Coachproof_Chat_Result (no provider call).
 *   4. If intake complete   → delegate to the provider and return the answer.
 *
 * The intake gate cannot be bypassed by manipulating client-side state because the
 * backend re-validates from the supplied payload on every single request.
 *
 * @package CoachProofAI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gated_Response_Builder {

    /** @var Coachproof_Chat_Provider_Interface */
    private Coachproof_Chat_Provider_Interface $provider;

    /**
     * @param Coachproof_Chat_Provider_Interface $provider
     */
    public function __construct( Coachproof_Chat_Provider_Interface $provider ) {
        $this->provider = $provider;
    }

    /**
     * Process an incoming message against the intake gate.
     *
     * @param string       $message     The user's raw message text.
     * @param Lead_Profile $profile     Lead profile built from the request payload.
     * @param array        $context     conversation_id, page_context.
     * @param string       $trace_id    For log correlation.
     * @return Coachproof_Chat_Result
     */
    public function build( string $message, Lead_Profile $profile, array $context, string $trace_id ): Coachproof_Chat_Result {

        $step    = $profile->current_step();
        $missing = $profile->missing_fields();

        // ---------------------------------------------------------------
        // GATE: intake not complete — return a prompt, never call OpenAI.
        // ---------------------------------------------------------------
        if ( ! $profile->is_complete() ) {
            error_log( sprintf(
                '[coachproof-ai][%s] Intake gate blocked (step=%s, missing=%s)',
                $trace_id,
                $step,
                implode( ',', $missing )
            ) );

            $prompt_text = $this->intake_prompt( $step, $profile );

            return new Coachproof_Chat_Result(
                reply_text:      $prompt_text,
                ui_state:        'intake',
                delivery_mode:   'single',
                conversation_id: $context['conversation_id'] ?? '',
                error_code:      null,
                trace_id:        $trace_id,
                actions:         [
                    'mode'            => 'intake',
                    'current_step'    => $step,
                    'missing_fields'  => $missing,
                    'intake_complete' => false,
                    'objectives'      => Lead_Profile::OBJECTIVE_LABELS,
                ],
                requires_auth:   false
            );
        }

        // ---------------------------------------------------------------
        // GATE PASSED: all four fields valid — build an enriched system
        // prompt and forward to the provider.
        // ---------------------------------------------------------------
        $enriched_context = array_merge( $context, [
            'lead_profile' => $profile->to_array(),
            'system_note'  => $this->system_note( $profile ),
        ] );

        $result = $this->provider->send_message( $message, $enriched_context );

        // Merge intake metadata + any provider-supplied answer metadata
        // (answer_type, sources) into the actions array.
        $provider_actions = $result->actions ?? [];
        $result->actions  = array_merge( $provider_actions, [
            'mode'            => 'faq',
            'current_step'    => 'faq_mode',
            'missing_fields'  => [],
            'intake_complete' => true,
        ] );

        return $result;
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * Generate a conversational intake prompt for the current step.
     *
     * @param string       $step
     * @param Lead_Profile $profile
     * @return string
     */
    private function intake_prompt( string $step, Lead_Profile $profile ): string {
        switch ( $step ) {
            case 'collect_name_age':
                return "Hi there! Before I can help you, I just need a couple of quick details.\n\nWhat's your **name** and **age**?";

            case 'collect_occupation':
                $first = explode( ' ', $profile->name )[0];
                return "Thanks, {$first}! To make sure I point you in the right direction — what's your current **occupation or profession**?";

            case 'collect_objective':
                $first = explode( ' ', $profile->name )[0];
                return "Almost there, {$first}! Which of these best describes what you're looking for?";

            default:
                return "Let's get started. Could you share your name and age?";
        }
    }

    /**
     * Build a system note injected into the provider prompt to personalise answers.
     *
     * This note tells the AI who the user is and what to do:
     *   1. Recommend a specific coaching/training module based on the profile.
     *   2. Invite the user to provide more details for a more targeted recommendation.
     *   3. Offer to answer any questions within scope.
     *
     * @param Lead_Profile $profile
     * @return string
     */
    private function system_note( Lead_Profile $profile ): string {
        $label = Lead_Profile::OBJECTIVE_LABELS[ $profile->objective ] ?? $profile->objective;
        return sprintf(
            "USER PROFILE:\n" .
            "- Name: %s\n" .
            "- Age: %d\n" .
            "- Occupation: %s\n" .
            "- Selected objective: %s\n\n" .
            "INSTRUCTIONS FOR THIS CONVERSATION:\n" .
            "1. Based on the user's profile and objective, recommend the most suitable coaching or training module/package from the approved knowledge documents.\n" .
            "2. Explain briefly WHY this module is a good fit for their specific situation (age, occupation, objective).\n" .
            "3. After your recommendation, explicitly tell the user they can:\n" .
            "   a) Provide more details about their situation for a more targeted recommendation, OR\n" .
            "   b) Ask any questions about the services, packages, or processes.\n" .
            "4. Keep all answers grounded in the approved business documents. Do not speculate.\n" .
            "5. Be warm, professional, and concise. Use the user's first name.",
            $profile->name,
            $profile->age,
            $profile->occupation,
            $label
        );
    }
}
