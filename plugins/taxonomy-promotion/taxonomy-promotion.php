<?php
/*
 *  Plugin Name: Taxonomy Promotion
 *  Description: Shows titles or excerpts in the selected taxonomy and term.
 *  Author: Pasi Lallinaho
 *  Version: 2.0
 *  Author URI: http://open.knome.fi/
 *  Plugin URI: http://wordpress.knome.fi/
 *
 *  License: GNU General Public License v2 or later
 *  License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 */

/*  Init plugin
 *
 */

add_action( 'plugins_loaded', 'TaxonomyPromotionInit' );

function TaxonomyPromotionInit( ) {
	/* Load text domain for i18n */
	load_plugin_textdomain( 'taxonomy-promotion', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/*  Enable some JS
 *
 */

add_action( 'admin_enqueue_scripts', 'TaxonomyPromotionScripts' );

function TaxonomyPromotionScripts( ) {
	wp_enqueue_script( 'jquery' );

	if( is_admin( ) ) {
		wp_register_script( 'taxonomy-promotion-admin', plugins_url( 'taxpromo-admin.js', __FILE__ ), array( 'jquery' ), '1.1' );
		# make the full url available for the script
		wp_localize_script( 'taxonomy-promotion-admin', 'WP_AJAX', array( 'url' => admin_url( 'admin-ajax.php' ) ) );

		# translate the script
		wp_localize_script( 'taxonomy-promotion-admin', 'l10n', array(
			'selecttax' => __( '-- Select taxonomy first --', 'taxonomy-promotion' )
		) );

		wp_enqueue_script( 'taxonomy-promotion-admin' );
	}
}

/*  AJAX function to get taxonomy terms
 *
 */

add_action( 'wp_ajax_taxpromo_ajax', 'TaxonomyPromotionAJAX' );

function TaxonomyPromotionAJAX( ) {
	switch( $_REQUEST['fn'] ) {
		case 'get_taxonomy_terms':
			$out = get_terms( $_REQUEST['taxonomy'] );
		break;
	}

	$out = json_encode( $out );

	if( is_array( $out ) ) {
		print_r( $out );
	} else {
		echo $out;
	}

	die;
}

/*  Widget
 *
 */

add_action( 'widgets_init', function( ) { register_widget( 'TaxonomyPromotionWidget' ); } );

class TaxonomyPromotionWidget extends WP_Widget {
	/** constructor */
	function __construct( ) {
		$widget_ops = array( 'description' => __( 'Shows titles or excerpts in the selected taxonomy and term.', 'taxonomy-promotion' ) );

		parent::__construct( 'taxonomy_promotion', _x( 'Taxonomy Promotion', 'widget name', 'taxonomy-promotion' ), $widget_ops );
	}

	/** @see WP_Widget::widget */
	function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );

		echo '<div class="taxpromo">';
		echo $before_widget;

		if( !empty( $title ) ) {
			echo $before_title . $title . $after_title;
		}

		if( $amount < 1 ) { $amount = 0; }

		//
		$tp_query = new WP_Query( array(
			'posts_per_page' => $instance['amount'],
			'tax_query' => array( array(
				'taxonomy' => $instance['taxonomy'],
				'field' => 'id',
				'terms' => $instance['taxonomy_term']
			) )
		) );
		//

		if( $instance['display'] == 'title' ) {
			echo '<ul>';
		}

		while( $tp_query->have_posts( ) ) {
			$tp_query->the_post( );
			if( !$prev ) { $first = " first"; $prev = 1; } else { $first = ""; }

			switch( $instance['display'] ) {
				case 'title_excerpt':
					echo '<div class="item">';
					echo '<strong class="title' . $first . '">' . get_the_title( ) . '</strong>';
					echo '<p class="excerpt">' . get_the_excerpt( );
					echo '<br /><span class="more"><a href="' . get_permalink( ) . '">' . __( 'Read more &raquo;', 'taxonomy-promotion' ) . '</a></span>';
					echo '</p>';
					echo '</div>';
				break;
				case 'featured_title_excerpt':
					echo '<div class="item">';
					echo '<div class="featured">' . get_the_post_thumbnail( ) . '</div>';
					echo '<strong class="title' . $first . '">' . get_the_title( ) . '</strong>';
					echo '<p class="excerpt">' . get_the_excerpt( );
					echo '<br /><span class="more"><a href="' . get_permalink( ) . '">' . __( 'Read more &raquo;', 'taxonomy-promotion' ) . '</a></span>';
					echo '</p>';
					echo '</div>';
				break;
				case 'title':
				default:
					echo '<li class="title"><a href="' . get_permalink( ) . '">' . get_the_title( ) . '</a></li>';
				break;
			}
		}

		if( $instance['display'] == 'title' ) {
			echo '</ul>';
		}

		echo '<p class="more-link"><strong><a href="' . get_term_link( (int) $instance['taxonomy_term'], $instance['taxonomy'] ) . '">' . $instance['morelink'] . '</a></strong></p>';

		echo $after_widget;
		echo '</div>';

		wp_reset_postdata( );
	}

	/** @see WP_Widget::update */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		if( $new_instance['taxonomy_and_term'] ) {
			list( $new_instance['taxonomy'], $new_instance['taxonomy_term'] ) = explode( '.', $new_instance['taxonomy_and_term'] );
		}

		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['taxonomy'] = strip_tags( $new_instance['taxonomy'] );
		$instance['taxonomy_term'] = strip_tags( $new_instance['taxonomy_term'] );
		$instance['display'] = strip_tags( $new_instance['display'] );
		$instance['amount'] = strip_tags( $new_instance['amount'] );
		$instance['morelink'] = strip_tags( $new_instance['morelink'] );

		return $instance;
	}

	/** @see WP_Widget::form */
	function form( $instance ) {
		$title = esc_attr( $instance['title'] );
		$taxonomy = esc_attr( $instance['taxonomy'] );
		$taxonomy_term = esc_attr( $instance['taxonomy_term'] );
		$display = esc_attr( $instance['display'] );
		$amount = esc_attr( $instance['amount'] );
		$morelink = esc_attr( $instance['morelink'] );

		if( $amount < 1 ) { $amount = 0; }

		$disabled = disabled( $taxonomy, 0, false );

		?>
		<p><!-- Title -->
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title', 'taxonomy-promotion' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
		</p>

		<!-- Taxonomy and term -->
		<!-- JS is enabled -->
		<p class="taxpromo hide-if-no-js">
			<label for="<?php echo $this->get_field_id( 'taxonomy' ); ?>"><?php _e( 'Taxonomy and term', 'taxonomy-promotion' ); ?><br />
				<select class="widefat taxpromo-tax" id="<?php echo $this->get_field_id( 'taxonomy' ); ?>" name="<?php echo $this->get_field_name( 'taxonomy' ); ?>">
					<?php
						$args = array( "public" => true, "show_ui" => true );
						$taxs = get_taxonomies( $args, 'objects' );

						print '<option value="0" ' . selected( 0, $taxonomy, false ) . '>' . __( '-- Select taxonomy --' ) . '</option>';

						foreach( $taxs as $tax ) {
							if( count( get_terms( $tax->name, array( 'number' => 1 ) ) ) > 0 ) {
								print '<option value="' . $tax->name . '"' . selected( $tax->name, $taxonomy, false ) . '>' . $tax->labels->singular_name . '</option>';
							}
						}
					?>
				</select>
			</label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'taxonomy_term' ); ?>" name="<?php echo $this->get_field_name( 'taxonomy_term' ); ?>" <?php echo $disabled; ?> style="margin-top: 0.2em;">
				<?php
					if( $disabled ) {
						print '<option value="0">' . __( '-- Select taxonomy first --', 'taxonomy-promotion' ) . '</option>';
					} else {
						$terms = get_terms( $taxonomy );

						foreach( $terms as $term ) {
							print '<option value="' . $term->term_id . '"' . selected( $term->term_id, $taxonomy_term, false ) . '>' . $term->name . '</option>';
						}
					}
				?>
			</select>
		</p>
		<p class="taxpromo hide-if-js">
			<label for="<?php echo $this->get_field_id( 'taxonomy_and_term' ); ?>"><?php _e( 'Taxonomy and term', 'taxonomy-promotion' ); ?><br />
				<select class="widefat" id="<?php echo $this->get_field_id( 'taxonomy_and_term' ); ?>" name="<?php echo $this->get_field_name( 'taxonomy_and_term' ); ?>">
					<?php
						$args = array( "public" => true, "show_ui" => true );
						$taxs = get_taxonomies( $args, 'objects' );

						print '<option value="0" ' . selected( 0, $taxonomy, false ) . '>' . __( '-- Select taxonomy and term --' ) . '</option>';

						foreach( $taxs as $tax ) {
							foreach( get_terms( $tax->name ) as $term ) {
								print '<option value="' . $tax->name . '.' . $term->term_id . '"' . selected( $tax->name . '.' . $term->term_id, $taxonomy . '.' . $taxonomy_term, false ) . '>' . $tax->labels->singular_name . ': ' . $term->name . '</option>';
							}
						}
					?>
				</select>
			</label>
		</p>

		<p><!-- What to display? -->
			<label for="<?php echo $this->get_field_id( 'display' ); ?>"><?php _e( 'Display', 'taxonomy-promotion' ); ?><br />
				<select class="widefat" id="<?php echo $this->get_field_id( 'display' ); ?>" name="<?php echo $this->get_field_name( 'display' ); ?>">
					<option value="title" <?php selected( $display, 'title' ); ?>><?php _e( 'Title', 'taxonomy-promotion' ); ?></option>
					<option value="title_excerpt" <?php selected( $display, 'title_excerpt' ); ?>><?php _e( 'Title, Excerpt', 'taxonomy-promotion' ); ?></option>
					<?php if( current_theme_supports( 'post-thumbnails' ) ) { ?>
						<option value="featured_title_excerpt" <?php selected( $display, 'featured_title_excerpt' ); ?>><?php _e( 'Featured image, Title, Excerpt', 'taxonomy-promotion' ); ?></option>
					<?php } ?>
				</select>
			</label>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'amount' ); ?>"><?php _e( 'Number of items', 'taxonomy-promotion' ); ?></label>
			<input id="<?php echo $this->get_field_id( 'amount' ); ?>" name="<?php echo $this->get_field_name( 'amount' ); ?>" type="text" value="<?php echo $amount; ?>" size="3" />
			<br /><small><?php _e( '0 for all items.', 'taxonomy-promotion' ); ?></small>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'morelink' ); ?>"><?php _e( "Text for 'more items' link", 'taxonomy-promotion' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'morelink' ); ?>" name="<?php echo $this->get_field_name( 'morelink' ); ?>" type="text" value="<?php echo $morelink; ?>" />
			<br /><small><?php _e( '<b>Only if number of items is more than 0.</b> If you leave this field empty, no link will be shown.', 'taxonomy-promotion' ); ?></small>
		</p>
		<?php 
	}

}

?>
