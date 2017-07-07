<?php

/**
 * Plugin Name: Permalinks Customizer
 * Version: 0.3.6
 * Plugin URI: https://wordpress.org/plugins/permalinks-customizer/
 * Description: Set permalinks for default post-type and custom post-type which can be changed from the single post edit page.
 * Author: Sami Ahmed Siddiqui
 * Author URI: http://www.yassglobal.com
 * Text Domain: permalinks-customizer
 * License: GPL v3
 */

/*  Copyright 2008-2015 Michael Tyson <michael@atastypixel.com>

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
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 *  Permalinks Customizer Plugin
 *  Copyright (C) 2016, Sami Ahmed Siddiqui <sami@samisiddiqui.com>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.

 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

function permalinks_customizer_settings_link($links)
{
    $settings_link = '<a href="admin.php?page=permalinks-customizer-settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function permalinks_customizer_menu()
{
    add_menu_page('Set Your Permalinks', 'Permalinks Customizer', 'administrator', 'permalinks-customizer-settings', 'permalinks_customizer_options_page');
    add_submenu_page('permalinks-customizer-settings', 'Set Your Permalinks', 'Set Permanlinks', 'administrator', 'permalinks-customizer-settings', 'permalinks_customizer_options_page');
    add_submenu_page('permalinks-customizer-settings', 'Structure Tags', 'Structure Tags', 'administrator', 'permalinks-customizer-tags', 'permalinks_customizer_tags_page');
    add_submenu_page('permalinks-customizer-settings', 'Convert Custom Permalink', 'Convert Custom Permalink', 'administrator', 'permalinks-customizer-convert-url', 'permalinks_customizer_convert_url');
    add_action('admin_init', 'register_permalinks_customizer_settings');
}

function register_permalinks_customizer_settings()
{
    $post_types = get_post_types('', 'names');
    foreach ($post_types as $post_type) {
        if ($post_type == 'revision' || $post_type == 'nav_menu_item' || $post_type == 'attachment') {
            continue;
        }
        register_setting('permalinks-customizer-settings-group', 'permalinks_customizer_'.$post_type);
    }
}

function permalinks_customizer_options_page()
{
    $post_types = get_post_types('', 'objects');
    echo '<div class="wrap">';
    echo '<h2>Set Your Permalinks Settings</h2>';
    echo '<div>
          <p>Define the Permalinks for each post type. You can define different structures for each post type.</p>
          <p>Please check all the <a href="'.site_url().'/wp-admin/admin.php?page=permalinks-customizer-tags" title="structured tags">structured tags</a> which can be used with this plugin, <a href="'.site_url().'/wp-admin/admin.php?page=permalinks-customizer-tags" title="here">here</a>.</p>
        </div>';
    echo '<form method="post" action="options.php">';
    settings_fields('permalinks-customizer-settings-group');
    do_settings_sections('permalinks-customizer-settings-group');
    echo '<table class="form-table">';
    foreach ($post_types as $post_type) {
        if ($post_type->name == 'revision' || $post_type->name == 'nav_menu_item' || $post_type->name == 'attachment') {
            continue;
        }
        $perm_struct = 'permalinks_customizer_'.$post_type->name;
        echo '<tr valign="top">
                  <th scope="row">'.$post_type->labels->name.'</th>
                  <td>'.site_url().'/<input type="text" name="'.$perm_struct.'" value="'.esc_attr(get_option($perm_struct)) .'" class="regular-text" /></td>
               </tr>';
    }
    echo '</table>';
    echo '<p><b>Note:</b> Use trailing slash only if it has been set in the <a href="options-permalink.php">permalink structure</a>.</p>';
    submit_button();
    echo '</form>';
    echo '</div>';
}

function permalinks_customizer_customization($post_id, $post, $update)
{
    if ($post_id == get_option('page_on_front')) {
        return;
    }
    $get_permalink = esc_attr(get_option('permalinks_customizer_'.$post->post_type));
    if (empty($get_permalink)) {
        $get_permalink = esc_attr(get_option('permalink_structure'));
    }
    if ($post->post_status == 'publish') {
        $set_permalink = permalinks_customizer_replace_tags($post_id, $post, $get_permalink);
        global $wpdb;
        $permalink = $set_permalink;
        $trailing_slash = substr($permalink, -1);
        if ($trailing_slash == '/') {
            $permalink = rtrim($permalink, '/');
            $set_permalink = rtrim($set_permalink, '/');
        }
        $qry = "SELECT * FROM $wpdb->postmeta WHERE meta_key = 'permalink_customizer' AND meta_value = '".$permalink."' AND post_id != ".$post_id." OR meta_key = 'permalink_customizer' AND meta_value = '".$permalink."/' AND post_id != ".$post_id." AND post_name != ".$post->post_name." LIMIT 1";
        $check_exist_url = $wpdb->get_results($qry);
        if (!empty($check_exist_url)) {
            $i = 2;
            while (1) {
                $permalink = $set_permalink.'-'.$i;
                $qry = "SELECT * FROM $wpdb->postmeta WHERE meta_key = 'permalink_customizer' AND meta_value = '".$permalink."' AND post_id != ".$post_id." OR meta_key = 'permalink_customizer' AND meta_value = '".$permalink."/' AND post_id != ".$post_id." LIMIT 1";
                $check_exist_url = $wpdb->get_results($qry);
                if (empty($check_exist_url)) {
                    break;
                }
                $i++;
            }
        }
        if ($trailing_slash == '/') {
            $permalink = $permalink.'/';
        }
        if (strpos($permalink, "/") == 0) {
            $permalink = substr($permalink, 1);
        }
        update_post_meta($post_id, 'permalink_customizer', $permalink);
    } else {
        permalinks_customizer_delete_permalink($post_id);
    }
}

function permalinks_customizer_delete_permalink($id)
{
    global $wpdb;
    $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->postmeta WHERE meta_key = 'permalink_customizer' AND post_id = %d", $id));
}

function permalinks_customizer_tags_page()
{
    $html = '<div class="wrap">';
    $html .= '<h2>Structure Tags</h2>';
    $html .= '<div>These tags can be used to create Permalink Customizers for each post type.</div>';
    $html .= '<table class="form-table">';
    $html .= '<tr valign="top">
                <th scope="row">%title%</th>
                <td>Title of the post. let&#039;s say the title is "This Is A Great Post!" so, it becomes this-is-a-great-post in the URI.</td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%year%</th>
                <td>The year of the post, four digits, for example 2004</td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%monthnum%</th>
                <td>Month of the year, for example 05</td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%day%</th>
                <td>Day of the month, for example 28</td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%hour%</th>
                <td>Hour of the day, for example 15</td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%minute%</th>
                <td>Minute of the hour, for example 43</td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%second%</th>
                <td>Second of the minute, for example 33</td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%post_id%</th>
                <td>The unique ID # of the post, for example 423</td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%postname%</th>
                <td>A sanitized version of the title of the post (post slug field on Edit Post/Page panel). So "This Is A Great Post!" becomes this-is-a-great-post in the URI.</td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%category%</th>
                <td>A sanitized version of the category name (category slug field on New/Edit Category panel). Nested sub-categories appear as nested directories in the URI.</td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%child-category%</th>
                <td>A sanitized version of the category name (category slug field on New/Edit Category panel).</td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%product_cat%</th>
                <td>A sanitized version of the product category name (category slug field on New/Edit Category panel). Nested sub-categories appear as nested directories in the URI. <i>This <strong>tag</strong> is specially used for WooCommerce Products.</i></td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%author%</th>
                <td>A sanitized version of the author name.</td>
             </tr>';
    $html .= '<tr valign="top">
                <th scope="row">%category-permalink%</th>
                <td>A sanitized version of the category permalink (category permalink field on New/Edit Category panel).</td>
             </tr>';
    $html .= '</table>';
    $html .= '<p><b>Note:</b> "%postname%" is similar as of the "%title%" tag but the difference is that "%postname%" can only be set once whereas "%title%" can be changed. let&#039;s say the title is "This Is A Great Post!" so, it becomes "this-is-a-great-post" in the URI(At the first time, "%postname%" and "%title%" works same) but if you edit and change title let&#039;s say "This Is A WordPress Post!" so, "%postname%" in the URI remains same "this-is-a-great-post" whereas "%title%" in the URI becomes "this-is-a-wordpress-post" </p>';
    $html .= '</div>';
    echo $html;
}

function permalinks_customizer_static_page($prev_home_page_id, $new_home_page_id)
{
    permalinks_customizer_delete_permalink($new_home_page_id);
}

function permalinks_customizer_post_link($permalink, $post)
{
    $permalinks_customizer = get_post_meta($post->ID, 'permalink_customizer', true);
    if ($permalinks_customizer) {
        return home_url($permalinks_customizer);
    }

    return $permalink;
}

function permalinks_customizer_page_link($permalink, $page)
{
    $permalinks_customizer = get_post_meta($page, 'permalink_customizer', true);
    if ($permalinks_customizer) {
        return home_url($permalinks_customizer);
    }

    return $permalink;
}

function permalinks_customizer_redirect()
{
    $url = parse_url(get_bloginfo('url'));
    $url = isset($url['path']) ? $url['path'] : '';
    $request = ltrim(substr($_SERVER['REQUEST_URI'], strlen($url)), '/');
    if (($pos=strpos($request, "?"))) {
        $request = substr($request, 0, $pos);
    }

    global $wp_query;

    $permalinks_customizer = '';
    $original_permalink = '';

    if (is_single() || is_page()) {
        $post = $wp_query->post;
        $permalinks_customizer = get_post_meta($post->ID, 'permalink_customizer', true);
        $original_permalink = ($post->post_type == 'page' ? permalinks_customizer_original_page_link($post->ID) : permalinks_customizer_original_post_link($post->ID));
    } elseif (is_tag() || is_category()) {
        $theTerm = $wp_query->get_queried_object();
        $permalinks_customizer  = permalinks_customizer_permalink_for_term($theTerm->term_id);
        $original_permalink = (is_tag() ? permalinks_customizer_original_tag_link($theTerm->term_id) : permalinks_customizer_original_category_link($theTerm->term_id));
    }
    if ($permalinks_customizer && (substr($request, 0, strlen($permalinks_customizer)) != $permalinks_customizer || $request == $permalinks_customizer."/")) {
        $url = $permalinks_customizer;

        if (substr($request, 0, strlen($original_permalink)) == $original_permalink && trim($request, '/') != trim($original_permalink, '/')) {
            $url = preg_replace('@//*@', '/', str_replace(trim($original_permalink, '/'), trim($permalinks_customizer, '/'), $request));
            $url = preg_replace('@([^?]*)&@', '\1?', $url);
        }

        $url .= strstr($_SERVER['REQUEST_URI'], "?");

        wp_redirect(home_url($url), 301);
        exit();
    }
}

function permalinks_customizer_request($query)
{
    if (is_admin()) {
        return $query;
    }

    global $wpdb;
    global $_CPRegisteredURL;
    $originalUrl = null;
    $term = null;
    $url = parse_url(get_bloginfo('url'));
    $url = isset($url['path']) ? $url['path'] : '';
    $request = ltrim(substr($_SERVER['REQUEST_URI'], strlen($url)), '/');
    $request = (($pos=strpos($request, '?')) ? substr($request, 0, $pos) : $request);
    $request_noslash = preg_replace('@/+@', '/', trim($request, '/'));
    if (!$request) {
        return $query;
    }

    $sql = $wpdb->prepare("SELECT $wpdb->posts.ID, $wpdb->postmeta.meta_value, $wpdb->posts.post_type, $wpdb->posts.post_status FROM $wpdb->posts  ".
              "LEFT JOIN $wpdb->postmeta ON ($wpdb->posts.ID = $wpdb->postmeta.post_id) WHERE ".
              "  meta_key = 'permalink_customizer' AND ".
              "  meta_value != '' AND ".
              "  ( LOWER(meta_value) = LEFT(LOWER('%s'), LENGTH(meta_value)) OR ".
              "    LOWER(meta_value) = LEFT(LOWER('%s'), LENGTH(meta_value)) ) ".
              "  AND post_status != 'trash' AND post_type != 'nav_menu_item'".
              " ORDER BY LENGTH(meta_value) DESC, ".
              " FIELD(post_status,'publish','private','draft','auto-draft','inherit'),".
              " FIELD(post_type,'post','page'),".
              "$wpdb->posts.ID ASC  LIMIT 1",
               $request_noslash,
               $request_noslash."/"
            );
    $posts = $wpdb->get_results($sql);

    if ($posts) {
        if ($request_noslash == trim($posts[0]->meta_value, '/')) {
            $_CPRegisteredURL = $request;
        }

        if ($posts[0]->post_status == 'draft') {
            if ($posts[0]->post_type == 'page') {
                $originalUrl = "?page_id=" . $posts[0]->ID;
            } else {
                $originalUrl = "?p=" . $posts[0]->ID;
            }
        } else {
            $originalUrl =  preg_replace('@/+@', '/', str_replace(trim(strtolower($posts[0]->meta_value), '/'),
                      ($posts[0]->post_type == 'page' ?
                      permalinks_customizer_original_page_link($posts[0]->ID)
                      : permalinks_customizer_original_post_link($posts[0]->ID)),
                      strtolower($request_noslash)));
        }
    }
    if ($originalUrl === null) {
        $table = get_option('permalinks_customizer_table');
        if (!$table) {
            return $query;
        }
        foreach (array_keys($table) as $permalink) {
            if ($permalink == substr($request_noslash, 0, strlen($permalink)) || $permalink == substr($request_noslash."/", 0, strlen($permalink))) {
                $term = $table[$permalink];
                if ($request_noslash == trim($permalink, '/')) {
                    $_CPRegisteredURL = $request;
                }
                if ($term['kind'] == 'category') {
                    $originalUrl = str_replace(trim($permalink, '/'), permalinks_customizer_original_category_link($term['id']), trim($request, '/'));
                } else {
                    $originalUrl = str_replace(trim($permalink, '/'), permalinks_customizer_original_tag_link($term['id']), trim($request, '/'));
                }
            }
        }
    }

    if ($originalUrl !== null) {
        $originalUrl = str_replace('//', '/', $originalUrl);

        if (($pos=strpos($_SERVER['REQUEST_URI'], '?')) !== false) {
            $queryVars = substr($_SERVER['REQUEST_URI'], $pos+1);
            $originalUrl .= (strpos($originalUrl, '?') === false ? '?' : '&') . $queryVars;
        }
        $oldRequestUri = $_SERVER['REQUEST_URI'];
        $oldQueryString = $_SERVER['QUERY_STRING'];
        $_SERVER['REQUEST_URI'] = '/'.ltrim($originalUrl, '/');
        $_SERVER['QUERY_STRING'] = (($pos=strpos($originalUrl, '?')) !== false ? substr($originalUrl, $pos+1) : '');
        parse_str($_SERVER['QUERY_STRING'], $queryArray);
        $oldValues = array();
        if (is_array($queryArray)) {
            foreach ($queryArray as $key => $value) {
                $oldValues[$key] = $_REQUEST[$key];
                $_REQUEST[$key] = $_GET[$key] = $value;
            }
        }
        remove_filter('request', 'permalinks_customizer_request', 'edit_files', 1);

        global $wp;
        $wp->parse_request();
        $query = $wp->query_vars;
        add_filter('request', 'permalinks_customizer_request', 'edit_files', 1);
        $_SERVER['REQUEST_URI'] = $oldRequestUri;
        $_SERVER['QUERY_STRING'] = $oldQueryString;
        foreach ($oldValues as $key => $value) {
            $_REQUEST[$key] = $value;
        }

        if (!is_null($term) && $term['kind'] === 'category') {
            unset($query['name']);
            unset($query['pagename']);
            $query['category_name'] = permalinks_customizer_category_slug_tree($term['id'], '/');
        }
    }

    return $query;
}

function permalinks_customizer_get_sample_permalink_html($html, $id, $new_title, $new_slug)
{
    $permalink = get_post_meta($id, 'permalink_customizer', true);
    $post = get_post($id);

    ob_start(); ?>
    <?php permalinks_customizer_form($permalink, ($post->post_type == "page" ? permalinks_customizer_original_page_link($id) : permalinks_customizer_original_post_link($id)), false); ?>
<?php
   $content = ob_get_contents();
    ob_end_clean();
    if ($post->post_type == 'attachment' || $post->ID == get_option('page_on_front')) {
        return $html;
    }
    if ('publish' == $post->post_status) {
        $view_post = 'page' == $post->post_type ? __('View Page', 'permalinks-customizer') : __('View '.ucfirst($post->post_type), 'permalinks-customizer');
    }

    if (preg_match("@view-post-btn.*?href='([^']+)'@s", $html, $matches)) {
        $permalink = $matches[1];
    } else {
        list($permalink, $post_name) = get_sample_permalink($post->ID, $new_title, $new_slug);
        if (false !== strpos($permalink, '%postname%') || false !== strpos($permalink, '%pagename%')) {
            $permalink = str_replace(array('%pagename%', '%postname%'), $post_name, $permalink);
        }
    }

    return '<strong>' . __('Permalink:', 'permalinks-customizer') . "</strong>\n" . $content .
       (isset($view_post) ? "<span id='view-post-btn'><a href='$permalink' class='button button-small' target='_blank'>$view_post</a></span>\n" : "");
}

function permalinks_customizer_term_options($object)
{
    if (isset($object) && isset($object->term_id)) {
        $permalink = permalinks_customizer_permalink_for_term($object->term_id);

        if ($object->term_id) {
            $originalPermalink = ($object->taxonomy == 'post_tag' ? permalinks_customizer_original_tag_link($object->term_id) : permalinks_customizer_original_category_link($object->term_id));
        }

        permalinks_customizer_form($permalink, $originalPermalink);

        wp_enqueue_script('jquery');
?>
    <script type="text/javascript">
    jQuery(document).ready(function() {
      var button = jQuery('#permalinks_customizer_form').parent().find('.submit');
      button.remove().insertAfter(jQuery('#permalinks_customizer_form'));
    });
    </script>
<?php

    }
}

function permalinks_customizer_original_tag_link($tag_id)
{
    remove_filter('tag_link', 'permalinks_customizer_term_link', 'edit_files', 2);
    // remove_filter('user_trailingslashit', 'permalinks_customizer_trailingslash', 'edit_files', 2);
    $originalPermalink = ltrim(str_replace(home_url(), '', get_tag_link($tag_id)), '/');
    // add_filter('user_trailingslashit', 'permalinks_customizer_trailingslash', 'edit_files', 2);
    add_filter('tag_link', 'permalinks_customizer_term_link', 'edit_files', 2);
    return $originalPermalink;
}

function permalinks_customizer_original_category_link($category_id)
{
    remove_filter('category_link', 'permalinks_customizer_term_link', 'edit_files', 2);
    // remove_filter('user_trailingslashit', 'permalinks_customizer_trailingslash', 'edit_files', 2);
    $originalPermalink = ltrim(str_replace(home_url(), '', get_category_link($category_id)), '/');
    // add_filter('user_trailingslashit', 'permalinks_customizer_trailingslash', 'edit_files', 2);
    add_filter('category_link', 'permalinks_customizer_term_link', 'edit_files', 2);
    return $originalPermalink;
}

function permalinks_customizer_term_link($permalink, $term)
{
    $table = get_option('permalinks_customizer_table');
    if (is_object($term)) {
        $term = $term->term_id;
    }

    $permalinks_customizer = permalinks_customizer_permalink_for_term($term);
    if ($permalinks_customizer) {
        return home_url($permalinks_customizer);
    }
    return $permalink;
}

function permalinks_customizer_permalink_for_term($id)
{
    $table = get_option('permalinks_customizer_table');
    if ($table) {
        foreach ($table as $link => $info) {
            if ($info['id'] == $id) {
                return $link;
            }
        }
    }
    return false;
}

function permalinks_customizer_save_tag($id)
{
    if (!isset($_REQUEST['permalinks_customizer_edit']) || isset($_REQUEST['post_ID'])) {
        return;
    }
    $newPermalink = ltrim(stripcslashes($_REQUEST['permalinks_customizer']), "/");

    if ($newPermalink == permalinks_customizer_original_tag_link($id)) {
        $newPermalink = '';
    }

    $term = get_term($id, 'post_tag');
    permalinks_customizer_save_term($term, str_replace('%2F', '/', urlencode($newPermalink)));
}

function permalinks_customizer_save_category($id)
{
    if (!isset($_REQUEST['permalinks_customizer_edit']) || isset($_REQUEST['post_ID'])) {
        return;
    }
    $newPermalink = ltrim(stripcslashes($_REQUEST['permalinks_customizer']), "/");

    if ($newPermalink == permalinks_customizer_original_category_link($id)) {
        $newPermalink = '';
    }

    $term = get_term($id, 'category');

   /**
    * Filters create permalink when category saved.
    *
    * @param string $newPermalink permalink
    * @param WP_Term $term category object
    * @return string the permalink which is filtered
    */
    $newPermalink = apply_filters('permalinks_customizer_save_category', $newPermalink, $term);

    permalinks_customizer_save_term($term, str_replace('%2F', '/', urlencode($newPermalink)));
}

