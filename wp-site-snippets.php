<?php
/*
Plugin Name: WP Site Snippets
Plugin URI: https://github.com/Link7/wp-site-snippets
Version: 0.1
Author: Link7
Description: Wordpress plugin that create site snippets based on domain name
Text Domain: wp-site-snippets
License: GPLv3

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>
*/

class WPSiteSnippets
{
	public $mapped_domains = NULL;

	public function __construct(){
		$this->define_constants();
		$this->setup_actions();
		$this->setup_filters();
	}

	private function setup_actions(){
		// Register the custom post type
		add_action( 'init', array( $this, 'wpss_register_post_type' ));
		// Register the taxonomy for categories

		// check mapped domains
		add_action('init', array($this,'wpss_get_mapped_domains'));

		// remove default cat box
		add_action('init',array($this,'wpss_remove_cat_box'));

		// replace with a customised cat box
		add_action( 'add_meta_boxes', array($this,'wpss_add_meta_box' ));
		add_action( 'init', array( $this, 'wpss_register_taxonomy' ));

		// add snippet to wp_head/wp_footer
		add_action( 'wp_head', array($this,'wpss_add_snippet' ));
		add_action( 'wp_footer', array($this,'wpss_add_snippet' ));
	}	

	/**
	 * Define WP Content Mixer constants
	 */
	private function define_constants() {
			define( 'WPSS_POST_TYPE', 'wpss-code');
			define( 'WPSS_TAXONOMY', 'wpss-snippet-options');
	}

	private function setup_filters(){
		add_filter('wp_list_categories', array($this,'wpss_customise_category_widget'));
	}

	public function wpss_get_mapped_domains(){
		// 1. check if WPMU domain mapping db is available
		global $wpdb;
		if(isset($wpdb->dmtable)){
			if($wpdb->get_var("SHOW TABLES LIKE '$wpdb->dmtable'") == $wpdb->dmtable) {
				
				// 2. table exists fetch the domains
    			$domains = $wpdb->get_results( "SELECT * FROM {$wpdb->dmtable} WHERE blog_id = '{$wpdb->blogid}'", ARRAY_A );
    			if ( is_array( $domains ) && !empty( $domains ) ) {
    				$this->mapped_domains = $domains;
    			}
			}
		}
	}

	/**
	* Register the WP Site Snippets Post Type
	* @return void
	*/
	public function wpss_register_post_type() {
		$labels = array(
		'name' => __('Site Snippets', WPSS_POST_TYPE),
		'singular_name' => __('Site Snippet', WPSS_POST_TYPE),
		'add_new' => __('Add New Snippet', WPSS_POST_TYPE),
		'add_new_item' => __('Add New Snippet', WPSS_POST_TYPE),
		'edit_item' => __('Edit Site Snippet', WPSS_POST_TYPE),
		'new_item' => __('New Site Snippet', WPSS_POST_TYPE),
		'all_items' => __('All Site Snippets', WPSS_POST_TYPE),
		'view_item' => __('View Site Snippets', WPSS_POST_TYPE),
		'search_items' => __('Search Site Snippet', WPSS_POST_TYPE),
		'not_found' =>  __('No Site Snippet found', WPSS_POST_TYPE),
		'not_found_in_trash' => __('No Site Snippet found in Trash', WPSS_POST_TYPE), 
		'menu_name' => __('Site Snippets', WPSS_POST_TYPE),
		);

		$args = array(
		'menu_icon' => 'dashicons-welcome-add-page',	
		'labels' => $labels,
		'public' => false,
		'show_ui' => true, 
		'show_in_menu' => true, 
		'query_var' => WPSS_POST_TYPE,
		'rewrite' => false,
		'capability_type' => 'post',
		'has_archive' => false, 
		'hierarchical' => false,
		'menu_position' => null,
		'categories'=>true,
		'supports' => array( 'title', 'editor','author')
		);
		register_post_type(WPSS_POST_TYPE, $args);
	}

