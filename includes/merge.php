<?php
/**
 * Mention user links in HyperTaxonomy term merges.
 *
 * A mention term picked from the @ autocomplete's local-user results is linked to that user
 * (`_tcsoc_user_id`), and later mentions of the user resolve to it. A merge deletes the source
 * terms with their meta, so a merged mention's link moves to the target, when the target isn't
 * linked to a user yet; otherwise it is dropped. It never stays on a redirect stub, which would
 * make new mentions of the user resolve to the stub. The Merge Terms page's check step says
 * what will happen (htax_merge_terms_check); there is no choice to make.
 *
 * Conversions need nothing: the link only means something in the mention taxonomy, and a
 * redirect stub left there keeps it.
 *
 * @package TermConnectSocial
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'htax_merge_terms_check', 'tcsoc_merge_check', 10, 4 );
add_action( 'htax_before_merge_terms', 'tcsoc_before_merge', 10, 3 );
add_action( 'htax_after_merge_terms', 'tcsoc_after_merge', 10, 3 );
add_filter( 'htax_merge_terms_messages', 'tcsoc_merge_messages' );

/**
 * The merge in progress: the sources' user links (user ID by source term ID, in merge order),
 * and the messages about the last merge.
 *
 * @since 1.0.0
 * @return array{links: array<int, int>, messages: string[]}
 */
function &tcsoc_merge_state(): array {
    static $state = array(
        'links' => array(),
        'messages' => array(),
    );
    return $state;
}

/**
 * The linked users of mention terms, by term ID, for those that have one.
 *
 * @since 1.0.0
 * @param int[] $term_ids Term IDs.
 * @return array<int, int> User ID by term ID.
 */
function tcsoc_merge_user_links( array $term_ids ): array {
    $links = array();
    foreach ( $term_ids as $term_id ) {
        $user_id = (int) get_term_meta( (int) $term_id, '_tcsoc_user_id', true );
        if ( $user_id > 0 ) {
            $links[ (int) $term_id ] = $user_id;
        }
    }
    return $links;
}

/**
 * Where each source's user link goes in a merge, as the merge would apply it: to the target
 * when no user is linked there yet (the first source's link wins), else nowhere.
 *
 * @since 1.0.0
 * @param array<int, int> $links       User ID by source term ID.
 * @param int             $target_user User linked to the target, or 0.
 * @return array<int, bool> By source term ID: whether its link moves to the target. A link to
 *                          the target's own user is left out: nothing changes for it.
 */
function tcsoc_merge_plan( array $links, int $target_user ): array {
    $plan = array();
    foreach ( $links as $term_id => $user_id ) {
        if ( $user_id === $target_user ) {
            continue;
        }
        $plan[ $term_id ] = 0 === $target_user;
        if ( 0 === $target_user ) {
            $target_user = $user_id;
        }
    }
    return $plan;
}

/**
 * A user's display name, or their ID when the user no longer exists.
 *
 * @since 1.0.0
 * @param int $user_id User ID.
 * @return string
 */
function tcsoc_merge_user_name( int $user_id ): string {
    $user = get_user_by( 'id', $user_id );
    return $user ? $user->display_name : '#' . $user_id;
}

/**
 * Filter htax_merge_terms_check: say what happens to merged mentions' user links.
 *
 * @since 1.0.0
 * @param array  $sections   Sections so far.
 * @param int[]  $source_ids Source term IDs.
 * @param int    $target_id  Target term ID.
 * @param string $taxonomy   Taxonomy.
 * @return array
 */
