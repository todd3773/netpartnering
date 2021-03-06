<?php

class core_pdf_embedder {
	
	protected function useminified() {
		return true;
	}
	
	protected function __construct() {
		$this->add_actions();
		register_activation_hook($this->my_plugin_basename(), array( $this, 'pdfemb_activation_hook' ) );
	}
	
	// May be overridden in basic or premium
	public function pdfemb_activation_hook($network_wide) {
	}
	
	public function pdfemb_wp_enqueue_scripts() {
	}
	
	protected $inserted_scripts = false;
	protected function insert_scripts() {
		if (!$this->inserted_scripts) {
			$this->inserted_scripts = true;
			wp_enqueue_script( 'pdfemb_embed_pdf_js' );
			
			wp_enqueue_script( 'pdfemb_pdf_js' );
			
			wp_enqueue_style( 'pdfemb_embed_pdf_css', $this->my_plugin_url().'css/pdfemb-embed-pdf.css' );
		}
	}
	
	protected function get_translation_array() {
		return Array('worker_src' => $this->my_plugin_url().'js/pdfjs/pdf.worker'.($this->useminified() ? '.min' : '').'.js',
		             'cmap_url' => $this->my_plugin_url().'js/pdfjs/cmaps/');
	}
	
	protected function get_extra_js_name() {
		return '';
	}
		
	
	// SHORTCODES
	
	// Take over PDF type in media gallery
	public function pdfemb_upload_mimes($existing_mimes = array()) {
		$existing_mimes['pdf'] = 'application/pdf';
		return $existing_mimes;
	}
	
	public function pdfemb_post_mime_types($post_mime_types) {
		$post_mime_types['application/pdf'] = array( __( 'PDFs' ), __( 'Manage PDFs' ), _n_noop( 'PDF <span class="count">(%s)</span>', 'PDFs <span class="count">(%s)</span>' ) );
		return $post_mime_types;
	}
	
	// Embed PDF shortcode instead of link
	public function pdfemb_media_send_to_editor($html, $id, $attachment) {
		if (preg_match( "/\.pdf$/i", $attachment['url'])) {
			return '[pdf-embedder url="' . $attachment['url'] . '"]';
		} else {
			return $html;
		}
	}
	
	protected function modify_pdfurl($url) {
		return set_url_scheme($url);
	}
	
	/* public function pdfemb_wp_get_attachment_link( $link, $id, $size, $permalink, $icon, $text ) {
		return $link;
	}*/

	public function pdfemb_shortcode_display_pdf($atts, $content=null) {
		if (!isset($atts['url'])) {
			return '<b>PDF Embedder requires a url attribute</b>';
		}
		$url = $atts['url'];
		
		$this->insert_scripts();

        // Get defaults

        $options = $this->get_option_pdfemb();
		
		$width = isset($atts['width']) ? $atts['width'] : $options['pdfemb_width'];
		$height = isset($atts['height']) ? $atts['height'] : $options['pdfemb_height'];
		
		$extra_style = "";
		if (is_numeric($width)) {
			$extra_style .= "width: ".$width."px; ";
		}
        elseif ($width!='max' && $width!='auto') {
            $width = 'max';
        }

		if (is_numeric($height)) {
			$extra_style .= "height: ".$height."px; ";
		}
		elseif ($height!='max' && $height!='auto') {
			$height = 'max';
		}
		
		$toolbar = isset($atts['toolbar']) && in_array($atts['toolbar'], array('top', 'bottom', 'both')) ? $atts['toolbar'] : $options['pdfemb_toolbar'];
        if (!in_array($toolbar, array('top', 'bottom', 'both'))) {
            $toolbar = 'bottom';
        }
		
		$toolbar_fixed = isset($atts['toolbarfixed']) ? $atts['toolbarfixed'] : $options['pdfemb_toolbarfixed'];
        if (!in_array($toolbar_fixed, array('on', 'off'))) {
            $toolbar_fixed = 'off';
        }

		$returnhtml = '<div class="pdfemb-viewer" data-pdf-url="'.esc_attr($this->modify_pdfurl($url)).'" style="'.esc_attr($extra_style).'" '
						.'data-width="'.esc_attr($width).'" data-height="'.esc_attr($height).'" ';
		
		$returnhtml .= $this->extra_shortcode_attrs($atts, $content);
						
		$returnhtml .= ' data-toolbar="'.$toolbar.'" data-toolbar-fixed="'.$toolbar_fixed.'"></div>';
		
		if (!is_null($content)) {
			$returnhtml .= do_shortcode($content);
		}
		return $returnhtml;
	}
	