	/**
	 * Register the WP Site Snippets Post type Taxonomy
	 * @return void
	 */
	public function wpss_register_taxonomy() {
		global $wpdb;
		$args = array(
				'labels' => array(
						'name' => 'Snippet Options',
						'add_new_item' => 'Add New Snippet Option',
						'new_item_name' => 'New Snippet Option'
				),
				'show_ui' => true,
				'show_tagcloud' => false,
				'hierarchical' => true,
				'show_admin_column' => false,
				'show_in_nav_menus' => false
		);
		register_taxonomy( WPSS_TAXONOMY, WPSS_POST_TYPE, $args);

		// now create the taxonomies using the mapped domian
		$mapped_domains = array();
		$deleted_terms = array();
		if($this->mapped_domains !== NULL){
			// Add new domains
			foreach ($this->mapped_domains as $details) {
				$mapped_domains[] = $details['domain'];
				$term = term_exists($details['domain']);
				
				if($term === NULL){
					// insert the term
					$parent_id = wp_insert_term(
					  $details['domain'], // the term 
					  WPSS_TAXONOMY, // the taxonomy
					  array(
					    'description'=> 'domain name',
					    'slug' => $details['domain']
					  )
					);
					
					// insert child terms (positions)
					wp_insert_term(
					  'wp_head', // the term 
					  WPSS_TAXONOMY, // the taxonomy
					  array(
					    'description'=> 'position',
					    'slug' => 'wp_head_'.$parent_id['term_id'],
					    'parent' => $parent_id['term_id']
					  )
					);
					wp_insert_term(
					  'wp_footer', // the term 
					  WPSS_TAXONOMY, // the taxonomy
					  array(
					    'description'=> 'position',
					    'slug' => 'wp_footer_'.$parent_id['term_id'],
					    'parent' => $parent_id['term_id']
					  )
					);
				}
			}
		}

		// Now remove terms for domains not mapped
		$term_args=array(
		  'hide_empty' => false,
		  'orderby' => 'name',
		  'order' => 'ASC'
		);
		$available_terms = get_terms(WPSS_TAXONOMY, $term_args);
		if(!empty($available_terms)){
			// delete the parent
			foreach ($available_terms as $available_term) {
				
				if(!in_array($available_term->name, $mapped_domains) && $available_term->parent == 0){
					// delete term
					wp_delete_term($available_term->term_id, WPSS_TAXONOMY);
					$deleted_terms[]=$available_term->term_id;
				}

			}
			// delete the children
			foreach ($available_terms as $available_term) {
				if($available_term->parent !== 0 && in_array($available_term->parent, $deleted_terms)){
					wp_delete_term($available_term->term_id, WPSS_TAXONOMY);
				}
			}

		}
	}

	public function wpss_remove_cat_box(){
		remove_meta_box(WPSS_TAXONOMY.'div', WPSS_POST_TYPE, 'side');
	}

	public function wpss_add_meta_box(){
		if($this->mapped_domains!==NULL)
			add_meta_box(
				WPSS_TAXONOMY,
				__( 'Snippet Options', 'wpss_textdomain' ),
				array($this,'wpss_post_categories_meta_box'),
				WPSS_POST_TYPE,
				'normal'
			);
	}

	public function wpss_post_categories_meta_box( $post, $box ) {

	$defaults = array('taxonomy' => WPSS_TAXONOMY);
	if ( !isset($box['args']) || !is_array($box['args']) )
		$args = array();
	else
		$args = $box['args'];
	extract( wp_parse_args($args, $defaults), EXTR_SKIP );
	$tax = get_taxonomy($taxonomy);

	?>

	<div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
		<ul id="<?php echo $taxonomy; ?>-tabs" class="category-tabs">
			<li class="tabs"><a href="#<?php echo $taxonomy; ?>-all"><?php echo $tax->labels->all_items; ?></a></li>
		</ul>

		<div id="<?php echo $taxonomy; ?>-all" class="tabs-panel">
			<?php
            $name = ( $taxonomy == 'category' ) ? 'post_category' : 'tax_input[' . $taxonomy . ']';
            echo "<input type='hidden' name='{$name}[]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
            ?>
			<ul id="<?php echo $taxonomy; ?>checklist" data-wp-lists="list:<?php echo $taxonomy?>" class="categorychecklist form-no-clear">
				<?php wp_terms_checklist($post->ID, array( 'taxonomy' => $taxonomy,'checked_ontop' => false) ) ?>
			</ul>
		</div>
		<script>
		jQuery(document).ready(function($){
		  synchronize_child_and_parent_category($);
		});
		 
		function synchronize_child_and_parent_category($) {
		  $('#wpss-snippet-optionschecklist').find('input').each(function(index, input) {
		    $(input).bind('change', function() {
		      var checkbox = $(this);
		      var is_checked = $(checkbox).is(':checked');
		      if(is_checked) {
		        $(checkbox).parents('li').children('label').children('input').attr('checked', 'checked');
		      } else {
		        $(checkbox).parentsUntil('ul').find('input').removeAttr('checked');
		      }
		    });
		  });
		}
		</script>
	
	</div>
	<?php
	}

	public function wpss_add_snippet($default){
		global $wpdb;
		// get domain name
		$domain = $_SERVER['HTTP_HOST']; //var_dump($domain);
		
		// get position in page
		$position = current_filter();

		$blog_id = $wpdb->blogid;

		$generic = "_{$blog_id}_";
		
		if($blog_id==1)
	    	$generic = "_";

		$snippet_query = "SELECT post_content"
		." FROM wp{$generic}posts"
		." INNER JOIN wp{$generic}term_relationships ON (ID = object_id)"
		." WHERE term_taxonomy_id = ("
    	." SELECT term_taxonomy_id FROM wp{$generic}term_taxonomy"
    	." INNER JOIN wp{$generic}terms ON wp{$generic}term_taxonomy.term_id = wp{$generic}terms.term_id"
    	." WHERE wp{$generic}term_taxonomy.taxonomy = '".WPSS_TAXONOMY."'"
    	." AND wp{$generic}terms.name = '{$position}'"
    	." AND wp{$generic}term_taxonomy.parent = ("
        ." SELECT term_id FROM wp{$generic}terms WHERE name = '{$domain}'"
    	.")"
		.")"
		." AND post_type = 'wpss-code' AND (post_status = 'publish')";

		$result = $wpdb->get_results($snippet_query, OBJECT);

		if($result){
			foreach ($result as $snippet) {
				//$snippet_content.=$snippet->post_content;
				echo $snippet->post_content;
			}
			
		}

	}
}

new WPSiteSnippets;