function tcsoc_merge_check( $sections, $source_ids, $target_id, $taxonomy ) {
    $target = get_term( (int) $target_id, 'mention' );
    if ( 'mention' !== $taxonomy || ! $target instanceof WP_Term ) {
        return $sections;
    }
    $links = tcsoc_merge_user_links( (array) $source_ids );
    if ( ! $links ) {
        return $sections;
    }

    $target_user = (int) get_term_meta( $target->term_id, '_tcsoc_user_id', true );
    $notices     = array();
    foreach ( tcsoc_merge_plan( $links, $target_user ) as $term_id => $moves ) {
        $term = get_term( $term_id, 'mention' );
        $name = $term instanceof WP_Term ? $term->name : '#' . $term_id;
        $user = tcsoc_merge_user_name( $links[ $term_id ] );
        if ( $moves ) {
            /* translators: 1: source mention name, 2: user's display name, 3: target mention name */
            $text = sprintf( __( '%1$s is linked to the user %2$s. The link moves to %3$s, so later mentions of %2$s use it.', 'termconnect-social' ), $name, $user, $target->name );
        } else {
            /* translators: 1: source mention name, 2: user's display name, 3: target mention name */
            $text = sprintf( __( '%1$s is linked to the user %2$s. %3$s is linked to another user, so this link is dropped.', 'termconnect-social' ), $name, $user, $target->name );
        }
        $notices[] = array(
            'type' => 'info',
            'text' => $text,
        );
    }

    if ( $notices ) {
        $sections   = (array) $sections;
        $sections[] = array(
            'id'      => 'termconnect-social',
            'title'   => __( 'TermConnect Social', 'termconnect-social' ),
            'notices' => $notices,
            'fields'  => array(),
        );
    }
    return $sections;
}

/**
 * Action htax_before_merge_terms: keep the source mentions' user links, which the merge
 * deletes with the terms.
 *
 * @since 1.0.0
 * @param int[]  $source_ids Source term IDs.
 * @param int    $target_id  Target term ID.
 * @param string $taxonomy   Taxonomy.
 */
function tcsoc_before_merge( $source_ids, $target_id, $taxonomy ): void {
    $state             = &tcsoc_merge_state();
    $state['links']    = 'mention' === $taxonomy ? tcsoc_merge_user_links( (array) $source_ids ) : array();
    $state['messages'] = array();
}

/**
 * Action htax_after_merge_terms: move the first user link to the target, when the target has
 * none, and report what happened to each.
 *
 * @since 1.0.0
 * @param int[]  $source_ids Source term IDs (now deleted).
 * @param int    $target_id  Target term ID.
 * @param string $taxonomy   Taxonomy.
 */
function tcsoc_after_merge( $source_ids, $target_id, $taxonomy ): void {
    $state          = &tcsoc_merge_state();
    $links          = $state['links'];
    $state['links'] = array();
    $target         = get_term( (int) $target_id, 'mention' );
    if ( ! $links || ! $target instanceof WP_Term ) {
        return;
    }

    $target_user = (int) get_term_meta( $target->term_id, '_tcsoc_user_id', true );
    foreach ( tcsoc_merge_plan( $links, $target_user ) as $term_id => $moves ) {
        $user = tcsoc_merge_user_name( $links[ $term_id ] );
        if ( $moves ) {
            tcsoc_set_term_user_link( $target->term_id, $links[ $term_id ] );
            /* translators: 1: user's display name, 2: target mention name */
            $state['messages'][] = sprintf( __( 'The link to the user %1$s moved to %2$s.', 'termconnect-social' ), $user, $target->name );
        } else {
            /* translators: 1: user's display name, 2: target mention name */
            $state['messages'][] = sprintf( __( 'The link to the user %1$s was dropped: %2$s is linked to another user.', 'termconnect-social' ), $user, $target->name );
        }
    }
}

/**
 * Filter htax_merge_terms_messages: report what happened to user links.
 *
 * @since 1.0.0
 * @param string[] $messages Messages.
 * @return string[]
 */
function tcsoc_merge_messages( $messages ) {
    $state             = &tcsoc_merge_state();
    $messages          = array_merge( (array) $messages, $state['messages'] );
    $state['messages'] = array();
    return $messages;
}
