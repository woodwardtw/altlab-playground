<?php 
/*
Plugin Name: Playground Cleaner - 24hr deleter of non-admin users and content
Plugin URI:  https://github.com/
Description: delete non-admin users and content every 24 hours, use in conjunction with join my multisite or other way to add users
Version:     1.0
Author:      Tom Woodward
Author URI:  http://altlab.vcu.edu
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: my-toolset

*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


function get_users_and_destroy_them(){
	$blog_id = get_current_blog_id();
	$args = array(
	'role__not_in' => array('Administrator'),	//leave the admins alone
 ); 
	$users = get_users( $args );
	foreach ($users as $key => $user) {
		echo $user->ID;
		$posts = new WP_Query( array( 'author' => $user->ID ) );
		get_content_and_destroy_it($user->ID);
		remove_user_from_blog($user->ID, $blog_id, NULL);
	}
}


function get_content_and_destroy_it($user_id){
	$args = array(
		'author' => $user_id,
		'post_type' => array('post','page','attachment'),// add foreach ( get_post_types( '', 'names' ) as $post_type  to deal w custom post types - also attachments
    	'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash'),    
		'posts_per_page' => -1,

	);
	$the_query = new WP_Query( $args );
	// The Loop
	if ( $the_query->have_posts() ) :
		while ( $the_query->have_posts() ) : $the_query->the_post();
			if (get_post_type() === 'post' || get_post_type() === 'page'){
			  wp_delete_post( get_the_id(), TRUE );
			}
			if (get_post_type() === 'attachment'){
			  wp_delete_attachment( get_the_id(), TRUE );
			}
		endwhile;
	endif;

	// Reset Post Data
	wp_reset_postdata();

}

add_shortcode( 'destroy', 'get_users_and_destroy_them' );


//only show your own posts/pages in admin land
function posts_for_current_author($query) {
    global $pagenow;
 
    if( 'edit.php' != $pagenow || !$query->is_admin )
        return $query;
 
    if( !current_user_can( 'activate_plugins' ) ) {
        global $user_ID;
        $query->set('author', $user_ID );
    }
    return $query;
}
add_filter('pre_get_posts', 'posts_for_current_author');


//make a page that allows you to manually delete by visiting the page and stores the hours for the cron job

function make_destroy_page(){
	if(get_page_by_title('destroy')){
		return;
	} else {
		$my_post = array(
		  'post_title'    => 'destroy',
		  'post_content'  => '[destroy]',
		  'post_status'   => 'publish',
		  'post_author'   => 1,
		  'post_type'	=> 'page',	
		);
		 
		// Insert the post into the database
		$post_id = wp_insert_post( $my_post );
		update_post_meta($post_id, 'destroy_content_pattern','48');
	}
}

register_activation_hook( __FILE__, 'make_destroy_page' );



//make it run every 24 hrs & deactivate if plugin deactivated
register_activation_hook(__FILE__, 'scheduled_purge');

function scheduled_purge() {
    if (! wp_next_scheduled ( 'my_scheduled_purge' )) {
	wp_schedule_event(time(), 'daily', 'my_scheduled_purge'); //change back to daily 
    }
}

add_action('my_scheduled_purge', 'get_users_and_destroy_them');


//turn off purge if plugin deactivated
register_deactivation_hook(__FILE__, 'purge_deactivation');
function purge_deactivation() {
	wp_clear_scheduled_hook('my_scheduled_purge');
}
