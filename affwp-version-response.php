<?php
/*
 * Plugin Name: AffWP Add-On Version Response
 * Description: Sets up the response for the AffiliateWP add-on update checks
 * Version: 1.0
 * Author: Pippin Williamson
 */

class AFFWP_Add_On_Version_Response {

        public function __construct() {
                add_action( 'init', array( $this, 'check_for_request' ), 9 );
        }

        public function check_for_request() {

                if( empty( $_POST['affwp_action'] ) || 'get_version' != $_POST['affwp_action'] ) {
                        return;
                }

                if( empty( $_POST['license'] ) ) {
                        return;
                }

                if( empty( $_POST['id'] ) || ! is_numeric( $_POST['id'] ) ) {
                        return;
                }

                if( empty( $_POST['slug'] ) ) {
                        return;
                }

                if( empty( $_POST['url'] ) ) {
                        return;
                }

                if( ! function_exists( 'edd_software_licensing' ) ) {
                        return;
                }

                $add_on = get_post( absint( $_POST['id'] ) );
                if( ! $add_on ) {
                        status_header( 404 );
                        return;
                }

		$licensing = edd_software_licensing();
                $license_key = sanitize_text_field( $_POST['license'] );
                $license   = $licensing->get_license( sanitize_text_field( $_POST['license'] ), true );

                if( ! $license ) {
                        status_header( 402 );
                        return;
                }

                $price_id = (int) $license->price_id;
                if( 2 !== $price_id && 3 !== $price_id ) {
                        status_header( 401 );
                        return; // Not a developer license
                }

                // All good, retrieve the Add On details

                if( 'expired' === $licensing->get_license_status( $license ) ) {
                        $description = '<p><strong>' . __( 'Your license is expired. Please renew it or purchase a new one in order to update this item.', 'edd_sl' ) . '</strong></p>' . $description;
                        $changelog   = '<p><strong>' . __( 'Your license is expired. Please renew it or purchase a new one in order to update this item.', 'edd_sl' ) . '</strong></p>' . $changelog;
                } else {

                        $changelog   = get_post_meta( $add_on->ID, '_edd_sl_changelog', true );
                        $description = ! empty( $add_on->post_excerpt ) ? $add_on->post_excerpt : $add_on->post_content;

                }

                $url = isset( $_POST['url'] ) ? sanitize_text_field( urldecode( $_POST['url'] ) ) : false;

                $response = array(
                        'new_version'   => get_post_meta( $add_on->ID, '_edd_sl_version', true ),
                        'name'          => $add_on->post_title,
                        'slug'          => $_POST['slug'],
                        'url'           => get_permalink( $add_on->ID ),
                        'homepage'      => get_permalink( $add_on->ID ),
                        'package'       => $this->get_encoded_download_package_url( $add_on->ID, $license_key, $url ),
                        'download_link' => $this->get_encoded_download_package_url( $add_on->ID, $license_key, $url ),
                        'sections'      => serialize(
                                array(
                                        'description' => wpautop( strip_tags( $description, '<p><li><ul><ol><strong><a><em><span><br>' ) ),
                                        'changelog'   => wpautop( strip_tags( stripslashes( $changelog ), '<p><li><ul><ol><strong><a><em><span><br>' ) )
                                 )
                         )
                );

                echo json_encode( $response ); exit;

        }

        public function get_encoded_download_package_url( $add_on_id = 0, $license_key = '', $url = '' ) {

                $download_name = get_the_title( $add_on_id );
                $expires       = strtotime( '+12 hours' );
                $file_key      = get_post_meta( $add_on_id, '_edd_sl_upgrade_file_key', true );
                $hash          = md5( $download_name . $file_key . $add_on_id . $license_key . $expires );
                $url           = str_replace( ':', '@', $url );

                $token = base64_encode( sprintf( '%s:%s:%d:%s:%s:%d', $expires, $license_key, $add_on_id, $hash, $url, 0 ) );

                $package_url = trailingslashit( home_url() ) . 'edd-sl/package_download/' . $token;

                return apply_filters( 'edd_sl_encoded_package_url', $package_url );
        }

}
new AFFWP_Add_On_Version_Response;