function permalinks_customizer_save_term($term, $permalink)
{
    permalinks_customizer_delete_term($term->term_id);
    $table = get_option('permalinks_customizer_table');
    if ($permalink) {
        $table[$permalink] = array(
                              'id' => $term->term_id,
                              'kind' => ($term->taxonomy == 'category' ? 'category' : 'tag'),
                              'slug' => $term->slug
                           );
    }

    update_option('permalinks_customizer_table', $table);
}

function permalinks_customizer_delete_term($id)
{
    $table = get_option('permalinks_customizer_table');
    if ($table) {
        foreach ($table as $link => $info) {
            if ($info['id'] == $id) {
                unset($table[$link]);
                break;
            }
        }
    }

    update_option('permalinks_customizer_table', $table);
}

function permalinks_customizer_trailingslash($string, $type)
{
    global $_CPRegisteredURL;

    $url = parse_url(get_bloginfo('url'));
    $request = ltrim(isset($url['path']) ? substr($string, strlen($url['path'])) : $string, '/');

    if (!trim($request)) {
        return $string;
    }

    if (trim($_CPRegisteredURL, '/') == trim($request, '/')) {
        if (isset($url['path'])) {
            return ($string{0} == '/' ? '/' : '') . trailingslashit($url['path']) . $_CPRegisteredURL;
        } else {
            return ($string{0} == '/' ? '/' : '') . $_CPRegisteredURL;
        }
    }
    return $string;
}

