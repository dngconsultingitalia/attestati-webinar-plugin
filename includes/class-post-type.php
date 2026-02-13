<?php
/**
 * Custom Post Type: Attestato Webinar
 */

if (!defined('ABSPATH')) {
    exit;
}

class Att_Webinar_Post_Type {
    
    public function __construct() {
        // Registra direttamente: questa classe viene gia istanziata durante l'hook init
        $this->register_post_type();
    }
    
    public function register_post_type() {
        $labels = array(
            'name'               => __('Attestati', 'attestati-webinar'),
            'singular_name'      => __('Attestato', 'attestati-webinar'),
            'menu_name'          => __('Attestati Webinar', 'attestati-webinar'),
            'add_new'            => __('Nuovo Attestato', 'attestati-webinar'),
            'add_new_item'       => __('Aggiungi Nuovo Attestato', 'attestati-webinar'),
            'edit_item'          => __('Modifica Attestato', 'attestati-webinar'),
            'new_item'           => __('Nuovo Attestato', 'attestati-webinar'),
            'view_item'          => __('Visualizza Attestato', 'attestati-webinar'),
            'search_items'       => __('Cerca Attestati', 'attestati-webinar'),
            'not_found'          => __('Nessun attestato trovato', 'attestati-webinar'),
            'not_found_in_trash' => __('Nessun attestato nel cestino', 'attestati-webinar'),
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 56,
            'menu_icon'           => 'dashicons-awards',
            'supports'            => array('title'),
        );
        
        register_post_type('attestato_webinar', $args);
    }
}
