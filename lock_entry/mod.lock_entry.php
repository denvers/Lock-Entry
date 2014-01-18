<?php if (!defined("BASEPATH")) exit('No direct script access allowed.');

require_once(dirname(__FILE__) . "/settings.php");

/**
 * Module File for Lock Entry
 *
 * This file must be in your /system/third_party/lock_entry directory of your ExpressionEngine installation
 *
 * @package             Lock_entry
 * @author              Denver Sessink (dsessink@gmail.com)
 * @copyright           Copyright (c) 2012 Denver Sessink
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

        // Accept object ID
        if (!$this->EE->input->get('object_id')) {
            die('ERROR.10');
        }

        if (!$this->EE->input->get('session_id')) {
            die('ERROR.20');
        }

        // accept entry or template mode
        if ( !$this->EE->input->get('mode') == "entry" && !$this->EE->input->get('mode') == "template" ) {
            die('ERROR.30');
        }

        // - Ping (keep alive, activity update) (AJAX)
        if ( $this->EE->input->get('mode') == 'template' )
        {
            $mode = "template";
        }
        else
        {
            $mode = "entry"; // which is default
        }

        $object_id = $this->EE->input->get('object_id');
        $session_id = $this->EE->input->get('session_id');

        // validate entry_id|template_id and member_id
        if (lock_entry_settings::_generate_ping_hash($object_id, $session_id) != $this->EE->input->get('hash')) {
            die('ERROR.40');
        }

        $data = array('last_activity' => date("Y-m-d H:i:s"));
        $sql = $this->EE->db->update_string(
            'lock_entry_entries',
            $data,
            sprintf(
                "`%s` = %d AND `session_id` = %d",
                ($mode == "template") ? "template_id" : "entry_id",
                $object_id,
                $session_id
            )
        );
        $this->EE->db->query($sql);

        die('OK');
    }
}

/* End of file mod.lock_entry.php */