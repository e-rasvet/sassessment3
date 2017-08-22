<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file keeps track of upgrades to the sassessment module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod_sassessment
 * @copyright  2014 Igor Nikulin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute sassessment upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_sassessment_upgrade($oldversion) {
    global $CFG, $THEME, $DB;

    $result = true;

    $dbman = $DB->get_manager();

    if ($oldversion < 2017052600) {
        // Define table assign_user_mapping to be created.
        $table = new xmldb_table('sassessment');

        $field = new xmldb_field('autodelete', XMLDB_TYPE_INTEGER, '11', null,
            XMLDB_NOTNULL, null, '0', 'audio');

        // Conditionally launch add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('sassessment_studdent_answers');
        $field = new xmldb_field('var1', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'summary');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('var2', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'var1');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('var3', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'var2');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('var4', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'var3');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('var5', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'var4');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('var6', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'var5');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('var7', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'var6');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('var8', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'var7');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('var9', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'var8');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);

        $field = new xmldb_field('var10', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'var9');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);


        // Assign savepoint reached.
        upgrade_mod_savepoint(true, 2017052600, 'sassessment');
    }

    if ($oldversion < 2017062000) {
        // Define table assign_user_mapping to be created.
        $table = new xmldb_table('sassessment_appfiles');

        // Adding fields to table assign_user_mapping.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('instance', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('fileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcefileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('var', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table assign_user_mapping.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('user', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

        // Conditionally launch create table for assign_user_mapping.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Assign savepoint reached.
        upgrade_mod_savepoint(true, 2017062000, 'sassessment');
    }

    if ($oldversion < 2017063000) {
      /*
        // Define table assign_user_mapping to be created.
        $table = new xmldb_table('sassessment_appfiles');
        $field = new xmldb_field('text', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'userid');
        // Launch change of type for field grade.
        $dbman->add_field($table, $field);
      */
        // Assign savepoint reached.
        upgrade_mod_savepoint(true, 2017063000, 'sassessment');
    }

    return $result;
}