function permalinks_customizer_form($permalink, $original="", $renderContainers=true)
{
?>
    <input value="true" type="hidden" name="permalinks_customizer_edit" />
    <input value="<?php echo htmlspecialchars(urldecode($permalink)) ?>" type="hidden" name="permalinks_customizer" id="permalinks_customizer" />

    <?php if ($renderContainers) : ?>
    <table class="form-table" id="permalinks_customizer_form">
    <tr>
        <th scope="row"><?php _e('Permalink', 'permalinks-customizer') ?></th>
            <td>
    <?php endif; ?>
                <?php echo home_url() ?>/
                <span id="editable-post-name" title="Click to edit this part of the permalink">
                    <input type="text" id="new-post-slug" class="text" value="<?php echo htmlspecialchars($permalink ? urldecode($permalink) : urldecode($original)) ?>"
                        style="width: 250px; <?php if (!$permalink) { echo 'color: #ddd;'; } ?>"
                        onfocus="if ( this.style.color = '#ddd' ) { this.style.color = '#000'; }"
                        onblur="document.getElementById('permalinks_customizer').value = this.value; if ( this.value == '' || this.value == '<?php echo htmlspecialchars(urldecode($original)) ?>' ) { this.value = '<?php echo htmlspecialchars(urldecode($original)) ?>'; this.style.color = '#ddd'; }"/>
                </span>
    <?php if ($renderContainers) : ?>
                <br />
                <small><?php _e('Leave blank to disable', 'permalinks-customizer') ?></small>
            </td>
        </tr>
    </table>
    <?php endif;
}

