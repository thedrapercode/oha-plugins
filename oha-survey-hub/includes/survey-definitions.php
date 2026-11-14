<?php
/**
 * Survey Definitions
 * Contains all survey structures, questions, and configurations
 */

if (!defined('ABSPATH')) {
    exit;
}

class OHA_Survey_Definitions {
    
    public static function get_all_surveys() {
        return array(
            'member' => self::get_member_survey(),
            'haunt_owner' => self::get_haunt_owner_survey(),
            'scare_actor' => self::get_scare_actor_survey(),
            'behind_scenes' => self::get_behind_scenes_survey(),
            'home_haunt' => self::get_home_haunt_survey(),
            'industry_deep_dive'   => self::get_industry_deep_dive_survey(),
            'convention_planning'  => self::get_convention_planning_survey()
        );
    }
    
    public static function get_survey($survey_type) {
        $surveys = self::get_all_surveys();
        return isset($surveys[$survey_type]) ? $surveys[$survey_type] : null;
    }

    // Returns the most recently completed haunt season year (past) and the upcoming one (next).
    // Haunt season runs August through October. After October, that year's season is complete.
    public static function haunt_season_years() {
        $month = (int) date('n');
        $year  = (int) date('Y');
        if ($month >= 11) {
            return array('past' => $year, 'next' => $year + 1);
        }
        return array('past' => $year - 1, 'next' => $year);
    }

