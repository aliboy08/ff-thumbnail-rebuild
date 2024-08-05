<?php
/**
 * Plugin name: FF Thumbnail Rebuild
 * Author: Five by Five
 * Description: Rebuild Thumbnails
 * Version: 2.0
 */

class FF_Thumbnail_Rebuild {

	function __construct() {
		add_action( 'admin_menu', [$this, 'admin_menu'] );
		add_filter( 'attachment_fields_to_edit', [$this, 'attachment_field_edit'], 10, 2 );

        add_action( 'wp_ajax_ff_thumbnail_rebuild', [$this, 'ajax_rebuild'] );
	}

	function admin_menu() {
        add_submenu_page( 'fivebyfive', 'Rebuild thumbnails', 'Rebuild thumbnails', 'manage_options', 'ff_rebuild_thumbnails', [$this, 'admin_page'] );
	}

    function admin_page() {
		?>
		<div id="message" class="updated fade" style="display:none"></div>
		<script type="text/javascript">
		// <![CDATA[
		function setMessage(msg) {
			jQuery("#message").html(msg);
			jQuery("#message").show();
		}

		function regenerate() {
			
			jQuery("#ff_thumbnail_rebuild").prop("disabled", true);
			setMessage("<p><?php _e('Reading attachments...', 'ff-thumbnail-rebuild') ?></p>");

			inputs = jQuery( 'input:checked' );
			var thumbnails= '';
			if( inputs.length != jQuery( 'input[type=checkbox]' ).length ){
				inputs.each( function(){
					thumbnails += '&thumbnails[]='+jQuery(this).val();
				} );
			}

			var onlyfeatured = jQuery("#onlyfeatured").prop('checked') ? 1 : 0;
			
			var post_type = jQuery('#select_post_type').val();
            
			jQuery.ajax({
				url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
				type: "POST",
				data: "action=ff_thumbnail_rebuild&do=getlist&onlyfeatured=" + onlyfeatured + "&post_type="+ post_type,
				success: function(result) {
					var list = eval(result);
					var curr = 0;

					if (!list) {
						setMessage("<?php _e( 'No attachments found.', 'ff-thumbnail-rebuild' ) ?>");
						jQuery("#ff_thumbnail_rebuild").prop("disabled", false);
						return;
					}

					function regenItem(throttling) {

						if (curr >= list.length) {
							jQuery("#ff_thumbnail_rebuild").prop("disabled", false);
							setMessage("<?php _e('Done.', 'ff-thumbnail-rebuild') ?>");
							return;
						}

						setMessage( '<?php printf( __( 'Rebuilding %s of %s (%s)...', 'ff-thumbnail-rebuild' ), "' + (curr + 1) + '", "' + list.length + '", "' + list[curr].title + '" ); ?>' );

						jQuery.ajax({
							url: "<?php echo admin_url('admin-ajax.php'); ?>",
							type: "POST",
							data: "action=ff_thumbnail_rebuild&do=regen&id=" + list[curr].id + thumbnails,
							success: function(result) {
								curr = curr + 1;
								if (result != '-1') {
									jQuery("#thumb").show();
									jQuery("#thumb-img").attr("src",result);
								}
								setTimeout(function() {
									regenItem(throttling);
								}, throttling * 1000);
							},
							error: function(request, status, error) {
								if ((request.status == 503 && 60 <= throttling) || (20 <= throttling)) {
									// console.log('ff-thumbnail-rebuild gave up on "' + curr + '" after too many errors!');
									// skip this image (most likely malformed or oom_reaper)
									curr = curr + 1;
									throttling = Math.round(throttling / 2);
								} else {
									throttling = throttling + 1;
								}
								setTimeout(function() {
									regenItem(throttling);
								}, throttling * 1000);
							}
						});
					}

					regenItem(0);
				},
				error: function(request, status, error) {
					setMessage("<?php _e( 'Error', 'ff-thumbnail-rebuild' ) ?>" + request.status);
				}
			});
		}

		jQuery(document).ready(function() {
			jQuery('#size-toggle').click(function() {
				jQuery("#sizeselect").find("input[type=checkbox]").each(function() {
					jQuery(this).prop("checked", !jQuery(this).prop("checked"));
				});
			});
		});

		// ]]>
		</script>

		<form method="post" action="" style="display:inline; float:left; padding-right:30px;">
			<h4><?php _e( 'Select which thumbnails you want to rebuild', 'ff-thumbnail-rebuild' ); ?>:</h4>
			<?php
			$post_types = get_post_types([
				'show_ui' => true,
			]);
			$exclude = [
				'attachment',
				'wp_block',
				'wp_navigation',
				'e-landing-page',
				'elementor_library',
				'elementor_snippet',
				'acf-field-group',
				'elementor_font',
				'elementor_icons',
			];
			?>
			<p>
				<select id="select_post_type">
					<option value="" selected>Post type</option>
					<?php
					foreach( $post_types as $pt ) {
						if( in_array( $pt, $exclude ) ) continue;
						echo '<option value="'. $pt .'">'. $pt .'</option>';
					}
					?>
				</select>
			</p>

			<a href="javascript:void(0);" id="size-toggle"><?php _e( 'Toggle all', 'ff-thumbnail-rebuild' ); ?></a>

			<ul id="sizeselect">

				<?php foreach ( $this->get_sizes() as $image_size ) : ?>

				<li>

					<label>
						<input type="checkbox" name="thumbnails[]" id="sizeselect" checked="checked" value="<?php echo $image_size['name'] ?>" />
						<?php
						$crop_setting = '';

						if( $image_size['crop'] ) {
							if( is_array( $image_size['crop'] ) ) {
								$crop_setting = sprintf( '%s, %s', $image_size['crop'][0], $image_size['crop']['1'] );
							}
							else {
								$crop_setting = ' ' . __( 'cropped', 'ff-thumbnail-rebuild' );
							}
						}

						printf( '<em>%s</em> (%sx%s%s)', $image_size['name'], $image_size['width'], $image_size['height'], $crop_setting );
						?>
					</label>

				</li>

				<?php endforeach; ?>

			</ul>

			<p>
				<label>
					<input type="checkbox" id="onlyfeatured" name="onlyfeatured" />
					<?php _e( 'Only rebuild featured images', 'ff-thumbnail-rebuild' ); ?>
				</label>
			</p>

			<p><?php _e( 'Note: If you\'ve changed the dimensions of your thumbnails, existing thumbnail images will not be deleted.',
			'ff-thumbnail-rebuild' ); ?></p>

			<input type="button" onClick="javascript:regenerate();" class="button" name="ff_thumbnail_rebuild" id="ff_thumbnail_rebuild" value="<?php _e( 'Rebuild All Thumbnails', 'ff-thumbnail-rebuild' ) ?>" />
			<br />
		</form>

		<div id="thumb" style="display:none;"><h4><?php _e( 'Last image', 'ff-thumbnail-rebuild' ); ?>:</h4><img id="thumb-img" /></div>
	
		<?php
	}
	
