<?php
/*
Plugin Name: Smartservices, Mailchimp List By Woocommerce Product
Description: Subscribe users to different Mailchimp Lists Based on the Product they Buy
Version:     1.1.2
Author:      Hernán J. Fraind
Author URI:  https://www.smartservices.com.ar
*/

add_action('admin_menu', 'mlbp_menu');

function mlbp_menu(){
        add_submenu_page(
            'tools.php',
            'Mailchimp List By Woocommerce Product',
            'Mailchimp List By Woocommerce Product',
            'manage_options',
            'mlbp',
            'mlbp_submenu_page' 
        );
}

function mlbp_submenu_page() {
     // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $retrieved_nonce = $_REQUEST['_wpnonce'];
    if (isset($_POST['mlbp_apikey']) && !wp_verify_nonce($retrieved_nonce, 'apikey_edit_action' ) ) die( 'Failed security check' );
    
    $mlbp_option_Key = get_option('mlbp_apikey');
    if($_POST['mlbp_apikey'] ){
        $my_post = stripslashes_deep($_POST);
        $sanit_mlbp_apikey = strip_tags(sanitize_text_field($my_post['mlbp_apikey']),'<strong><b><br><a><script><u><em><i><span><img>');
        
        if (!$mlbp_option_Key) {
            add_option('mlbp_apikey', $sanit_mlbp_apikey);
        }
        else
            update_option('mlbp_apikey', $sanit_mlbp_apikey);
        $mlbp_option_Key = $sanit_mlbp_apikey;
        }           
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="" method="post">
            <?php wp_nonce_field('apikey_edit_action'); ?>
            Mailchimp Api Key: <input type="text" name="mlbp_apikey" size="40" value="<?php echo $mlbp_option_Key; ?>" /><br />
            <input type="submit" value="submit"/>
        </form>
    </div>
    <?php
}

function mlbp_js_init() {}
function mlbp_js_admin_init() {}
function mlbp_plugin_install() {
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'mlbp_plugin_install' );
add_action('wp_enqueue_scripts','mlbp_js_init');
add_action( 'admin_enqueue_scripts', 'mlbp_js_admin_init' );

// Display Fields
add_action( 'woocommerce_product_options_general_product_data', 'mlbp_woo_add_custom_general_fields' );
// Save Fields
add_action( 'woocommerce_process_product_meta', 'mlbp_woo_add_custom_general_fields_save' );

function mlbp_woo_add_custom_general_fields() {

    global $woocommerce, $post;
    $actualList = get_post_meta( $post->ID, '_mailchimp_list', true );
    if($actualList == '' or !isset($actualList))
        $actualList = 'xxxxxxxxxx';
        
    echo '<div class="options_group">';
    woocommerce_wp_text_input( 
	array( 
		'id'          => '_mailchimp_list', 
		'label'       => __( 'Mailchimp List', 'woocommerce' ), 
		'placeholder' => $actualList,
		'desc_tip'    => 'true',
		'description' => __( 'Ingrese la lista de Mailchimp del Producto.', 'woocommerce' ) 
	)
    ); 
    echo '</div>';	
}

function mlbp_woo_add_custom_general_fields_save( $post_id ){
    $woocommerce_text_field = sanitize_text_field($_POST['_mailchimp_list']);
    if(!empty($woocommerce_text_field) && strlen($woocommerce_text_field) == 10) {
        update_post_meta($post_id, '_mailchimp_list', esc_attr( $woocommerce_text_field ));	
    }
}

function mlbp_order_completed($order_id) {
    $apiKey = get_option('mlbp_apikey');
    if($apiKey) {
        $dataCenter = substr($apiKey,strpos($apiKey,'-')+1);

        $order = new WC_Order( $order_id );
        $email = $order->billing_email;
        $fname = $order->billing_first_name;
        $lname = $order->billing_last_name;
        $items = $order->get_items();
        foreach ( $items as $item ) {
            $product_id = $item['product_id'];
            $listID = get_post_meta( $product_id, '_mailchimp_list', true );
            if ($listID != '') {
                $memberID = md5(strtolower($email));
                $url = 'https://' . $dataCenter . '.api.mailchimp.com/3.0/lists/' . $listID . '/members/' . $memberID;
                $json = json_encode([
                    'email_address' => $email,
                    'status'        => 'subscribed',
                    'merge_fields'  => [
                        'FNAME'     => $fname,
                        'LNAME'     => $lname
                    ]
                ]);
                // send a HTTP POST request with curl
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_USERPWD, 'user:' . $apiKey);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                $result = curl_exec($ch);
                curl_close($ch);
            }   
        }     
    }
}
add_action( 'woocommerce_order_status_completed', 'mlbp_order_completed',10,1);
?>