<?php if (!defined("BASEPATH")) exit('No direct script access allowed.');

require_once(dirname(__FILE__) . "/settings.php");

/**
 * Extension File for Lock Entry
 *
 * This file must be in your /system/third_party/lock_entry directory of your ExpressionEngine installation
 *
 * @package             Lock_entry
 * @author              Denver Sessink (dsessink@gmail.com)
 * @copyright           Copyright (c) 2012 Denver Sessink
 */
class Lock_entry_ext
{
    /**
     * @var string
     */
    public $name = LOCK_ENTRY_NAME;

    /**
     * @var string
     */
    public $version = LOCK_ENTRY_VERSION;

    /**
     * @var string
     */
    public $description = LOCK_ENTRY_DESCRIPTION;

    /**
     * @var string
     */
    public $settings_exist = LOCK_ENTRY_SETTINGS_EXIST;

    /**
     * @var string
     */
    public $docs_url = LOCK_ENTRY_DOCS_URL;

    /**
     * @var CI_Controller
     */
    private $EE;

    /**
     * Constructor
     *
     * @return Lock_entry_ext
     */
    function Lock_entry_ext($settings = '')
    {
        $this->EE =& get_instance();
        $this->EE->lang->loadfile("lock_entry");
        $this->active_site = $this->EE->config->item('site_id');
    }

    /**
     * Fires when extension is activated. Registers hook(s).
     */
    function activate_extension()
    {
        // Register hook: cp_js_end
        $this->_register_hook('cp_js_end', 'add_javascript', 5);

        // Register hook: sessions_end (for every page load)
        $this->_register_hook('sessions_end', 'sessions_end', 5);

        // Register hook: after entry submission
        $this->_register_hook('entry_submission_absolute_end', 'entry_submission_absolute_end', 10);

        // Register hook: at entry form
        $this->_register_hook('publish_form_entry_data', 'register_entry_editor', 10);

        // Register the action for enabling pinging...
        $this->_register_ping_action();

        // No comments needed :-P
        $this->_create_database_tables();
    }

    /**
     * @param   string      $hook
     * @param   string      $method
     * @param   int         $priority
     */
    private function _register_hook($hook, $method, $priority = 10)
    {
        $data = array(
            'class' => __CLASS__,
            'method' => $method,
            'hook' => $hook,
            'settings' => '',
            'priority' => $priority,
            'version' => $this->version,
            'enabled' => 'y'
        );

        $this->EE->db->insert('extensions', $data);
    }

    /**
     * Register an action for pinging
     */
    private function _register_ping_action()
    {
        $data = array(
            'class' => 'Lock_entry', // refers to mod.lock_entry.php
            'method' => 'ping'
        );

        $this->EE->db->insert('actions', $data);
    }

    /**
     * Create DB tables upon activation
     */
    function _create_database_tables()
    {
        $this->EE->load->dbforge();

        $data = array(
            'module_name' => $this->name,
            'module_version' => $this->version,
            'has_cp_backend' => 'y',
            'has_publish_fields' => 'n'
        );

        $this->EE->db->insert('modules', $data);

        // create lock entry table
        $fields = array(
            'id' => array('type' => 'int', 'constraint' => 10, 'unsigned' => TRUE, 'auto_increment' => TRUE),
            'since' => array('type' => 'DATETIME'),
            'last_activity' => array('type' => 'DATETIME'),
            'site_id' => array('type' => 'int', 'constraint' => 10),
            'entry_id' => array('type' => 'int', 'constraint' => 10),
            'member_id' => array('type' => 'int', 'constraint' => 10),
        );

        $this->EE->dbforge->add_field($fields);
        $this->EE->dbforge->add_key('id', TRUE);

        $this->EE->dbforge->create_table('lock_entry_entries');
    }

    /**
     * Fires when extension is disabled. Removes hook(s).
     */
    public function disable_extension()
    {
        $this->EE->db->where('class', __CLASS__);
        $this->EE->db->delete('extensions');

        $this->EE->db->where('class', 'Lock_entry');
        $this->EE->db->delete('actions');

        $this->_delete_database_tables();
    }

    /**
     * Delete DB tables when deactivated
     */
    private function _delete_database_tables()
    {
        $this->EE->load->dbforge();

        $this->EE->db->select('module_id');
        $query = $this->EE->db->get_where('modules', array('module_name' => $this->name));

        $this->EE->db->where('module_id', $query->row('module_id'));
        $this->EE->db->delete('module_member_groups');

        $this->EE->db->where('module_name', $this->name);
        $this->EE->db->delete('modules');

        $this->EE->db->where('class', $this->name);
        $this->EE->db->delete('actions');

        $this->EE->dbforge->drop_table('lock_entry_entries');
    }

