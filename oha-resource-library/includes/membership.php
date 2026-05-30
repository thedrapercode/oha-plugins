<?php
if ( ! defined('ABSPATH') ) { exit; }

function oha_rl_user_is_member( $user_id = 0 ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    if ( ! $user_id ) return false;

    // Must have an assigned OHA member ID (Board Hub canonical gate)
    if ( ! get_user_meta( $user_id, 'oha_member_id', true ) ) return false;

    // Check membership expiration if set
    $expires = get_user_meta( $user_id, 'membership_trial_end', true );
    if ( $expires && strtotime( $expires ) < time() ) return false;

    return apply_filters( 'oha_rl_user_is_member', true, $user_id );
}