	protected function extra_shortcode_attrs($atts, $content=null) {
		return '';
	}
	
	// ADMIN OPTIONS
	// *************
	
	protected function get_options_menuname() {
		return 'pdfemb_list_options';
	}
	
	protected function get_options_pagename() {
		return 'pdfemb_options';
	}
	
	protected function get_settings_url() {
		return is_multisite()
		? network_admin_url( 'settings.php?page='.$this->get_options_menuname() )
		: admin_url( 'options-general.php?page='.$this->get_options_menuname() );
	}
	
	public function pdfemb_admin_menu() {
		if (is_multisite()) {
			add_submenu_page( 'settings.php', 'PDF Embedder settings', 'PDF Embedder',
			'manage_network_options', $this->get_options_menuname(),
			array($this, 'pdfemb_options_do_page'));
		}
		else {
			add_options_page( 'PDF Embedder settings', 'PDF Embedder',
			'manage_options', $this->get_options_menuname(),
			array($this, 'pdfemb_options_do_page'));
		}
	}
	
	public function pdfemb_options_do_page() {

        wp_enqueue_script( 'pdfemb_admin_js', $this->my_plugin_url().'js/admin/pdfemb-admin.js', array('jquery') );
        wp_enqueue_style( 'pdfemb_admin_css', $this->my_plugin_url().'css/pdfemb-admin.css' );

        $submit_page = is_multisite() ? 'edit.php?action='.$this->get_options_menuname() : 'options.php';
	
		if (is_multisite()) {
			$this->pdfemb_options_do_network_errors();
		}
		?>
			  
		<div>
		
    		<h2>PDF Embedder setup</h2>

            <p>To use the plugin, just embed PDFs in the same way as you would normally embed images in your posts/pages - but try with a PDF file instead.</p>
            <p>From the post editor, click Add Media, and then drag-and-drop your PDF file into the media library.
                When you insert the PDF into your post, it will automatically embed using the plugin's viewer.</p>


            <div id="pdfemb-tablewrapper">

            <div id="pdfemb-tableleft" class="pdfemb-tablecell">

                <h2 id="pdfemb-tabs" class="nav-tab-wrapper">
                    <a href="#main" id="main-tab" class="nav-tab nav-tab-active">Main Settings</a>
                    <a href="#mobile" id="mobile-tab" class="nav-tab">Mobile</a>
                    <a href="#secure" id="secure-tab" class="nav-tab">Secure</a>
                    <?php $this->draw_more_tabs(); ?>
                </h2>

                <form action="<?php echo $submit_page; ?>" method="post" id="pdfemb_form" enctype="multipart/form-data" >

        <?php

        echo '<div id="main-section" class="pdfembtab active">';
        $this->pdfemb_mainsection_text();
        echo '</div>';

        echo '<div id="mobile-section" class="pdfembtab">';
        $this->pdfemb_mobilesection_text();
        echo '</div>';

        echo '<div id="secure-section" class="pdfembtab">';
        $this->pdfemb_securesection_text();
        echo '</div>';

        $this->draw_extra_sections();

        settings_fields($this->get_options_pagename());
		
		?>

                    <p class="submit">
                        <input type="submit" value="Save Changes" class="button button-primary" id="submit" name="submit">
                    </p>
				
                </form>
            </div>

        </div>
		
		</div>  <?php
	}