function permalinks_customizer_replace_tags($post_id, $post, $replace_tag)
{
    $date = new DateTime($post->post_date);
    if (strpos($replace_tag, "%title%") !== false) {
        $title = sanitize_title($post->post_title);
        $replace_tag = str_replace('%title%', $title, $replace_tag);
    }
    if (strpos($replace_tag, "%year%") !== false) {
        $year = $date->format('Y');
        $replace_tag = str_replace('%year%', $year, $replace_tag);
    }
    if (strpos($replace_tag, "%monthnum%") !== false) {
        $month = $date->format('m');
        $replace_tag = str_replace('%monthnum%', $month, $replace_tag);
    }
    if (strpos($replace_tag, "%day%") !== false) {
        $day = $date->format('d');
        $replace_tag = str_replace('%day%', $day, $replace_tag);
    }
    if (strpos($replace_tag, "%hour%") !== false) {
        $hour = $date->format('H');
        $replace_tag = str_replace('%hour%', $hour, $replace_tag);
    }
    if (strpos($replace_tag, "%minute%") !== false) {
        $minute = $date->format('i');
        $replace_tag = str_replace('%minute%', $minute, $replace_tag);
    }
    if (strpos($replace_tag, "%second%") !== false) {
        $second = $date->format('s');
        $replace_tag = str_replace('%second%', $second, $replace_tag);
    }
    if (strpos($replace_tag, "%post_id%") !== false) {
        $replace_tag = str_replace('%post_id%', $post_id, $replace_tag);
    }
    if (strpos($replace_tag, "%postname%") !== false) {
        /**
         * Filters replace postname tag.
         *
         * @param string $replaced_tag replaced_tag after replacing
         * @param string $slug post slug
         * @param int $post_id post ID
         * @param WP_Post post object
         * @param string $replace_tag permalink
         * @return string replace_tag after replacing
         */
        $replace_tag = apply_filters(
            'permalinks_customizer_replace_postname_tag',
            str_replace('%postname%', $post->post_name, $replace_tag),
            $post->post_name,
            $post_id,
            $post,
            $replace_tag
        );
    }
    if (strpos($replace_tag, "%category%") !== false) {
        $category = permalinks_customizer_create_category_link($post_id, $post);
        $replace_tag = str_replace('%category%', $category, $replace_tag);
    }
    if (strpos($replace_tag, "%child-category%") !== false) {
        $categories = get_the_category($post_id);
        $total_cat = count($categories);
        $tid = 1;
        if ($total_cat > 0) {
            $tid = '';
            foreach ($categories as $cat) {
                if ($cat->term_id < $tid || empty($tid)) {
                    $tid = $cat->term_id;
                }
            }
        }
        $term_category = get_term($tid);
        $category = $term_category->slug;
        $replace_tag = str_replace('%child-category%', $category, $replace_tag);
    }
    if (strpos($replace_tag, "%product_cat%") !== false) {
        $categories = get_the_terms($post_id, 'product_cat');
        $total_cat = count($categories);
        $tid = 1;
        if ($total_cat > 0) {
            $tid = '';
            foreach ($categories as $cat) {
                if ($cat->term_id < $tid || empty($tid)) {
                    $tid = $cat->term_id;
                    $pid = '';
                    if (!empty($cat->parent)) {
                        $pid = $cat->parent;
                    }
                }
            }
        }
        $term_category = get_term($tid);
        $category = $term_category->slug;
        if (!empty($pid)) {
            $parent_category = get_term($pid);
            $category = $parent_category->slug.'/'.$category;
        }
        $replace_tag = str_replace('%product_cat%', $category, $replace_tag);
    }
    if (strpos($replace_tag, "%author%") !== false) {
        $author = get_the_author_meta('user_login', $post->post_author);
        $replace_tag = str_replace('%author%', $author, $replace_tag);
    }
    // add new tag "%category-permalink%"
    // use permalink of the category
    if (strpos($replace_tag, "%category-permalink%") !== false) {
        $category_permalink = '';
        $categories = get_the_category($post_id);

        if ($categories) {
            $categories = wp_list_sort($categories, array('term_id' => 'ASC'));

            $category_object = apply_filters('post_link_category', $categories[0], $categories, $post);
            $category_object = get_term($category_object, 'category');

            $category_permalink = untrailingslashit(permalinks_customizer_permalink_for_term($category_object->term_id));

            if (empty($category_permalink)) {
                $category_permalink = permalinks_customizer_create_category_link($post_id, $post);
            }
        }
        $replace_tag = str_replace('%category-permalink%', $category_permalink, $replace_tag);
    }
    return $replace_tag;
}

