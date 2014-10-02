<?php
/*
Plugin Name: Credits Shortcode
Version: 1.2
Plugin URI: http://techzei.com
Description: Easy shortcode to insert Source and Via Link inside posts in Wordpress
Author: Jash Jacob
Author URI: http://jashjacob.com

Copyright 2013

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

wp_enqueue_style('source_style', plugin_dir_url( __FILE__ ) . 'css/style.css');

function creditsPrint($atts,$content=NULL) {
	extract( shortcode_atts( array(
		'link'		=> '#',
		'type'		=> 'type',
	), $atts) );


	$source = '<ul class="credits"><li class="credits"><span class="cre_cate">Source</span><span class="cre_cate_link"><a href="'.$link.'">'.$content.'</a></span></li></ul>';
	$via = '<ul class="credits"><li class="credits"><span class="cre_cate">Via</span><span class="cre_cate_link"><a href="'.$link.'">'.$content.'</a></span></li></ul>';
 
 if($type=="source")
 {
 	return $source;
 }
 else if($type=="via")
 {
 	return $via;
 }
	
}
add_shortcode('credits','creditsPrint');

add_action( 'init', 'credits_buttons');
function credits_buttons() {
    add_filter( "mce_external_plugins", "credits_add_buttons" );
    add_filter( 'mce_buttons', 'credits_register_buttons' );
}
function credits_add_buttons( $plugin_array ) {
    $plugin_array['credits'] = plugins_url('credits_shortcode_plugin.js',__FILE__);
    return $plugin_array;
}
function credits_register_buttons( $buttons ) {
    array_push( $buttons, 'addcredits');
    return $buttons;
}

?>
