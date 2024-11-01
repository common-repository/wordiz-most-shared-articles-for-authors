<?php
/*
  Plugin Name: WordiZ - Most Shared Articles for Authors
  Description: Display authorship based 'most shared articles' in a <a href="widgets.php">widget</a>. Please, create an account on <a href="http://www.elokenz.com">WordiZ</a> to get your data collected prior using the widget.
  Author: <a href="http://boiteaweb.fr">Julio Potier</a>, <a href="http://www.elokenz.com">Elokenz</a> 
  Version: 2.0.1
  Author URI: http://boiteaweb.fr
  Licence: GPLv2
 */

add_action( 'plugins_loaded', 'baw_load_langs_elokenz_widget' );
function baw_load_langs_elokenz_widget()
{
    load_plugin_textdomain( 'baw_elokenz_widget', false, dirname(plugin_basename(__FILE__)) . '/langs/' );
    wp_register_style( 'elokenz',  plugins_url( '/elokenz_widget.css', __FILE__ ) );
}

// register elokenz_Widget widget
add_action( 'widgets_init', 'baw_register_elokenz_widget' );
function baw_register_elokenz_widget()
{
    register_widget( 'baw_elokenz_widget' );
}

function baw_elokenz_widget_admin_scripts(){
	?>
	<style type="text/css">
		.elokenz_support{
			border: 1px solid #DFDFDF;
			padding: 5px 13px;
			background: #F9F9F9;
			text-decoration:none;
			margin: 10px -13px;
		}
		.elokenz_support a{
			text-decoration: none;
		}
		.elokenz_support a:hover{
			text-decoration: underline;
		}
	</style>
	<?php
}
add_action('sidebar_admin_page', 'baw_elokenz_widget_admin_scripts');

class BAW_Elokenz_Widget extends WP_Widget {

    function __construct() {

        $widget_args = array(
            'classname'   => 'baw_elokenz_widget',
            'description' => __( 'Display  \'most shared articles\' based on authorship tag.', 'baw_elokenz_widget' )
        );

        parent::__construct( 'baw_elokenz_widget', __( 'Elokenz Widget - Authorship Best articles', 'baw_elokenz_widget' ), $widget_args );
    }

    function widget( $arguments, $instance ) {

        extract( $arguments );

        $title       = apply_filters( 'widget_title', $instance['title'] );
        $width       = !$instance['width'] ? 'auto' : (int)$instance['width']."px";
        $class       = 'bright' == $instance['style'] ? 'bright' : 'dark';
        $follow      = 'do' == $instance['follow'] ? 'do' : 'no';
        $item_number = (int) esc_attr( $instance['item_number'] );
        $network     = (int) esc_attr( $instance['network'] );
        $user_id     = esc_attr( $instance['user_id'] );
        $from_cache = true;

        if( false === ($json = get_transient( 'elokenz_' . $user_id ) ))
        {
            // It wasn't there, so regenerate the data and save the transient
            $url_elokenz_widget = 'http://www.elokenz.com/api/authors/'.$user_id.'/articles/top/?format=json';
            $json = wp_remote_get( $url_elokenz_widget );
            $from_cache  = false;
        }
        if( $from_cache || ( !is_wp_error( $json ) && $json = wp_remote_retrieve_body( $json ) ) )
        {   
            $json = json_decode( $json );
            set_transient( 'elokenz_' . $user_id, json_encode( $json ), 100 );
            if (!$from_cache){
                // If not from cache, then set the transients
                set_transient( 'elokenz_' . $user_id, json_encode( $json ), 12 * HOUR_IN_SECONDS );
            }
            
            if( !isset( $json->Error ) ) {
                wp_enqueue_style( 'elokenz' );
                echo $before_widget;
                echo !empty( $title ) ? $before_title . $title . $after_title : '';
                ?>
                <ul class="elokenz_widget_best_articles <?php echo $class; ?>">
                <?php
					// Check that $json-> top_articles exist
                    if (is_array($json->$network->articles)) {
                        foreach ($json->$network->articles as $n=>$article )
                        {
                            if( $n==$item_number )
                                break;
                            $the_prop = '';
                            $props = array( 'title'=>esc_attr( $article->title ), 'href'=>esc_url( $article->url ), 'target'=>'_blank' );
                            if( 'no'==$follow )
                                $props['rel'] = 'nofollow';
                            $props = apply_filters( 'elokenz_a_props', $props, $article );
                            foreach( $props as $prop=>$val )
                                $the_prop .= "{$prop}=\"{$val}\" ";
                            echo '<li>';
                                echo "<a {$the_prop}>". esc_html( $article->title ) .'</a><br />';
                                echo '<span class="elokenz_widget twitter">' . (int)$article->tweet_count .'</span>';
                                echo '<span class="elokenz_widget google">' . (int)$article->plus_count .'</span>';
                                echo '<span class="elokenz_widget facebook">' . (int)$article->like_count .'</span>';
                            echo '</li>';
                        }
                    }
                ?>
                </ul>
				Powered by <a href="http://www.elokenz.com" target="_blank" title="Content marketing automation tool for bloggers">elokenz</a>
                <?php
                echo $after_widget;
            }else{
                echo esc_html( str_replace( '.', ' : ' . esc_html( $user_id ), $json->Error ) );
            }
        }
    }