function permalinks_customizer_create_category_link($post_id, $post)
{
    $category = '';
    $categories = get_the_category($post_id);

    if ($categories) {
        $categories = wp_list_sort($categories, array('term_id' => 'ASC'));

        $category_object = apply_filters('post_link_category', $categories[0], $categories, $post);

        $category_object = get_term($category_object, 'category');
        $category = $category_object->slug;

        if ($parent = $category_object->parent) {
            $category = get_category_parents($parent, false, '/', true) . $category;
        }
    }

    return $category;
}

function permalinks_customizer_original_post_link($post_id)
{
    remove_filter('post_link', 'permalinks_customizer_post_link', 'edit_files', 2); // original hook
    remove_filter('post_type_link', 'permalinks_customizer_post_link', 'edit_files', 2);
    $originalPermalink = ltrim(str_replace(home_url(), '', get_permalink($post_id)), '/');
    add_filter('post_link', 'permalinks_customizer_post_link', 'edit_files', 2); // original hook
    add_filter('post_type_link', 'permalinks_customizer_post_link', 'edit_files', 2);
    return $originalPermalink;
}

function permalinks_customizer_original_page_link($post_id)
{
    remove_filter('page_link', 'permalinks_customizer_page_link', 'edit_files', 2);
    $originalPermalink = ltrim(str_replace(home_url(), '', get_permalink($post_id)), '/');
    add_filter('page_link', 'permalinks_customizer_page_link', 'edit_files', 2);
    return $originalPermalink;
}

