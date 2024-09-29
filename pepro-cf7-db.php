<?php
/*
Plugin Name: PeproDev CF7 Database
Description: Reliable Solution to Save CF7 Submissions and Files, Works with CF7 v5.9+
Contributors: amirhpcom, blackswanlab, peprodev
Tags: contact form 7 database, cf7 files, save contact form 7 uploads
Author: Pepro Dev. Group
Author URI: https://pepro.dev/
Plugin URI: https://pepro.dev/cf7-database/
Version: 2.0.0
Stable tag: 2.0.0
Requires at least: 5.0
Tested up to: 6.6.2
Requires PHP: 5.6
Text Domain: cf7db
Domain Path: /languages
Copyright: (c) Pepro Dev. Group, All rights reserved.
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * @Last modified by: amirhp-com <its@amirhp.com>
 * @Last modified time: 2024/09/29 15:03:21
 */

defined("ABSPATH") or die("Pepro CF7 Database :: Unauthorized Access!");

if (!class_exists("cf7Database")) {
  class cf7Database {
    public $url;
    public $td = "cf7db";
    public $db_slug = "cf7db";
    public $version = "2.0.0";
		public $db_version = "2.0.0";
    public $title;
    public $title_w;
    private $assets_url;
    private $plugin_basename;
    private $title2;
    private $db_table = null;
    public function __construct() {
      global $wpdb;
      $this->db_table = $wpdb->prefix . $this->db_slug;
      $this->assets_url = plugins_url("/assets/", __FILE__);
      $this->plugin_basename = plugin_basename(__FILE__);
      $this->url = admin_url("admin.php?page={$this->db_slug}");
      $this->title = __("CF7 Database", $this->td);
      $this->title2 = __("Pepro CF7 Database", $this->td);
      $this->title_w = sprintf(__("%2\$s ver. %1\$s", $this->td), $this->version, $this->title);
      
      add_action("init", array($this, "init_plugin"));
      
      if (isset($_GET["force-cf7db"], $_GET["nonce"]) && current_user_can("manage_options") && wp_verify_nonce($_GET["nonce"], "cf7db") ){
        $this->CreateDatabase(true);
        wp_die("Database structure updated/regenerated.", $this->title, ["link_url" => $this->url, "link_text" => $this->title2, "back_link" => true]);
      }
      
    }
    public function init_plugin() {
      load_plugin_textdomain("cf7db", false, dirname(plugin_basename(__FILE__)) . "/languages/");
      
      $cur_version = get_option("cf7db_cur_db_version", "1.0.0");
      if (version_compare($cur_version, $this->db_version, "<")) {
        $this->CreateDatabase(true);
        update_option("cf7db_cur_db_version", $this->db_version);
      }

      add_filter("plugin_action_links_{$this->plugin_basename}", array($this, 'plugins_row_links'));
      add_action("admin_menu"                                  , array($this, "admin_menu"));
      add_action("admin_enqueue_scripts"                       , array($this, "admin_enqueue_scripts"));
      add_action("wpcf7_admin_footer"                          , array($this, "wpcf7_admin_footer"));
      add_action("wpcf7_before_send_mail"                      , array($this, "cf7_before_send_mail_hook"), 10, 3);
      add_action("wp_ajax_nopriv_cf7db_{$this->td}"            , array($this, "handel_ajax_req"));
      add_action("wp_ajax_cf7db_{$this->td}"                   , array($this, "handel_ajax_req"));
    }
    public function wpcf7_admin_footer($post) {
      $contact_form = \WPCF7_ContactForm::get_current();
      $contact_form_id = $contact_form->id();
      echo "
      <div id='peprocf7db' class='postbox'>
        <h3>$this->title2</h3>
        <div class='inside'>
          <p>" . __("A Reliable Solution to Save Contact Form 7 Submissions and Files", $this->td) . "</p>
          <ol>
              <li><a href='{$this->url}&cf7=$contact_form_id'>" . __("Saved Submission", $this->td) . "</a></li>
              <li><a href='{$this->url}'>" . __("All CF7 Databases", $this->td) . "</a></li>
              <li><a href='https://wordpress.org/support/plugin/pepro-cf7-database/reviews/#new-post'>" . __("Give five-star review", $this->td) . "</a></li>
              <li><a href='https://wordpress.org/plugins/pepro-cf7-sms-notifier/'>" . __("Pepro CF7 SMS Notifier", $this->td) . "</a></li>
              <li><a href='https://profiles.wordpress.org/peprodev/#content-plugins'>" . __("Pepro Dev FREE offerings", $this->td) . "</a></li>
          </ol>
        </div>
      </div>
      <a class='button' id='viewsavedsubmission' target='_blank' href='{$this->url}&cf7=$contact_form_id'>" . __("Saved Submission", $this->td) . "</a>
      <script>document.getElementById('postbox-container-1').appendChild(document.getElementById('peprocf7db'));
      document.getElementById('minor-publishing-actions').appendChild(document.getElementById('viewsavedsubmission'));</script>
      ";
    }
    public function cf7_before_send_mail_hook($contact_form, $abort, $form_to_DB) {
      $form_to_DB = \WPCF7_Submission::get_instance();
      if ($form_to_DB) {
        $formData = $form_to_DB->get_posted_data();
        $contact_form = \WPCF7_ContactForm::get_current();
        $contact_form_id = $contact_form->id();
        $uploaded_files = $form_to_DB->uploaded_files();
        if ($uploaded_files) {
          foreach ($uploaded_files as $fieldName => $filepath) {
            $data = $this->save_cf7_attachment($filepath, $fieldName, $contact_form_id);
            $formData[$fieldName] = "FILEURL:$data";
          }
        }
        $subject = isset($formData["your-subject"]) ? $formData["your-subject"] : "";
        $name    = isset($formData["your-name"]) ? $formData["your-name"] : "";
        $email   = isset($formData["your-email"]) ? $formData["your-email"] : "";
        $details = serialize($formData);
        $this->save_cf7_submission($subject, $name, $email, $details, $contact_form_id);
      }
    }
    protected function save_cf7_attachment($filename, $fieldName, $postID) {
      $filename = $filename[0];
      if (!$filename) return false;
      // Check the type of file. We'll use this as the 'post_mime_type'.
      $filetype = wp_check_filetype(basename($filename), null);
      // Get the path to the upload directory.
      remove_all_filters("wp_date");
      remove_all_filters("date_i18n");
      $filenameNew = uniqid($fieldName . "-") . "-" . date_i18n("Y-m-d-H-i-s", current_time("timestamp")) . "." . $filetype['ext'];
      $wp_upload_dir = wp_upload_dir();
      $attachFileName = $wp_upload_dir['path'] . '/' . $filenameNew;
      copy($filename, $attachFileName);
      // Prepare an array of post data for the attachment.
      $attachment = array(
        'guid'           => $attachFileName,
        'post_mime_type' => $filetype['type'],
        'post_title'     => preg_replace('/\.[^.]+$/', '', $filenameNew),
        'post_content'   => '',
        'post_status'    => 'inherit'
      );
      // Insert the attachment.
      $attach_id = wp_insert_attachment($attachment, $attachFileName, $postID);
      // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
      require_once(ABSPATH . 'wp-admin/includes/image.php');
      // Generate the metadata for the attachment, and update the database record.
      $attach_data = wp_generate_attachment_metadata($attach_id, $attachFileName);
      wp_update_attachment_metadata($attach_id, $attach_data);
      return wp_get_attachment_url($attach_id);
    }
    public function plugins_row_links($links) {
      if (isset($links["deactivate"])) {
        $getManageLinks = array(__("Support", $this->td) => "mailto:support@pepro.dev?subject={$this->title}",);
        foreach ($getManageLinks as $title => $href) {
          array_unshift($links, "<a href='$href' target='_self'>$title</a>");
        }
      }
      return $links;
    }
    public static function activation_hook() {
      (new cf7Database)->CreateDatabase(true);
    }
    public function CreateDatabase($force = false) {
      global $wpdb;
      if (!function_exists('dbDelta')) {
        include_once ABSPATH . 'wp-admin/includes/upgrade.php';
      }
      $tbl = $this->db_table;
      $charset_collate = $wpdb->get_charset_collate();
      if ($wpdb->get_var("SHOW TABLES LIKE '" . $tbl . "'") != $tbl || $force) {
        dbDelta("CREATE TABLE `$tbl` (
          `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `subject` VARCHAR(512),
          `from` VARCHAR(320),
          `email` VARCHAR(320),
          `details` TEXT,
          `extra_info` TEXT,
          PRIMARY KEY id (id)
        ) $charset_collate;");
      }
    }
    protected function update_footer_info() {
      $f = "pepro_temp_stylesheet." . current_time("timestamp");
      wp_register_style($f, null);
      wp_add_inline_style($f, "
      #footer-left b a::before { content: ''; background: url('{$this->assets_url}/images/peprodev.svg') no-repeat; background-position-x: center; background-position-y: center; background-size: contain; width: 60px; height: 40px; display: inline-block; pointer-events: none; position: absolute; -webkit-margin-before: calc(-60px + 1rem); margin-block-start: calc(-60px + 1rem); -webkit-filter: opacity(0.0);
      filter: opacity(0.0); transition: all 0.3s ease-in-out; }#footer-left b a:hover::before { -webkit-filter: opacity(1.0); filter: opacity(1.0); transition: all 0.3s ease-in-out; }[dir=rtl] #footer-left b a::before {margin-inline-start: calc(30px);}
      .notice.peproicon::after { top: 0; opacity: 0.3; left: 0; content: ''; transition: all 0.3s ease-in-out; background: url('{$this->assets_url}/images/peprodev.svg') calc(100% - 1rem) center/4rem no-repeat; pointer-events: none; position: absolute; display: block; width: 100%; height: 100%; }
      [dir=rtl] .notice.peproicon::after { background-position-x: 1rem; }");
      wp_enqueue_style($f);
      add_filter('admin_footer_text', function () {
        return sprintf(_x("Thanks for using %s products", "footer-copyright", $this->td), "<b><a href='https://pepro.dev/' target='_blank' >" . __("Pepro Dev", $this->td) . "</a></b>");
      }, 11000);
      add_filter('update_footer', function () {
        return sprintf(_x("%s — Version %s", "footer-copyright", $this->td), $this->title, $this->version);
      }, 1100);
    }
    public function handel_ajax_req() {
      if (wp_doing_ajax() && $_POST['action'] == "cf7db_{$this->td}") {

        if (!wp_verify_nonce($_POST["nonce"], $this->td)) {

          wp_send_json_error(array("msg" => __("Unauthorized Access!", $this->td)));
        }

        if (isset($_POST["wparam"]) && "delete_item" == trim($_POST["wparam"]) && !empty($_POST["lparam"])) {

          global $wpdb;
          $id = (int) trim($_POST["lparam"]);
          $del = $wpdb->delete($this->db_table, array('ID' => $id));
          if (false !== $del) {
            wp_send_json_success(array("msg" => sprintf(__("Submition ID %s Successfully Deleted.", $this->td), $id)));
          } else {
            wp_send_json_error(array("msg" => sprintf(__("Error Deleting Submition ID %s.", $this->td), $id)));
          }
        }

        if (isset($_POST["wparam"]) && "clear_db_cf7" == trim($_POST["wparam"]) && !empty($_POST["lparam"])) {

          global $wpdb;
          $id = (int) trim($_POST["lparam"]);
          $del = $wpdb->delete($this->db_table, array('extra_info' => $id));
          if (false !== $del) {
            wp_send_json_success(array("msg" => sprintf(__("All data regarding Contact form %s (ID %s) were Successfully Deleted.", $this->td), get_the_title($id), $id)));
          } else {
            wp_send_json_error(array("msg" => sprintf(__("Error Deleting Contact form %s (ID %s) data from database.", $this->td), get_the_title($id), $id)));
          }
        }

        if (isset($_POST["wparam"]) && "clear_db" == trim($_POST["wparam"])) {

          global $wpdb;
          $del = $wpdb->query("TRUNCATE TABLE `$this->db_table`");
          if (false !== $del) {
            wp_send_json_success(array("msg" => sprintf(__("Database Successfully Cleared.", $this->td), $id)));
          } else {
            wp_send_json_error(array("msg" => sprintf(__("Error Clearing Database.", $this->td), $id)));
          }
        }

        die();
      }
    }
    public function admin_menu() {
      add_submenu_page("wpcf7", $this->title_w, $this->title, "manage_options", $this->db_slug, array($this, 'db_container'));
    }
    public function localize_script($the_title) {
      $currentTimestamp = current_time("timestamp");
      $currentDate = date_i18n(get_option('date_format'), $currentTimestamp);
      $currentTime = date_i18n(get_option('time_format'), $currentTimestamp);
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
        "tr_submit"           => _x("Submit", "js-string", $this->td),
        "tr_today"            => _x("Today", "js-string", $this->td),
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

        "str1"    => sprintf(_x("Data Exported via %s", "wc-setting-js", $this->td), "$this->title_w"),
        "str2"    => sprintf(_x("CF7 Database of %s", "wc-setting-js", $this->td), $the_title),
        "str3"    => sprintf(_x("Exported at %s", "wc-setting-js", $this->td), $currentDate),
        "str4"    => "PeproCF7Database-" . date_i18n("YmdHis", current_time("timestamp")),
        "str5"    => sprintf(_x("Exported via %s — Date: %s — Developed by Pepro Dev ( https://pepro.dev/ )", "wc-setting-js", $this->td), $this->title_w, $currentDate),

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
        "tbl177"   => _x("Column visibility", "data-table", $this->td),
        "tbl18"    => _x("Export CSV", "data-table", $this->td),
        "tbl19"    => _x("Export Excel", "data-table", $this->td),
        "tbl20"    => _x("Export PDF", "data-table", $this->td),

      );
    }
    public function db_container() {
      ob_start();
      $this->update_footer_info();
      wp_enqueue_style("{$this->db_slug}", "{$this->assets_url}css/backend.css");
      wp_enqueue_style("{$this->td}_datatable");
      wp_enqueue_style("{$this->td}_jQconfirm");
      wp_enqueue_style("{$this->td}_fontawesome", "//use.fontawesome.com/releases/v5.13.1/css/all.css", array(), '5.13.1', 'all');
      wp_enqueue_script("{$this->td}_jQconfirm");
      wp_enqueue_script("{$this->td}_datatable");
      wp_enqueue_script("{$this->td}_highlight.js");
      wp_enqueue_script("{$this->td}_SrchHighlt");
      wp_register_script("{$this->db_slug}", "{$this->assets_url}/js/backend.js", array('jquery'), null, true);
      is_rtl() and wp_add_inline_style("{$this->db_slug}", ".form-table th {}#wpfooter, #wpbody-content *:not(.dashicons ), #wpbody-content input:not([dir=ltr]), #wpbody-content textarea:not([dir=ltr]), h1.had, .caqpde>b.fa{ font-family: bodyfont, roboto, Tahoma; }");
      global $wpdb;
      $table = $this->db_table;
      $post_per_page = isset($_GET['per_page']) ? abs((int) $_GET['per_page']) : 100;
      $page = isset($_GET['num']) ? abs((int) $_GET['num']) : 1;
      $cf7 = isset($_GET['cf7']) ? abs((int) $_GET['cf7']) : 0;
      $offset = ($page * $post_per_page) - $post_per_page;
      $the_title = get_the_title($cf7);
      $lclz = $this->localize_script($the_title);
      wp_localize_script("{$this->db_slug}", "_i18n", $lclz);
      wp_enqueue_script("{$this->db_slug}");
      $title = $this->title;
      $select = "";
      $posts = get_posts(array('post_type' => 'wpcf7_contact_form', 'numberposts' => -1));
      foreach ($posts as $p) {
        $select .= "&nbsp;<a style='margin-inline-end: 0.5rem;' class='dt-button hrefbtn' href='{$this->url}&cf7=$p->ID' title='$p->post_title (ID #$p->ID)'>$p->post_title</a>";
      }
      if (!$cf7 || 0 == $cf7) {
        echo "<br><div class='notice notice-success peproicon'>
          <h1><strong>" . __("Pepro CF7 Database", $this->td) . "</strong></h1>
            <p>" . sprintf(
          __("This plugin is proudly developed by %s for FREE, you can support us by giving a %s in WordPress", $this->td),
          "<a href='https://pepro.dev/' target='_blank' >" . __("Pepro Dev", $this->td) . "</a>",
          "<a href='https://wordpress.org/support/plugin/pepro-cf7-database/reviews/#new-post' target='_blank'>" . __("five-star review", $this->td) . "</a>"
        ) . "
            </p>
            <p>
              <a class='dt-button hrefbtn' id='emptyDbNow' title='" . esc_attr__("BE CAUTIOUS! ONCE YOU EMPTY THE DATABASE, THERE WILL BE NO WAY BACK!", $this->td) . "' href='javascript:;'>" . _x("Empty Database and All Saved Submission", "setting-general", $this->td) . "</a>
              <a class='dt-button hrefbtn' target='_self' href='" . admin_url("?force-cf7db=yes&nonce=".wp_create_nonce($this->td)) . "'>" . __("Force Re-generate Database", $this->td) . "</a>
            </p>
        </div>
        <div class='notice notice-info'><p>" . __("To view Saved Submission, select a CF7 Form from below list:", $this->td) . "</p><p>$select</p></div>";
        return;
      }
      if ('publish' != get_post_status($cf7)) {
        $breadcrumb = sprintf(
          __('You are here: %s → %s → %s', $this->td),
          "<strong>" . __("Pepro CF7 Database", $this->td) . "</strong>",
          "<a target='_self' href='$this->url'>" . __("Contact Forms", $this->td) . "</a>",
          "<u>$cf7</u>"
        );
        echo "<br><div class='notice notice-error peproicon'>
          <h1><strong>" . __("Pepro CF7 Database", $this->td) . "</strong></h1>
          <p>$breadcrumb</p>
          <p>" . __("Entered CF7 is not valid, Go back and select another Contact form", $this->td) . "</p>
        </div>";
        return;
      }


      $total = $wpdb->get_var("SELECT COUNT(1) FROM $table AS combined_table WHERE `extra_info`='{$cf7}'");
      $res_obj = $wpdb->get_results("SELECT * FROM $table WHERE `extra_info`='{$cf7}' ORDER BY `date_created` DESC LIMIT {$offset}, {$post_per_page}");

      $items_per_page_selceter =
        "<select id='itemsperpagedisplay' name='per_page' style='width:auto !important; margin: 0 0 0 .5rem; float: right;' title='" . __("Items per page", $this->td) . "' >
      <option value='50' " . selected(100, $post_per_page, false) . ">" . sprintf(_x("Show %s items per page", "items_per_page", $this->td), 50) . "</option>
      <option value='100' " . selected(100, $post_per_page, false) . ">" . sprintf(_x("Show %s items per page", "items_per_page", $this->td), 100) . "</option>
      <option value='200' " . selected(200, $post_per_page, false) . ">" . sprintf(_x("Show %s items per page", "items_per_page", $this->td), 200) . "</option>
      <option value='300' " . selected(300, $post_per_page, false) . ">" . sprintf(_x("Show %s items per page", "items_per_page", $this->td), 300) . "</option>
      <option value='400' " . selected(500, $post_per_page, false) . ">" . sprintf(_x("Show %s items per page", "items_per_page", $this->td), 400) . "</option>
      <option value='500' " . selected(500, $post_per_page, false) . ">" . sprintf(_x("Show %s items per page", "items_per_page", $this->td), 500) . "</option>
      <option value='600' " . selected(500, $post_per_page, false) . ">" . sprintf(_x("Show %s items per page", "items_per_page", $this->td), 600) . "</option>
      <option value='700' " . selected(500, $post_per_page, false) . ">" . sprintf(_x("Show %s items per page", "items_per_page", $this->td), 700) . "</option>
      <option value='800' " . selected(500, $post_per_page, false) . ">" . sprintf(_x("Show %s items per page", "items_per_page", $this->td), 800) . "</option>
      <option value='900' " . selected(500, $post_per_page, false) . ">" . sprintf(_x("Show %s items per page", "items_per_page", $this->td), 900) . "</option>
      <option value='1000' " . selected(1000, $post_per_page, false) . ">" . sprintf(_x("Show %s items per page", "items_per_page", $this->td), 1000) . "</option>
      <option disabled>-----------------</option>
      <option value='$total' " . selected($total, $post_per_page, false) . ">" . sprintf(_n("Show your only saved submition", "Show all %s items at once", $total, $this->td), $total) . "</option>
      </select>";
      $empty_now = "";
      if (!empty($wpdb->num_rows)) {
        $empty_now = "<a style='margin-inline-end: 0.5rem;' class='dt-button hrefbtn' id='emptySelectedCf7DB' href='javascript:;' data-rel='$cf7'>" . __("Delete All Submission of Current Contact form", $this->td) . "</a>";
      }
      $breadcrumb = sprintf(
        __('You are here: %s → %s → %s', $this->td),
        "<strong>" . __("Pepro CF7 Database", $this->td) . "</strong>",
        "<a target='_self' href='$this->url'>" . __("Contact Forms", $this->td) . "</a>",
        "<a target='_self' href='" . admin_url("admin.php?page=wpcf7&post=$cf7&action=edit") . "'>$the_title</a>"
      );
      echo "<br><div class='notice notice-success peproicon'><h1><strong>" . __("Pepro CF7 Database", $this->td) . "</strong></h1><p>$breadcrumb</p>$empty_now</div>";

      if (empty($wpdb->num_rows)) {
        echo "<div class='notice notice-error'><p>" . __("Failed Rendering Database! No data saved for this form yet.", $this->td) . "</p></div>";
        $tcona = ob_get_contents();
        ob_end_clean();
        print $tcona;
        return;
      }
      ?>
      <div class='notice notice-info'>
        <div class="wrap">
          <form action="<?php echo admin_url("admin.php?page=cf7db"); ?>" id='mainform'>
            <input type="hidden" name="page" value="cf7db" />
            <input type="hidden" name="num" value="<?= $page; ?>" />
            <input type="hidden" name="cf7" value="<?= $cf7; ?>" />
            <?php
            if (!empty($wpdb->num_rows)) {
              $header = array(
                "_sharp_id"            =>  __('ID', $this->td),
                "date_created"  =>  __('Date Created', $this->td),
              );
              foreach ($res_obj as $obj) {
                $data_array = maybe_unserialize($obj->details);
                if (!$data_array || !is_array($data_array)) { continue; }
                if (isset($data_array["your-email"]) && isset($data_array["your-name"])) {
                  unset($data_array["your-name"]);
                  unset($data_array["your-email"]);
                  $header["your-email"] = __('From', $this->td);
                }
                if (isset($data_array["your-subject"])) {
                  unset($data_array["your-subject"]);
                  $header["your-subject"] = __('Subject', $this->td);
                }
                if (isset($data_array["your-message"])) {
                  unset($data_array["your-message"]);
                  $header["your-message"] = __('Message', $this->td);
                }
                foreach ($data_array as $key => $value) {
                  $filter_header = apply_filters("pepro_cf7db_filter_header", array(
                    // "_wpcf7",
                    // "_wpcf7_version",
                    // "_wpcf7_locale",
                    // "_wpcf7_unit_tag",
                    // "_wpcf7_container_post",
                    "g-recaptcha-response",
                  ));
                  if (!in_array($key, $filter_header)) {
                    if (!$this->startsWith($key, "_wpcf7")) {
                      $header[$key] = ucfirst(str_replace(array("-", "_"), array(" ", " "), $key));
                    }
                  }
                }
              }
              $header["action"] = __('Action', $this->td);
              $header = array_unique($header);
              echo "
              <p><b>" . sprintf(_n("Your very first saved submition is showing below", "%s Saved Submission found", $total, $this->td), $total) . "</b>   {$items_per_page_selceter}</p>
              <table border=\"1\" id=\"exported_data\" class=\"exported_data\">
              <thead>
              <tr>";
              foreach ($header as $key => $value) {
                $extraClass = "";
                if (in_array($key, apply_filters("pepro_cf7db_hide_col_from_export", array("action", "_sharp_id")))) { $extraClass = "noExport"; }
                echo "<th class='th-{$key} $extraClass'>{$value}</th>";
              }
              echo "
              </tr>
              </thead>
              <tfoot>
              <tr>";
              foreach ($header as $key => $value) {
                $extraClass = "";
                if (in_array($key, apply_filters("pepro_cf7db_hide_col_from_export", array("action", "_sharp_id")))) { $extraClass = "noExport"; }
                echo "<th class='th-{$key} $extraClass'>{$value}</th>";
              }
              echo "
              </tr>
              </tfoot>
              <tbody>";
              foreach ($res_obj as $obj) {
                $data_array = unserialize($obj->details);
                echo "<tr class=\"item_{$obj->id}\">";
                foreach ($header as $key => $value) {
                  switch ($key) {
                    case '_sharp_id':
                      $val = $obj->id;
                      echo "<td class='item_{$key} itd_{$obj->id}'>{$val}</th>";
                      break;
                    case 'date_created':
                      $val = "<p>" . date_i18n(get_option('date_format'), strtotime($obj->date_created)) . "</p><p>" . date_i18n(get_option('time_format'), strtotime($obj->date_created)) . "</p>";
                      echo "<td class='item_{$key} itd_{$obj->id}'>{$val}</th>";
                      break;
                    case 'your-email':
                      $name = (isset($data_array['your-name']) ? $data_array['your-name'] : "");
                      $email = (isset($data_array['your-email']) ? $data_array['your-email'] : "");
                      $email = (isset($data_array['your-email']) ? $data_array['your-email'] : "");
                      $val = "{$name}\r\n&lt;{$email}&gt;";
                      echo "<td class='item_{$key} itd_{$obj->id}'>{$val}</th>";
                      break;
                    case 'action':
                      $val = "<a href='javascript:;' title='" . esc_attr__("Delete this specific submition", $this->td) . "' class=\"button delete_item\" data-lid='{$obj->id}' ><span class='dashicons dashicons-trash'></span></a>
                    <span class='spinner loading_{$obj->id}'></span>";
                      echo "<td class='item_{$key} itd_{$obj->id}'>{$val}</th>";
                      break;

                    default:
                      $data = isset($data_array[$key]) ? ($data_array[$key]) : "";
                      if (is_array($data)) { $data = implode(",\r\n", $data); }
                      $val = esc_html(sanitize_textarea_field($data));
                      if (substr($val, 0, strlen("FILEURL:")) === "FILEURL:") {
                        $val = substr($val, strlen("FILEURL:"));
                        $name = pathinfo($val, PATHINFO_FILENAME);
                        $val = "<a href='$val' target='_blank'>$name</a><span style='font-size:0;'> [$val]</span>";
                      }
                      echo "<td class='item_{$key} itd_{$obj->id}'><pre>{$val}</pre></th>";
                      break;
                  }
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
                  'prev_text' => '<span class="button button-primary">' . __('< Previous', $this->td) . "</span>",
                  'next_text' => '<span class="button button-primary">' . __('Next >', $this->td) . "</span>",
                  'total' => ceil($total / $post_per_page),
                  'current' => $page,
                  'before_page_number' => '<span class="button">',
                  'after_page_number' => "</span>",
                  'type' => 'list'
                )
              );
              echo "</div>";
            }
            ?>
          </form>
        </div>
      </div>
      <?php
      $html = ob_get_contents();
      ob_end_clean();
      print $html;
    }
    private function startsWith($string, $startString) {
      $len = strlen($startString);
      return (substr($string, 0, $len) === $startString);
    }
    public function admin_enqueue_scripts($hook) {

      wp_enqueue_style("{$this->db_slug}-backend-all", "{$this->assets_url}css/backend-all.css", array(), '1.0', 'all');

      wp_register_style("{$this->td}_select2",       "{$this->assets_url}css/select2.min.css", false, "4.1.0", "all");
      wp_register_script("{$this->td}_select2",      "{$this->assets_url}js/select2.min.js", array("jquery"), "4.1.0", true);

      wp_register_style("{$this->td}_jQconfirm",     "{$this->assets_url}css/jquery-confirm.css", false, "4.1.0", "all");
      wp_register_script("{$this->td}_jQconfirm",    "{$this->assets_url}js/jquery-confirm.js", array("jquery"), "4.1.0", true);

      wp_register_style("{$this->td}_datatable",     "{$this->assets_url}css/jquery.dataTables.min.css", array(), current_time("timestamp"));
      wp_register_script("{$this->td}_datatable",    "{$this->assets_url}js/jquery.dataTables.min.js", array("jquery"), "1.10.21", true);

      wp_register_script("{$this->td}_SrchHighlt",   "{$this->assets_url}js/dataTables.searchHighlight.min.js", array("jquery"), "1.0.1", true);
      wp_register_script("{$this->td}_highlight.js", "{$this->assets_url}js/highlight.js", array("jquery"), "3.0.0", true);
    }
    public function save_cf7_submission($subject, $from, $email, $details, $extra_info) {
      global $wpdb;
      $inserted = $wpdb->insert($this->db_table, array(
        "from"       => esc_html(sanitize_text_field(wp_strip_all_tags($from))),
        "subject"    => esc_html(sanitize_text_field(wp_strip_all_tags($subject))),
        "email"      => esc_html(sanitize_text_field(wp_strip_all_tags($email))),
        "details"    => $details,
        "extra_info" => esc_html(sanitize_textarea_field(wp_strip_all_tags($extra_info))),
      ), '%s');
      return $inserted;
    }
  }
  /**
   * load plugin and load textdomain then set a global variable to access plugin class!
   *
   * @version 1.0.0
   * @since   1.0.0
   * @license https://pepro.dev/license Pepro.dev License
   */
  add_action("plugins_loaded", function () {
    global $cf7Database;
    $cf7Database = new cf7Database;
    register_activation_hook(__FILE__, array("cf7Database", "activation_hook"));
  });
}
/*##################################################
Lead Developer: [amirhp-com](https://amirhp.com/)
##################################################*/