    protected function draw_more_tabs() {
    }

    protected function draw_extra_sections() {
    }

    // Override elsewhere
	protected function pdfemb_mainsection_text() {
        $options = $this->get_option_pdfemb();
		?>


        <h2>Default Viewer Settings</h2>

        <label for="input_pdfemb_width" class="textinput">Width</label>
        <input id='input_pdfemb_width' class='textinput' name='<?php echo $this->get_options_name(); ?>[pdfemb_width]' size='10' type='text' value='<?php echo esc_attr($options['pdfemb_width']); ?>' />
		<br class="clear"/>

        <label for="input_pdfemb_height" class="textinput">Height</label>
        <input id='input_pdfemb_height' class='textinput' name='<?php echo $this->get_options_name(); ?>[pdfemb_height]' size='10' type='text' value='<?php echo esc_attr($options['pdfemb_height']); ?>' />
        <br class="clear"/>

        <p class="desc big"><i>Enter <b>max</b> or an integer number of pixels</i></p>

        <br class="clear"/>

        <label for="pdfemb_toolbar" class="textinput">Toolbar Location</label>
        <select name='<?php echo $this->get_options_name(); ?>[pdfemb_toolbar]' id='pdfemb_toolbar' class='select'>
            <option value="top" <?php echo $options['pdfemb_toolbar'] == 'top' ? 'selected' : ''; ?>>Top</option>
            <option value="bottom" <?php echo $options['pdfemb_toolbar'] == 'bottom' ? 'selected' : ''; ?>>Bottom</option>
            <option value="both" <?php echo $options['pdfemb_toolbar'] == 'both' ? 'selected' : ''; ?>>Both</option>
        </select>
        <br class="clear" />
        <br class="clear" />

        <label for="pdfemb_toolbarfixed" class="textinput">Toolbar Hover</label>
        <span>
        <input type="radio" name='<?php echo $this->get_options_name(); ?>[pdfemb_toolbarfixed]' id='pdfemb_toolbarfixed_off' class='radio' value="off" <?php echo $options['pdfemb_toolbarfixed'] == 'off' ? 'checked' : ''; ?> />
        <label for="pdfemb_toolbarfixed_off" class="radio">Toolbar appears only on hover over document</label>
        </span>
        <br/>
        <span>
        <input type="radio" name='<?php echo $this->get_options_name(); ?>[pdfemb_toolbarfixed]' id='pdfemb_toolbarfixed_on' class='radio' value="on" <?php echo $options['pdfemb_toolbarfixed'] == 'on' ? 'checked' : ''; ?> />
        <label for="pdfemb_toolbarfixed_on" class="radio">Toolbar always visible</label>
        </span>

		<?php
            $this->pdfemb_mainsection_extra();
        ?>

        <br class="clear" />
        <br class="clear" />



        <p>You can override these defaults for specific embeds by modifying the shortcodes - see <a href="<?php echo $this->get_instructions_url(); ?>" target="_blank">instructions</a>.</p>

        <?php
	}

	protected function pdfemb_mainsection_extra() {
        // Override in Basic and Commercial
	}

    protected function get_instructions_url() {
        return 'http://wp-pdf.com/free-instructions/?utm_source=PDF%20Settings%20Main&utm_medium=freemium&utm_campaign=Freemium';
    }

    protected function pdfemb_mobilesection_text() {
    }

    protected function pdfemb_securesection_text()
    {
        ?>

        <h2>Protect your PDFs using PDF Embedder Secure</h2>
        <p>Our <b>PDF Embedder Premium Secure</b> plugin provides the same simple but elegant viewer for your website visitors, with the added protection that
            it is difficult for users to download or print the original PDF document.</p>

        <p>This means that your PDF is unlikely to be shared outside your site where you have no control over who views, prints, or shares it.</p>

        <p>See our website <a href="http://wp-pdf.com/secure/?utm_source=PDF%20Settings%20Secure&utm_medium=freemium&utm_campaign=Freemium">wp-pdf.com</a> for more
            details and purchase options.
        </p>

        <?php
    }