function permalinks_customizer_convert_url()
{
    global $wpdb;
    $plugin_slug = 'permalinks-customizer-convert-url';
    $step        = isset($_GET['processing']) ? absint($_GET['processing']) : 1;
    $steps       = isset($_GET['limit']) ? $_GET['limit'] : 0;
    $data        = $wpdb->get_row("SELECT meta_id from $wpdb->postmeta where meta_key = 'custom_permalink' LIMIT 1");
    echo '<div class="wrap"><h2>'.esc_html(get_admin_page_title()).'</h2>';
    if (isset($_GET['processing'])) :
        if (isset($data) && !empty($data)) {
            $wpdb->query($wpdb->prepare("UPDATE $wpdb->postmeta SET meta_key = 'permalink_customizer' where meta_id = %d ", $data->meta_id));
            echo '<p>The batch update routine has started. Please be patient as this may take some time to complete <img class="conversion-in-process" src="'.includes_url('images/spinner-2x.gif').'" alt="Loading..." ) width="20px" height="20px" style="vertical-align:bottom" /></p>';
            echo '<p class="processing"><strong>Converting '.(int) $step.' out of '.(int) $steps.' custom permalinks</strong></p>';
?>
            <script type="text/javascript">
            jQuery(document).ready(function($){
                $.post(
                    ajaxurl,
                    {
                        action: 'permalinks-customizer-convert-url',
                        processing: '<?php echo $step; ?>',
                        limit: '<?php echo absint($_GET["limit"]); ?>'
                    },
                    function(res){
                        var step = '<?php echo $step; ?>';
                        var total = '<?php echo $steps; ?>';
                        if ( step == total ) {
                            $('.conversion-in-process').remove();
                            window.location = window.location.pathname="?page=permalinks-customizer-convert-url&processed="+total;
                            return;
                        } else {
                            document.location.href = '<?php echo add_query_arg(array( "page" => $plugin_slug, "processing" => (int) $step + 1, "limit" => absint($_GET["limit"]) )); ?>';
                        }
                    },
                    'json'
                );
            });
            </script>
<?php
        } else {
?>
            <script type="text/javascript">
            jQuery(document).ready(function($){
               window.location = window.location.pathname="?page=permalinks-customizer-convert-url&processed=<?php echo $step; ?>&no-permalink=1";
            });
            </script>
<?php
        }
    else :
        if ($_GET["no-permalink"] == 1) {
            $completed = $_GET["processed"] - 1;
            $cat_data = $wpdb->get_row("SELECT option_id from $wpdb->options where option_name LIKE '%custom_permalink_table%' ");
            if (isset($cat_data) && !empty($cat_data)) {
                $wpdb->query($wpdb->prepare("UPDATE $wpdb->options SET option_name = 'permalinks_customizer_table' where option_id = %d ", $cat_data->option_id));
            }
            echo '<div class="updated"><p>'. $completed .' <strong>Custom Permalink</strong> have been converted to <strong>Permalink Customizer</strong> successfully.</p></div>';
        } elseif ($_GET["processed"] > 0) {
            $cat_data = $wpdb->get_row("SELECT option_id from $wpdb->options where option_name LIKE '%custom_permalink_table%' ");
            if (isset($cat_data) && !empty($cat_data)) {
                $wpdb->query($wpdb->prepare("UPDATE $wpdb->options SET option_name = 'permalinks_customizer_table' where option_id = %d ", $cat_data->option_id));
            }
            echo '<div class="updated"><p>'. $_GET["processed"] .' <strong>Custom Permalink</strong> have been converted to <strong>Permalink Customizer</strong> successfully.</p></div>';
        }
        echo '<p>Click on the "Convert Permalink" button to convert custom permalink to Permalink Customizer. By doing this, all of your previous permalink which was created by custom permalink plugin would be converted to Permalink Customizer.</p>';
        echo '<form id="permalinks-customizer-convert-url" method="get" action="'.add_query_arg('page', 'permalinks-customizer-convert-url').'">';
        echo '<input type="hidden" name="page" value="'.$plugin_slug.'" />';
        echo '<input type="hidden" name="processing" value="1" />';
        echo '<input type="number" name="limit" value="100" />';
        echo '<p><input class="button button-primary" type="submit" name="submit" value="Convert Permalink" /></p>';
        echo '</form>';
    endif;
    echo '</div>';
}

