<?php if (!defined("BASEPATH")) exit('No direct script access allowed.');

define("LOCK_ENTRY_NAME", "Lock Entry");
define("LOCK_ENTRY_VERSION", "1.0.1");
define("LOCK_ENTRY_DESCRIPTION", "Lock entry if the entry is currently being edited by another user.");
define("LOCK_ENTRY_SETTINGS_EXIST", "n");
define("LOCK_ENTRY_DOCS_URL", "");
define("LOCK_ENTRY_SALT", "245kj3h45kl32&$*&^*#^%QQ&^%W323#@^42l46;3lj");

class lock_entry_settings
{
    static function _generate_ping_hash($entry_id, $member_id)
    {
        return md5(sprintf("entry_id=%d&member_id=%d&salt=%s", $entry_id, $member_id, LOCK_ENTRY_SALT));
    }
}