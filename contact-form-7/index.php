<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class PDF_Creator_Contact_Form_7_Backend {
	private $link_pdfs = array();
	private $email_pdfs = array();
	private $uploaded_files = array();
	private $check_run = false;
	function __construct(){
		add_action("yeepdf_head_settings",array($this,"add_head_settings"));
		add_action("save_post_yeepdf",array( $this, 'save_metabox' ), 10, 2 );
		add_filter("yeepdf_shortcodes",array($this,"add_shortcode"));
		add_filter("wpcf7_mail_components",array($this,"notification_attachments"),10,3);
		add_filter("wpcf7_editor_panels",array($this,"custom_form"));
		add_filter("save_post",array($this,"notification_save"));
		add_action('wpcf7_before_send_mail', array($this,"before_cf7_send_mail"));
		add_filter("flamingo_add_inbound",array($this,"flamingo_add_inbound"));
		add_filter("superaddons_pdf_check_pro",array($this,"check_pro"));
		add_shortcode("pdf_download",array($this,"pdf_contact_form_7_link"));
		add_action('admin_enqueue_scripts', array($this,"settings_js"));
		add_filter("yeepdf_add_libs",array($this,"yeepdf_add_libs"));
	}
	function settings_js(){
		if(isset($_GET["page"]) && $_GET["page"] == "wpcf7"){
			wp_enqueue_script('yeepdf_contact_form_7', BUIDER_PDF_CF7_PLUGIN_URL. 'contact-form-7/yeepdf-contact-form-7.js',array("jquery"));
			$pro ="free";
			if (get_option( '_redmuber_item_1534') == "ok") { 
				$pro ="pro";
			}
			wp_localize_script("yeepdf_contact_form_7","yeepdf_contact_form_7",array("pro"=>$pro));
		}
	}
	function yeepdf_add_libs($add){
		if(isset($_GET["page"]) && $_GET["page"] == "wpcf7"){
			$add = true;
		}
		return $add;
	}
	function check_pro($pro){
		$check = get_option( '_redmuber_item_1534');
		if($check == "ok"){
			$pro = true;
		}
		return $pro;
	}
	function flamingo_add_inbound($args){
		$data_links = $this->link_pdfs;
		if(is_array($data_links) && count($data_links) > 0 ){
			$args["meta"]["pdf_links"] = implode(", ", $data_links);
		}
		$this->check_run = true;
		return $args;
	}
	function notification_attachments(  $components,$current , $form  ) {
		$type = $form->get_current_template_name();
		$upload_dir = wp_upload_dir();
		$data_links = $this->link_pdfs;
		$email_pdfs = $this->email_pdfs;
		$contact_fotm_id = $current->id();
		$id_notification = $form->name();
		$notifications_send = array();
		$rand = rand(100,999999);
		$path_main = $upload_dir['basedir'] . '/pdfs/contact_form_7_upload/';
		if( $this->check_run == false ) {
			$settings = get_post_meta( $contact_fotm_id,'_yeepdf_datas',true);
			if( isset($settings["enable"]) && $settings["enable"] == 1){
				$yeepdf_logic = get_post_meta( $contact_fotm_id,'_yeepdf_logic',true);
				$paths= $this->create_pdf_link($settings,$form,$yeepdf_logic);
				if($paths){
					$data_links[]= $paths["url"];
					$notifications = $settings["notifications"];
					if(is_array($notifications) ){
						foreach($notifications as $noty){
							if(isset($settings["save_type"]) && $settings["save_type"] == "jpg" && extension_loaded('imagick')){
								$imagick = new Imagick();
								$imagick->readImage($paths["path"]."[0]");
								$imagick->writeImages($path_main.$rand.'upload.jpg', false);
								$email_pdfs[$noty][] = $path_main.$rand.'upload.jpg';	
							}else{
								$email_pdfs[$noty][] = $paths["path"];	
							}
						}
					}
				}	
			}
			for ($i = 2; $i <= 5; $i++) {
				$settings1 = get_post_meta( $contact_fotm_id,'_yeepdf_datas_'.$i,true);
				if( isset($settings1["enable"]) && $settings1["enable"] == 1){
					$paths= $this->create_pdf_link($settings1,$form);
					$data_links[] = $paths["url"];
					$notifications = $settings1["notifications"];
					if(is_array($notifications) ){
						foreach($notifications as $noty){
							$email_pdfs[$noty][] = $paths["path"];	
						}
					}
				}
			}
			if(is_array($email_pdfs) && isset( $email_pdfs[$id_notification])){
				$current_components = $components['attachments'];
				if(is_array($current_components)){
					$components['attachments'] = array_merge($email_pdfs[$id_notification],$current_components);
				}else{
					$components['attachments'] = $email_pdfs[$id_notification];
				}
			}
			//str_replace notification_attachments [pdf_download]
			if(is_array($data_links)) {
				$components_body = $components["body"];
				$pdf_download = implode(", ",$data_links);
				$bodytag = str_replace("[pdf_download]", $pdf_download, $components_body);
				$components["body"] = $bodytag;
			}
			$this->check_run = true;
			$this->link_pdfs = $data_links;
			$this->email_pdfs = $email_pdfs;
			update_option( "_cf7_pdfs_link", $data_links );
		}else{
			if(is_array($email_pdfs) && isset( $email_pdfs[$id_notification])){
				$current_components = $components['attachments'];
				if(is_array($current_components)){
					$components['attachments'] = array_merge($email_pdfs[$id_notification],$current_components);
				}else{
					$components['attachments'] = $email_pdfs[$id_notification];
				}
			}
			//str_replace notification_attachments [pdf_download]
			if(is_array($data_links)) {
				$components_body = $components["body"];
				$pdf_download = implode(", ",$data_links);
				$bodytag = str_replace("[pdf_download]", $pdf_download, $components_body);
				$components["body"] = $bodytag;
			}
		}
        return $components;
	}
	function pdf_contact_form_7_link($atts){
		$datas = get_option( "_cf7_pdfs_link", array() );
		$text ="";
		foreach($datas as $link){
			$text .= '<a href="'.$link.'" download >'.$link.'</a> ';
		}
		return $text;
	}
	function create_pdf_link($settings,$form,$yeepdf_logic=""){
		$upload_dir = wp_upload_dir();
		$uploaded_files = $this->uploaded_files;
		$name = $settings["filename"];
		$password = $settings["password"];
		$template_id = $settings["template_id"];
		$form_data = array();
		$form = WPCF7_Submission::get_instance();
		$form_title = $form->get_contact_form()->title;
		$form_id = $form->get_contact_form()->id;
		$all_field = '<table border="0" cellpadding="0" cellspacing="0" width="100%">';
		$style = 'padding-top: 25px;padding-bottom: 25px;border-top: 1px solid #e2e2e2;min-width: 113px;padding-right: 10px;line-height: 22px;';
		$style_first = 'padding-top: 25px;padding-bottom: 25px;min-width: 113px;padding-right: 10px;line-height: 22px;';
		$i = 0;
		foreach( $form->get_posted_data() as $n=> $value){
			$form_data["[".$n."]"] = $value;
			if($i == 0){
				$all_field .= '<tr>
				<td style="'.$style_first.'"><strong>'.$n.'</strong></td>
				<td style="'.$style_first.'">'.$value.'</td>
				</tr>';
			}else{
				$all_field .= '<tr>
				<td style="'.$style.'"><strong>'.$n.'</strong></td>
				<td style="'.$style.'">'.$value.'</td>
				</tr>';
			}
			$i++;
		}
		$uploaded_files_new = array();
		foreach( $uploaded_files as $key =>$value ){
    		$form_data["[".$key."]"] = $value;
    		$uploaded_files_new["[".$key."]"] = $value;
			$all_field .= '<tr>
				<td style="'.$style.'"><strong>'.$key.'</strong></td>
				<td style="'.$style.'">'.$value.'</td>
				</tr>';
    	}
		$form_data["[form_name]"] = $form_title;
		$form_data["[form_id]"] = $form_id;
		$all_field .="</table>";
    	$show = true;
		if( isset($settings["conditional_logic"]) && $settings["conditional_logic"] == 1 ){
			$show = Yeepdf_Create_PDF::is_logic(json_encode($yeepdf_logic),$form_data);
		}
		if(!$show){
			return false;
		}
    	if( $password != ""){
    		$password = wpcf7_mail_replace_tags($password);
    	}
		if( $name == ""){
    		$name= "contact-form";
    	}else{
    		$name = wpcf7_mail_replace_tags($name);
			$name = do_shortcode($name);
    	} 
		if(isset($settings["random_key"]) && $settings["random_key"] == "yes"){
			$name = sanitize_title($name)."-".rand(1000,9999);
		}
    	$data_send_settings = array(
    		"id_template"=> $template_id,
    		"type"=> "html",
    		"name"=> $name,
    		"datas" =>$form_data,
    		"return_html" =>true,
    	);
    	$content =Yeepdf_Create_PDF::pdf_creator_preview($data_send_settings);
    	$content = str_replace(array("[all-fields]","[form_name]","[form_id]"),array($all_field,$form_title,$form_id),$content);
    	$content = str_replace(array_keys($uploaded_files_new),($uploaded_files),$content);
    	$content = wpcf7_mail_replace_tags($content);
    	$data_send_settings_download = array(
    		"id_template"=> $template_id,
    		"type"=> "upload",
    		"name"=> $name,
    		"datas" =>$form_data,
    		"html" =>$content,
    		"password" =>$password,
    	);
    	$path =Yeepdf_Create_PDF::pdf_creator_preview($data_send_settings_download);
    	$link_path = $upload_dir["baseurl"]."/pdfs/".$name.".pdf";
    	return array("url"=>$link_path,"path"=>$path); 
	}
	function upload_files($filename,$name =""){
	    $filetype = wp_check_filetype(basename($filename), null);
	    $wp_upload_dir = wp_upload_dir();
	    $path_main = $wp_upload_dir['basedir'] . '/pdfs/contact_form_7_upload/';
		if ( ! file_exists( $path_main ) ) {
			wp_mkdir_p( $path_main );
		}
        $rand_name =  rand(1000,9999)."_" . basename($filename);
	    $attachFileName = $path_main . $rand_name;
	    $attach_data = copy($filename, $attachFileName);
		$this->uploaded_files[$name] = $wp_upload_dir['baseurl'] . '/pdfs/contact_form_7_upload/'. $rand_name;
	    return $attach_data;
	}
	function before_cf7_send_mail(\WPCF7_ContactForm $contactForm){
	    $submission = WPCF7_Submission::get_instance();
	    if ($submission) {
	        $uploaded_files = $submission->uploaded_files();
	        if ($uploaded_files) {
	            foreach ($uploaded_files as $fieldName => $filepath) {
	                //cf7 5.4
	                if (is_array($filepath)) {
	                    foreach ($filepath as $key => $value) {
	                        $data = $this->upload_files($value,$fieldName);
	                    }
	                } else {
	                    $data =  $this->upload_files($filepath,$fieldName);
	                }
	            }
	        }
	    }
	}
	function custom_form($panels){
        $panels["form-panel-paypal-setting"] = array(
                'title' => esc_html__( 'PDF', "pdf-for-wpforms" ),
                'callback' => array($this,"add_settings" ));
        return $panels;
    }
	function add_head_settings($post){
		global $wpdb;
        $post_id= $post->ID;
        $data = get_post_meta( $post_id,'_yeepdf_contact_form_7',true);
        ?>
        <div class="yeepdf-testting-order">
            <select name="yeepdf_contact_form_7" class="builder_pdf_woo_testing">
			<option value='-1'>--- <?php esc_html_e("Contact Form 7","pdf-for-wpforms") ?> ---</option>
                <?php
				$forms = new WP_Query( array("post_type"=>"wpcf7_contact_form","posts_per_page"=>-1) );
				if ( $forms->have_posts() ){
					while ( $forms->have_posts() ) : 
						$forms->the_post();
						$form_id = get_the_id();
						$form_title = get_the_title();
						?>
							<option <?php selected($data,$form_id) ?> value="<?php echo esc_attr($form_id) ?>"><?php echo esc_html($form_title) ?></option>
						<?php
					endwhile;
				}else{
					printf( "<option value='0'>%s</option>",esc_html__("No Form","pdf-for-wpforms"));
				}
				wp_reset_postdata();
                ?>
            </select>
        </div>
        <?php
    }
    function save_metabox($post_id, $post){
        if( isset($_POST['yeepdf_contact_form_7'])) {
            $id = sanitize_text_field($_POST['yeepdf_contact_form_7']);
            update_post_meta($post_id,'_yeepdf_contact_form_7',$id);
        }
    }
	function add_shortcode($shortcode) {
		$inner_shortcode = array(
			"form_name" => "Form Name",
			"form_id"=>"Form ID",
			"all_fields"=>"All Fields",
		);
		if( isset($_GET["post"]) ){
			if(isset($_GET["page"]) && $_GET["page"] == "wpcf7") {
				$form_id = sanitize_text_field($_GET['post']);
			}else{
				$post_id = sanitize_text_field($_GET['post']);
				$form_id = get_post_meta( $post_id,'_yeepdf_contact_form_7',true);	
			}
			$fields = array();
			if($form_id && $form_id>0){
				$ContactForm = WPCF7_ContactForm::get_instance( $form_id );
				$tags = $ContactForm->scan_form_tags();
				foreach ($tags as $tag_inner):
				    if ($tag_inner['type'] == 'group' || $tag_inner['name'] == '') continue;
				    $inner_shortcode[$tag_inner['name']] = "[".$tag_inner['name']."]";
				endforeach;           
			}
		}
		$shortcode["Contact Form 7"] = $inner_shortcode;   
		return $shortcode;
	}
	function add_settings($post){
		$templates = array();
		$post_id = $post->id();
		$settings = get_post_meta( $post_id,'_yeepdf_datas',true);
		$template_upload = get_post_meta( $post_id,'_yeepdf_datas_template_upload',true);
		$template_upload1 = get_post_meta( $post_id,'_yeepdf_datas_template_upload_2',true);
		if(!$settings){
			$settings = array(
				"enable"=>"",
				"random_key"=>"yes",
				"notifications"=>array("mail"),
				"filename"=>'',
				"password"=>'',
				"conditional_logic"=>'',
				"conditional_logic_datas"=>'',
			);
		}
		if( !isset($settings["enable"])) {
			$settings["enable"] = "";
		}
	    ?>
	    <p>
			<div class="gform-settings-description gform-kitchen-sink">
				<input data-tab=".contact-form-editor-box-pdf-container" class="yeepdf_data_enable" <?php checked($settings["enable"],1) ?> type="checkbox" name="yeepdf_datas[enable]" value="1"> <?php esc_html_e("Enable PDF","pdf-for-wpforms") ?>
			</div>
		</p>
		<div class="contact-form-editor-box-pdf-container <?php echo esc_attr( ($settings["enable"]==1)?"pdf-show":"hidden"  ) ?>">
			<div class="contact-form-editor-box-mail">
	        	<?php $this->settings_html($settings,$post_id) ?>
            </div>
			<?php 
			for ($i = 2; $i <= 5; $i++) {
				$settings1 = get_post_meta( $post_id,'_yeepdf_datas_'.$i,true);
				$this->settings_box($i,$settings1,$post_id);
			}
			?>
		</div>
		<?php
	}
	function settings_box($tab,$settings1,$post_id){
		?>
		<style>
			#contact-form-editor .form-table-200 th{
				width: 200px;
			}
		</style>
		<div class="contact-form-editor-box-mail">
			<h2><?php esc_html_e("PDF","pdf-for-wpforms") ?> (<?php echo esc_attr($tab) ?>)</h2>
			<div class="gform-settings-description gform-kitchen-sink">
				<?php if( isset($settings1["enable"]) && $settings1["enable"] ==1){
					$checked = 'checked="checked"';
				}else{
					$checked ="";
				} ?>
				<input data-tab=".contact-form-editor-box-pdf-<?php echo esc_attr($tab) ?>" class="yeepdf_data_enable" <?php echo esc_attr($checked) ?> type="checkbox" name="yeepdf_datas_<?php echo esc_attr($tab) ?>[enable]" value="1"> <?php esc_html_e("Enable PDF","pdf-for-wpforms") ?>
			</div>
			<div class="contact-form-editor-box-pdf-<?php echo esc_attr($tab) ?> <?php echo esc_attr( ($checked == "")?"hidden":"pdf-show"  ) ?>">
					<?php 
					if($settings1 == ""){
					$settings1 = array(
						"enable"=>"",
						"random_key"=>"yes",
						"template_id"=>'',
						"notifications"=>array("mail"),
						"filename"=>'',
						"password"=>'',
						"conditional_logic"=>'',
						"conditional_logic_datas"=>'',
					);
					}
					$this->settings_html($settings1,$post_id,"_".esc_attr($tab));
					?>
			</div>
			</div>
		<?php
	}
	function settings_html($settings,$post_id,$key=""){
		$pro = Yeepdf_Settings_Builder_PDF_Backend::check_pro();
		?>
		<table class="form-table form-table-200">
			<tr>
				<th scope="row"><label for="blogname"><?php esc_html_e("PDF Template","pdf-for-wpforms") ?></label></th>
				<td>
					<select name="yeepdf_datas<?php echo esc_attr($key) ?>[template_id]" >
					<?php 
						$templates = array();
						$template_id = "-1";
						if($settings["template_id"]){
							$template_id = $settings["template_id"];
						}
						$pdf_templates = get_posts(array( 'post_type' => 'yeepdf','post_status' => 'publish','numberposts'=>-1 ) );
						if($pdf_templates){
							foreach ( $pdf_templates as $post ) {
								$post_id_template = $post->ID;
								?>
								<option <?php selected($template_id,$post_id_template) ?> value="<?php echo esc_attr($post_id_template) ?>"><?php echo esc_html($post->post_title) ?></option>
								<?php
							}
						}else{
							?>
							<option value="-1"><?php esc_html_e("No template","pdf-for-wpforms") ?></option>
							<?php
						}
					?>	
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="blogname"><?php esc_html_e("Notifications Attachment","pdf-for-wpforms") ?></label></th>
				<td>
					<?php
						$checked1="";
						$checked2="";
						if(isset($settings["notifications"]) && is_array($settings["notifications"]) && in_array("mail",$settings["notifications"])){
							$checked1 = 'checked="checked"';
						}
						if(isset($settings["notifications"]) &&  is_array($settings["notifications"]) && in_array("mail_2",$settings["notifications"])){
							$checked2 = 'checked="checked"';
						}
						?>
					<input <?php echo esc_attr($checked1) ?> type="checkbox" class="regular-text " name="yeepdf_datas<?php echo esc_attr($key) ?>[notifications][]" value="mail"> <?php esc_html_e("Mail","pdf-for-wpforms") ?>
					<input <?php echo esc_attr($checked2) ?> type="checkbox" class="regular-text " name="yeepdf_datas<?php echo esc_attr($key) ?>[notifications][]" value="mail_2"> <?php esc_html_e("Mail (1)","pdf-for-wpforms") ?>
					<p class="description"><?php esc_html_e("Send the PDF as an email attachment for the selected notifications.","pdf-for-wpforms") ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="blogname"><?php esc_html_e("Filename","pdf-for-wpforms") ?></label></th>
				<td>
					<?php 
					Yeepdf_Settings_Main::add_number_seletor("yeepdf_datas".$key."[filename]",$settings["filename"]);
					?>
					<p class="description"><?php esc_html_e("Set the filename for the generated PDF","pdf-for-wpforms") ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="blogname"><?php esc_html_e("Add ID Random File Name","pdf-for-wpforms") ?></label></th>
				<td>
					<?php
					$checked_key="";
					if(isset($settings["random_key"]) && $settings["random_key"] == "yes"){
						$checked_key = 'checked="checked"';
					}
					?>
					<input <?php echo esc_attr($checked_key) ?> type="checkbox" class="regular-text " name="yeepdf_datas<?php echo esc_attr($key) ?>[random_key]" value="yes"> <?php esc_html_e("Enable","pdf-for-wpforms") ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="blogname"><?php esc_html_e("Password","pdf-for-wpforms") ?></label></th>
				<td>
					<?php 
					if($pro){
						$password="";
						if(isset($settings["password"])){
							$password = $settings["password"];
						}
						Yeepdf_Settings_Main::add_number_seletor("yeepdf_datas".$key."[password]",$password);
					}else{
						Yeepdf_Settings_Main::add_number_seletor("pro","Upgrade to pro version","pro_disable","readonly");					
					} ?>
					<p class="description"><?php esc_html_e("You have the option to password-protect your PDF documents","pdf-for-wpforms") ?></p>
				</td>
			</tr>
			<?php if($key ==""){ ?>
				<tr>
					<th scope="row"><label for="blogname"><?php esc_html_e("Conditional Logic","pdf-for-wpforms") ?></label></th>
					<td>
						<?php
						$check_logic ='';
						$class_logic_container = "hidden";
						if(isset($settings["conditional_logic"]) && $settings["conditional_logic"] == 1){
							$check_logic = 'checked="checked"';
							$class_logic_container="";
						}
						if($pro){
						?>
						<input <?php echo esc_attr($check_logic) ?> value="1"  type="checkbox" name="yeepdf_datas[conditional_logic]" id="pdf_creator_conditional_logic"> <?php esc_html_e(" Enable conditional logic","pdf-for-wpforms") ?>
						<?php }else{
						?>
						<p class="pro_disable"><input  type="checkbox"  disabled> <?php esc_html_e(" Enable conditional logic (Upgrade to pro version)","pdf-for-wpforms") ?> </p>
						<?php
						}
						?>
						</div>
						<p class="description"><?php esc_html_e("Add rules to dynamically enable or disable the PDF. When disabled, PDFs do not show up in the admin area, cannot be viewed, and will not be attached to notifications.","pdf-for-wpforms") ?></p>
						<?php 
						$conditional = get_post_meta( $post_id,'_yeepdf_logic',true);
						Yeepdf_Settings_Main::get_conditional_logic($conditional,$class_logic_container);
						?>
					</td>
				</tr>
			<?php 
				} 
				if (extension_loaded('imagick')){
			?>
			<tr>
				<th scope="row"><label for="blogname"><?php esc_html_e("Save Type","pdf-for-wpforms") ?></label></th>
				<td>
					<?php
					$save_type="pdf";
					if(isset($settings["save_type"])){
						$save_type = $settings["save_type"];
					}
					?>
					<select name="yeepdf_datas<?php echo esc_attr($key) ?>[save_type]" class="regular-text">
							<option value="pdf">PDF</option>
							<option <?php selected( "jpg", $save_type) ?> value="jpg">JPG</option>
					</select>
				</td>
			</tr>
			<?php }else{
				?>
				<tr>
				<th scope="row"><label for="blogname"><?php esc_html_e("Save Image Type","pdf-for-wpforms") ?></label></th>
				<td>
					<p>Install imagick: <a href="https://www.php.net/manual/en/imagick.setup.php">https://www.php.net/manual/en/imagick.setup.php</a></p>
				</td>
			</tr>
				<?php
			} ?>	
		</table>
		<hr>
        <?php
	}
	function notification_save($post_id){
		if( isset( $_POST["yeepdf_datas"] )) {
			$yeepdf_logic = map_deep( $_POST["yeepdf_logic"], 'sanitize_text_field' );
			$yeepdf_datas = map_deep( $_POST["yeepdf_datas"], 'sanitize_text_field' );
			add_post_meta($post_id, '_yeepdf_datas', $yeepdf_datas,true) or update_post_meta($post_id, '_yeepdf_datas', $yeepdf_datas);
			for ($i = 2; $i <= 5; $i++) {
				$yeepdf_datas = map_deep( $_POST["yeepdf_datas_".$i], 'sanitize_text_field' );
				add_post_meta($post_id, '_yeepdf_datas_'.$i, $yeepdf_datas,true) or update_post_meta($post_id, '_yeepdf_datas_'.$i, $yeepdf_datas);
			}
			add_post_meta($post_id, '_yeepdf_logic', $yeepdf_logic,true) or update_post_meta($post_id, '_yeepdf_logic', $yeepdf_logic);
		}
    }	
}
new PDF_Creator_Contact_Form_7_Backend;