    function update( $new_instance, $old_instance ) {

        $instance = $old_instance;

        $instance['title']       = sanitize_text_field( $new_instance['title'] );
        $instance['width']       = intval( $new_instance['width'] ) > 0 ? intval( $new_instance['width'] ) : 'auto';
        $instance['style']       = 'bright' == $new_instance['style'] ? 'bright' : 'dark';
        $instance['follow']      = 'no' == $new_instance['follow'] ? 'no' : 'do';
        // 10 items max
        $instance['item_number'] = (int)$new_instance['item_number'] > 0 && (int)$new_instance['item_number'] <= 10 ? (int)$new_instance['item_number'] : (int)$old_instance['item_number'];
        // 4 Networks (total, twitter, facebook, g+)
        $instance['network']     = (int)$new_instance['network'] > 0 && (int)$new_instance['network'] <= 3 ? (int)$new_instance['network'] : (int)$old_instance['network'];
        $instance['user_id']     = str_replace( '^([0-9])+$', '', $new_instance['user_id'] );

        return $instance;
    }

    function form( $instance ) {

        $defaults = array(
            'title'       => '',
            'width'       => 250,
            'follow'      => 'do',
            'style'       => 'bright',
            'item_number' => 10,
            'network' => 0,
            'user_id'     => ''
        );
        
        $networks = array(
            0 => 'Total',
            1 => 'Twitter',
            2 => 'Facebook',
            3 => 'Google+',
        );

        $instance = wp_parse_args( (array)$instance, $defaults );

        $follow      = esc_attr( $instance['follow'] );
        $title       = esc_attr( $instance['title'] );
        $width       = esc_attr( $instance['width'] );
        $style       = esc_attr( $instance['style'] );
        $item_number = esc_attr( $instance['item_number'] );
        $network = esc_attr( $instance['network'] );
        $user_id     = esc_attr( $instance['user_id'] );
        ?>

            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title', 'baw_elokenz_widget'); ?> :</label>
            <input value="<?php echo $title; ?>" type="text" name="<?php echo $this->get_field_name('title'); ?>" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('style'); ?>"><?php _e('Display on bright or dark background ?', 'baw_elokenz_widget'); ?> :</label>
            <select name="<?php echo $this->get_field_name('style'); ?>" id="<?php echo $this->get_field_id('style'); ?>" class="widefat">
                <option <?php selected( $style, 'bright', true ); ?> value="bright"><?php _e('Bright', 'baw_elokenz_widget'); ?></option>
                <option <?php selected( $style, 'dark', true ); ?> value="dark"><?php _e('Dark', 'baw_elokenz_widget'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('item_number'); ?>"><?php _e('Number of items', 'baw_elokenz_widget'); ?> :</label>
            <select name="<?php echo $this->get_field_name('item_number'); ?>" id="<?php echo $this->get_field_id('item_number'); ?>" class="widefat">
                <?php for ($i = 1; $i <= $defaults['item_number']; $i++) : ?>
                    <option <?php selected( $item_number, $i, true ); ?> value="<?php echo $i; ?>"><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('network'); ?>"><?php _e('Ordering System', 'baw_elokenz_widget'); ?> :</label>
            <select name="<?php echo $this->get_field_name('network'); ?>" id="<?php echo $this->get_field_id('network'); ?>" class="widefat">
                 <?php for ($i = 0; $i <= 3; $i++) : ?>
                    <option <?php selected( $network, $i, true ); ?> value="<?php echo $i; ?>"><?php echo $networks[$i]; ?></option>
                <?php endfor; ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('follow'); ?>"><?php _e('Follow or Nofollow?', 'baw_elokenz_widget'); ?> :</label>
            <select name="<?php echo $this->get_field_name('follow'); ?>" id="<?php echo $this->get_field_id('follow'); ?>" class="widefat">
                <option <?php selected( $follow, 'do', true ); ?> value="do"><?php _e( 'dofollow links', 'baw_elokenz_widget' ); ?></option>
                <option <?php selected( $follow, 'no', true ); ?> value="no"><?php _e( 'nofollow links', 'baw_elokenz_widget' ); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('user_id'); ?>"><?php _e('Google+ User ID', 'baw_elokenz_widget'); ?> :</label>
            <input value="<?php echo $user_id; ?>" type="text" name="<?php echo $this->get_field_name('user_id'); ?>" class="widefat" id="<?php echo $this->get_field_id('user_id'); ?>" />
        </p>

         <div class="elokenz_support">
               <p>Click on the sign in button below to get your G+ user id number. It will appear on a new page. Copy it above.</p>
               <center> <a href="http://www.elokenz.com/accounts/login/google-oauth2/?next=/profile/google-id/" class="signin-button google" target="_blank"><img src="https://developers.google.com/+/images/branding/sign-in-buttons/Red-signin_Medium_base_44dp.png" style="width: 120px; height: 40px;"></a></center>
               <p>The Google+ authorization allows <a href="http://www.elokenz.com">elokenz</a> to import the articles you tagged as an author.</p>
           
           
		<p><small style="opacity: 0.7"><a href="http://wordpress.org/support/view/plugin-reviews/elokenz-most-shared-articles-for-authors?filter=5" target="_blank">Support</a> | <a href="https://twitter.com/elokenz_com" target="_blank">Follow</a></small></p>		</div>
        <?php
    }
}