    private static function get_member_survey() {
        return array(
            'title' => __('OHA Member Survey', 'oha-survey-hub'),
            'description' => __('Help us better understand and support the Ohio haunt community.', 'oha-survey-hub'),
            'pages' => array(
                array(
                    'title' => __('Welcome & Context', 'oha-survey-hub'),
                    'description' => __('Your feedback helps OHA serve the Ohio haunt community better. This survey is anonymous - we only collect your email for confirmation and to prevent duplicate responses.', 'oha-survey-hub'),
                    'questions' => array()
                ),
                array(
                    'title' => __('Your Role & Background', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'role_in_industry',
                            'question' => __('Which best describes your role in the haunt industry?', 'oha-survey-hub'),
                            'type' => 'select',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'owner_operator' => __('Owner/Operator', 'oha-survey-hub'),
                                'manager' => __('Manager', 'oha-survey-hub'),
                                'actor' => __('Actor', 'oha-survey-hub'),
                                'builder_designer' => __('Builder/Designer', 'oha-survey-hub'),
                                'vendor' => __('Vendor', 'oha-survey-hub'),
                                'enthusiast' => __('Enthusiast', 'oha-survey-hub'),
                                'other' => __('Other', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'years_involved',
                            'question' => __('How long have you been involved in the haunt industry?', 'oha-survey-hub'),
                            'type' => 'select',
                            'required' => true,
                            'options' => array(
                                'less_than_1' => __('Less than 1 year', 'oha-survey-hub'),
                                '1_to_3' => __('1-3 years', 'oha-survey-hub'),
                                '4_to_7' => __('4-7 years', 'oha-survey-hub'),
                                '8_to_15' => __('8-15 years', 'oha-survey-hub'),
                                'more_than_15' => __('More than 15 years', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'oha_connection_length',
                            'question' => __('How long have you been connected to the Ohio Haunters Association (OHA)?', 'oha-survey-hub'),
                            'type' => 'select',
                            'required' => true,
                            'options' => array(
                                'new_member' => __('New member (less than 1 year)', 'oha-survey-hub'),
                                '1_to_2' => __('1-2 years', 'oha-survey-hub'),
                                '3_to_5' => __('3-5 years', 'oha-survey-hub'),
                                'more_than_5' => __('More than 5 years', 'oha-survey-hub'),
                                'not_member' => __('Not an OHA member', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'how_found_oha',
                            'question' => __('How did you first find out about OHA?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'facebook_social' => __('Facebook or other social media', 'oha-survey-hub'),
                                'word_of_mouth' => __('Word of mouth from another haunter', 'oha-survey-hub'),
                                'web_search' => __('Found the OHA website through a search', 'oha-survey-hub'),
                                'haunt_convention' => __('At a haunt convention or event', 'oha-survey-hub'),
                                'owner_referral' => __('A haunt owner told me', 'oha-survey-hub'),
                                'oha_convention' => __('Through the OHA Convention itself', 'oha-survey-hub'),
                                'other' => __('Other', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'knew_oha_scope',
                            'question' => __('Before today, did you know OHA is for more than just haunt owners?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => true,
                            'options' => array(
                                'yes' => __('Yes', 'oha-survey-hub'),
                                'no' => __('No', 'oha-survey-hub'),
                                'somewhat' => __('Somewhat', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Value & Support', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'oha_value_rating',
                            'question' => __('Overall, how valuable is OHA to you right now?', 'oha-survey-hub'),
                            'type' => 'scale',
                            'required' => true,
                            'priority' => 'high',
                            'scale_min' => 1,
                            'scale_max' => 5,
                            'scale_labels' => array(
                                1 => __('Not valuable', 'oha-survey-hub'),
                                5 => __('Extremely valuable', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'oha_community_support',
                            'question' => __('How well do you feel OHA supports the Ohio haunt community?', 'oha-survey-hub'),
                            'type' => 'scale',
                            'required' => true,
                            'scale_min' => 1,
                            'scale_max' => 5,
                            'scale_labels' => array(
                                1 => __('Poor support', 'oha-survey-hub'),
                                5 => __('Excellent support', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'current_most_value',
                            'question' => __('What currently provides the most value to you from OHA?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false
                        ),
                        array(
                            'id' => 'improvement_areas',
                            'question' => __('Where do you feel OHA could improve or better support you?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'membership_fee_value',
                            'question' => __('Does your annual OHA membership feel worth the cost?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'definitely_yes' => __('Definitely yes', 'oha-survey-hub'),
                                'yes' => __('Yes', 'oha-survey-hub'),
                                'somewhat' => __('Somewhat', 'oha-survey-hub'),
                                'not_sure' => __('Not sure yet', 'oha-survey-hub'),
                                'not_really' => __('Not really', 'oha-survey-hub'),
                                'no' => __('No', 'oha-survey-hub'),
                                'not_a_member' => __('I am not a paying member', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Convention Experience', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'attended_oha_convention',
                            'question' => __('Have you attended any OHA Convention?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'yes' => __('Yes', 'oha-survey-hub'),
                                'no' => __('No', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'convention_most_recent',
                            'question' => __('Which was the most recent OHA Convention you attended?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'options' => array(
                                '2026' => __('2026', 'oha-survey-hub'),
                                '2024_2025' => __('2024 or 2025', 'oha-survey-hub'),
                                '2023_earlier' => __('2023 or earlier', 'oha-survey-hub'),
                                'not_sure' => __('Not sure', 'oha-survey-hub')
                            ),
                            'depends_on' => array(
                                'field' => 'attended_oha_convention',
                                'value' => 'yes'
                            )
                        ),
                        array(
                            'id' => 'valuable_convention_parts',
                            'question' => __('Which parts of the convention are most valuable to you?', 'oha-survey-hub'),
                            'type' => 'checkbox',
                            'required' => false,
                            'options' => array(
                                'seminars' => __('Educational seminars', 'oha-survey-hub'),
                                'vendor_showcase' => __('Vendor showcase', 'oha-survey-hub'),
                                'networking' => __('Networking opportunities', 'oha-survey-hub'),
                                'awards' => __('Awards ceremony', 'oha-survey-hub'),
                                'location_tours' => __('Location tours', 'oha-survey-hub'),
                                'social_events' => __('Social events', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'convention_improvements',
                            'question' => __('What would most improve the convention experience for you?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Education & Learning', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'education_satisfaction',
                            'question' => __('How satisfied are you with OHA\'s educational offerings?', 'oha-survey-hub'),
                            'type' => 'scale',
                            'required' => true,
                            'priority' => 'high',
                            'scale_min' => 1,
                            'scale_max' => 5,
                            'scale_labels' => array(
                                1 => __('Very dissatisfied', 'oha-survey-hub'),
                                5 => __('Very satisfied', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'valuable_learning_formats',
                            'question' => __('Which learning formats do you find most valuable?', 'oha-survey-hub'),
                            'type' => 'checkbox',
                            'required' => false,
                            'options' => array(
                                'in_person_seminars' => __('In-person seminars', 'oha-survey-hub'),
                                'online_webinars' => __('Online webinars', 'oha-survey-hub'),
                                'hands_on_workshops' => __('Hands-on workshops', 'oha-survey-hub'),
                                'recorded_sessions' => __('Recorded sessions', 'oha-survey-hub'),
                                'written_guides' => __('Written guides', 'oha-survey-hub'),
                                'peer_discussions' => __('Peer discussions', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'topics_to_expand',
                            'question' => __('Which topics would you most like OHA to expand on?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'missing_learning_resources',
                            'question' => __('Is there something you want to learn but haven\'t found good resources for yet?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false
                        )
                    )
                ),
                array(
                    'title' => __('Technology & Digital Engagement', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'convention_app_used',
                            'question' => __('Have you used the OHA Convention App during the convention?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'yes_regularly' => __('Yes, used it regularly', 'oha-survey-hub'),
                                'yes_briefly' => __('Yes, tried it briefly', 'oha-survey-hub'),
                                'no_did_not_use' => __('No, did not use it', 'oha-survey-hub'),
                                'not_aware' => __('Was not aware of it', 'oha-survey-hub'),
                                'did_not_attend' => __('I did not attend the convention', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'off_season_members_area',
                            'question' => __('Outside of convention season, what would you want from an OHA members area or mobile app?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'haunt_profile_layout',
                            'question' => __('Do you like the layout of your Haunt Profile on the OHA website?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'options' => array(
                                'love_it' => __('Love it', 'oha-survey-hub'),
                                'like_it' => __('Like it', 'oha-survey-hub'),
                                'neutral' => __('Neutral', 'oha-survey-hub'),
                                'dislike_it' => __('Dislike it', 'oha-survey-hub'),
                                'no_profile' => __('Don\'t have a profile', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'profile_updated_recently',
                            'question' => __('Have you updated your Haunt Profile recently?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'options' => array(
                                'yes' => __('Yes', 'oha-survey-hub'),
                                'no' => __('No', 'oha-survey-hub'),
                                'didnt_know' => __('Didn\'t know I could', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'business_website_type',
                            'question' => __('What type of website or mobile app does your haunt or business use, and who is your provider?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Programs & Benefits', 'oha-survey-hub'),
                    'description' => __('These questions help us understand which programs would create real value for you and the Ohio haunt community.', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'industry_outlook_2026',
                            'question' => __('Looking ahead to the 2027 haunt season, are you personally cutting back, staying the same, or expanding your haunt/business involvement?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'cutting_back' => __('Cutting back', 'oha-survey-hub'),
                                'staying_same' => __('Staying about the same', 'oha-survey-hub'),
                                'expanding' => __('Expanding', 'oha-survey-hub'),
                                'unsure' => __('Not sure yet', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'oha_season_pass_interest',
                            'question' => __('Would you be interested in an "OHA Season Pass" program (ex: one participating haunt or business benefit each week during season)?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'yes' => __('Yes', 'oha-survey-hub'),
                                'no' => __('No', 'oha-survey-hub'),
                                'maybe' => __('Maybe / depends on details', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'oha_season_pass_ideal',
                            'question' => __('If interested, what would make an OHA Season Pass worthwhile for you?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'depends_on' => array(
                                'field' => 'oha_season_pass_interest',
                                'value' => array('yes', 'maybe')
                            )
                        ),
                        array(
                            'id' => 'group_discounts_interest',
                            'question' => __('Would you use OHA-negotiated discounts for products or services?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'yes' => __('Yes', 'oha-survey-hub'),
                                'no' => __('No', 'oha-survey-hub'),
                                'maybe' => __('Maybe', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'discount_categories',
                            'question' => __('Which discount categories would you actually use? (Select all that apply)', 'oha-survey-hub'),
                            'type' => 'checkbox',
                            'required' => false,
                            'options' => array(
                                'retail' => __('Retail (costumes, makeup, props, décor)', 'oha-survey-hub'),
                                'raw_materials' => __('Raw materials (lumber, foam, paint, hardware)', 'oha-survey-hub'),
                                'business_services' => __('Business services (printing, insurance, security, accounting)', 'oha-survey-hub'),
                                'software' => __('Software & tech (ticketing, CRM, design tools, scheduling)', 'oha-survey-hub'),
                                'training' => __('Training & certifications', 'oha-survey-hub'),
                                'other' => __('Other', 'oha-survey-hub')
                            ),
                            'depends_on' => array(
                                'field' => 'group_discounts_interest',
                                'value' => array('yes', 'maybe')
                            )
                        ),
                        array(
                            'id' => 'marketplace_job_board_interest',
                            'question' => __('Would you use an OHA Marketplace or Job Board (for-sale listings, services, hiring, crew needs)?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'yes' => __('Yes', 'oha-survey-hub'),
                                'no' => __('No', 'oha-survey-hub'),
                                'maybe' => __('Maybe', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'marketplace_features',
                            'question' => __('What would you want an OHA Marketplace / Job Board to include?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'depends_on' => array(
                                'field' => 'marketplace_job_board_interest',
                                'value' => array('yes', 'maybe')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Future & Community', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'community_connection',
                            'question' => __('How connected do you currently feel to the OHA community?', 'oha-survey-hub'),
                            'type' => 'scale',
                            'required' => true,
                            'priority' => 'high',
                            'scale_min' => 1,
                            'scale_max' => 5,
                            'scale_labels' => array(
                                1 => __('Not connected', 'oha-survey-hub'),
                                5 => __('Very connected', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'underserved_groups',
                            'question' => __('Are there groups within the haunt community you feel are underserved by OHA?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'recommendation_likelihood',
                            'question' => __('How likely are you to recommend OHA to others in the haunt industry?', 'oha-survey-hub'),
                            'type' => 'scale',
                            'required' => true,
                            'priority' => 'high',
                            'scale_min' => 0,
                            'scale_max' => 10,
                            'scale_labels' => array(
                                0 => __('Not at all likely', 'oha-survey-hub'),
                                10 => __('Extremely likely', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'membership_renewal_intent',
                            'question' => __('OHA memberships renew on January 1. Do you plan to renew your OHA membership?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'definitely_yes' => __('Yes, definitely', 'oha-survey-hub'),
                                'probably_yes' => __('Yes, probably', 'oha-survey-hub'),
                                'unsure' => __('Unsure', 'oha-survey-hub'),
                                'probably_not' => __('Probably not', 'oha-survey-hub'),
                                'no' => __('No, I do not plan to renew', 'oha-survey-hub'),
                                'not_a_member' => __('I am not currently a member', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'additional_feedback',
                            'question' => __('Is there anything else you would like the OHA Board to know?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false
                        )
                    )
                )
            )
        );
    }

    private static function get_haunt_owner_survey() {
        // This would be the industry deep dive survey focused on haunt owners
        return self::get_industry_deep_dive_survey();
    }
    
    private static function get_scare_actor_survey() {
        return array(
            'title' => __('Scare Actor & Crew Survey', 'oha-survey-hub'),
            'description' => __('Help us better understand the needs, motivations, and challenges of scare actors and haunt crew members.', 'oha-survey-hub'),
            'pages' => array(
                array(
                    'title' => __('Welcome & Your Role', 'oha-survey-hub'),
                    'description' => __('This survey helps OHA better represent and support actors while providing insights to haunt owners and vendors.', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'primary_role',
                            'question' => __('What best describes your primary role in the haunt industry?', 'oha-survey-hub'),
                            'type' => 'select',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'scare_actor' => __('Scare actor', 'oha-survey-hub'),
                                'stunt_performer' => __('Stunt performer', 'oha-survey-hub'),
                                'queue_actor' => __('Queue actor', 'oha-survey-hub'),
                                'makeup_artist' => __('Makeup artist', 'oha-survey-hub'),
                                'costume_crew' => __('Costume crew', 'oha-survey-hub'),
                                'tech_support' => __('Tech/scene support', 'oha-survey-hub'),
                                'other' => __('Other', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'seasons_worked',
                            'question' => __('How many seasons have you worked in haunted attractions?', 'oha-survey-hub'),
                            'type' => 'select',
                            'required' => true,
                            'options' => array(
                                'first_season' => __('This is my first season', 'oha-survey-hub'),
                                '2_to_3' => __('2-3 seasons', 'oha-survey-hub'),
                                '4_to_6' => __('4-6 seasons', 'oha-survey-hub'),
                                '7_to_10' => __('7-10 seasons', 'oha-survey-hub'),
                                'more_than_10' => __('More than 10 seasons', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'age_range',
                            'question' => __('What age range do you fall into? (Optional)', 'oha-survey-hub'),
                            'type' => 'select',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                '16_to_18' => __('16-18', 'oha-survey-hub'),
                                '19_to_25' => __('19-25', 'oha-survey-hub'),
                                '26_to_35' => __('26-35', 'oha-survey-hub'),
                                '36_to_45' => __('36-45', 'oha-survey-hub'),
                                '46_to_55' => __('46-55', 'oha-survey-hub'),
                                'over_55' => __('Over 55', 'oha-survey-hub'),
                                'prefer_not_to_say' => __('Prefer not to say', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Motivation & Compensation', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'primary_motivation',
                            'question' => __('What motivates you most to work in haunted attractions?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'performance' => __('Performance and acting', 'oha-survey-hub'),
                                'community' => __('Community and friendships', 'oha-survey-hub'),
                                'creativity' => __('Creative expression', 'oha-survey-hub'),
                                'income' => __('Income', 'oha-survey-hub'),
                                'tradition' => __('Halloween tradition', 'oha-survey-hub'),
                                'networking' => __('Industry networking', 'oha-survey-hub'),
                                'other' => __('Other', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'adequate_compensation',
                            'question' => __('Do you feel you are adequately compensated for the work you do?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => true,
                            'options' => array(
                                'yes_very_fair' => __('Yes, very fair', 'oha-survey-hub'),
                                'yes_fair' => __('Yes, fair', 'oha-survey-hub'),
                                'somewhat' => __('Somewhat', 'oha-survey-hub'),
                                'no' => __('No', 'oha-survey-hub'),
                                'unpaid_volunteer' => __('I\'m an unpaid volunteer', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'reasons_actors_leave',
                            'question' => __('What are the biggest reasons actors leave a haunt after a season?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Work Environment & Support', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'rewards_help_morale',
                            'question' => __('Do rewards, prizes, or giveaways help promote a positive work environment at your haunt?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'yes_very_much' => __('Yes, very much', 'oha-survey-hub'),
                                'yes_somewhat' => __('Yes, somewhat', 'oha-survey-hub'),
                                'neutral' => __('Neutral', 'oha-survey-hub'),
                                'no_not_really' => __('No, not really', 'oha-survey-hub'),
                                'no_not_at_all' => __('No, not at all', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'experienced_burnout',
                            'question' => __('Have you ever felt burned out during a season? If so, what contributed to it?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'needed_tools_training',
                            'question' => __('What tools, training, or resources would most help you during the season?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'access_wishes',
                            'question' => __('What do you wish you had access to that most actors currently do not?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Community & Recognition', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'support_other_haunts',
                            'question' => __('Do you support or visit other haunts when you have the opportunity?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'yes_frequently' => __('Yes, frequently', 'oha-survey-hub'),
                                'yes_occasionally' => __('Yes, occasionally', 'oha-survey-hub'),
                                'rarely' => __('Rarely', 'oha-survey-hub'),
                                'never' => __('Never', 'oha-survey-hub'),
                                'cant_afford' => __('Can\'t afford to', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'prefer_meetup_type',
                            'question' => __('Do you prefer in-person meetups, online meetups, or a mix of both?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'in_person' => __('In-person only', 'oha-survey-hub'),
                                'online' => __('Online only', 'oha-survey-hub'),
                                'mix_both' => __('Mix of both', 'oha-survey-hub'),
                                'not_interested' => __('Not interested in meetups', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'recognition_matters_most',
                            'question' => __('What kind of recognition matters most to you as an actor?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'awards_improvements',
                            'question' => __('What changes or improvements would make the OHA Haunters Awards more meaningful for actors?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Performance & Characters', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'guest_reviews_impact',
                            'question' => __('Do guest reviews or feedback affect you positively, negatively, or both?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'very_positive' => __('Very positive impact', 'oha-survey-hub'),
                                'mostly_positive' => __('Mostly positive', 'oha-survey-hub'),
                                'mixed' => __('Mixed impact', 'oha-survey-hub'),
                                'mostly_negative' => __('Mostly negative', 'oha-survey-hub'),
                                'no_impact' => __('No impact', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'fairly_represented_reviews',
                            'question' => __('Do you feel actors are fairly represented in online reviews and social media?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'yes_always' => __('Yes, always', 'oha-survey-hub'),
                                'usually' => __('Usually', 'oha-survey-hub'),
                                'sometimes' => __('Sometimes', 'oha-survey-hub'),
                                'rarely' => __('Rarely', 'oha-survey-hub'),
                                'never' => __('Never', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'character_social_media',
                            'question' => __('Does your character have a social media presence?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'yes_active' => __('Yes, active presence', 'oha-survey-hub'),
                                'yes_minimal' => __('Yes, minimal presence', 'oha-survey-hub'),
                                'no_interested' => __('No, but interested', 'oha-survey-hub'),
                                'no_not_interested' => __('No, not interested', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'creative_ownership_importance',
                            'question' => __('How important is creative ownership of your character to you?', 'oha-survey-hub'),
                            'type' => 'scale',
                            'required' => false,
                            'priority' => 'high',
                            'scale_min' => 1,
                            'scale_max' => 5,
                            'scale_labels' => array(
                                1 => __('Not important', 'oha-survey-hub'),
                                5 => __('Very important', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Equipment & Technology', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'mask_improvements',
                            'question' => __('If you could change how masks are made, what would you improve?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'vendor_material_wishes',
                            'question' => __('What materials or designs do you wish vendors would explore more?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'multiple_scares_effective',
                            'question' => __('Do you think multiple scares in a single scene are effective or overwhelming?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'very_effective' => __('Very effective', 'oha-survey-hub'),
                                'effective' => __('Effective', 'oha-survey-hub'),
                                'depends' => __('Depends on execution', 'oha-survey-hub'),
                                'overwhelming' => __('Often overwhelming', 'oha-survey-hub'),
                                'always_overwhelming' => __('Always overwhelming', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'excited_new_tech',
                            'question' => __('What new technology or materials are you excited to try for your character?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'underused_tech',
                            'question' => __('What tech do you think is underused in actor performance today?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Final Thoughts', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'oha_represents_actors',
                            'question' => __('Do you feel OHA represents scare actors as much as owners and vendors?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'yes_equally' => __('Yes, equally', 'oha-survey-hub'),
                                'mostly' => __('Mostly', 'oha-survey-hub'),
                                'somewhat' => __('Somewhat', 'oha-survey-hub'),
                                'not_really' => __('Not really', 'oha-survey-hub'),
                                'not_at_all' => __('Not at all', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'tell_owners_one_thing',
                            'question' => __('If you could tell haunt owners one thing actors need most, what would it be?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'additional_thoughts',
                            'question' => __('Is there anything else you want the OHA board, vendors, or owners to understand about actors?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false
                        )
                    )
                )
            )
        );
    }
    
    private static function get_behind_scenes_survey() {
        return array(
            'title' => __('Behind-the-Scenes & Technical Crew Survey', 'oha-survey-hub'),
            'description' => __('Give voice to the behind-the-scenes professionals who make haunted attractions function.', 'oha-survey-hub'),
            'pages' => array(
                array(
                    'title' => __('Your Role & Experience', 'oha-survey-hub'),
                    'description' => __('Help us understand the technical and creative talent that makes haunts possible.', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'behind_scenes_roles',
                            'question' => __('Which behind-the-scenes roles do you primarily fill?', 'oha-survey-hub'),
                            'type' => 'checkbox',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'lighting' => __('Lighting', 'oha-survey-hub'),
                                'sound' => __('Sound', 'oha-survey-hub'),
                                'scenic_build' => __('Scenic build', 'oha-survey-hub'),
                                'fabrication' => __('Fabrication', 'oha-survey-hub'),
                                'props' => __('Props', 'oha-survey-hub'),
                                'animatronics' => __('Animatronics', 'oha-survey-hub'),
                                'tech_ops' => __('Tech ops', 'oha-survey-hub'),
                                'purchasing' => __('Purchasing', 'oha-survey-hub'),
                                'maintenance' => __('Maintenance', 'oha-survey-hub'),
                                'other' => __('Other', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'job_classification',
                            'question' => __('Is haunt work your primary job, side job, or seasonal passion?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'primary_job' => __('Primary job', 'oha-survey-hub'),
                                'side_job' => __('Side job', 'oha-survey-hub'),
                                'seasonal_passion' => __('Seasonal passion', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Value & Recognition', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'work_valued_understood',
                            'question' => __('Do you feel your work is valued and understood within your haunt?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'very_much' => __('Very much', 'oha-survey-hub'),
                                'mostly' => __('Mostly', 'oha-survey-hub'),
                                'somewhat' => __('Somewhat', 'oha-survey-hub'),
                                'not_really' => __('Not really', 'oha-survey-hub'),
                                'not_at_all' => __('Not at all', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'actors_respect_work',
                            'question' => __('Do actors and performers generally respect and care for the work you create?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'always' => __('Always', 'oha-survey-hub'),
                                'usually' => __('Usually', 'oha-survey-hub'),
                                'sometimes' => __('Sometimes', 'oha-survey-hub'),
                                'rarely' => __('Rarely', 'oha-survey-hub'),
                                'never' => __('Never', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'oha_useful_offerings',
                            'question' => __('What could OHA offer that would be genuinely useful to behind-the-scenes workers?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'participate_bts_events',
                            'question' => __('Would you participate in behind-the-scenes focused community events or meetups?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'definitely' => __('Definitely', 'oha-survey-hub'),
                                'probably' => __('Probably', 'oha-survey-hub'),
                                'maybe' => __('Maybe', 'oha-survey-hub'),
                                'probably_not' => __('Probably not', 'oha-survey-hub'),
                                'definitely_not' => __('Definitely not', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Technology & Innovation', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'planning_new_tech',
                            'question' => __('What new technology are you planning or hoping to introduce in the 2027 haunt season or beyond?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'innovation_vs_proven',
                            'question' => __('Are you more excited by innovation or by refining proven systems?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'love_innovation' => __('Love innovation and new tech', 'oha-survey-hub'),
                                'prefer_innovation' => __('Prefer innovation', 'oha-survey-hub'),
                                'balanced' => __('Balanced approach', 'oha-survey-hub'),
                                'prefer_proven' => __('Prefer proven systems', 'oha-survey-hub'),
                                'stick_proven' => __('Stick with proven systems', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'want_innovate',
                            'question' => __('Do you want to actively innovate and push the haunt industry forward in Ohio?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'absolutely' => __('Absolutely', 'oha-survey-hub'),
                                'yes' => __('Yes', 'oha-survey-hub'),
                                'maybe' => __('Maybe', 'oha-survey-hub'),
                                'not_really' => __('Not really', 'oha-survey-hub'),
                                'no' => __('No', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'satisfied_refining',
                            'question' => __('Or are you satisfied refining and improving existing tools and methods?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'very_satisfied' => __('Very satisfied with refining', 'oha-survey-hub'),
                                'satisfied' => __('Satisfied', 'oha-survey-hub'),
                                'neutral' => __('Neutral', 'oha-survey-hub'),
                                'want_more' => __('Want more innovation', 'oha-survey-hub'),
                                'need_innovation' => __('Need more innovation', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'innovation_limits',
                            'question' => __('What limits your ability to innovate?', 'oha-survey-hub'),
                            'type' => 'checkbox',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'budget' => __('Budget constraints', 'oha-survey-hub'),
                                'time' => __('Time constraints', 'oha-survey-hub'),
                                'buy_in' => __('Lack of buy-in from management', 'oha-survey-hub'),
                                'tools' => __('Lack of proper tools', 'oha-survey-hub'),
                                'safety' => __('Safety concerns', 'oha-survey-hub'),
                                'knowledge' => __('Knowledge gaps', 'oha-survey-hub'),
                                'other' => __('Other', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Materials & Resources', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'reuse_materials',
                            'question' => __('Do you reuse or repurpose materials from previous seasons or other haunts?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'always' => __('Always when possible', 'oha-survey-hub'),
                                'frequently' => __('Frequently', 'oha-survey-hub'),
                                'sometimes' => __('Sometimes', 'oha-survey-hub'),
                                'rarely' => __('Rarely', 'oha-survey-hub'),
                                'never' => __('Never', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'affordable_materials_wish',
                            'question' => __('What materials or components do you wish were more affordable or accessible?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'vendor_frustrations',
                            'question' => __('What frustrates you most about current vendor offerings?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'vendor_missing_info',
                            'question' => __('What information do vendors rarely provide but should?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Systems & Reliability', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'pneumatic_vs_electric',
                            'question' => __('Do you prefer pneumatic effects or newer electric/servo-driven systems?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'pneumatic_strong' => __('Strongly prefer pneumatic', 'oha-survey-hub'),
                                'pneumatic_slight' => __('Slightly prefer pneumatic', 'oha-survey-hub'),
                                'no_preference' => __('No strong preference', 'oha-survey-hub'),
                                'electric_slight' => __('Slightly prefer electric', 'oha-survey-hub'),
                                'electric_strong' => __('Strongly prefer electric', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'failure_points_stress',
                            'question' => __('What failure points cause the most stress during a season?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'improve_collaboration',
                            'question' => __('What would improve collaboration between builders and performers?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Community & Support', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'attend_bts_events',
                            'question' => __('Would you attend behind-the-scenes focused OHA events or workshops?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'definitely' => __('Definitely', 'oha-survey-hub'),
                                'probably' => __('Probably', 'oha-survey-hub'),
                                'maybe' => __('Maybe', 'oha-survey-hub'),
                                'probably_not' => __('Probably not', 'oha-survey-hub'),
                                'definitely_not' => __('Definitely not', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'oha_advocacy',
                            'question' => __('What could OHA do to better advocate for technical and build teams?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'change_one_thing',
                            'question' => __('If you could change one thing about how haunts are built or maintained, what would it be?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'help_job_better',
                            'question' => __('What would help you do your job better next season?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                )
            )
        );
    }
    
    private static function get_home_haunt_survey() {
        return array(
            'title' => __('Home Haunt Industry Survey', 'oha-survey-hub'),
            'description' => __('Supporting the creativity, community engagement, and growth of home haunt operators.', 'oha-survey-hub'),
            'pages' => array(
                array(
                    'title' => __('Your Home Haunt', 'oha-survey-hub'),
                    'description' => __('Tell us about your home haunt operation and what motivates you to create.', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'haunt_description',
                            'question' => __('How would you describe your haunt?', 'oha-survey-hub'),
                            'type' => 'select',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'home_yard' => __('Home yard haunt', 'oha-survey-hub'),
                                'walkthrough' => __('Walkthrough haunt', 'oha-survey-hub'),
                                'driveway' => __('Driveway haunt', 'oha-survey-hub'),
                                'charity' => __('Charity haunt', 'oha-survey-hub'),
                                'community' => __('Community haunt', 'oha-survey-hub'),
                                'other' => __('Other', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'haunt_funding',
                            'question' => __('Is your haunt free, donation-based, charity-supported, or a mix?', 'oha-survey-hub'),
                            'type' => 'select',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'completely_free' => __('Completely free', 'oha-survey-hub'),
                                'donation_based' => __('Donation-based', 'oha-survey-hub'),
                                'charity_supported' => __('Charity-supported', 'oha-survey-hub'),
                                'mix_approaches' => __('Mix of approaches', 'oha-survey-hub'),
                                'small_admission' => __('Small admission fee', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'motivation',
                            'question' => __('What motivates you to create and operate a home haunt?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => true,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'haunt_purpose',
                            'question' => __('Do you see your haunt primarily as a creative outlet, community event, or stepping stone to something larger?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'creative_outlet' => __('Creative outlet', 'oha-survey-hub'),
                                'community_event' => __('Community event', 'oha-survey-hub'),
                                'stepping_stone' => __('Stepping stone to larger operation', 'oha-survey-hub'),
                                'mix_all' => __('Mix of all three', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Financial Reality', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'personally_finance',
                            'question' => __('Do you personally finance most or all of your haunt expenses?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'all_personal' => __('All from personal funds', 'oha-survey-hub'),
                                'most_personal' => __('Most from personal funds', 'oha-survey-hub'),
                                'mix_funding' => __('Mix of personal and other funding', 'oha-survey-hub'),
                                'mostly_donations' => __('Mostly from donations', 'oha-survey-hub'),
                                'other_funding' => __('Other funding sources', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'seasonal_investment',
                            'question' => __('Approximately how much do you invest out-of-pocket each season?', 'oha-survey-hub'),
                            'type' => 'select',
                            'required' => false,
                            'options' => array(
                                'under_100' => __('Under $100', 'oha-survey-hub'),
                                '100_to_250' => __('$100-$250', 'oha-survey-hub'),
                                '250_to_500' => __('$250-$500', 'oha-survey-hub'),
                                '500_to_1000' => __('$500-$1,000', 'oha-survey-hub'),
                                '1000_to_2500' => __('$1,000-$2,500', 'oha-survey-hub'),
                                'over_2500' => __('Over $2,500', 'oha-survey-hub'),
                                'prefer_not_say' => __('Prefer not to say', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'creates_financial_stress',
                            'question' => __('Does operating a haunt ever create financial stress in your personal life?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'never' => __('Never', 'oha-survey-hub'),
                                'rarely' => __('Rarely', 'oha-survey-hub'),
                                'sometimes' => __('Sometimes', 'oha-survey-hub'),
                                'often' => __('Often', 'oha-survey-hub'),
                                'always' => __('Always', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Growth & Vision', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'hope_for_profit',
                            'question' => __('Do you hope to one day operate a for-profit haunt?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'definitely_yes' => __('Definitely yes', 'oha-survey-hub'),
                                'probably_yes' => __('Probably yes', 'oha-survey-hub'),
                                'maybe' => __('Maybe', 'oha-survey-hub'),
                                'probably_no' => __('Probably no', 'oha-survey-hub'),
                                'definitely_no' => __('Definitely no', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'rely_on_help',
                            'question' => __('Do you rely on volunteers, paid helpers, or a mix of both?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'work_alone' => __('Work alone', 'oha-survey-hub'),
                                'volunteers_only' => __('Volunteers only', 'oha-survey-hub'),
                                'paid_helpers' => __('Paid helpers', 'oha-survey-hub'),
                                'mix_both' => __('Mix of volunteers and paid', 'oha-survey-hub'),
                                'family_friends' => __('Family and friends', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'team_management_help',
                            'question' => __('What tools or guidance would help you better manage your team?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Resources & Support', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'helpful_tools_resources',
                            'question' => __('What types of tools or resources would help you most as a home haunt operator?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'hardest_info_to_find',
                            'question' => __('What information was hardest to find when you first started your haunt?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'products_wish_retailers_made',
                            'question' => __('What types of products do you wish retailers made specifically for home haunts?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'cost_compromises',
                            'question' => __('What compromises do you most often have to make due to cost?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Challenges & Operations', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'biggest_operational_challenges',
                            'question' => __('What are the biggest operational challenges you face each season?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'scaled_back_regulations',
                            'question' => __('Have you ever had to scale back due to regulations or complaints?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'never' => __('Never', 'oha-survey-hub'),
                                'once' => __('Once', 'oha-survey-hub'),
                                'few_times' => __('A few times', 'oha-survey-hub'),
                                'multiple_times' => __('Multiple times', 'oha-survey-hub'),
                                'every_season' => __('Every season', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'excited_trends',
                            'question' => __('What trends are you excited about in home haunting?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'pressure_go_bigger',
                            'question' => __('Do you feel pressure to constantly add more or go bigger each year?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'intense_pressure' => __('Intense pressure', 'oha-survey-hub'),
                                'some_pressure' => __('Some pressure', 'oha-survey-hub'),
                                'little_pressure' => __('Little pressure', 'oha-survey-hub'),
                                'no_pressure' => __('No pressure', 'oha-survey-hub'),
                                'pressure_to_scale_back' => __('Pressure to scale back', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Industry Connection', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'welcomed_in_industry',
                            'question' => __('Do you feel welcomed and represented in the larger haunt industry?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'very_welcomed' => __('Very welcomed', 'oha-survey-hub'),
                                'welcomed' => __('Welcomed', 'oha-survey-hub'),
                                'neutral' => __('Neutral', 'oha-survey-hub'),
                                'not_very_welcomed' => __('Not very welcomed', 'oha-survey-hub'),
                                'excluded' => __('Excluded', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'interested_mentorship',
                            'question' => __('Would you be interested in mentorship from professional haunt operators?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'very_interested' => __('Very interested', 'oha-survey-hub'),
                                'interested' => __('Interested', 'oha-survey-hub'),
                                'maybe' => __('Maybe', 'oha-survey-hub'),
                                'not_interested' => __('Not interested', 'oha-survey-hub'),
                                'already_have' => __('Already have mentors', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'oha_more_supportive',
                            'question' => __('How could OHA be more useful or supportive to home haunters?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'oha_represents_home_haunts',
                            'question' => __('What would make you feel that OHA truly represents home haunts?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Future Outlook', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'optimism_future',
                            'question' => __('How optimistic are you about the future of home haunting?', 'oha-survey-hub'),
                            'type' => 'scale',
                            'required' => false,
                            'priority' => 'high',
                            'scale_min' => 1,
                            'scale_max' => 5,
                            'scale_labels' => array(
                                1 => __('Very pessimistic', 'oha-survey-hub'),
                                5 => __('Very optimistic', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'advice_new_haunters',
                            'question' => __('What advice would you give someone starting their first home haunt?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'want_industry_understand',
                            'question' => __('Is there anything else you want OHA, retailers, or the haunt industry to understand about home haunters?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false
                        )
                    )
                )
            )
        );
    }
    
    private static function get_industry_deep_dive_survey() {
        $hy   = self::haunt_season_years();
        $past = $hy['past'];
        $prev = $past - 1;
        $next = $hy['next'];
        return array(
            'title' => sprintf( __('Haunt Owner Industry Deep-Dive Survey (%1$d to %2$d)', 'oha-survey-hub'), $past, $next ),
            'description' => __('Provide a serious, data-driven snapshot of the haunted attraction industry in Ohio.', 'oha-survey-hub'),
            'pages' => array(
                array(
                    'title' => __('Haunt Overview & Scale', 'oha-survey-hub'),
                    'description' => __('Tell us about your haunted attraction operation and its scope.', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'attraction_type',
                            'question' => __('What type of haunted attraction do you operate?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'indoor' => __('Indoor attraction', 'oha-survey-hub'),
                                'outdoor' => __('Outdoor attraction', 'oha-survey-hub'),
                                'hybrid' => __('Hybrid indoor/outdoor', 'oha-survey-hub'),
                                'multiple_attractions' => __('Multiple attractions', 'oha-survey-hub'),
                                'home_haunt' => __('Home haunt', 'oha-survey-hub'),
                                'professional_attraction' => __('Professional attraction', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'attendance_vs_2024',
                            'question' => "Compared to {$prev}, your attendance was:",
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'up_significantly' => __('Up significantly (20%+)', 'oha-survey-hub'),
                                'up_moderately' => __('Up moderately (5-19%)', 'oha-survey-hub'),
                                'flat' => __('Flat (±5%)', 'oha-survey-hub'),
                                'down_moderately' => __('Down moderately (5-19%)', 'oha-survey-hub'),
                                'down_significantly' => __('Down significantly (20%+)', 'oha-survey-hub'),
                                'new_operation' => __('New operation', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'estimated_attendance_2025',
                            'question' => "What was your total estimated attendance in the {$past} season?",
                            'type' => 'select',
                            'required' => false,
                            'options' => array(
                                'under_500' => __('Under 500', 'oha-survey-hub'),
                                '500_to_1000' => __('500-1,000', 'oha-survey-hub'),
                                '1000_to_2500' => __('1,000-2,500', 'oha-survey-hub'),
                                '2500_to_5000' => __('2,500-5,000', 'oha-survey-hub'),
                                '5000_to_10000' => __('5,000-10,000', 'oha-survey-hub'),
                                '10000_to_25000' => __('10,000-25,000', 'oha-survey-hub'),
                                'over_25000' => __('Over 25,000', 'oha-survey-hub'),
                                'prefer_not_say' => __('Prefer not to say', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Financial Health', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'financial_health_2025',
                            'question' => "How would you describe your haunt's overall financial health after the {$past} season?",
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'very_strong' => __('Very strong', 'oha-survey-hub'),
                                'strong' => __('Strong', 'oha-survey-hub'),
                                'stable' => __('Stable', 'oha-survey-hub'),
                                'tight' => __('Tight', 'oha-survey-hub'),
                                'struggling' => __('Struggling', 'oha-survey-hub'),
                                'crisis' => __('In crisis', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'top_expense_categories',
                            'question' => "What were your top three expense categories in {$past}?",
                            'type' => 'checkbox',
                            'required' => false,
                            'priority' => 'high',
                            'max_selections' => 3,
                            'options' => array(
                                'labor' => __('Labor/Payroll', 'oha-survey-hub'),
                                'materials' => __('Materials/Props', 'oha-survey-hub'),
                                'marketing' => __('Marketing/Advertising', 'oha-survey-hub'),
                                'insurance' => __('Insurance', 'oha-survey-hub'),
                                'rent_mortgage' => __('Rent/Mortgage', 'oha-survey-hub'),
                                'utilities' => __('Utilities', 'oha-survey-hub'),
                                'security' => __('Security', 'oha-survey-hub'),
                                'maintenance' => __('Maintenance/Repairs', 'oha-survey-hub'),
                                'permits_licenses' => __('Permits/Licenses', 'oha-survey-hub'),
                                'other' => __('Other', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'investment_2026',
                            'question' => "How much do you plan to invest for the {$next} haunt season?",
                            'type' => 'select',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'under_5k' => __('Under $5,000', 'oha-survey-hub'),
                                '5k_to_15k' => __('$5,000-$15,000', 'oha-survey-hub'),
                                '15k_to_30k' => __('$15,000-$30,000', 'oha-survey-hub'),
                                '30k_to_60k' => __('$30,000-$60,000', 'oha-survey-hub'),
                                '60k_to_100k' => __('$60,000-$100,000', 'oha-survey-hub'),
                                'over_100k' => __('Over $100,000', 'oha-survey-hub'),
                                'uncertain' => __('Uncertain', 'oha-survey-hub'),
                                'prefer_not_say' => __('Prefer not to say', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'insurance_coverage',
                            'question' => __('Do you currently carry haunted attraction-specific insurance?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'yes_specific' => __('Yes, a haunt-specific policy', 'oha-survey-hub'),
                                'yes_general' => __('Yes, covered under a general business policy', 'oha-survey-hub'),
                                'no' => __('No, I do not carry insurance', 'oha-survey-hub'),
                                'not_sure' => __('Not sure what type of coverage I have', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'insurance_challenges',
                            'question' => __('What challenges have you faced with insurance for your haunt, if any?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Pricing & Revenue', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'average_ticket_price_2025',
                            'question' => "What was your average ticket price in the {$past} season?",
                            'type' => 'select',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'free_donations' => __('Free/Donations only', 'oha-survey-hub'),
                                'under_10' => __('Under $10', 'oha-survey-hub'),
                                '10_to_15' => __('$10-$15', 'oha-survey-hub'),
                                '16_to_20' => __('$16-$20', 'oha-survey-hub'),
                                '21_to_25' => __('$21-$25', 'oha-survey-hub'),
                                '26_to_30' => __('$26-$30', 'oha-survey-hub'),
                                '31_to_40' => __('$31-$40', 'oha-survey-hub'),
                                'over_40' => __('Over $40', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'pricing_strategies_2025',
                            'question' => "What promotions or pricing strategies worked best in {$past}?",
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Staffing & Operations', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'staffing_difficulties',
                            'question' => "Did you have difficulty hiring or retaining staff and actors in {$past}?",
                            'type' => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options' => array(
                                'no_issues' => __('No issues', 'oha-survey-hub'),
                                'minor_issues' => __('Minor issues', 'oha-survey-hub'),
                                'moderate_issues' => __('Moderate issues', 'oha-survey-hub'),
                                'major_issues' => __('Major issues', 'oha-survey-hub'),
                                'critical_issues' => __('Critical issues', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'staffing_limited_operations',
                            'question' => __('Did staffing issues limit your operating nights or capacity?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'never' => __('Never', 'oha-survey-hub'),
                                'rarely' => __('Rarely (1-2 times)', 'oha-survey-hub'),
                                'occasionally' => __('Occasionally (3-5 times)', 'oha-survey-hub'),
                                'frequently' => __('Frequently (6+ times)', 'oha-survey-hub'),
                                'constantly' => __('Constantly throughout season', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'actor_recruitment_sources',
                            'question' => __('How do you primarily recruit actors and volunteers for your haunt? (Select all that apply)', 'oha-survey-hub'),
                            'type' => 'checkbox',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'facebook_groups' => __('Facebook groups', 'oha-survey-hub'),
                                'word_of_mouth' => __('Word of mouth', 'oha-survey-hub'),
                                'returning_cast' => __('Returning cast from prior seasons', 'oha-survey-hub'),
                                'craigslist' => __('Craigslist', 'oha-survey-hub'),
                                'local_theater' => __('Local theater or performing arts groups', 'oha-survey-hub'),
                                'tiktok_social' => __('TikTok or other social media', 'oha-survey-hub'),
                                'posted_flyers' => __('Flyers or local postings', 'oha-survey-hub'),
                                'oha' => __('Through OHA', 'oha-survey-hub'),
                                'other' => __('Other', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Security & Safety', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'security_incidents_2025',
                            'question' => "How many security incidents or troublemakers did you experience in the {$past} season? (Approximate)",
                            'type' => 'select',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'none' => __('None', 'oha-survey-hub'),
                                '1_to_2' => __('1-2 incidents', 'oha-survey-hub'),
                                '3_to_5' => __('3-5 incidents', 'oha-survey-hub'),
                                '6_to_10' => __('6-10 incidents', 'oha-survey-hub'),
                                '11_to_20' => __('11-20 incidents', 'oha-survey-hub'),
                                'over_20' => __('Over 20 incidents', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'security_support_needs',
                            'question' => __('What additional support or resources would help improve security?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('External Factors', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'weather_impact',
                            'question' => "How significantly did weather impact your {$past} season?",
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'no_impact' => __('No impact', 'oha-survey-hub'),
                                'minimal' => __('Minimal impact', 'oha-survey-hub'),
                                'moderate' => __('Moderate impact', 'oha-survey-hub'),
                                'significant' => __('Significant impact', 'oha-survey-hub'),
                                'major' => __('Major impact', 'oha-survey-hub'),
                                'devastating' => __('Devastating impact', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'greatest_negative_factor',
                            'question' => __('What external factor had the greatest negative impact on your season?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'competition_awareness',
                            'question' => __('How has competition from other haunted attractions in your area changed over the past year?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'increased_significantly' => __('Increased significantly, new competitors opened nearby', 'oha-survey-hub'),
                                'increased_somewhat' => __('Increased somewhat', 'oha-survey-hub'),
                                'stayed_same' => __('Stayed about the same', 'oha-survey-hub'),
                                'decreased_closures' => __('Decreased, some nearby haunts closed or reduced operations', 'oha-survey-hub'),
                                'not_aware' => __('Not aware of significant changes', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Vendor Relations', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'vendor_satisfaction',
                            'question' => __('How satisfied are you with current haunt vendors and retailers?', 'oha-survey-hub'),
                            'type' => 'scale',
                            'required' => false,
                            'priority' => 'high',
                            'scale_min' => 1,
                            'scale_max' => 5,
                            'scale_labels' => array(
                                1 => __('Very dissatisfied', 'oha-survey-hub'),
                                5 => __('Very satisfied', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'collective_purchasing_value',
                            'question' => __('Would collective purchasing or negotiated discounts through OHA be valuable?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'very_valuable' => __('Very valuable', 'oha-survey-hub'),
                                'valuable' => __('Valuable', 'oha-survey-hub'),
                                'somewhat_valuable' => __('Somewhat valuable', 'oha-survey-hub'),
                                'not_valuable' => __('Not valuable', 'oha-survey-hub'),
                                'already_have_deals' => __('Already have good deals', 'oha-survey-hub')
                            )
                        )
                    )
                ),
                array(
                    'title' => __('Technology & Innovation', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'technologies_adopting_2026',
                            'question' => __('What technologies are you planning to adopt for the 2027 haunt season?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'marketing_roi_channels',
                            'question' => __('What marketing channels produced the best ROI?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Future Outlook', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'industry_confidence',
                            'question' => __('How confident are you in the future of the haunt industry in Ohio?', 'oha-survey-hub'),
                            'type' => 'scale',
                            'required' => true,
                            'priority' => 'high',
                            'scale_min' => 1,
                            'scale_max' => 5,
                            'scale_labels' => array(
                                1 => __('Very pessimistic', 'oha-survey-hub'),
                                5 => __('Very optimistic', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'operating_five_years',
                            'question' => __('Do you see yourself operating your haunt five years from now?', 'oha-survey-hub'),
                            'type' => 'radio',
                            'required' => false,
                            'priority' => 'high',
                            'options' => array(
                                'definitely_yes' => __('Definitely yes', 'oha-survey-hub'),
                                'probably_yes' => __('Probably yes', 'oha-survey-hub'),
                                'uncertain' => __('Uncertain', 'oha-survey-hub'),
                                'probably_no' => __('Probably no', 'oha-survey-hub'),
                                'definitely_no' => __('Definitely no', 'oha-survey-hub')
                            )
                        ),
                        array(
                            'id' => 'oha_role_owners',
                            'question' => __('What role should OHA play in supporting haunt owners?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'make_oha_indispensable',
                            'question' => __('What would make OHA indispensable to you as a haunt owner?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        )
                    )
                ),
                array(
                    'title' => __('Final Reflection', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id' => 'biggest_lesson_2025',
                            'question' => "What was the single biggest lesson you learned from the {$past} season?",
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'advice_new_owners',
                            'question' => __('What advice would you give new haunt owners entering the industry?', 'oha-survey-hub'),
                            'type' => 'textarea',
                            'required' => false,
                            'priority' => 'high'
                        ),
                        array(
                            'id' => 'industry_preparation_2026',
                            'question' => "Is there anything else you believe the industry needs to confront or prepare for heading into the {$next} haunt season?",
                            'type' => 'textarea',
                            'required' => false
                        )
                    )
                )
            )
        );
    }

    private static function get_convention_planning_survey() {
        $year = (int) get_option('oha_convention_survey_year', 2027);
        $prev = $year - 1;

        return array(
            'title'       => sprintf( __('OHA Convention %d Planning Survey', 'oha-survey-hub'), $year ),
            'description' => sprintf( __('Help us plan OHA Convention %d. Tell us what you want to see, what logistics matter to you, and how to make the event worth your time and money. This survey takes about 10 to 15 minutes.', 'oha-survey-hub'), $year ),
            'pages' => array(

                // Page 1: Attendance Intent
                array(
                    'title'       => __('Are You Coming?', 'oha-survey-hub'),
                    'description' => sprintf( __('Tell us whether you plan to attend OHA Convention %d and what factors are shaping that decision.', 'oha-survey-hub'), $year ),
                    'questions'   => array(
                        array(
                            'id'       => 'cp_attendance_intent',
                            'question' => sprintf( __('Are you planning to attend OHA Convention %d?', 'oha-survey-hub'), $year ),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'yes'          => __('Yes, I am planning to attend', 'oha-survey-hub'),
                                'likely'       => __('Likely yes, but not confirmed', 'oha-survey-hub'),
                                'undecided'    => __('Undecided', 'oha-survey-hub'),
                                'probably_not' => __('Probably not', 'oha-survey-hub'),
                                'no'           => __('No', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_barrier_to_attending',
                            'question' => __('If you are undecided or not planning to attend, what would most change that?', 'oha-survey-hub'),
                            'type'     => 'textarea',
                            'required' => false,
                            'depends_on' => array(
                                'field' => 'cp_attendance_intent',
                                'value' => array('undecided', 'probably_not', 'no')
                            )
                        ),
                        array(
                            'id'       => 'cp_previous_attendance',
                            'question' => __('Have you attended a previous OHA Convention?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'yes_recent' => sprintf( __('Yes, I attended in %d', 'oha-survey-hub'), $prev ),
                                'yes_prior'  => sprintf( __('Yes, but not in %d', 'oha-survey-hub'), $prev ),
                                'no_first'   => __('No, this would be my first convention', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_planning_lead_time',
                            'question' => __('How far in advance do you typically finalize plans for an event like this?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'options'  => array(
                                '6_months_plus'  => __('6 months or more', 'oha-survey-hub'),
                                '3_to_6_months'  => __('3 to 6 months', 'oha-survey-hub'),
                                '1_to_3_months'  => __('1 to 3 months', 'oha-survey-hub'),
                                'under_1_month'  => __('Less than 1 month', 'oha-survey-hub'),
                                'last_minute'    => __('I decide last minute', 'oha-survey-hub'),
                            )
                        ),
                    )
                ),

                // Page 2: Tickets and Budget
                array(
                    'title'     => __('Tickets and Budget', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id'       => 'cp_ticket_type_interest',
                            'question' => __('Which ticket type are you considering?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'ga'             => __('General Admission', 'oha-survey-hub'),
                                'vip'            => __('VIP (includes vendor floor, education, and costume ball)', 'oha-survey-hub'),
                                'undecided'      => __('Have not decided yet', 'oha-survey-hub'),
                                'depends_lineup' => __('I will decide based on the final event lineup', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_ticket_price_feel',
                            'question' => sprintf( __('The %d convention offered General Admission at $30 and VIP at $80. How do those prices feel for %d?', 'oha-survey-hub'), $prev, $year ),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'very_reasonable' => __('Very reasonable, I would pay more', 'oha-survey-hub'),
                                'fair'            => __('Fair, about right', 'oha-survey-hub'),
                                'bit_high'        => __('A bit high but I would still attend', 'oha-survey-hub'),
                                'ga_too_high'     => __('GA price is too high', 'oha-survey-hub'),
                                'vip_too_high'    => __('VIP price is too high', 'oha-survey-hub'),
                                'not_a_factor'    => __('Price is not a deciding factor for me', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_early_bird_interest',
                            'question' => __('Would an early-bird discount for tickets purchased 3 or more months before the event make you more likely to buy earlier?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'options'  => array(
                                'yes_definitely' => __('Yes, definitely', 'oha-survey-hub'),
                                'yes_probably'   => __('Yes, probably', 'oha-survey-hub'),
                                'no'             => __('No, I will buy when I am ready regardless', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_addon_awareness',
                            'question' => __('Were you aware that Education sessions, the Costume Ball, and Blood Prison each require separate tickets during checkout?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'options'  => array(
                                'yes_all'  => __('Yes, I knew all of them required separate tickets', 'oha-survey-hub'),
                                'yes_some' => __('I knew some required it, but not all', 'oha-survey-hub'),
                                'no'       => __('No, I did not know this', 'oha-survey-hub'),
                            )
                        ),
                    )
                ),

                // Page 3: New Features
                array(
                    'title'       => sprintf( __('New Features at the %d Convention', 'oha-survey-hub'), $year ),
                    'description' => __('We are adding several new features this year. Tell us how interested you are in each one.', 'oha-survey-hub'),
                    'questions'   => array(
                        array(
                            'id'       => 'cp_main_stage_interest',
                            'question' => sprintf( __('The %d convention is adding a Main Stage for the Opening Ceremony, Closing Ceremony, and on-stage entertainment. How interested are you?', 'oha-survey-hub'), $year ),
                            'type'     => 'scale',
                            'required' => true,
                            'priority' => 'high',
                            'scale_min' => 1,
                            'scale_max' => 5,
                            'scale_labels' => array(
                                1 => __('Not interested', 'oha-survey-hub'),
                                5 => __('Very interested', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_haunt_showcase_interest',
                            'question' => sprintf( __('The %d convention is adding a Haunt Showcase area where Ohio haunts promote their attractions to attendees. How interested are you in visiting?', 'oha-survey-hub'), $year ),
                            'type'     => 'scale',
                            'required' => true,
                            'priority' => 'high',
                            'scale_min' => 1,
                            'scale_max' => 5,
                            'scale_labels' => array(
                                1 => __('Not interested', 'oha-survey-hub'),
                                5 => __('Very interested', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_more_edu_rooms',
                            'question' => __('With more education rooms available, there will be more session options running at the same time. Would you attend more sessions if more options were available?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'options'  => array(
                                'yes_more'       => __('Yes, more options would lead me to attend more sessions', 'oha-survey-hub'),
                                'yes_conflicts'  => __('Yes, it would help me avoid scheduling conflicts', 'oha-survey-hub'),
                                'probably'       => __('Probably, depending on the topics', 'oha-survey-hub'),
                                'no_fine'        => __('No, the current amount is fine', 'oha-survey-hub'),
                                'never_attend'   => __('I do not typically attend education sessions', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_new_features_excitement',
                            'question' => sprintf( __('What aspect of the %d additions are you most excited about? (Select all that apply)', 'oha-survey-hub'), $year ),
                            'type'     => 'checkbox',
                            'required' => false,
                            'priority' => 'high',
                            'options'  => array(
                                'main_stage'     => __('Main Stage events', 'oha-survey-hub'),
                                'haunt_showcase' => __('Haunt Showcase area', 'oha-survey-hub'),
                                'more_edu_rooms' => __('More education room options', 'oha-survey-hub'),
                                'more_vendors'   => __('More vendors on the vendor floor', 'oha-survey-hub'),
                                'none'           => __('None of these, I come for the existing features', 'oha-survey-hub'),
                            )
                        ),
                    )
                ),

                // Page 4: Education Sessions
                array(
                    'title'     => __('Education Sessions', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id'       => 'cp_edu_intent',
                            'question' => __('Do you plan to attend Education sessions at the convention?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'yes'   => __('Yes, definitely', 'oha-survey-hub'),
                                'maybe' => __('Maybe, depending on the topics', 'oha-survey-hub'),
                                'no'    => __('No, I do not typically attend sessions', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_edu_topics',
                            'question' => __('What education topics would you attend a session on? (Select all that apply)', 'oha-survey-hub'),
                            'type'     => 'checkbox',
                            'required' => false,
                            'priority' => 'high',
                            'options'  => array(
                                'makeup_sfx'      => __('Makeup and special effects', 'oha-survey-hub'),
                                'building_props'  => __('Set building and prop construction', 'oha-survey-hub'),
                                'lighting_audio'  => __('Lighting and audio design', 'oha-survey-hub'),
                                'business_ops'    => __('Business and operations (permits, insurance, marketing)', 'oha-survey-hub'),
                                'actor_training'  => __('Actor training and performance', 'oha-survey-hub'),
                                'ticketing_cx'    => __('Ticketing and customer experience', 'oha-survey-hub'),
                                'safety_security' => __('Safety and security', 'oha-survey-hub'),
                                'technology'      => __('Technology and automation', 'oha-survey-hub'),
                                'finances'        => __('Haunt business finances and revenue', 'oha-survey-hub'),
                                'social_media'    => __('Social media and marketing', 'oha-survey-hub'),
                                'other'           => __('Other', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_edu_session_length',
                            'question' => __('How long do you prefer education sessions to be?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => false,
                            'options'  => array(
                                '30_or_less' => __('30 minutes or less', 'oha-survey-hub'),
                                '45_to_60'   => __('45 to 60 minutes', 'oha-survey-hub'),
                                '90_min'     => __('90 minutes', 'oha-survey-hub'),
                                'multi_hour' => __('Multiple hours or workshop format', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_presenter_interest',
                            'question' => __('Would you be interested in presenting or teaching a session at the convention?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => false,
                            'options'  => array(
                                'yes'   => __('Yes, I would be interested', 'oha-survey-hub'),
                                'maybe' => __('Maybe, if the topic fit', 'oha-survey-hub'),
                                'no'    => __('No', 'oha-survey-hub'),
                            )
                        ),
                    )
                ),

                // Page 5: Entertainment and Events
                array(
                    'title'     => __('Entertainment and Events', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id'       => 'cp_costume_ball_intent',
                            'question' => __('Do you plan to attend the Costume Ball?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'yes'   => __('Yes, definitely', 'oha-survey-hub'),
                                'maybe' => __('Maybe', 'oha-survey-hub'),
                                'no'    => __('No', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_costume_contest_intent',
                            'question' => __('Would you enter the Costume Ball costume contest?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => false,
                            'options'  => array(
                                'yes'                => __('Yes', 'oha-survey-hub'),
                                'watch_only'         => __('No, but I would attend and watch', 'oha-survey-hub'),
                                'not_attending_ball' => __('I am not planning to attend the Costume Ball', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_blood_prison_intent',
                            'question' => __('Do you plan to attend Blood Prison?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'yes'   => __('Yes, definitely', 'oha-survey-hub'),
                                'maybe' => __('Maybe', 'oha-survey-hub'),
                                'no'    => __('No', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_main_stage_content',
                            'question' => __('What entertainment would you most want to see on the Main Stage? (Select all that apply)', 'oha-survey-hub'),
                            'type'     => 'checkbox',
                            'required' => false,
                            'priority' => 'high',
                            'options'  => array(
                                'live_band_dj'      => __('Live band or DJ', 'oha-survey-hub'),
                                'haunt_performance' => __('Haunt-themed performance or stage show', 'oha-survey-hub'),
                                'ceremonies'        => __('Opening and closing ceremonies with awards', 'oha-survey-hub'),
                                'fire_circus'       => __('Fire performance or circus acts', 'oha-survey-hub'),
                                'comedy_improv'     => __('Comedy or improv show', 'oha-survey-hub'),
                                'industry_speaker'  => __('Guest speaker from the haunt industry', 'oha-survey-hub'),
                                'other'             => __('Other', 'oha-survey-hub'),
                            )
                        ),
                    )
                ),

                // Page 6: Food and Dining
                array(
                    'title'       => __('Food and Dining', 'oha-survey-hub'),
                    'description' => __('Help us plan the food and beverage offerings to match what attendees actually want.', 'oha-survey-hub'),
                    'questions'   => array(
                        array(
                            'id'       => 'cp_food_importance',
                            'question' => __('How important is food quality and variety to your convention experience?', 'oha-survey-hub'),
                            'type'     => 'scale',
                            'required' => true,
                            'priority' => 'high',
                            'scale_min' => 1,
                            'scale_max' => 5,
                            'scale_labels' => array(
                                1 => __('Not important', 'oha-survey-hub'),
                                5 => __('Very important', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_food_options_wanted',
                            'question' => __('What type of food options would you most like to see at the convention? (Select all that apply)', 'oha-survey-hub'),
                            'type'     => 'checkbox',
                            'required' => false,
                            'priority' => 'high',
                            'options'  => array(
                                'food_trucks'       => __('Food trucks with varied options', 'oha-survey-hub'),
                                'sit_down'          => __('A sit-down meal option on-site', 'oha-survey-hub'),
                                'grab_and_go'       => __('Grab-and-go snacks and drinks', 'oha-survey-hub'),
                                'bar_service'       => __('Full bar or beer service', 'oha-survey-hub'),
                                'vegetarian_vegan'  => __('Vegetarian or vegan options', 'oha-survey-hub'),
                                'allergy_friendly'  => __('Allergy-friendly options', 'oha-survey-hub'),
                                'local_vendors'     => __('Ohio-based or local food vendors', 'oha-survey-hub'),
                                'coffee_hot_bev'    => __('Coffee and hot beverages', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_dietary_needs',
                            'question' => __('Do you have any dietary restrictions OHA should plan for? (Select all that apply)', 'oha-survey-hub'),
                            'type'     => 'checkbox',
                            'required' => false,
                            'options'  => array(
                                'vegetarian'    => __('Vegetarian', 'oha-survey-hub'),
                                'vegan'         => __('Vegan', 'oha-survey-hub'),
                                'gluten_free'   => __('Gluten-free', 'oha-survey-hub'),
                                'nut_allergy'   => __('Nut allergy', 'oha-survey-hub'),
                                'other_allergy' => __('Other food allergy or restriction', 'oha-survey-hub'),
                                'none'          => __('None', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_catered_dinner_interest',
                            'question' => __('Would you be interested in a catered dinner or social event the evening of the convention?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'yes_paid'         => __('Yes, I would pay for a separate ticket to that', 'oha-survey-hub'),
                                'yes_vip_included' => __('Yes, if it were included with VIP admission', 'oha-survey-hub'),
                                'maybe'            => __('Maybe', 'oha-survey-hub'),
                                'no'               => __('No, I would not attend something like that', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_food_spending',
                            'question' => __('How much would you reasonably spend on food during a full convention day?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => false,
                            'options'  => array(
                                'under_10' => __('Under $10', 'oha-survey-hub'),
                                '10_to_20' => __('$10 to $20', 'oha-survey-hub'),
                                '20_to_35' => __('$20 to $35', 'oha-survey-hub'),
                                'over_35'  => __('Over $35', 'oha-survey-hub'),
                                'bring_own' => __('I bring my own food', 'oha-survey-hub'),
                            )
                        ),
                    )
                ),

                // Page 7: Travel and Hotel
                array(
                    'title'       => __('Travel and Hotel', 'oha-survey-hub'),
                    'description' => __('This information helps us plan accommodations support and understand how far people are traveling.', 'oha-survey-hub'),
                    'questions'   => array(
                        array(
                            'id'       => 'cp_travel_distance',
                            'question' => __('How far would you be traveling to attend the convention?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'under_1hr'   => __('Under 1 hour', 'oha-survey-hub'),
                                '1_to_2hr'    => __('1 to 2 hours', 'oha-survey-hub'),
                                '2_to_3hr'    => __('2 to 3 hours', 'oha-survey-hub'),
                                'over_3hr'    => __('Over 3 hours', 'oha-survey-hub'),
                                'out_of_state' => __('Out of state', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_overnight_stay',
                            'question' => __('Are you planning to stay overnight near the convention venue?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'yes'   => __('Yes, I plan to stay overnight', 'oha-survey-hub'),
                                'maybe' => __('Maybe, depending on how the day goes', 'oha-survey-hub'),
                                'no'    => __('No, I will drive home the same day', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_hotel_block_interest',
                            'question' => __('If OHA negotiated a discounted hotel block rate near the Ohio Reformatory, would you use it?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'yes_definitely'  => __('Yes, definitely if the rate was competitive', 'oha-survey-hub'),
                                'probably'        => __('Probably yes', 'oha-survey-hub'),
                                'own_preference'  => __('No, I already have my own accommodations preference', 'oha-survey-hub'),
                                'not_staying'     => __('No, I am not staying overnight', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_hotel_info_timing',
                            'question' => __('How far in advance would you need hotel information to plan your trip?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => false,
                            'options'  => array(
                                '6_plus_months'   => __('6 or more months before the event', 'oha-survey-hub'),
                                '3_to_6_months'   => __('3 to 6 months before', 'oha-survey-hub'),
                                '1_to_3_months'   => __('1 to 3 months before', 'oha-survey-hub'),
                                '1_month_or_less' => __('1 month or less', 'oha-survey-hub'),
                            )
                        ),
                    )
                ),

                // Page 8: Logistics and Accessibility
                array(
                    'title'     => __('Logistics and Accessibility', 'oha-survey-hub'),
                    'questions' => array(
                        array(
                            'id'       => 'cp_days_attending',
                            'question' => __('Which day or days are you most likely to attend?', 'oha-survey-hub'),
                            'type'     => 'checkbox',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'saturday'  => __('Saturday', 'oha-survey-hub'),
                                'sunday'    => __('Sunday', 'oha-survey-hub'),
                                'both_days' => __('Both days', 'oha-survey-hub'),
                                'not_sure'  => __('Not sure yet', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_arrival_time',
                            'question' => __('What time would you typically plan to arrive on the day of the convention?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => false,
                            'options'  => array(
                                'doors_open'  => __('Right when doors open, or earlier', 'oha-survey-hub'),
                                'mid_morning' => __('Mid-morning', 'oha-survey-hub'),
                                'midday'      => __('Around midday', 'oha-survey-hub'),
                                'afternoon'   => __('Early afternoon', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_parking_concern',
                            'question' => __('The Ohio Reformatory has limited parking. Is parking availability an important factor in your planning?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'yes_critical' => __('Yes, limited parking would significantly affect my decision to attend', 'oha-survey-hub'),
                                'somewhat'     => __('Somewhat, but I would work around it', 'oha-survey-hub'),
                                'no'           => __('No, it does not affect my plans', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_accessibility_needs',
                            'question' => __('Does your group have any accessibility needs OHA should plan for? (Select all that apply)', 'oha-survey-hub'),
                            'type'     => 'checkbox',
                            'required' => false,
                            'options'  => array(
                                'wheelchair_mobility' => __('Wheelchair or mobility accessibility', 'oha-survey-hub'),
                                'hearing'             => __('Hearing or communication accommodations', 'oha-survey-hub'),
                                'other_accessibility' => __('Other accessibility need', 'oha-survey-hub'),
                                'none'                => __('None', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_shuttle_interest',
                            'question' => __('Would you be interested in a convention shuttle or ride-share coordination organized by OHA?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => false,
                            'options'  => array(
                                'yes'   => __('Yes, I would use that', 'oha-survey-hub'),
                                'maybe' => __('Maybe, depending on where it ran from', 'oha-survey-hub'),
                                'no'    => __('No', 'oha-survey-hub'),
                            )
                        ),
                    )
                ),

                // Page 9: Communication
                array(
                    'title'       => __('Communication and Planning', 'oha-survey-hub'),
                    'description' => __('Help us know when and how to reach you with convention updates.', 'oha-survey-hub'),
                    'questions'   => array(
                        array(
                            'id'       => 'cp_communication_preference',
                            'question' => __('How do you prefer to receive updates and announcements about the convention? (Select all that apply)', 'oha-survey-hub'),
                            'type'     => 'checkbox',
                            'required' => false,
                            'priority' => 'high',
                            'options'  => array(
                                'email'         => __('Email newsletter', 'oha-survey-hub'),
                                'facebook'      => __('Facebook posts', 'oha-survey-hub'),
                                'oha_website'   => __('OHA website', 'oha-survey-hub'),
                                'convention_app' => __('The OHA Convention App', 'oha-survey-hub'),
                                'instagram'     => __('Instagram', 'oha-survey-hub'),
                                'text_sms'      => __('Text message or SMS', 'oha-survey-hub'),
                                'other'         => __('Other', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_critical_info',
                            'question' => __('What information is most critical for you to have before you commit to attending? (Select all that apply)', 'oha-survey-hub'),
                            'type'     => 'checkbox',
                            'required' => false,
                            'priority' => 'high',
                            'options'  => array(
                                'full_schedule'       => __('Full event lineup and schedule', 'oha-survey-hub'),
                                'vendor_list'         => __('Full vendor list', 'oha-survey-hub'),
                                'edu_topics'          => __('Education session topics', 'oha-survey-hub'),
                                'hotel_options'       => __('Hotel and accommodation options', 'oha-survey-hub'),
                                'parking_details'     => __('Parking details', 'oha-survey-hub'),
                                'ticket_prices'       => __('Ticket prices confirmed', 'oha-survey-hub'),
                                'blood_prison_details' => __('Blood Prison details', 'oha-survey-hub'),
                                'costume_ball_details' => __('Costume Ball details', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_promo_timing',
                            'question' => sprintf( __('When is the ideal time for OHA to start promoting the %d convention heavily?', 'oha-survey-hub'), $year ),
                            'type'     => 'radio',
                            'required' => false,
                            'options'  => array(
                                'now_nov_jan'    => __('Right now (November through January)', 'oha-survey-hub'),
                                'jan_to_mar'     => __('January through March', 'oha-survey-hub'),
                                'mar_to_apr'     => __('March through April', 'oha-survey-hub'),
                                'within_60_days' => __('Within 60 days of the event', 'oha-survey-hub'),
                            )
                        ),
                    )
                ),

                // Page 10: Overall Feedback
                array(
                    'title'     => sprintf( __('Overall Feedback for %d', 'oha-survey-hub'), $year ),
                    'questions' => array(
                        array(
                            'id'       => 'cp_biggest_concern',
                            'question' => sprintf( __('What is your biggest concern or barrier to attending OHA Convention %d?', 'oha-survey-hub'), $year ),
                            'type'     => 'textarea',
                            'required' => false,
                            'priority' => 'high',
                        ),
                        array(
                            'id'       => 'cp_biggest_draw',
                            'question' => sprintf( __('What one thing could OHA do to make Convention %d worth attending for you?', 'oha-survey-hub'), $year ),
                            'type'     => 'textarea',
                            'required' => false,
                            'priority' => 'high',
                        ),
                        array(
                            'id'       => 'cp_overall_excitement',
                            'question' => sprintf( __('Overall, how excited are you about OHA Convention %d?', 'oha-survey-hub'), $year ),
                            'type'     => 'scale',
                            'required' => true,
                            'priority' => 'high',
                            'scale_min' => 1,
                            'scale_max' => 10,
                            'scale_labels' => array(
                                1  => __('Not at all excited', 'oha-survey-hub'),
                                10 => __('Extremely excited', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_recommend_attending',
                            'question' => __('Would you recommend attending OHA Convention to others in the haunt community?', 'oha-survey-hub'),
                            'type'     => 'radio',
                            'required' => true,
                            'priority' => 'high',
                            'options'  => array(
                                'yes_definitely' => __('Yes, definitely', 'oha-survey-hub'),
                                'probably'       => __('Probably', 'oha-survey-hub'),
                                'unsure'         => __('Unsure', 'oha-survey-hub'),
                                'probably_not'   => __('Probably not', 'oha-survey-hub'),
                                'no'             => __('No', 'oha-survey-hub'),
                            )
                        ),
                        array(
                            'id'       => 'cp_other_suggestions',
                            'question' => sprintf( __('Any other thoughts, suggestions, or ideas for OHA Convention %d?', 'oha-survey-hub'), $year ),
                            'type'     => 'textarea',
                            'required' => false,
                        ),
                    )
                ),

            )
        );
    }
}
