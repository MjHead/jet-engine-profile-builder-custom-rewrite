<?php
/**
 * Plugin Name: JetEngine - profile builder custom rewites
 * Plugin URI:
 * Description: Allow to change user pages URLs to site.name/username/ format.
 * Version:     1.0.0
 * Author:      Crocoblock
 * Author URI:  https://crocoblock.com/
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die();
}

class JEPB_Custom_Rewrite {
    
    public function __construct() {
        add_action( 'parse_request', [ $this, 'setup_props' ] );
        add_filter( 'jet-engine/profile-builder/rewrite-rules', [ $this, 'rewrite_subpages' ], 10, 2 );
        add_filter( 'jet-engine/profile-builder/subpage-url', [ $this, 'rewrite_render_urls' ], 10, 5 );
    }

    public function module() {
        return \Jet_Engine\Modules\Profile_Builder\Module::instance();
    }

    public function rewrite_subpages( $rules, $manager ) {
    
        $pages     = $this->module()->settings->get_pages();
        $user_page = ! empty( $pages['single_user_page'] ) ? absint( $pages['single_user_page'] ) : false;

        if ( $user_page ) {

            $page_object = get_page( $user_page );

            if ( $page_object ) {
                
                $slug             = $page_object->post_name;
                $raw_data         = $this->module()->settings->get( 'user_page_structure', [] );
                $subpage_rewrites = [];

                foreach ( $raw_data as $subpage ) {
                    $subpage_rewrites[ '([^/]+)/' . $subpage['slug'] . '/?' ] = 'pagename=' . $slug . '&' . $manager->page_var . '=single_user_page&' . $manager->user_var . '=$matches[1]&' . $manager->subpage_var . '=' . $subpage['slug'];
                }

                $rules = array_merge( $subpage_rewrites, $rules );
            }
            
        }

        return $rules;

    }

    public function rewrite_render_urls( $url, $slug, $page, $subpage, $manager ) {
    
        if ( 'single_user_page' === $page ) {
            
            if ( $slug ) {
                $slug = '/' . $slug . '/';
            } else {
                $slug = '/';
            }

            $url = home_url( '/' . $this->module()->query->get_queried_user_slug() . $slug );
        }

        return $url;

    }

    public function setup_props( &$wp ) {

        if ( '([^/]+)(?:/([0-9]+))?/?$' !== $wp->matched_rule ) {
            return;
        }
        
        $requested_user = $wp->request;
        $user = $this->maybe_get_user( $requested_user );

        if ( ! $user ) {
            return;
        }

        $pages     = $this->module()->settings->get_pages();
        $user_page = ! empty( $pages['single_user_page'] ) ? absint( $pages['single_user_page'] ) : false;
        $user_page = get_page( $user_page );

        if ( ! $user_page ) {
            return;
        }

        set_query_var( 'name', $user_page->post_name );
        set_query_var( 'pagename', $user_page->post_name );
        set_query_var( $this->module()->rewrite->page_var, 'single_user_page' );
        set_query_var( $this->module()->rewrite->subpage_var, null );
        set_query_var( $this->module()->rewrite->user_var, $requested_user );

        $wp->request = $user_page->post_name;
        $wp->query_vars = [
            'page' => '',
            'name' => $user_page->post_name,
            $this->module()->rewrite->page_var => 'single_user_page',
            $this->module()->rewrite->subpage_var => null,
            $this->module()->rewrite->user_var => $requested_user,
        ];

    }

    public function maybe_get_user( $name ) {

        $rewrite = $this->module()->settings->get( 'user_page_rewrite' );
        $user = false;

        switch ( $rewrite ) {

            case 'login':
            case 'id':
                $user = get_user_by( $rewrite, $name );
                break;

            case 'user_nicename':               
                $user = get_user_by( 'slug', $name );
                break;

            
        }

        return $user;
        
    }
}

new JEPB_Custom_Rewrite();