    /**
     * Hook: publish_form_entry_data
     * Called: on requesting the entry publish form page
     *
     * @param   array   $params [entry_id, site_id]
     */
    public function register_entry_editor($params)
    {
        // Delete all locks, except lock my own lock for current entry
        $this->_remove_old_locks($params['entry_id'], $this->EE->session->userdata('member_id'));

        // Do not store editing new entries
        if ($params['entry_id'] == 0) {
            return;
        }

        if ($this->_entry_has_member_lock($params['entry_id'], $this->EE->session->userdata('member_id'))) {
            return false;
        }

        if ($this->_get_entry_lock($params['entry_id']) !== false) {
            return false;
        }

        $this->_lock_entry($params['site_id'], $params['entry_id'], $this->EE->session->userdata('member_id'));
    }

    /**
     * @param $entry_id
     * @param $member_id
     */
    private function _entry_has_member_lock($entry_id, $member_id)
    {
        if (($lock_info = $this->_get_entry_lock($entry_id)) !== false) {
            if ($lock_info['member_id'] == $member_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates a lock for an entry
     *
     * @param   int $site_id
     * @param   int $entry_id
     * @param   int $member_id
     */
    private function _lock_entry($site_id, $entry_id, $member_id)
    {
        $data = array(
            'since' => date("Y-m-d H:i:s"),
            'last_activity' => date("Y-m-d H:i:s"),
            'site_id' => $site_id,
            'entry_id' => $entry_id,
            'member_id' => $this->EE->session->userdata('member_id'),
        );

        $this->EE->db->insert('lock_entry_entries', $data);
    }

    /**
     * Returns info of lock for entry_id or false if not locked
     *
     * @param   int $entry_id
     * @return  array|bool
     */
    private function _get_entry_lock($entry_id)
    {
        $this->EE->db->select('*')
            ->from('exp_lock_entry_entries')
            ->where('entry_id', $entry_id);

        $query = $this->EE->db->get();

        if (count($query->result_array()) > 0) {
            $record = array_shift($query->result_array());

            // get member name from lock
            $this->EE->db->select('member_id,screen_name,email')
                ->from('exp_members')
                ->where('member_id', $record['member_id']);

            $query = $this->EE->db->get();
            if (count($query->result_array()) == 0) return false; // member not found

            $member = array_shift($query->result_array());

            return array(
                'member_id' => $member['member_id'],
                'member_screen_name' => $member['screen_name'],
                'member_email' => $member['email'],
                'last_activity' => $record['last_activity'],
                'since' => $record['since'],
            );
        }

        return false;
    }

    /**
     * Add JavaScript to the entry form page
     *
     * @return string
     */
    public function add_javascript()
    {
        // Just add JavaScript at the content_publish page (editing an entry)
        $http_referer = parse_url($_SERVER['HTTP_REFERER']);
        parse_str($http_referer['query'], $url_params);

        if (!isset($http_referer['query']) || $http_referer['query'] == "") return;
        if (!strstr($http_referer['query'], "D=cp")) return;
        if (!strstr($http_referer['query'], "C=content_publish")) return;
        if (!strstr($http_referer['query'], "M=entry_form")) return;

        if (($entry_lock_info = $this->_get_entry_lock($url_params['entry_id'])) === false) {
            return; // entry is not locked
        }

        // If entry is locked by someone else -> hard lock, Yay! :)
        if ($entry_lock_info['member_id'] != $this->EE->session->userdata('member_id')) {

            // Prepare date.. don't show date if the date is today (yep, that's most of the time ;))
            list($date, $time) = explode(" ", $entry_lock_info['since']);
            list($hours, $mins, $sec) = explode(":", $time);
            if ($date == date("Y-m-d")) {
                // If lock since is today.. show only the time when the lock started
                $since = sprintf("%s:%s", $hours, $mins);
            } else {
                list($year, $month, $day) = explode("-", $date);
                $since = sprintf("%s-%s-%s at %s:%s", $day, $month, $year, $hours, $mins);
            }

            // Prepare last activity time
            list($date, $time) = explode(" ", $entry_lock_info['last_activity']);
            list($min, $sec) = explode(":", $time);
            $last_activity = sprintf("%s:%s", $min, $sec);

            // Prepare JavaScript
            $js = "var hard_lock = true; \n";
            $js .= file_get_contents(dirname(__FILE__) . "/javascript/lock_entry.js");
            $js .= sprintf(
                'var message_html = "<strong>%5$s</strong> %6$s <a href=\"mailto:%1$s\" style=\"color: white;\" title=\"%7$s %1$s\"><em>%2$s</em></a> %8$s %3$s. (%9$s: %4$s)";',
                $entry_lock_info['member_email'],
                $entry_lock_info['member_screen_name'],
                $since,
                $last_activity,
                $this->EE->lang->line('lock_entry_warning'),
                $this->EE->lang->line('lock_entry_this_entry_is_already_being_edited_by'),
                $this->EE->lang->line('lock_entry_send_an_email_to_this_member_at'),
                $this->EE->lang->line('lock_entry_since'),
                $this->EE->lang->line('lock_entry_last_activity')
            );
            $js .= " \n ";
        } else {
            // Locked for me, so we need a pingback for keeping the activity of the lock alive
            $action_id = $this->EE->cp->fetch_action_id('Lock_entry', 'ping');
            $entry_id = $url_params['entry_id'];
            $member_id = $this->EE->session->userdata('member_id');

            $url_ping_hash = lock_entry_settings::_generate_ping_hash($entry_id, $member_id);

            $js = "var hard_lock = true; \n";
            $js .= "var lock_entry_ping_url = '" . $this->EE->functions->fetch_site_index(0, 0) . QUERY_MARKER . 'ACT=' . $action_id . "&entry_id=" . $entry_id . "&member_id=" . $member_id . "&hash=" . $url_ping_hash . "'; ";
            $js .= file_get_contents(dirname(__FILE__) . "/javascript/lock_entry_ping.js");
        }

        return $this->EE->extensions->last_call . $js;
    }

    /**
     * Hook: entry_submission_absolute_end
     * Called: after submitting the entry form
     */
    function entry_submission_absolute_end($entry_id, $meta, $data)
    {
        $this->_delete_member_entry_locks($this->EE->session->userdata('member_id'));
    }

    /**
     * Delete all entry locks for given member_id
     *
     * @param $member_id
     */
    private function _delete_member_entry_locks($member_id)
    {
        $this->EE->db->delete('exp_lock_entry_entries', array('member_id' => $member_id));
    }

    /**
     * Called every page request in the backend
     *
     * @param $obj
     */
    function sessions_end($obj)
    {
        if ($this->_is_entry_submission_form()) {
            // do not delete locks for current entry as we are currently at this entry form
            $this->_remove_old_locks($this->_get_current_entry_id(), $obj->userdata['member_id']);
        }
    }

    /**
     * Check if current page is an entry submission form (based in GET)
     *
     * @return bool
     */
    private function _is_entry_submission_form()
    {
        if ($this->EE->input->get('D') == "cp" && $this->EE->input->get('C') == "content_publish" && $this->EE->input->get('M') == "entry_form" && $this->EE->input->get('entry_id') != "") {
            return true;
        }

        return false;
    }

    /**
     * Gets current entry id out of the current URL (if you are at the entry publish form)
     *
     * @return int
     */
    private function _get_current_entry_id()
    {
        $current_entry_id = -1;
        if ($this->EE->input->get('D') == "cp" && $this->EE->input->get('C') == "content_publish" && $this->EE->input->get('M') == "entry_form") {
            $current_entry_id = $this->EE->input->get('entry_id');
        }

        return $current_entry_id;
    }

    /**
     * soort van cron.. alle 'verouderde' records opruimen
     * bijv. met een verlooptijd van 5minuten
     *
     * @param   int     $except_entry_id = null
     * @param   int     $except_member_id = null
     */
    private function _remove_old_locks($entry_id = null, $member_id = null)
    {
        // select all old locks
        $this->EE->db->from('exp_lock_entry_entries')
            ->where('DATE_ADD(last_activity, INTERVAL 5 MINUTE) < NOW()');

        $result_array = $this->EE->db->get()->result_array();

        // Loop through them, and check if we may delete them
        foreach ($result_array as $entry) {
            if (
                !is_null($entry_id) && !is_null($member_id)
            ) {
                if ($entry['entry_id'] == $entry_id && $entry['member_id'] == $member_id) {
                    // Right, this one should NOT be deleted!
                } else {
                    $this->EE->db->where('entry_id', $entry['entry_id'])->delete('exp_lock_entry_entries');
                }
            } elseif (
                !is_null($entry_id)
            ) {
                if ($entry_id != $entry['entry_id']) {
                    $this->EE->db->where('entry_id', $entry['entry_id'])->delete('exp_lock_entry_entries');
                }
            } elseif (
                !is_null($member_id)
            ) {
                if ($entry['member_id'] != $member_id) {
                    $this->EE->db->where('entry_id', $entry['entry_id'])->delete('exp_lock_entry_entries');
                }
            } else {
                $this->EE->db->where('entry_id', $entry['entry_id'])->delete('exp_lock_entry_entries');
            }
        }
    }
}

/* End of file ext.lock_entry.php */