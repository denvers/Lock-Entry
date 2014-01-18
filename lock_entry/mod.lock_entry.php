<?php if (!defined("BASEPATH")) exit('No direct script access allowed.');

require_once(dirname(__FILE__) . "/settings.php");

/**
 * Module File for Lock Entry
 *
 * This file must be in your /system/third_party/lock_entry directory of your ExpressionEngine installation
 *
 * @package             Lock_entry
 * @author              Denver Sessink (dsessink@gmail.com)
 * @copyright            Copyright (c) 2012 Denver Sessink
 */
class Lock_entry
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
     */
    public function __construct()
    {
        $this->EE =& get_instance();
    }

    /**
     * Update the last_activity
     */
    public function ping()
    {
        if (!$this->EE->input->get('hash')) {
            die('ERROR.0');
        }

        if (!$this->EE->input->get('entry_id')) {
            die('ERROR.1');
        }

        if (!$this->EE->input->get('member_id')) {
            die('ERROR.2');
        }

        // - Ping (keep alive, activity update) (AJAX)
        $entry_id = intval($this->EE->input->get('entry_id'));
        $member_id = intval($this->EE->input->get('member_id'));

        // validate entry_id and member_id
        if (lock_entry_settings::_generate_ping_hash($entry_id, $member_id) != $this->EE->input->get('hash')) {
            die('ERROR.3');
        }

        $data = array('last_activity' => date("Y-m-d H:i:s"));
        $sql = $this->EE->db->update_string('lock_entry_entries', $data, sprintf("entry_id = '%d AND member_id = %d'", $entry_id, $member_id));
        $this->EE->db->query($sql);

        die('OK');
    }
}

/* End of file mod.lock_entry.php */