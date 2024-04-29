<?php

// Prevent loading this file directly and/or if the class is already defined
if ( ! defined('ABSPATH') || class_exists('WP_GitHub_Updater_DPO_Group')) {
    return;
}

/**
 *
 *
 * @version 1.7
 * @author Joachim Kudish <info@jkudish.com>
 * @link http://jkudish.com
 * @package WP_GitHub_Updater_DPO_Group
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright Copyright (c) 2011-2013, Joachim Kudish
 *
 * GNU General Public License, Free Software Foundation
 * <http://creativecommons.org/licenses/GPL/2.0/>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
class WP_GitHub_Updater_DPO_Group
{

    /**
     * GitHub Updater version
     */
    const VERSION = 1.7;

    /**
     *  the config for the updater
     * @access public
     */
    public $config;

    /**
     *  any config that is missing from the initialization of this instance
     * @access public
     */
    public $missing_config;

    /**
     *  temporarily store the data fetched from GitHub, allows us to only load the data once per class instance
     * @access private
     */
    private $github_data;

    /**
     * Class Constructor
     *
     * @param array $config the configuration required for the updater to work
     *
     * @return void
     * @see has_minimum_config()
     * @since 1.0
     */
    public function __construct($config = array())
    {
        $defaults = array(
            'slug'               => plugin_basename(__FILE__),
            'proper_folder_name' => dirname(plugin_basename(__FILE__)),
            'sslverify'          => true,
            'access_token'       => '',
        );

        $this->config = wp_parse_args($config, $defaults);

        // if the minimum config isn't set, issue a warning and bail
        if ( ! $this->has_minimum_config()) {
            $message = 'The GitHub Updater was initialized without the minimum required configuration, please check the config in your plugin. The following params are missing: ';
            $message .= implode(',', $this->missing_config);
            _doing_it_wrong(__CLASS__, $message, self::VERSION);

            return;
        }

        $this->set_defaults();

        add_filter('pre_set_site_transient_update_plugins', array($this, 'api_check'));

        // Hook into the plugin details screen
        add_filter('plugins_api', array($this, 'get_plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'upgrader_post_install'), 10, 3);

        // set timeout
        add_filter('http_request_timeout', array($this, 'http_request_timeout'));

        // set sslverify for zip download
        add_filter('http_request_args', array($this, 'http_request_sslverify'), 10, 2);
    }

    public function has_minimum_config()
    {
        $this->missing_config = array();

        $required_config_params = array(
            'api_url',
            'raw_url',
            'github_url',
            'zip_url',
            'requires',
            'tested',
            'readme',
        );

        foreach ($required_config_params as $required_param) {
            if (empty($this->config[$required_param])) {
                $this->missing_config[] = $required_param;
            }
        }

        return (empty($this->missing_config));
    }

    /**
     * Check whether or not the transients need to be overruled and API needs to be called for every single page load
     *
     * @return bool overrule or not
     */
    public function overrule_transients()
    {
        return (defined('WP_GITHUB_FORCE_UPDATE') && WP_GITHUB_FORCE_UPDATE);
    }

    /**
     * Set defaults
     *
     * @return void
     * @since 1.2
     */
    public function set_defaults()
    {
        if ( ! empty($this->config['access_token'])) {
            // See Downloading a zipball (private repo) https://help.github.com/articles/downloading-files-from-the-command-line
            extract(parse_url($this->config['zip_url'])); // $scheme, $host, $path

            $zip_url = $scheme . '://api.github.com/repos' . $path;
            $zip_url = add_query_arg(array('access_token' => $this->config['access_token']), $zip_url);

            $this->config['zip_url'] = $zip_url;
        }

        if ( ! isset($this->config['raw_response'])) {
            $this->config['raw_response'] = $this->get_raw_response();
        }

        if ( ! isset($this->config['new_version'])) {
            $this->config['new_version'] = $this->get_new_version();
        }

        if ( ! isset($this->config['new_tested'])) {
            $this->config['new_tested'] = $this->get_new_tested();
        }

        if ( ! isset($this->config['icons'])) {
            $this->config['icons'] = $this->get_icons();
        }

        if ( ! isset($this->config['last_updated'])) {
            $this->config['last_updated'] = $this->get_date();
        }

        if ( ! isset($this->config['description'])) {
            $this->config['description'] = $this->get_description();
        }

        if ( ! isset($this->config['changelog'])) {
            $this->config['changelog'] = $this->get_changelog();
        }

        $plugin_data = $this->get_plugin_data();
        if ( ! isset($this->config['plugin_name'])) {
            $this->config['plugin_name'] = $plugin_data['Name'];
        }

        if ( ! isset($this->config['version'])) {
            $this->config['version'] = $plugin_data['Version'];
        }

        if ( ! isset($this->config['author'])) {
            $this->config['author'] = $plugin_data['Author'];
        }

        if ( ! isset($this->config['homepage'])) {
            $this->config['homepage'] = $plugin_data['PluginURI'];
        }

        if ( ! isset($this->config['readme'])) {
            $this->config['readme'] = 'README.md';
        }
    }

    /**
     * Callback fn for the http_request_timeout filter
     *
     * @return int timeout value
     * @since 1.0
     */
    public function http_request_timeout()
    {
        return 2;
    }

    /**
     * Callback fn for the http_request_args filter
     *
     * @param unknown $args
     * @param unknown $url
     *
     * @return mixed
     */
    public function http_request_sslverify($args, $url)
    {
        if ($this->config['zip_url'] == $url) {
            $args['sslverify'] = $this->config['sslverify'];
        }

        return $args;
    }

    /**
     * Get Icons from GitHub
     *
     * @return array $icons the plugin icons
     * @since 1.7
     */
    public function get_icons()
    {
        $asset_url = $this->config['raw_url'] . '/assets/images/';

        return array(
            'default' => $asset_url . 'icon-128x128.png',
            '1x'      => $asset_url . 'icon-128x128.png',
            '2x'      => $asset_url . 'icon-256x256.png',
        );
    }

    /**
     * Get Raw Response from GitHub
     *
     * @return mixed $raw_response the raw response
     * @since 1.7
     */
    public function get_raw_response(): mixed
    {
        return $this->remote_get(trailingslashit($this->config['raw_url']) . basename($this->config['slug']));
    }

    /**
     * Get New Version from GitHub
     *
     * @return int|String $version the version number
     * @since 1.0
     */
    public function get_new_version(): int|String
    {
        $version = get_site_transient(md5($this->config['slug']) . '_new_version');

        if ($this->overrule_transients() || ( ! isset($version) || ! $version || '' == $version)) {
            $raw_response = $this->config['raw_response'];

            if (is_wp_error($raw_response)) {
                $version = false;
            }

            if (is_array($raw_response) && ! empty($raw_response['body'])) {
                preg_match('/.*Version\:\s*(.*)$/mi', $raw_response['body'], $matches);
            }

            if (empty($matches[1])) {
                $version = false;
            } else {
                $version = $matches[1];
            }

            // refresh every 6 hours
            if (false !== $version) {
                set_site_transient(md5($this->config['slug']) . '_new_version', $version, 60 * 60 * 6);
            }
        }

        return $version;
    }

    /**
     * Get New Tested from GitHub
     *
     * @return int|String $tested the tested number
     * @since 1.7
     */
    public function get_new_tested(): int|String
    {
        $tested = get_site_transient(md5($this->config['slug']) . '_new_tested');

        if ($this->overrule_transients() || ( ! isset($tested) || ! $tested || '' == $tested)) {
            $raw_response = $this->config['raw_response'];

            if (is_wp_error($raw_response)) {
                $tested = false;
            }

            if (is_array($raw_response) && ! empty($raw_response['body'])) {
                preg_match('/.*Tested\:\s*(.*)$/mi', $raw_response['body'], $matches);
            }

            $tested = empty($matches[1]) ? $this->config['tested'] : $matches[1];

            // refresh every 6 hours
            if (false !== $tested) {
                set_site_transient(md5($this->config['slug']) . '_new_tested', $tested, 60 * 60 * 6);
            }
        }

        return $tested;
    }

    /**
     * Interact with GitHub
     *
     * @param string $query
     *
     * @return mixed
     * @since 1.6
     */
    public function remote_get($query)
    {
        if ( ! empty($this->config['access_token'])) {
            $query = add_query_arg(array('access_token' => $this->config['access_token']), $query);
        }

        return wp_remote_get($query, array(
            'sslverify' => $this->config['sslverify'],
        ));
    }

    /**
     * Get GitHub Data from the specified repository
     *
     * @return array $github_data the data
     * @since 1.0
     */
    public function get_github_data()
    {
        if (isset($this->github_data) && ! empty($this->github_data)) {
            $github_data_get = $this->github_data;
        } else {
            $github_data_get = get_site_transient(md5($this->config['slug']) . '_github_data');

            if ($this->overrule_transients(
                ) || ( ! isset($github_data_get) || ! $github_data_get || '' == $github_data_get)) {
                $github_data_get = $this->remote_get($this->config['api_url']);

                if (is_wp_error($github_data_get)) {
                    return false;
                }

                $github_data_get = json_decode($github_data_get['body']);

                // refresh every 6 hours
                set_site_transient(md5($this->config['slug']) . '_github_data', $github_data_get, 60 * 60 * 6);
            }

            // Store the data in this class instance for future calls
            $this->github_data = $github_data_get;
        }

        return $github_data_get;
    }

    /**
     * Get update date
     *
     * @return string $date the date
     * @since 1.0
     */
    public function get_date()
    {
        $_date = $this->get_github_data();

        return ( ! empty($_date->updated_at)) ? date('Y-m-d', strtotime($_date->updated_at)) : false;
    }

    /**
     * Get plugin description
     *
     * @return string $description the description
     * @since 1.0
     */
    public function get_description()
    {
        $_description = $this->get_github_data();

        return ( ! empty($_description->description)) ? $_description->description : false;
    }

    /**
     * Get plugin changelog
     *
     * @return string $_changelog the changelog
     * @since 1.0
     */
    public function get_changelog()
    {
        $_changelog = '';
        if ( ! is_wp_error($this->config)) {
            $_changelog = $this->remote_get($this->config['raw_url'] . '/changelog.txt');
        }
        if ( ! is_wp_error($_changelog)) {
            $_changelog = nl2br($_changelog['body']);
        } else {
            $_changelog = '';
        }

        // return
        return (! empty($_changelog) ? $_changelog : 'Could not get changelog from server.');
    }

    /**
     * Get Plugin data
     *
     * @return object $data the data
     * @since 1.0
     */
    public function get_plugin_data()
    {
        /** @noinspection PhpUndefinedConstantInspection */
        include_once ABSPATH . '/wp-admin/includes/plugin.php';

        /** @noinspection PhpUndefinedConstantInspection */
        return get_plugin_data(WP_PLUGIN_DIR . '/' . $this->config['slug']);
    }

    /**
     * Hook into the plugin update check and connect to GitHub
     *
     * @param object $transient the plugin data transient
     *
     * @return object $transient updated plugin data transient
     * @since 1.0
     */
    public function api_check($transient)
    {
        // Check if the transient contains the 'checked' information
        // If not, just return its value without hacking it
        if (empty($transient->checked)) {
            return $transient;
        }

        // check the version and decide if it's new
        $update = version_compare($this->config['new_version'], $this->config['version']);

        if (1 === $update) {
            $response              = new stdClass;
            $response->new_version = $this->config['new_version'];
            $response->slug        = $this->config['proper_folder_name'];
            $response->url         = add_query_arg(
                array('access_token' => $this->config['access_token']),
                $this->config['github_url']
            );
            $response->package     = $this->config['zip_url'];
            $response->icons       = $this->config['icons'];
            $response->tested      = $this->config['new_tested'];

            // If response is false, don't alter the transient
            if (false !== $response) {
                $transient->response[$this->config['slug']] = $response;
            }
        }

        return $transient;
    }

    /**
     * Get Plugin info
     *
     * @param $response
     *
     * @return bool|stdClass $response the plugin info
     * @since 1.0
     */
    public function get_plugin_info($response): bool|stdClass
    {
        // Check if this call API is for the right plugin
        if ( ! isset($response->slug) || $response->slug != $this->config['proper_folder_name']) {
            return false;
        } else {
            $res                = new stdClass();
            $res->name          = $this->config['plugin_name'];
            $res->slug          = $this->config['slug'];
            $res->version       = $this->config['new_version'];
            $res->author        = $this->config['author'];
            $res->homepage      = $this->config['homepage'];
            $res->requires      = $this->config['requires'];
            $res->tested        = $this->config['new_tested'];
            $res->downloaded    = 0;
            $res->last_updated  = $this->config['last_updated'];
            $res->sections      = array(
                'description' => $this->config['description'],
                'changelog'   => $this->config['changelog'],
            );
            $res->download_link = $this->config['zip_url'];

            // Useful fields for a later version
            // $res->rating = '100';
            // $res->num_ratings = '1124';
            // $res->active_installs = '11056';
            // $res->downloaded = '18056';

            return $res;
        }
    }

    /**
     * Upgrader/Updater
     * Move & activate the plugin, echo the update message
     *
     * @param bool $true - don't remove, it breaks the updates
     * @param mixed $hookExtra - don't remove, it breaks the updates
     * @param array $result the result of the move
     *
     * @return array $result the result of the move
     * @since 1.0
     */
    public function upgrader_post_install(bool $true, mixed $hookExtra, array $result): array
    {
        global $wp_filesystem;

        // Move & Activate
        /** @noinspection PhpUndefinedConstantInspection */
        $proper_destination = WP_PLUGIN_DIR . '/' . $this->config['proper_folder_name'];
        $wp_filesystem->move($result['destination'], $proper_destination);
        $result['destination'] = $proper_destination;
        /** @noinspection PhpUndefinedConstantInspection */
        $activate = activate_plugin(WP_PLUGIN_DIR . '/' . $this->config['slug']);

        // Output the update message
        $fail    = __(
            'The plugin has been updated, but could not be reactivated. Please reactivate it manually.',
            'github_plugin_updater'
        );
        $success = __('Plugin reactivated successfully.', 'github_plugin_updater');
        echo is_wp_error($activate) ? $fail : $success;

        return $result;
    }
}
