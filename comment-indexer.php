<?php
/*
Plugin Name: Comment Indexer
Plugin URI: http://premium.wpmudev.org/project/comment-indexer
Description: Indexes comments into a global table
Author: Paul Menard (Incsub)
Author URI: http://premium.wpmudev.org
Version: 1.0.9.1
Network: true
WDP ID: 27
*/

/* 
Copyright 2007-2011 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//------------------------------------------------------------------------//
//---Externals------------------------------------------------------------//
//------------------------------------------------------------------------//
include_once( dirname(__FILE__) . '/lib/dash-notices/wpmudev-dash-notification.php' );

//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//

register_activation_hook( __FILE__, 'comment_indexer_global_install' );

//index comments
add_action('comment_post', 'comment_indexer_comment_insert_update');
add_action('edit_comment', 'comment_indexer_comment_insert_update');
add_action('delete_comment', 'comment_indexer_delete');
add_action('wp_set_comment_status', 'comment_indexer_update_comment_status', 5, 2);
//handle blog changes
add_action('make_spam_blog', 'comment_indexer_change_remove');
add_action('archive_blog', 'comment_indexer_change_remove');
add_action('mature_blog', 'comment_indexer_change_remove');
add_action('deactivate_blog', 'comment_indexer_change_remove');
add_action('blog_privacy_selector', 'comment_indexer_public_update');
add_action('delete_blog', 'comment_indexer_change_remove', 10, 1);
//update blog types
add_action('blog_types_update', 'comment_indexer_sort_terms_update');


//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

function comment_indexer_global_install() {
	global $wpdb;
	
    $comment_indexer_table1 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "site_comments` (
      `site_comment_id` bigint(20) unsigned NOT NULL auto_increment,
      `blog_id` bigint(20),
      `site_id` bigint(20),
      `sort_terms` TEXT,
      `blog_public` int(2),
      `comment_approved` VARCHAR(255),
      `comment_id` bigint(20),
      `comment_post_id` bigint(20),
      `comment_post_permalink` TEXT,
      `comment_author` VARCHAR(60),
      `comment_author_email` VARCHAR(255),
      `comment_author_IP` VARCHAR(255),
      `comment_author_url` VARCHAR(50),
      `comment_author_user_id` bigint(20),
      `comment_content` TEXT,
      `comment_content_stripped` TEXT,
      `comment_karma` VARCHAR(255),
      `comment_agent` VARCHAR(255),
      `comment_type` VARCHAR(255),
      `comment_parent` VARCHAR(255),
      `comment_date_gmt` datetime NOT NULL default '0000-00-00 00:00:00',
      `comment_date_stamp` VARCHAR(255),
      PRIMARY KEY  (`site_comment_id`)
    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

    $wpdb->query( $comment_indexer_table1 );
}

function comment_indexer_update_comment_status($tmp_comment_ID, $tmp_comment_status){
    global $wpdb;

	if (!$tmp_comment_status)
		$tmp_comment_status = '0';
	
	$new_comment_status = '';
    switch ( $tmp_comment_status ) {
		case '1':
        case 'approve':
            //$query = "UPDATE " . $wpdb->base_prefix . "site_comments SET comment_approved='1' WHERE comment_id ='" . $tmp_comment_ID . "' and blog_id = '" . $wpdb->blogid . "' LIMIT 1";
			$new_comment_status = '1';
            break;

        case 'spam':
            //$query = "UPDATE " . $wpdb->base_prefix . "site_comments SET comment_approved='spam' WHERE comment_id ='" . $tmp_comment_ID . "' and blog_id = '" . $wpdb->blogid . "' LIMIT 1";
			$new_comment_status = 'spam';
            break;

		case 'trash':
    		//$query = "UPDATE " . $wpdb->base_prefix . "site_comments SET comment_approved='trash' WHERE comment_id ='" . $tmp_comment_ID . "' and blog_id = '" . $wpdb->blogid . "' LIMIT 1";
			$new_comment_status = 'trash';
			
    		break;

        case 'delete':
            comment_indexer_delete($tmp_comment_ID);
            //return true;
            break;

		case '0':
        case 'hold':
        default:
			//$query = "UPDATE " . $wpdb->base_prefix . "site_comments SET comment_approved='0' WHERE comment_id ='" . $tmp_comment_ID . "' and blog_id = '" . $wpdb->blogid . "' LIMIT 1";
			$new_comment_status = '0';
			break;

    }

	if ($new_comment_status != '') {
		$wpdb->update($wpdb->base_prefix . "site_comments",
			array(
				'comment_approved'	=>	$new_comment_status
			), 
			array(
				'comment_id'		=>	$tmp_comment_ID,
				'blog_id'			=>	$wpdb->blogid
			), array('%s'), array('%d', '%d')
		);
	}
	return false;
}

function comment_indexer_get_sort_terms($tmp_blog_ID){
	$comment_indexer_blog_lang = get_blog_option($tmp_blog_ID,"WPLANG");
	if ($comment_indexer_blog_lang == ''){
		$comment_indexer_blog_lang = 'en_EN';
	}
	$comment_indexer_blog_types = get_blog_option($tmp_blog_ID,"blog_types");
	if ($comment_indexer_blog_types == ''){
		$comment_indexer_blog_types = '||';
	}
	$comment_indexer_class = get_blog_option($tmp_blog_ID,"blog_class");
	
	$tmp_sort_terms = array();
	
	$comment_indexer_blog_types = explode("|", $comment_indexer_blog_types);
	foreach ( $comment_indexer_blog_types as $comment_indexer_blog_type ) {
		if ( $comment_indexer_blog_type != '' ) {
			$tmp_sort_terms[] = 'blog_type_' . $comment_indexer_blog_type;
		}
	}
	if ( $comment_indexer_class != '' ) {
		$tmp_sort_terms[] = 'class_' . $comment_indexer_class;
	}
	
	$tmp_sort_terms[] = 'blog_lang_' . strtolower( $comment_indexer_blog_lang );
	
	return '|' . implode("|", $tmp_sort_terms) . '|all|';

}

function comment_indexer_comment_insert_update($tmp_comment_ID){
	global $wpdb, $current_site;
	
	$tmp_blog_public = get_blog_status( $wpdb->blogid, 'public');
	$tmp_blog_archived = get_blog_status( $wpdb->blogid, 'archived');
	$tmp_blog_mature = get_blog_status( $wpdb->blogid, 'mature');
	$tmp_blog_spam = get_blog_status( $wpdb->blogid, 'spam');
	$tmp_blog_deleted = get_blog_status( $wpdb->blogid, 'deleted');
	
	$tmp_comment = get_comment($tmp_comment_ID);
	if ($tmp_blog_archived == '1'){
		comment_indexer_delete($tmp_comment_ID);
	} else if ($tmp_blog_mature == '1'){
		comment_indexer_delete($tmp_comment_ID);
	} else if ($tmp_blog_spam == '1'){
		comment_indexer_delete($tmp_comment_ID);
	} else if ($tmp_blog_deleted == '1'){
		comment_indexer_delete($tmp_comment_ID);
	} else if ($tmp_comment->comment_content == ''){
		comment_indexer_delete($tmp_comment_ID);
	} else {
		//delete comment
		comment_indexer_delete($tmp_comment_ID);
		
		//get sort terms
		$tmp_sort_terms = comment_indexer_get_sort_terms($wpdb->blogid);
		
		//comment does not exist - insert site comment
        $wpdb->insert( $wpdb->base_prefix . "site_comments", 
			array(
          		'blog_id' => $wpdb->blogid,
          		'site_id' => $wpdb->siteid,
          		'sort_terms' => $tmp_sort_terms,
          		'blog_public' => $tmp_blog_public,
          		'comment_approved' => $tmp_comment->comment_approved,
          		'comment_id' => $tmp_comment_ID,
          		'comment_post_id' => $tmp_comment->comment_post_ID,
          		'comment_post_permalink' => get_permalink($tmp_comment->comment_post_ID),
          		'comment_author' => $tmp_comment->comment_author,
          		'comment_author_email' => $tmp_comment->comment_author_email,
          		'comment_author_IP' => $tmp_comment->comment_author_IP,
          		'comment_author_url' => $tmp_comment->comment_author_url,
          		'comment_author_user_id' => $tmp_comment->user_id,
          		'comment_content' => $tmp_comment->comment_content,
          		'comment_content_stripped' => comment_indexer_strip_content($tmp_comment->comment_content),
          		'comment_karma' => $tmp_comment->comment_karma,
          		'comment_agent' => $tmp_comment->comment_agent,
          		'comment_type' => $tmp_comment->comment_type,
          		'comment_parent' => $tmp_comment->comment_parent,
          		'comment_date_gmt' => $tmp_comment->comment_date_gmt,
          		'comment_date_stamp' => time()
        	), array(	
				'%d', 	// blog_id
				'%d', 	// site_id
				'%s', 	// sort_terms
				'%d', 	// blog_public
				'%s', 	// comment_approved
				'%d', 	// comment_id
				'%d', 	// comment_post_id
          		'%s',	// comment_post_permalink
          		'%s',	// comment_author
          		'%s',	// comment_author_email
          		'%s',	// comment_author_IP
          		'%s',	// comment_author_url
          		'%d',	// comment_author_user_id
          		'%s',	// comment_content
          		'%s',	// comment_content_stripped
          		'%s',	// comment_karma
          		'%s',	// comment_agent
          		'%s',	// comment_type
          		'%s',	// comment_parent
          		'%s',	// comment_date_gmt
          		'%s'	// comment_date_stamp
			)
		);
  }
}

function comment_indexer_delete($tmp_comment_ID){
	global $wpdb;
	//delete site comment
	$wpdb->query( $wpdb->prepare("DELETE FROM " . $wpdb->base_prefix . "site_comments WHERE comment_id = %d AND blog_id = %d", $tmp_comment_ID, $wpdb->blogid ));
}

function comment_indexer_delete_by_site_comment_id($tmp_site_comment_ID, $tmp_blog_ID) {
	global $wpdb;
	//delete site comment
	$wpdb->query( $wpdb->prepare("DELETE FROM " . $wpdb->base_prefix . "site_comments WHERE site_comment_id = %d", $tmp_site_comment_ID));
}

function comment_indexer_public_update(){
	global $wpdb;
	if ( $_GET['updated'] == 'true' ) {
		//$wpdb->query("UPDATE " . $wpdb->base_prefix . "site_comments SET blog_public = '" . get_blog_status( $wpdb->blogid, 'public') . "' WHERE blog_id = '" . $wpdb->blogid . "' AND site_id = '" . $wpdb->siteid . "'");
		$wpdb->update($wpdb->base_prefix . "site_comments",
			array(
				'blog_public'		=>	get_blog_status( $wpdb->blogid, 'public')
			),
			array(
				'blog_id'			=>	$wpdb->blogid,
				'site_id'			=>	$wpdb->siteid
			), array('%s'), array('%d', '%d')
		);
	}
}
function comment_indexer_sort_terms_update(){
	global $wpdb;
	//$wpdb->query("UPDATE " . $wpdb->base_prefix . "site_comments SET sort_terms = '" . comment_indexer_get_sort_terms($wpdb->blogid) . "' WHERE blog_id = '" . $wpdb->blogid . "' AND site_id = '" . $wpdb->siteid . "'");
	
	$wpdb->update($wpdb->base_prefix . "site_comments",
		array(
			'sort_terms'		=>	comment_indexer_get_sort_terms($wpdb->blogid)
		),
		array(
			'blog_id'			=>	$wpdb->blogid,
			'site_id'			=>	$wpdb->siteid
		), array('%s'), array('%d', '%d')
	);
}

function comment_indexer_change_remove($tmp_blog_ID){
	global $wpdb, $current_user, $current_site;
	//delete site posts
	$blog_site_comments = $wpdb->get_results( $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "site_comments WHERE blog_id = %d AND site_id = %d", $tmp_blog_ID, $wpdb->siteid), ARRAY_A );
	if (count($blog_site_comments) > 0){
		foreach ($blog_site_comments as $blog_site_comment){
			comment_indexer_delete_by_site_comment_id($blog_site_comment['site_comment_id'], $tmp_blog_ID);
		}
	}
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Support Functions----------------------------------------------------//
//------------------------------------------------------------------------//

function comment_indexer_strip_content($tmp_content){
	$tmp_content = strip_tags($tmp_content);
	return $tmp_content;
}