	function attachment_field_edit( $fields, $post ) {

		$thumbnails = array();

		foreach ( $this->get_sizes() as $s ) {
			$thumbnails[] = 'thumbnails[]=' . $s['name'];
		}

		$thumbnails = '&' . implode( '&', $thumbnails );

		ob_start();
		?>
		<script>
			function setMessage(msg) {
				jQuery("#atr-message").html(msg);
				jQuery("#atr-message").show();
			}

			function regenerate() {
				jQuery("#ff_thumbnail_rebuild").prop("disabled", true);
				setMessage("<?php _e('Reading attachments...', 'ff-thumbnail-rebuild') ?>");
				thumbnails = '<?php echo $thumbnails ?>';
				jQuery.ajax({
					url: "<?php echo admin_url('admin-ajax.php'); ?>",
					type: "POST",
					data: "action=ff_thumbnail_rebuild&do=regen&id=<?php echo $post->ID ?>" + thumbnails,
					success: function(result) {
						if (result != '-1') {
							setMessage("<?php _e('Done.', 'ff-thumbnail-rebuild') ?>");
						}
					},
					error: function(request, status, error) {
						setMessage("<?php _e('Error', 'ff-thumbnail-rebuild') ?>" + request.status);
					},
					complete: function() {
						jQuery("#ff_thumbnail_rebuild").prop("disabled", false);
					}
				});
			}
		</script>
		<input type='button' onclick='javascript:regenerate();' class='button' name='ff_thumbnail_rebuild' id='ff_thumbnail_rebuild' value='Rebuild Thumbnails'>
		<span id="atr-message" class="updated fade" style="clear:both;display:none;line-height:28px;padding-left:10px;"></span>
		<?php
		$html = ob_get_clean();

		$fields['ff-thumbnail-rebuild'] = array(
			'label' => __( 'Ajax Thumbnail Rebuild', 'ff-thumbnail-rebuild' ),
			'input' => 'html',
			'html'  => $html
		);

		return $fields;
	}

