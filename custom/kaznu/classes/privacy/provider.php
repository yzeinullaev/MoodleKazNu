<?php
namespace local_kaznu\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $items): collection {
        $items->add_database_table('local_kaznu_conversation', [
            'userid' => 'privacy:metadata:conversation:userid',
            'title' => 'privacy:metadata:conversation:title',
        ], 'privacy:metadata:conversation');
        $items->add_database_table('local_kaznu_message', [
            'content' => 'privacy:metadata:message:content',
        ], 'privacy:metadata:message');
        return $items;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!$contextlist->count()) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $convs = $DB->get_records('local_kaznu_conversation', ['userid' => $userid, 'deleted' => 0]);
        $export = [];
        foreach ($convs as $conv) {
            $msgs = $DB->get_records('local_kaznu_message', ['conversationid' => $conv->id], 'timecreated ASC');
            $export[] = [
                'title' => $conv->title,
                'messages' => array_values(array_map(static fn($m) => [
                    'role' => $m->role,
                    'content' => $m->content,
                ], $msgs)),
            ];
        }
        writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'local_kaznu')],
            (object)['conversations' => $export]
        );
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!$contextlist->count()) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $convs = $DB->get_records('local_kaznu_conversation', ['userid' => $userid]);
        foreach ($convs as $conv) {
            $DB->delete_records('local_kaznu_message', ['conversationid' => $conv->id]);
            $DB->delete_records('local_kaznu_conversation', ['id' => $conv->id]);
        }
    }
}