	public function pdfemb_options_validate($input) {
		$newinput = Array();

        $newinput['pdfemb_width'] = isset($input['pdfemb_width']) ? trim(strtolower($input['pdfemb_width'])) : 'max';
        if (!is_numeric($newinput['pdfemb_width']) && $newinput['pdfemb_width']!='max' && $newinput['pdfemb_width']!='auto') {
            add_settings_error(
                'pdfemb_width',
                'widtherror',
                self::get_error_string('pdfemb_width|widtherror'),
                'error'
            );
        }

        $newinput['pdfemb_height'] = isset($input['pdfemb_height']) ? trim(strtolower($input['pdfemb_height'])) : 'max';
        if (!is_numeric($newinput['pdfemb_height']) && $newinput['pdfemb_height']!='max' && $newinput['pdfemb_height']!='auto') {
            add_settings_error(
                'pdfemb_height',
                'heighterror',
                self::get_error_string('pdfemb_height|heighterror'),
                'error'
            );
        }

        if (isset($input['pdfemb_toolbar']) && in_array($input['pdfemb_toolbar'], array('top', 'bottom', 'both'))) {
            $newinput['pdfemb_toolbar'] = $input['pdfemb_toolbar'];
        }
        else {
            $newinput['pdfemb_toolbar'] = 'bottom';
        }

        if (isset($input['pdfemb_toolbarfixed']) && in_array($input['pdfemb_toolbarfixed'], array('on', 'off'))) {
            $newinput['pdfemb_toolbarfixed'] = $input['pdfemb_toolbarfixed'];
        }

        $newinput['pdfemb_version'] = $this->PLUGIN_VERSION;
		return $newinput;
	}
	
	protected function get_error_string($fielderror) {
        $local_error_strings = Array(
            'pdfemb_width|widtherror' => 'Width must be "max" or an integer (number of pixels)',
            'pdfemb_height|heighterror' => 'Height must be "max" or an integer (number of pixels)'
        );
        if (isset($local_error_strings[$fielderror])) {
            return $local_error_strings[$fielderror];
        }

		return 'Unspecified error';
	}

	public function pdfemb_save_network_options() {
		check_admin_referer( $this->get_options_pagename().'-options' );
	
		if (isset($_POST[$this->get_options_name()]) && is_array($_POST[$this->get_options_name()])) {
			$inoptions = $_POST[$this->get_options_name()];
			
			$outoptions = $this->pdfemb_options_validate($inoptions);
			
			$error_code = Array();
			$error_setting = Array();
			foreach (get_settings_errors() as $e) {
				if (is_array($e) && isset($e['code']) && isset($e['setting'])) {
					$error_code[] = $e['code'];
					$error_setting[] = $e['setting'];
				}
			}
	
			update_site_option($this->get_options_name(), $outoptions);
				
			// redirect to settings page in network
			wp_redirect(
			add_query_arg(
			array( 'page' => $this->get_options_menuname(),
			'updated' => true,
			'error_setting' => $error_setting,
			'error_code' => $error_code ),
			network_admin_url( 'admin.php' )
			)
			);
			exit;
		}
	}
	
