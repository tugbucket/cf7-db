<?php
/*
Plugin Name: CF7 Database
Description: Save Contact From 7 Submission into database and allows you to export saved data as CSV, Excel Spreadsheet and ...
Contributors: amirhosseinhpv, peprodev
Tags: contact form 7, contact form 7 database, cf7 database
Author: Pepro Dev. Group
Developer: Amirhosseinhpv
Author URI: https://pepro.dev/
Developer URI: https://hpv.im/
Plugin URI: https://pepro.dev/cf7-database/
Version: 1.0.0
Stable tag: 1.0.0
Requires at least: 5.0
Tested up to: 5.4
Requires PHP: 5.6
WC requires at least: 4.0
WC tested up to: 4.2.0
Text Domain: cf7db
Domain Path: /languages
Copyright: (c) 2020 Pepro Dev. Group, All rights reserved.
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
defined("ABSPATH") or die("CF7 Database :: Unauthorized Access!");

if (!class_exists("cf7Database")) {
    class cf7Database
    {
        private static $_instance = null;
        public $td;
        public $url;
        public $version;
        public $title;
        public $title_w;
        public $db_slug;
        private $plugin_dir;
        private $plugin_url;
        private $assets_url;
        private $plugin_basename;
        private $plugin_file;
        private $deactivateURI;
        private $deactivateICON;
        private $versionICON;
        private $authorICON;
        private $settingICON;
        private $db_table = null;
        private $manage_links = array();
        private $meta_links = array();
        public function __construct()
        {
            global $wpdb;
            $this->td = "cf7db";
            self::$_instance = $this;
            $this->db_slug = $this->td;
            $this->db_table = $wpdb->prefix . $this->db_slug;
            $this->plugin_dir = plugin_dir_path(__FILE__);
            $this->plugin_url = plugins_url("", __FILE__);
            $this->assets_url = plugins_url("/assets/", __FILE__);
            $this->plugin_basename = plugin_basename(__FILE__);
            $this->url = admin_url("admin.php?page={$this->db_slug}");
            $this->plugin_file = __FILE__;
            $this->version = "1.0.0";
            $this->deactivateURI = null;
            $this->deactivateICON = '<span style="font-size: larger; line-height: 1rem; display: inline; vertical-align: text-top;" class="dashicons dashicons-dismiss" aria-hidden="true"></span> ';
            $this->versionICON = '<span style="font-size: larger; line-height: 1rem; display: inline; vertical-align: text-top;" class="dashicons dashicons-admin-plugins" aria-hidden="true"></span> ';
            $this->authorICON = '<span style="font-size: larger; line-height: 1rem; display: inline; vertical-align: text-top;" class="dashicons dashicons-admin-users" aria-hidden="true"></span> ';
            $this->settingURL = '<span style="display: inline;float: none;padding: 0;" class="dashicons dashicons-admin-settings dashicons-small" aria-hidden="true"></span> ';
            $this->submitionURL = '<span style="display: inline;float: none;padding: 0;" class="dashicons dashicons-images-alt dashicons-small" aria-hidden="true"></span> ';
            $this->title = __("CF7 Database", $this->td);
            $this->title_w = sprintf(__("%2\$s ver. %1\$s", $this->td), $this->version, $this->title);
            add_action("init", array($this, 'init_plugin'));
        }
        public function init_plugin()
        {
            add_filter("plugin_action_links_{$this->plugin_basename}", array($this, 'plugins_row_links'));
            add_action("plugin_row_meta", array( $this, 'plugin_row_meta' ), 10, 2);
            add_action("admin_menu", array($this, 'admin_menu'));
            add_action("admin_init", array($this, 'admin_init'));
            add_action("admin_enqueue_scripts", array($this, 'admin_enqueue_scripts'));
            add_action("wp_ajax_nopriv_cf7db_{$this->td}", array($this, 'handel_ajax_req'));
            add_action("wp_ajax_cf7db_{$this->td}", array($this, 'handel_ajax_req'));
            $this->CreateDatabase(); // always check if table exist or not
            add_action( 'wpcf7_before_send_mail',  array($this, 'contactform7_before_send_mail_hook') );
        }
        public function contactform7_before_send_mail_hook( $form_to_DBs ) {
          $form_to_DB = WPCF7_Submission::get_instance();
          if ( $form_to_DB ) {
            $formData = $form_to_DB->get_posted_data();
            $subject = isset($formData['your-subject']) ? $formData['your-subject'] : "";
            $name = isset($formData['your-name']) ? $formData['your-name'] : "";
            $email = isset($formData['your-email']) ? $formData['your-email'] : "";
            $extra_info = $form_to_DBs->id();
            $details = serialize( $formData );
            $this->save_submition($subject, $name, $email, $details, $extra_info);
          }
        }
        public function get_setting_options()
        {
            return array(
            array(
            "name" => "{$this->db_slug}_general",
            "data" => array(
                "{$this->db_slug}-clearunistall" => "no",
                "{$this->db_slug}-cleardbunistall" => "no",
            )
            ),
            );
        }
        public function get_meta_links()
        {
            if (!empty($this->meta_links)) {return $this->meta_links;
            }
            $this->meta_links = array(
                  'support'      => array(
                      'title'       => __('Support', $this->td),
                      'description' => __('Support', $this->td),
                      'icon'        => 'dashicons-admin-site',
                      'target'      => '_blank',
                      'url'         => "mailto:support@pepro.dev?subject={$this->title}",
                  ),
              );
            return $this->meta_links;
        }
        public function get_manage_links()
        {
            if (!empty($this->manage_links)) {return $this->manage_links;
            }
            $this->manage_links = array(
              $this->settingURL . __("Settings", $this->td) => $this->url,
              $this->submitionURL . __("Submission", $this->td) => $this->url,
            );
            return $this->manage_links;
        }
        public static function activation_hook()
        {
            (new cf7Database)->CreateDatabase();
        }
        public static function deactivation_hook()
        {
        }
        public static function uninstall_hook()
        {
            $ppa = new cf7Database;
            $dbClear = get_option("{$ppa->db_slug}-cleardbunistall", "no") === "yes" ? $ppa->DropDatabase() : null;
            if (get_option("{$ppa->db_slug}-clearunistall", "no") === "yes") {
                $cf7Database_class_options = $ppa->get_setting_options();
                foreach ($cf7Database_class_options as $options) {
                    $opparent = $options["name"];
                    foreach ($options["data"] as $optname => $optvalue) {
                        unregister_setting($opparent, $optname);
                        delete_option($optname);
                    }
                }
            }
        }
        public function CreateDatabase()
        {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();
            $tbl = $this->db_table;
            if ($wpdb->get_var("SHOW TABLES LIKE '". $tbl ."'") != $tbl ) {
                $sql = "CREATE TABLE `$tbl` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `date_created` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            `subject` VARCHAR(512),
            `from` VARCHAR(320),
            `email` VARCHAR(320),
            `details` TEXT,
            `extra_info` TEXT,
            PRIMARY KEY id (id)
          ) $charset_collate;";
                if(!function_exists('dbDelta')) {include_once ABSPATH . 'wp-admin/includes/upgrade.php';
                }
                dbDelta($sql);
                // error_log("$tbl Created");
            }else{
                // error_log("$tbl Already Exist");
            }
        }
        public function DropDatabase()
        {
            global $wpdb;
            $wpdb->query("DROP TABLE IF EXISTS {$this->db_table}");
        }
        private function update_footer_info()
        {
            add_filter(
                'admin_footer_text', function () {
                    return sprintf(_x("Thanks for using %s products", "footer-copyright", $this->td), "<b><a href='https://pepro.dev/' target='_blank' >".__("Pepro Dev", $this->td)."</a></b>");
                }, 11
            );
            add_filter(
                'update_footer', function () {
                    return sprintf(_x("%s — Version %s", "footer-copyright", $this->td), $this->title, $this->version);
                }, 11
            );
        }
        public function handel_ajax_req()
        {
            if (wp_doing_ajax() && $_POST['action'] == "cf7db_{$this->td}") {

                if (!wp_verify_nonce( $_POST["nonce"], $this->td)){

                  wp_send_json_error( array("msg"=>__("Unauthorized Access!",$this->td)));

                }

                if (isset($_POST["wparam"]) && "delete_item" == trim($_POST["wparam"]) && !empty($_POST["lparam"])) {

                  global $wpdb;
                  $id = (int) trim($_POST["lparam"]);
                  $del = $wpdb->delete( $this->db_table , array( 'ID' => $id ) );
                  if (false !== $del){
                    wp_send_json_success( array( "msg" => sprintf(__("Submition ID %s Successfully Deleted.",$this->td),$id ) ) );
                  }else{
                    wp_send_json_error( array( "msg" => sprintf(__("Error Deleting Submition ID %s.",$this->td),$id ) ) );
                  }

                }

                if (isset($_POST["wparam"]) && "clear_db_cf7" == trim($_POST["wparam"]) && !empty($_POST["lparam"])) {

                  global $wpdb;
                  $id = (int) trim($_POST["lparam"]);
                  $del = $wpdb->delete( $this->db_table , array( 'extra_info' => $id ) );
                  if (false !== $del){
                    wp_send_json_success( array( "msg" => sprintf(__("All data regarding Contact form %s (ID %s) were Successfully Deleted.",$this->td), get_the_title($id), $id ) ) );
                  }else{
                    wp_send_json_error( array( "msg" => sprintf(__("Error Deleting Contact form %s (ID %s) data from database.",$this->td), get_the_title($id), $id ) ) );
                  }

                }

                if (isset($_POST["wparam"]) && "clear_db" == trim($_POST["wparam"])) {

                  global $wpdb;
                  $del = $wpdb->query("TRUNCATE TABLE `$this->db_table`");
                  if (false !== $del){
                    wp_send_json_success( array( "msg" => sprintf(__("Database Successfully Cleared.",$this->td),$id ) ) );
                  }else{
                    wp_send_json_error( array( "msg" => sprintf(__("Error Clearing Database.",$this->td),$id ) ) );
                  }

                }

                die();
            }
        }
        public function admin_menu()
        {
            add_menu_page(
                $this->title_w,
                $this->title,
                "manage_options",
                $this->db_slug,
                array($this,'db_container'),
                "{$this->assets_url}images/peprodev.svg",
                81
            );
            $page_title = __("List of CF7 Submission", $this->td);
            $menu_title = __("Setting", $this->td);
            add_submenu_page($this->db_slug, $page_title, $menu_title, "manage_options", "{$this->db_slug}-setting", array($this,'help_container'));

        }
        public function help_container($hook)
        {
            ob_start();
            $this->update_footer_info();
            $input_number = ' dir="ltr" lang="en-US" min="0" step="1" ';
            $input_english = ' dir="ltr" lang="en-US" ';
            $input_required = ' required ';
            wp_enqueue_style("jQconfirm");
            wp_enqueue_script("jQconfirm");
            wp_enqueue_style("fontawesome","https://use.fontawesome.com/releases/v5.13.1/css/all.css", array(), '5.13.1', 'all');
            wp_enqueue_style("{$this->db_slug}", "{$this->assets_url}css/backend.css");
            wp_enqueue_script("{$this->db_slug}", "{$this->assets_url}js/backend.js", array('jquery'), null, true);
            wp_localize_script("{$this->db_slug}", "_i18n", $this->localize_script());

            is_rtl() AND wp_add_inline_style("{$this->db_slug}", ".form-table th {}#wpfooter, #wpbody-content *:not(.dashicons ), #wpbody-content input:not([dir=ltr]), #wpbody-content textarea:not([dir=ltr]), h1.had, .caqpde>b.fa{ font-family: bodyfont, roboto, Tahoma; }");
            echo "<h1 class='had'>".$this->title_w."</h1><div class=\"wrap\">";
            echo '<form method="post" action="options.php">';
            settings_fields("{$this->db_slug}_general");
            if (isset($_REQUEST["settings-updated"]) && $_REQUEST["settings-updated"] == "true") { echo '<div id="message" class="updated notice is-dismissible"><p>' . _x("Settings saved successfully.", "setting-general", $this->td) . "</p></div>";
            }
            echo '<br><table class="form-table"><tbody>';
            $this->print_setting_select("{$this->db_slug}-cleardbunistall", _x("Clear Database Data on Unistall", "setting-general", $this->td), array("yes" =>_x("Yes", "settings-general", $this->td), "no" => _x("No", "settings-general", $this->td)));
            echo '</tbody></table><div class="submtCC">';
            submit_button(__("Save setting", $this->td), "primary submt", "submit", false);
            echo "<a class='button button-primary submt' id='emptyDbNow' href='#'>"._x("Empty Database", "setting-general", $this->td)."</a>";
            echo "</form></div></div>";
            $tcona = ob_get_contents();
            ob_end_clean();
            print $tcona;
        }
        public function localize_script()
        {
          return array(
            "td"                  => "cf7db_{$this->td}",
            "ajax"                => admin_url("admin-ajax.php"),
            "home"                => home_url(),
            "nonce"               => wp_create_nonce($this->td),
            "title"               => _x("Select image file", "wc-setting-js", $this->td),
            "btntext"             => _x("Use this image", "wc-setting-js", $this->td),
            "clear"               => _x("Clear", "wc-setting-js", $this->td),
            "currentlogo"         => _x("Current preview", "wc-setting-js", $this->td),
            "selectbtn"           => _x("Select image", "wc-setting-js", $this->td),
            "tr_submit"           => _x("Submit","js-string",$this->td),
            "tr_today"            => _x("Today","js-string",$this->td),
            "errorTxt"            => _x("Error", "wc-setting-js", $this->td),
            "cancelTtl"           => _x("Canceled", "wc-setting-js", $this->td),
            "confirmTxt"          => _x("Confirm", "wc-setting-js", $this->td),
            "successTtl"          => _x("Success", "wc-setting-js", $this->td),
            "submitTxt"           => _x("Submit", "wc-setting-js", $this->td),
            "okTxt"               => _x("Okay", "wc-setting-js", $this->td),
            "txtYes"              => _x("Yes", "wc-setting-js", $this->td),
            "txtNop"              => _x("No", "wc-setting-js", $this->td),
            "cancelbTn"           => _x("Cancel", "wc-setting-js", $this->td),
            "sendTxt"             => _x("Send to all", "wc-setting-js", $this->td),
            "closeTxt"            => _x("Close", "wc-setting-js", $this->td),
            "deleteConfirmTitle"  => _x("Delete Submition", "wc-setting-js", $this->td),
            "deleteConfirmation"  => _x("Are you sure you want to delete submition ID %s ? This cannot be undone.", "wc-setting-js", $this->td),
            "clearDBConfirmation" => _x("Are you sure you want to clear all data from database? This cannot be undone.", "wc-setting-js", $this->td),
            "clearDBConfirmatio2" => _x("Are you sure you want to clear all Current Contact form data from database? This cannot be undone.", "wc-setting-js", $this->td),
            "clearDBConfTitle"    => _x("Clear Database", "wc-setting-js", $this->td),

            "str1"    => sprintf(_x("Contact Form 7 Database Exported via %s", "wc-setting-js", $this->td),"$this->title_w"),
            "str2"    => sprintf(_x("CF7 Database Export", "wc-setting-js", $this->td),$this->title_w),
            "str3"    => sprintf(_x("Exported at %s @ %s", "wc-setting-js", $this->td), date_i18n( get_option('date_format'),current_time( "timestamp")), date_i18n( get_option('time_format'),current_time( "timestamp")),),
            "str4"    => "PeproCF7Database-". date_i18n("YmdHis",current_time( "timestamp")),
            "str5"    => sprintf(_x("Exported via %s — Export Date: %s @ %s — Developed by Pepro Dev Team ( https://pepro.dev/ )", "wc-setting-js", $this->td),$this->title_w,date_i18n( get_option('date_format'),current_time( "timestamp")), date_i18n( get_option('time_format'),current_time( "timestamp")),),

            "tbl1"    => _x("No data available in table", "data-table", $this->td),
            "tbl2"    => _x("Showing _START_ to _END_ of _TOTAL_ entries", "data-table", $this->td),
            "tbl3"    => _x("Showing 0 to 0 of 0 entries", "data-table", $this->td),
            "tbl4"    => _x("(filtered from _MAX_ total entries)", "data-table", $this->td),
            "tbl5"    => _x("Show _MENU_ entries", "data-table", $this->td),
            "tbl6"    => _x("Loading...", "data-table", $this->td),
            "tbl7"    => _x("Processing...", "data-table", $this->td),
            "tbl8"    => _x("Search:", "data-table", $this->td),
            "tbl9"    => _x("No matching records found", "data-table", $this->td),
            "tbl10"    => _x("First", "data-table", $this->td),
            "tbl11"    => _x("Last", "data-table", $this->td),
            "tbl12"    => _x("Next", "data-table", $this->td),
            "tbl13"    => _x("Previous", "data-table", $this->td),
            "tbl14"    => _x(": activate to sort column ascending", "data-table", $this->td),
            "tbl15"    => _x(": activate to sort column descending", "data-table", $this->td),
            "tbl16"    => _x("Copy to clipboard", "data-table", $this->td),
            "tbl17"    => _x("Print", "data-table", $this->td),
            "tbl18"    => _x("Export CSV", "data-table", $this->td),
            "tbl19"    => _x("Export Excel", "data-table", $this->td),
            "tbl20"    => _x("Export PDF", "data-table", $this->td),

          );
        }
        public function db_container()
        {
            ob_start();

            $this->update_footer_info();
            $now = current_time('timestamp');
            $randomnum = random_int( 15740, 68414866 );
            // $this->save_submition(
            //   "New MSG on " . date_i18n("y-m-d h-m-s",$now),
            //   "Amirhossein",
            //   "user{$randomnum}@gmail.com",
            //   "{$randomnum}/{$randomnum} Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam",
            //   "{$randomnum}/{$randomnum} Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum."
            // );

            wp_enqueue_style("{$this->db_slug}", "{$this->assets_url}css/backend.css");

            wp_enqueue_style("datatable");
            wp_enqueue_style("SrchHighlt");
            wp_enqueue_style("jQconfirm");
            wp_enqueue_style("fontawesome","https://use.fontawesome.com/releases/v5.13.1/css/all.css", array(), '5.13.1', 'all');

            wp_enqueue_script("jQconfirm");
            wp_enqueue_script("datatable");
            wp_enqueue_script("highlight.js");
            wp_enqueue_script("SrchHighlt");

            /* needs for PDF export function word properly but due to not supporting utf-8 we ignore these*/
            // wp_enqueue_script( "s1", "https://cdn.datatables.net/buttons/1.6.2/js/dataTables.buttons.min.js", array("jquery"), false);
            // wp_enqueue_script( "s1", "https://cdn.datatables.net/buttons/1.6.2/js/buttons.flash.min.js", array("jquery"), false);
            // wp_enqueue_script( "s2", "https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js", array("jquery"), false);
            // wp_enqueue_script( "s3", "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js", array("jquery"), false);
            // wp_enqueue_script( "s4", "https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js", array("jquery"), false);
            // wp_enqueue_script( "s5", "https://cdn.datatables.net/buttons/1.6.2/js/buttons.html5.min.js", array("jquery"), false);
            // wp_enqueue_script( "s6", "https://cdn.datatables.net/buttons/1.6.2/js/buttons.print.min.js", array("jquery"), false);

            wp_enqueue_script("{$this->db_slug}", "{$this->assets_url}/js/backend.js", array('jquery'), null, true);
            wp_localize_script("{$this->db_slug}", "_i18n", $this->localize_script());
            $this->print_table_style();

            is_rtl() AND wp_add_inline_style("{$this->db_slug}", ".form-table th {}#wpfooter, #wpbody-content *:not(.dashicons ), #wpbody-content input:not([dir=ltr]), #wpbody-content textarea:not([dir=ltr]), h1.had, .caqpde>b.fa{ font-family: bodyfont, roboto, Tahoma; }");
            global $wpdb;
            $table = $this->db_table;
            $post_per_page = isset($_GET['per_page']) ? abs((int) $_GET['per_page']) : 100;
            $page = isset($_GET['num']) ? abs((int) $_GET['num']) : 1;
            $cf7 = isset($_GET['cf7']) ? abs((int) $_GET['cf7']) : 0;
            $offset = ( $page * $post_per_page ) - $post_per_page;
            $the_title = get_the_title($cf7);
            $title = $this->title;
            ?>
            <?php
            $posts = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'numberposts' => -1 ) );
            $select = "";
            foreach ( $posts as $p ) { $select .= "<button style='margin: .5rem;' class='button dt-button hrefbtn' href='{$this->url}&cf7=$p->ID' title='$p->post_title (ID #$p->ID)'>$p->post_title</button>"; }

            if (!$cf7 || 0 == $cf7){
              echo "
              <h1 class='had'>$title</h1>
              <div style='text-align: center;'><p>".__("Select a CF7 form from list below:",$this->td)."</p>$select</div>";
              return;
            }
            if ( 'publish' != get_post_status ( $cf7 ) ) {
              echo "<h1 class='had'>$title</h1>";
              ?>
              <p style="text-align: center; display: block;"><button class='button dt-button hrefbtn' href='<?="{$this->url}";?>'><?=__("Entered CF7 is not valid, Go back and select another Contact form",$this->td);?></button></p>
              <?php
              return;
            }

            $title = sprintf(__('%s → Contact Form "%s"', $this->td), $this->title, $the_title);
            echo "<h1 class='had'>$title</h1>";


            $total = $wpdb->get_var("SELECT COUNT(1) FROM $table AS combined_table WHERE `extra_info`='{$cf7}'");
            $res_obj = $wpdb->get_results("SELECT * FROM $table WHERE `extra_info`='{$cf7}' ORDER BY `date_created` DESC LIMIT {$offset}, {$post_per_page}");

            $items_per_page_selceter =
              "<select id='itemsperpagedisplay' name='per_page' style='width:auto !important; margin: 0 0 0 .5rem; float: right;' title='" . __("Items per page", $this->td) . "' >
            		<option value='50' " . selected(100, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 50)."</option>
            		<option value='100' " . selected(100, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 100)."</option>
            		<option value='200' " . selected(200, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 200)."</option>
            		<option value='300' " . selected(300, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 300)."</option>
            		<option value='400' " . selected(500, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 400)."</option>
            		<option value='500' " . selected(500, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 500)."</option>
            		<option value='600' " . selected(500, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 600)."</option>
            		<option value='700' " . selected(500, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 700)."</option>
            		<option value='800' " . selected(500, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 800)."</option>
            		<option value='900' " . selected(500, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 900)."</option>
            		<option value='1000' " . selected(1000, $post_per_page, false) . ">".sprintf(_x("Show %s items per page", "items_per_page", $this->td), 1000)."</option>
            		<option disabled>-----------------</option>
            		<option value='$total' " . selected($total, $post_per_page, false) . ">".sprintf(_n( "Show your only saved submition", "Show all %s items at once", $total, $this->td ), $total)."</option>
    		      </select>";

            ?>
            <div class="wrap">
              <p style="text-align: center; display: block;">
                <button style='margin: .5rem;' class='button dt-button hrefbtn' id="emptySelectedCf7DB" href='#' data-rel="<?=$cf7;?>"><?=__("Delete All Submission of Current Contact form",$this->td);?></button>
                <button style='margin: .5rem;' class='button dt-button hrefbtn' target="_blank" href='<?=admin_url("admin.php?page=wpcf7&post=$cf7&action=edit");?>'><?=__("Edit Current Contact form",$this->td);?></button>
                <button style='margin: .5rem;' class='button dt-button hrefbtn' href='<?="{$this->url}";?>'><?=__("Go back and select another Contact form",$this->td);?></button>
              </p>
              <form action="<?php echo admin_url("admin.php?page=cf7db");?>" id='mainform' >
                    <input type="hidden" name="page" value="cf7db" />
                    <input type="hidden" name="num" value="<?=$page;?>" />
                    <input type="hidden" name="cf7" value="<?=$cf7;?>" />
                    <?php
                    if (!empty($wpdb->num_rows)) {


                      $header = array(
                        "_sharp_id"            =>  __('ID',$this->td)           ,
                        "date_created"  =>  __('Date Created',$this->td) ,
                        "your-subject"  =>  __('Subject',$this->td)      ,
                        "email"         =>  __('From',$this->td)         ,
                        // "details"       =>  __('Details',$this->td)      ,
                        // "action"        =>  __('Action',$this->td)       ,
                      );

                      foreach ( $res_obj as $obj ){
                        $data_array = unserialize($obj->details);
                        unset($data_array["your-subject"]);
                        unset($data_array["your-name"]);
                        unset($data_array["your-email"]);
                        foreach ( $data_array as $key => $value) {
                          $header[$key] = $key;
                        }
                      }
                      $header["action"] = __('Action',$this->td);
                      $header = array_unique($header);
                      echo "
                            <p><b>". sprintf(_n( "Your very first saved submition is showing below", "%s Saved Submission found", $total, $this->td ), $total) . "</b>   {$items_per_page_selceter}</p>
                          			<table border=\"1\" id=\"exported_data\" class=\"exported_data\">
                          			   <thead>
                              			     <tr>";
                                         foreach ($header as $key => $value) {
                                           $extraClass = "";
                                           if (in_array($key, apply_filters( "pepro_cf7db_hide_col_from_export", array("action","_sharp_id")))){
                                             $extraClass = "noExport";
                                           }
                                           echo "<th class='th-{$key} $extraClass'>{$value}</th>";
                                         }
                    			               echo "
                                         </tr>
                        			     </thead>
                  			           <tbody>";
                                      foreach ( $res_obj as $obj ){
                                        $data_array = unserialize($obj->details);
                                        echo "<tr class=\"item_{$obj->id}\">";
                                          foreach ($header as $key => $value) {
                                              switch ($key) {
                                                case '_sharp_id':
                                                  $val = $obj->id;
                                                  break;
                                                case 'date_created':
                                                  $val = "<p>". date_i18n( get_option('date_format'), $obj->date_created ) . "</p><p>" . date_i18n( get_option('time_format'), $obj->date_created )."</p>";
                                                  break;
                                                case 'email':
                                                  $name = (isset($data_array['your-name'])?$data_array['your-name']:"");
                                                  $email = (isset($data_array['your-email'])?$data_array['your-email']:"");
                                                  $val = "{$name} &lt;{$email}&gt;";
                                                  break;
                                                case 'action':
                                                  $val = "<a href='javascript:;' title='".esc_attr__("Delete this specific submition", $this->td)."' class=\"button delete_item\" data-lid='{$obj->id}' ><span class='dashicons dashicons-trash'></span></a>
                                                  <span class='spinner loading_{$obj->id}'></span>";
                                                  break;

                                                default:
                                                  $val = nl2br(esc_html($data_array[$key]));
                                                  break;
                                              }
                                          echo "<td class='item_{$key} itd_{$obj->id}'>{$val}</th>";
                                        }
                                        echo "</tr>";
                                      }
                                  echo "</tbody>";
                                echo "</table>";
                                echo '<div class="pagination" style="margin-top: 1.5rem;display: block;">';
                                  echo paginate_links(
                                      array(
                                      'base' => add_query_arg('num', '%#%'),
                                      'format' => '',
                                      'show_all' => false,
                                      'mid_size' => 2,
                                      'end_size' => 2,
                                      'prev_text' => '<span class="button button-primary">' . __('< Previous',$this->td) . "</span>",
                                      'next_text' => '<span class="button button-primary">' . __('Next >',$this->td) . "</span>",
                                      'total' => ceil($total / $post_per_page),
                                      'current' => $page,
                                      'before_page_number' => '<span class="button">',
                                      'after_page_number' => "</span>",
                                      'type' => 'list'
                                      )
                                  );
                                echo "</div>";
                    }
                    else{
                      echo "<h1 align='center' style='margin-top: 6rem; font-weight: bold;'>" . __("Error Reading Database!", $this->td) . "</h1>";
                      echo "<h2 align='center'>" . __("It seems there's nothing to show.", $this->td) . "</h2>";
                    }
                  ?>
              </form>
            </div>
            <?php
            $tcona = ob_get_contents();
            ob_end_clean();
            print $tcona;
        }
        public function admin_init($hook)
        {
            $cf7Database_class_options = $this->get_setting_options();
            foreach ($cf7Database_class_options as $sections) {
                foreach ($sections["data"] as $id=>$def) {
                    add_option($id, $def);
                    register_setting($sections["name"], $id);
                }
            }
        }
        public function admin_enqueue_scripts($hook)
        {

            wp_enqueue_style("{$this->db_slug}-backend-all", "{$this->assets_url}css/backend-all.css", array(), '1.0', 'all');

            wp_register_style("select2",       "{$this->assets_url}css/select2.min.css", false, "4.1.0", "all");
            wp_register_script("select2",      "{$this->assets_url}js/select2.min.js", array( "jquery" ), "4.1.0", true);

            wp_register_style("jQconfirm",     "{$this->assets_url}css/jquery-confirm.css", false, "4.1.0", "all");
            wp_register_script("jQconfirm",    "{$this->assets_url}js/jquery-confirm.js", array( "jquery" ), "4.1.0", true);

            wp_register_style("datatable",     "{$this->assets_url}css/jquery.dataTables.min.css", false, "1.10.21", "all");
            wp_register_script("datatable",    "{$this->assets_url}js/jquery.dataTables.min.js", array( "jquery" ), "1.10.21", true);

            wp_register_style("SrchHighlt",    "{$this->assets_url}css/dataTables.searchHighlight.css", false, "1.0.1", "all");
            wp_register_script("SrchHighlt",   "{$this->assets_url}js/dataTables.searchHighlight.min.js", array( "jquery" ), "1.0.1", true);
            wp_register_script("highlight.js", "{$this->assets_url}js/highlight.js", array( "jquery" ), "3.0.0", true);

        }
        public function save_submition($subject, $from, $email, $details, $extra_info)
        {
            global $wpdb;
            $wpdbinsert = $wpdb->insert(
                $this->db_table,
                array(
                  'subject'     =>  $subject,
                  'from'        =>  $from,
                  'email'       =>  $email,
                  'details'     =>  $details,
                  'extra_info'  =>  $extra_info,
                ),
                array(
                  '%s',
                  '%s',
                  '%s',
                  '%s',
                  '%s'
                )
            );
            return $wpdbinsert;
        }
        /* common functions */
        public function _wc_activated()
        {
            if (!is_plugin_active('woocommerce/woocommerce.php')
                || !function_exists('is_woocommerce')
                || !class_exists('woocommerce')
            ) {
                return false;
            }else{
                return true;
            }
        }
        public function _vc_activated()
        {
            if (!is_plugin_active('js_composer/js_composer.php') || !defined('WPB_VC_VERSION')) {
                return false;
            }else{
                return true;
            }
        }
        public function read_opt($mc, $def="")
        {
            return get_option($mc) <> "" ? get_option($mc) : $def;
        }
        public function plugins_row_links($links)
        {
            foreach ($this->get_manage_links() as $title => $href) {
                array_unshift($links, "<a href='$href' target='_self'>$title</a>");
            }
            $a = new SimpleXMLElement($links["deactivate"]);
            $this->deactivateURI = "<a href='".$a['href']."'>".$this->deactivateICON.$a[0]."</a>";
            unset($links["deactivate"]);
            return $links;
        }
        public function plugin_row_meta($links, $file)
        {
            if ($this->plugin_basename === $file) {
                // unset($links[1]);
                unset($links[2]);
                $icon_attr = array(
                  'style' => array(
                  'font-size: larger;',
                  'line-height: 1rem;',
                  'display: inline;',
                  'vertical-align: text-top;',
                  ),
                );
                foreach ($this->get_meta_links() as $id => $link) {
                    $title = (!empty($link['icon'])) ? self::do_icon($link['icon'], $icon_attr) . ' ' . esc_html($link['title']) : esc_html($link['title']);
                    $links[ $id ] = '<a href="' . esc_url($link['url']) . '" title="'.esc_attr($link['description']).'" target="'.(empty($link['target'])?"_blank":$link['target']).'">' . $title . '</a>';
                }
                $links[0] = $this->versionICON . $links[0];
                $links[1] = $this->authorICON . $links[1];
                $links["deactivate"] = $this->deactivateURI;
            }
            return $links;
        }
        public static function do_icon($icon, $attr = array(), $content = '')
        {
            $class = '';
            if (false === strpos($icon, '/') && 0 !== strpos($icon, 'data:') && 0 !== strpos($icon, 'http')) {
                // It's an icon class.
                $class .= ' dashicons ' . $icon;
            } else {
                // It's a Base64 encoded string or file URL.
                $class .= ' vaa-icon-image';
                $attr   = self::merge_attr(
                    $attr, array(
                    'style' => array( 'background-image: url("' . $icon . '") !important' ),
                    )
                );
            }

            if (! empty($attr['class'])) {
                $class .= ' ' . (string) $attr['class'];
            }
            $attr['class']       = $class;
            $attr['aria-hidden'] = 'true';

            $attr = self::parse_to_html_attr($attr);
            return '<span ' . $attr . '>' . $content . '</span>';
        }
        public static function parse_to_html_attr($array)
        {
            $str = '';
            if (is_array($array) && ! empty($array)) {
                foreach ($array as $attr => $value) {
                    if (is_array($value)) {
                        $value = implode(' ', $value);
                    }
                    $array[ $attr ] = esc_attr($attr) . '="' . esc_attr($value) . '"';
                }
                $str = implode(' ', $array);
            }
            return $str;
        }
        public function print_setting_input($SLUG="", $CAPTION="", $extraHtml="", $type="text",$extraClass="")
        {
            $ON = sprintf(_x("Enter %s", "setting-page", $this->td), $CAPTION);
            echo "<tr>
    			<th scope='row'>
    				<label for='$SLUG'>$CAPTION</label>
    			</th>
    			<td><input name='$SLUG' $extraHtml type='$type' id='$SLUG' placeholder='$CAPTION' title='$ON' value='" . $this->read_opt($SLUG) . "' class='regular-text $extraClass' /></td>
    		</tr>";
        }
        public function print_setting_select($SLUG, $CAPTION, $dataArray=array())
        {
            $ON = sprintf(_x("Choose %s", "setting-page", $this->td), $CAPTION);
            $OPTS = "";
            foreach ($dataArray as $key => $value) {
                if ($key == "EMPTY") {
                    $key = "";
                }
                $OPTS .= "<option value='$key' ". selected($this->read_opt($SLUG), $key, false) .">$value</option>";
            }
            echo "<tr>
      			<th scope='row'>
      				<label for='$SLUG'>$CAPTION</label>
      			</th>
      			<td><select name='$SLUG' id='$SLUG' title='$ON' class='regular-text'>
            ".$OPTS."
            </select>
            </td>
      		</tr>";
        }
        public function print_setting_editor($SLUG, $CAPTION, $re="")
        {
            echo "<tr><th><label for='$SLUG'>$CAPTION</label></th><td>";
            wp_editor(
                $this->read_opt($SLUG, ''), strtolower(str_replace(array('-', '_', ' ', '*'), '', $SLUG)), array(
                'textarea_name' => $SLUG
                )
            );
            echo "<p class='$SLUG'>$re</p></td></tr>";
        }
        public function _callback($a)
        {
            return $a;
        }
        public function getIP()
        {
            // Get server IP address
            $server_ip = (isset($_SERVER['SERVER_ADDR'])) ? $_SERVER['SERVER_ADDR'] : '';

            // If website is hosted behind CloudFlare protection.
            if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $_SERVER['HTTP_CF_CONNECTING_IP'];
            }

            if (isset($_SERVER['X-Real-IP']) && filter_var($_SERVER['X-Real-IP'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $_SERVER['X-Real-IP'];
            }

            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = trim(current(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) && $ip != $server_ip) {
                    return $ip;
                }
            }

            if (isset($_SERVER['DEV_MODE'])) {
                return '175.138.84.5';
            }

            return $_SERVER['REMOTE_ADDR'];
        }
        protected function print_table_style()
        {

        	if (is_rtl()) {
        		$fC = ":last-child";
        		$lC = ":first-child";
        		echo "<style>body, p, input:not([dir=ltr]), button, h1, h2, h3, h4, h5, h6, textarea:not([dir=ltr]), p, .ui-widget {direction: rtl !important;}</style>";
        	}
        	else{
        		$fC = ":first-child";
        		$lC = ":last-child";
        		echo "<style>body, p, input:not([dir=rtl]), button, h1, h2, h3, h4, h5, h6, textarea:not([dir=rtl]), p, .ui-widget {direction: ltr !important;}</style>";
        	}

        	echo "<style>

        	.sechme {
        		cursor: alias;
        		-webkit-touch-callout: none;
        		-webkit-user-select: none;
        		-khtml-user-select: none;
        		-moz-user-select: none;
        		-ms-user-select: none;
        		user-select: none;
        	}

        	#exported_data_filter input[type=search] {
        		width: 20rem;
        	}

        	.fixedHeader-floating thead{
        		position: relative;
        		top: 2rem;
        	}

        	table{
        		border: solid #ccc 1px;
        		-moz-border-radius: 6px;
        		-webkit-border-radius: 6px;
        	}

        	table tr:hover {
        		background: #fbf8e9;
        		-o-transition: all 0.1s ease-in-out;
        		-webkit-transition: all 0.1s ease-in-out;
        		-moz-transition: all 0.1s ease-in-out;
        		-ms-transition: all 0.1s ease-in-out;
        		transition: all 0.1s ease-in-out;
        	}

      		.loadingdelete{
      			background: url('data:image/gif;base64,R0lGODlhEAALAPQAAP////8AAP7a2v7Q0P7q6v4GBv8AAP4uLv6Cgv5gYP66uv4iIv5KSv6Kiv5kZP6+vv4mJv4EBP5OTv7m5v7Y2P709P44OP7c3P7y8v62tv6goP7Kyv7u7gAAAAAAAAAAACH+GkNyZWF0ZWQgd2l0aCBhamF4bG9hZC5pbmZvACH5BAALAAAAIf8LTkVUU0NBUEUyLjADAQAAACwAAAAAEAALAAAFLSAgjmRpnqSgCuLKAq5AEIM4zDVw03ve27ifDgfkEYe04kDIDC5zrtYKRa2WQgAh+QQACwABACwAAAAAEAALAAAFJGBhGAVgnqhpHIeRvsDawqns0qeN5+y967tYLyicBYE7EYkYAgAh+QQACwACACwAAAAAEAALAAAFNiAgjothLOOIJAkiGgxjpGKiKMkbz7SN6zIawJcDwIK9W/HISxGBzdHTuBNOmcJVCyoUlk7CEAAh+QQACwADACwAAAAAEAALAAAFNSAgjqQIRRFUAo3jNGIkSdHqPI8Tz3V55zuaDacDyIQ+YrBH+hWPzJFzOQQaeavWi7oqnVIhACH5BAALAAQALAAAAAAQAAsAAAUyICCOZGme1rJY5kRRk7hI0mJSVUXJtF3iOl7tltsBZsNfUegjAY3I5sgFY55KqdX1GgIAIfkEAAsABQAsAAAAABAACwAABTcgII5kaZ4kcV2EqLJipmnZhWGXaOOitm2aXQ4g7P2Ct2ER4AMul00kj5g0Al8tADY2y6C+4FIIACH5BAALAAYALAAAAAAQAAsAAAUvICCOZGme5ERRk6iy7qpyHCVStA3gNa/7txxwlwv2isSacYUc+l4tADQGQ1mvpBAAIfkEAAsABwAsAAAAABAACwAABS8gII5kaZ7kRFGTqLLuqnIcJVK0DeA1r/u3HHCXC/aKxJpxhRz6Xi0ANAZDWa+kEAA7AAAAAAAAAAAAPGJyIC8+CjxiPldhcm5pbmc8L2I+OiAgbXlzcWxfcXVlcnkoKSBbPGEgaHJlZj0nZnVuY3Rpb24ubXlzcWwtcXVlcnknPmZ1bmN0aW9uLm15c3FsLXF1ZXJ5PC9hPl06IENhbid0IGNvbm5lY3QgdG8gbG9jYWwgTXlTUUwgc2VydmVyIHRocm91Z2ggc29ja2V0ICcvdmFyL3J1bi9teXNxbGQvbXlzcWxkLnNvY2snICgyKSBpbiA8Yj4vaG9tZS9hamF4bG9hZC93d3cvbGlicmFpcmllcy9jbGFzcy5teXNxbC5waHA8L2I+IG9uIGxpbmUgPGI+Njg8L2I+PGJyIC8+CjxiciAvPgo8Yj5XYXJuaW5nPC9iPjogIG15c3FsX3F1ZXJ5KCkgWzxhIGhyZWY9J2Z1bmN0aW9uLm15c3FsLXF1ZXJ5Jz5mdW5jdGlvbi5teXNxbC1xdWVyeTwvYT5dOiBBIGxpbmsgdG8gdGhlIHNlcnZlciBjb3VsZCBub3QgYmUgZXN0YWJsaXNoZWQgaW4gPGI+L2hvbWUvYWpheGxvYWQvd3d3L2xpYnJhaXJpZXMvY2xhc3MubXlzcWwucGhwPC9iPiBvbiBsaW5lIDxiPjY4PC9iPjxiciAvPgo8YnIgLz4KPGI+V2FybmluZzwvYj46ICBteXNxbF9xdWVyeSgpIFs8YSBocmVmPSdmdW5jdGlvbi5teXNxbC1xdWVyeSc+ZnVuY3Rpb24ubXlzcWwtcXVlcnk8L2E+XTogQ2FuJ3QgY29ubmVjdCB0byBsb2NhbCBNeVNRTCBzZXJ2ZXIgdGhyb3VnaCBzb2NrZXQgJy92YXIvcnVuL215c3FsZC9teXNxbGQuc29jaycgKDIpIGluIDxiPi9ob21lL2FqYXhsb2FkL3d3dy9saWJyYWlyaWVzL2NsYXNzLm15c3FsLnBocDwvYj4gb24gbGluZSA8Yj42ODwvYj48YnIgLz4KPGJyIC8+CjxiPldhcm5pbmc8L2I+OiAgbXlzcWxfcXVlcnkoKSBbPGEgaHJlZj0nZnVuY3Rpb24ubXlzcWwtcXVlcnknPmZ1bmN0aW9uLm15c3FsLXF1ZXJ5PC9hPl06IEEgbGluayB0byB0aGUgc2VydmVyIGNvdWxkIG5vdCBiZSBlc3RhYmxpc2hlZCBpbiA8Yj4vaG9tZS9hamF4bG9hZC93d3cvbGlicmFpcmllcy9jbGFzcy5teXNxbC5waHA8L2I+IG9uIGxpbmUgPGI+Njg8L2I+PGJyIC8+CjxiciAvPgo8Yj5XYXJuaW5nPC9iPjogIG15c3FsX3F1ZXJ5KCkgWzxhIGhyZWY9J2Z1bmN0aW9uLm15c3FsLXF1ZXJ5Jz5mdW5jdGlvbi5teXNxbC1xdWVyeTwvYT5dOiBDYW4ndCBjb25uZWN0IHRvIGxvY2FsIE15U1FMIHNlcnZlciB0aHJvdWdoIHNvY2tldCAnL3Zhci9ydW4vbXlzcWxkL215c3FsZC5zb2NrJyAoMikgaW4gPGI+L2hvbWUvYWpheGxvYWQvd3d3L2xpYnJhaXJpZXMvY2xhc3MubXlzcWwucGhwPC9iPiBvbiBsaW5lIDxiPjY4PC9iPjxiciAvPgo8YnIgLz4KPGI+V2FybmluZzwvYj46ICBteXNxbF9xdWVyeSgpIFs8YSBocmVmPSdmdW5jdGlvbi5teXNxbC1xdWVyeSc+ZnVuY3Rpb24ubXlzcWwtcXVlcnk8L2E+XTogQSBsaW5rIHRvIHRoZSBzZXJ2ZXIgY291bGQgbm90IGJlIGVzdGFibGlzaGVkIGluIDxiPi9ob21lL2FqYXhsb2FkL3d3dy9saWJyYWlyaWVzL2NsYXNzLm15c3FsLnBocDwvYj4gb24gbGluZSA8Yj42ODwvYj48YnIgLz4K') no-repeat 50% 50%;
      			display: none;
      			width: 100%;
      			height: 35px;
      		}

      		table td, table th {
      			border-left: 1px solid #ccc;
      			border-top: 1px solid #ccc;
      			padding: 10px;
      			text-align: left;
      		}

      		table th {
      			background-color: #dce9f9;
      			background-image: -webkit-gradient(linear, left top, left bottom, from(#ebf3fc), to(#dce9f9));
      			background-image: -webkit-linear-gradient(top, #ebf3fc, #dce9f9);
      			background-image:    -moz-linear-gradient(top, #ebf3fc, #dce9f9);
      			background-image:     -ms-linear-gradient(top, #ebf3fc, #dce9f9);
      			background-image:      -o-linear-gradient(top, #ebf3fc, #dce9f9);
      			background-image:         linear-gradient(top, #ebf3fc, #dce9f9);
      			border-top: none;
      			text-shadow: 0 1px 0 rgba(255,255,255,.5);
      		}


      		table th, table td {
      			text-align: center;
      		}


      		.pagination>ul{
      			margin: 0 !important;
      			padding: 0 !important;
      			cursor: default;
      		}
      		.pagination>ul>li{
      			display: inline-block;
      			padding: 4px;
      			margin: 0 !important;
      		}

      		.pagination {
      			text-align: center;
      			display: inline;
      		}

      		.pagination > ul > li > a.page-numbers,
          .pagination > ul > li > span.current{
            display: block;
            height: auto;
      		}
      		.pagination > ul > li > span.current *{
            color: gray;
            border-color: gray;
            pointer-events: none;
      		}

      		p[dir=ltr]{
      			font-family: roboto ,Arial !important;
      			dir: ltr !important;
      			text-align:left !important;
      			padding : 12px;
      		}
        		</style>";
        }
    }
    /**
     * load plugin and load textdomain then set a global varibale to access plugin class!
     *
     * @version 1.0.0
     * @since   1.0.0
     * @license https://pepro.dev/license Pepro.dev License
     */
    add_action(
        "plugins_loaded", function () {
            global $cf7Database;
            load_plugin_textdomain("cf7db", false, dirname(plugin_basename(__FILE__))."/languages/");
            $cf7Database = new cf7Database;
            register_activation_hook(__FILE__, array("cf7Database", "activation_hook"));
            register_deactivation_hook(__FILE__, array("cf7Database", "deactivation_hook"));
            register_uninstall_hook(__FILE__, array("cf7Database", "uninstall_hook"));
        }
    );
}
    /*################################################################################
    END OF PLUGIN || Programming is art // Artist : Amirhosseinhpv [https://hpv.im/]
    // */