function permalinks_customizer_category_slug_tree($id, $glue)
{
    $category = get_term($id, 'category');
    $parent_category = $category->parent;

    $cat_arr = [$category->slug];
    while(!empty($parent_category))
    {
        $parent = get_term($parent_category, 'category');
        $cat_arr[] = $parent->slug;
        $parent_category = $parent->parent;
    }

    $cat_arr = array_reverse($cat_arr);

    return implode($glue, $cat_arr);
}

if (function_exists("add_action") && function_exists("add_filter")) {
    add_action('template_redirect', 'permalinks_customizer_redirect', 5);
    add_filter('post_link', 'permalinks_customizer_post_link', 'edit_files', 2);
    add_filter('post_type_link', 'permalinks_customizer_post_link', 'edit_files', 2);
    add_filter('page_link', 'permalinks_customizer_page_link', 'edit_files', 2);
    add_filter('tag_link', 'permalinks_customizer_term_link', 'edit_files', 2);
    add_filter('category_link', 'permalinks_customizer_term_link', 0, 2);
    add_filter('request', 'permalinks_customizer_request', 'edit_files', 1);
    // add_filter('user_trailingslashit', 'permalinks_customizer_trailingslash', 'edit_files', 2);

    if (function_exists("get_bloginfo")) {
        $v = explode('.', get_bloginfo('version'));
    }
    if ($v[0] >= 2) {
        add_filter('get_sample_permalink_html', 'permalinks_customizer_get_sample_permalink_html', 'edit_files', 4);
    } else {
        add_action('edit_form_advanced', 'permalinks_customizers_post_options');
        add_action('update_option_page_on_front', 'permalinks_customizer_static_page', 10, 2);
        add_action('edit_page_form', 'permalinks_customizers_page_options');
    }

    add_action('edit_tag_form', 'permalinks_customizer_term_options');
    add_action('add_tag_form', 'permalinks_customizer_term_options');
    add_action('edit_category_form', 'permalinks_customizer_term_options');
    add_action('save_post', 'permalinks_customizer_customization', 10, 3);
    add_action('edited_post_tag', 'permalinks_customizer_save_tag');
    add_action('edited_category', 'permalinks_customizer_save_category');
    add_action('create_post_tag', 'permalinks_customizer_save_tag');
    add_action('create_category', 'permalinks_customizer_save_category');
    add_action('delete_post', 'permalinks_customizer_delete_permalink', 'edit_files');
    add_action('delete_post_tag', 'permalinks_customizer_delete_term');
    add_action('delete_post_category', 'permalinks_customizer_delete_term');
    add_action('admin_menu', 'permalinks_customizer_menu');

    $plugin = plugin_basename(__FILE__);
    add_filter("plugin_action_links_$plugin", 'permalinks_customizer_settings_link');
}