	protected function pdfemb_options_do_network_errors() {
		if (isset($_REQUEST['updated']) && $_REQUEST['updated']) {
			?>
					<div id="setting-error-settings_updated" class="updated settings-error">
					<p>
					<strong>Settings saved</strong>
					</p>
					</div>
				<?php
			}
	
			if (isset($_REQUEST['error_setting']) && is_array($_REQUEST['error_setting'])
				&& isset($_REQUEST['error_code']) && is_array($_REQUEST['error_code'])) {
				$error_code = $_REQUEST['error_code'];
				$error_setting = $_REQUEST['error_setting'];
				if (count($error_code) > 0 && count($error_code) == count($error_setting)) {
					for ($i=0; $i<count($error_code) ; ++$i) {
						?>
					<div id="setting-error-settings_<?php echo $i; ?>" class="error settings-error">
					<p>
					<strong><?php echo htmlentities2($this->get_error_string($error_setting[$i].'|'.$error_code[$i])); ?></strong>
					</p>
					</div>
						<?php
				}
			}
		}
	}
	
	// OPTIONS

    protected function get_options_name() {
        return 'pdfemb';
    }

	protected function get_default_options() {
		return Array(
            'pdfemb_width' => 'max',
            'pdfemb_height' => 'max',
            'pdfemb_toolbar' => 'bottom',
            'pdfemb_toolbarfixed' => 'off',
            'pdfemb_version' => $this->PLUGIN_VERSION
        );
	}
	
	protected $pdfemb_options = null;
	protected function get_option_pdfemb() {
		if ($this->pdfemb_options != null) {
			return $this->pdfemb_options;
		}
	
		$option = get_site_option($this->get_options_name(), Array());
	
		$default_options = $this->get_default_options();
		foreach ($default_options as $k => $v) {
			if (!isset($option[$k])) {
				$option[$k] = $v;
			}
		}
	
		$this->pdfemb_options = $option;
		return $this->pdfemb_options;
	}

	protected function save_option_pdfemb($option) {
		update_site_option($this->get_options_name(), $option);
		$this->pdfemb_options = $option;
	}

	// ADMIN
	
	public function pdfemb_admin_init() {
		// Add PDF as a supported upload type to Media Gallery
		add_filter( 'upload_mimes', array($this, 'pdfemb_upload_mimes') );
		
		// Filter for PDFs in Media Gallery
		add_filter( 'post_mime_types', array($this, 'pdfemb_post_mime_types') );

		// Embed PDF shortcode instead of link
		add_filter( 'media_send_to_editor', array($this, 'pdfemb_media_send_to_editor'), 20, 3 );
		
		register_setting( $this->get_options_pagename(), $this->get_options_name(), Array($this, 'pdfemb_options_validate') );
	}
	
	// Override in Premium
	public function pdfemb_init() {
	}

    public function pdfemb_plugin_action_links( $links, $file ) {
        if ($file == $this->my_plugin_basename()) {
            $links = $this->extra_plugin_action_links($links);

            $settings_link = '<a href="' . $this->get_settings_url() . '">Settings</a>';
            array_unshift($links, $settings_link);
        }

        return $links;
    }

    protected function extra_plugin_action_links( $links ) {
        return $links;
    }
	
	protected function add_actions() {

		add_action( 'init', array($this, 'pdfemb_init') );
		
		add_action( 'wp_enqueue_scripts', array($this, 'pdfemb_wp_enqueue_scripts'), 5, 0 );
		add_shortcode( 'pdf-embedder', Array($this, 'pdfemb_shortcode_display_pdf') );
		
		// When viewing attachment page, embded document instead of link
		// add_filter( 'wp_get_attachment_link', array($this, 'pdfemb_wp_get_attachment_link'), 20, 6 );
		
		if (is_admin()) {
			add_action( 'admin_init', array($this, 'pdfemb_admin_init'), 5, 0 );
			
			add_action(is_multisite() ? 'network_admin_menu' : 'admin_menu', array($this, 'pdfemb_admin_menu'));
			
			if (is_multisite()) {
				add_action('network_admin_edit_'.$this->get_options_menuname(), array($this, 'pdfemb_save_network_options'));
			}

            add_filter(is_multisite() ? 'network_admin_plugin_action_links' : 'plugin_action_links', array($this, 'pdfemb_plugin_action_links'), 10, 2 );
		}
	}

}


?>