    function ajax_rebuild(){
        global $wpdb;

        $action = $_POST["do"];
        $thumbnails = isset( $_POST['thumbnails'] )? $_POST['thumbnails'] : NULL;
        $onlyfeatured = isset( $_POST['onlyfeatured'] ) ? $_POST['onlyfeatured'] : 0;
        $post_type = isset( $_POST['post_type'] ) ? $_POST['post_type'] : 0;
    
        if ($action == "getlist") {
            $res = array();
    
            if ( $onlyfeatured ) {
                /* Get all featured images */
                $post_type_clause = '';
                if( $post_type ) {
                    $post_type_clause = " AND post_type='{$post_type}'";
                }
    
                $featured_images = $wpdb->get_results( "SELECT meta_value, {$wpdb->posts}.post_title AS title FROM {$wpdb->postmeta}, {$wpdb->posts} WHERE meta_key = '_thumbnail_id' AND {$wpdb->postmeta}.post_id={$wpdb->posts}.ID{$post_type_clause} ORDER BY post_date DESC");
    
                foreach( $featured_images as $image ) {
                    $res[] = array(
                        'id'    => $image->meta_value,
                        'title' => $image->title
                    );
                }
            }
            else {
                $attachments = get_children( array(
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'numberposts'    => -1,
                    'post_status'    => null,
                    'post_parent'    => null, // any parent
                    'output'         => 'object',
                    'orderby'        => 'post_date',
                    'order'          => 'desc'
                ) );
    
                foreach ( $attachments as $attachment ) {
    
                    if( $post_type ) {
                        if( get_post_type( $attachment->post_parent ) != $post_type ) {
                            continue;
                        }
                    }
                    
                    $res[] = array(
                        'id'    => $attachment->ID,
                        'title' => $attachment->post_title
                    );
                }
            }
    
            die( json_encode( $res ) );
        }
        else if ($action == "regen") {
            $id = $_POST["id"];
    
            $fullsizepath = get_attached_file( $id );
    
            if ( FALSE !== $fullsizepath && @file_exists( $fullsizepath ) ) {
                set_time_limit( 30 );
                wp_update_attachment_metadata( $id, $this->generate_attachment_metadata( $id, $fullsizepath, $thumbnails ) );
    
                die( wp_get_attachment_thumb_url( $id ));
            }
    
            die( '-1' );
        } 
    }

    function get_sizes() {

        global $_wp_additional_image_sizes;
    
        foreach ( get_intermediate_image_sizes() as $s ) {
            
            $sizes[$s] = array(
                'name'   => '',
                'width'  => '',
                'height' => '',
                'crop'   => FALSE
            );
    
            /* Read theme added sizes or fall back to default sizes set in options... */
    
            $sizes[$s]['name'] = $s;
    
            if ( isset( $_wp_additional_image_sizes[$s]['width'] ) ) {
                $sizes[$s]['width'] = intval( $_wp_additional_image_sizes[$s]['width'] );
            }
            else {
                $sizes[$s]['width'] = get_option( "{$s}_size_w" );
            }
    
            if ( isset( $_wp_additional_image_sizes[$s]['height'] ) ) {
                $sizes[$s]['height'] = intval( $_wp_additional_image_sizes[$s]['height'] );
            }
            else {
                $sizes[$s]['height'] = get_option( "{$s}_size_h" );
            }
    
            if ( isset( $_wp_additional_image_sizes[$s]['crop'] ) ) {
                if( ! is_array( $sizes[$s]['crop'] ) ) {
                    $sizes[$s]['crop'] = intval( $_wp_additional_image_sizes[$s]['crop'] );
                }
                else {
                    $sizes[$s]['crop'] = $_wp_additional_image_sizes[$s]['crop'];
                }
            }
            else {
                $sizes[$s]['crop'] = get_option( "{$s}_crop" );
            }
        }
    
        $sizes = apply_filters( 'intermediate_image_sizes_advanced', $sizes );
    
        return $sizes;
    }

    function generate_attachment_metadata( $attachment_id, $file, $thumbnails = NULL ){
        $attachment = get_post( $attachment_id );

        $metadata = array();
        if ( preg_match( '!^image/!', get_post_mime_type( $attachment ) ) && file_is_displayable_image( $file ) ) {
            $imagesize = getimagesize( $file );
            $metadata['width'] = $imagesize[0];
            $metadata['height'] = $imagesize[1];
            list($uwidth, $uheight) = wp_constrain_dimensions($metadata['width'], $metadata['height'], 128, 96);
            $metadata['hwstring_small'] = sprintf( "height='%s' width='%s'", $uheight, $uwidth );
    
            // Make the file path relative to the upload dir
            $metadata['file'] = _wp_relative_upload_path( $file );
    
            $sizes = $this->get_sizes();
    
            foreach ( $sizes as $size => $size_data ) {
                if( isset( $thumbnails ) && ! in_array( $size, $thumbnails ) ) {
                    $intermediate_size = image_get_intermediate_size( $attachment_id, $size_data['name'] );
                }
                else {
                    $intermediate_size = image_make_intermediate_size( $file, $size_data['width'], $size_data['height'], $size_data['crop'] );
                }
    
                if ( $intermediate_size ) {
                    $metadata['sizes'][$size] = $intermediate_size;
                }
            }
    
            // fetch additional metadata from exif/iptc
            $image_meta = wp_read_image_metadata( $file );
    
            if ( $image_meta ) {
                $metadata['image_meta'] = $image_meta;
            }
        }
    
        return apply_filters( 'wp_generate_attachment_metadata', $metadata, $attachment_id );
    }
    
};

new FF_Thumbnail_Rebuild();