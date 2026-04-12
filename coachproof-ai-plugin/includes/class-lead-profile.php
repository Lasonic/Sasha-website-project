<?php
/**
 * Lead Profile
 *
 * Holds and validates the four required intake fields for a CoachProof session.
 * This is a pure data/validation class with no side effects.
 *
 * Validation rules are config-driven where possible so an admin can tighten/loosen
 * age constraints without a code change.
 *
 * @package CoachProofAI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lead_Profile {

    /** @var string[] Approved objective enum values. */
    public const VALID_OBJECTIVES = [
        'retirement_planning_superannuation_advice',
        'investment_management_strategy',
        'risk_management_insurance_advice',
    ];

    /** @var string[] Human-readable objective labels keyed by enum value. */
    public const OBJECTIVE_LABELS = [
        'retirement_planning_superannuation_advice' => 'Retirement Planning & Superannuation Advice',
        'investment_management_strategy'            => 'Investment Management & Strategy',
        'risk_management_insurance_advice'          => 'Risk Management & Insurance Advice',
    ];

    /** @var string */
    public readonly string $name;

    /** @var int|null */
    public readonly ?int $age;

    /** @var string */
    public readonly string $occupation;

    /** @var string */
    public readonly string $objective;

    /**
     * @param string   $name
     * @param int|null $age
     * @param string   $occupation
     * @param string   $objective
     */
    public function __construct(
        string $name       = '',
        ?int   $age        = null,
        string $occupation = '',
        string $objective  = ''
    ) {
        $this->name       = trim( $name );
        $this->age        = $age;
        $this->occupation = trim( $occupation );
        $this->objective  = trim( $objective );
    }

    /**
     * Build a Lead_Profile from a raw associative array (e.g. from REST request).
     *
     * @param array $data
     * @return static
     */
    public static function from_array( array $data ): static {
        $age = isset( $data['age'] ) && is_numeric( $data['age'] )
            ? (int) $data['age']
            : null;

        return new static(
            name:       (string) ( $data['name']       ?? '' ),
            age:        $age,
            occupation: (string) ( $data['occupation'] ?? '' ),
            objective:  (string) ( $data['objective']  ?? '' ),
        );
    }

    /**
     * Determine which required intake fields are still missing or invalid.
     *
     * @return string[] Array of field names that still need to be collected.
     */
    public function missing_fields(): array {
        $missing = [];

        // --- name ---
        if ( strlen( $this->name ) < 2 || strlen( $this->name ) > 80 ) {
            $missing[] = 'name';
        }

        // --- age ---
        $age_min = (int) get_option( 'coachproof_intake_age_min', 18 );
        $age_max = (int) get_option( 'coachproof_intake_age_max', 100 );
        if ( $this->age === null || $this->age < $age_min || $this->age > $age_max ) {
            $missing[] = 'age';
        }

        // --- occupation ---
        if ( strlen( $this->occupation ) < 1 ) {
            $missing[] = 'occupation';
        }

        // --- objective ---
        if ( ! in_array( $this->objective, self::VALID_OBJECTIVES, true ) ) {
            $missing[] = 'objective';
        }

        return $missing;
    }

    /**
     * Determine the current intake step based on what has been collected so far.
     *
     * @return string One of: collect_name_age | collect_occupation | collect_objective | faq_mode
     */
    public function current_step(): string {
        $age_min = (int) get_option( 'coachproof_intake_age_min', 18 );
        $age_max = (int) get_option( 'coachproof_intake_age_max', 100 );

        $name_ok = strlen( $this->name ) >= 2 && strlen( $this->name ) <= 80;
        $age_ok  = $this->age !== null && $this->age >= $age_min && $this->age <= $age_max;

        if ( ! $name_ok || ! $age_ok ) {
            return 'collect_name_age';
        }

        if ( strlen( $this->occupation ) < 1 ) {
            return 'collect_occupation';
        }

        if ( ! in_array( $this->objective, self::VALID_OBJECTIVES, true ) ) {
            return 'collect_objective';
        }

        return 'faq_mode';
    }

    /**
     * Returns true only when all four fields pass validation.
     *
     * @return bool
     */
    public function is_complete(): bool {
        return empty( $this->missing_fields() );
    }

    /**
     * Serialise to a safe array for inclusion in REST responses.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'name'       => $this->name,
            'age'        => $this->age,
            'occupation' => $this->occupation,
            'objective'  => $this->objective,
        ];
    }
}
