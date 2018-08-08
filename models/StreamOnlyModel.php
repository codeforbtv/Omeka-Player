<?php
/**
 * Stream Only Omeka Player Plugin
 * StreamOnly Model
 *
 * Accesses records in the db table for StreamOnly Plugin
 *
 * @copyright Copyright 2018 Code for Burlington
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

class StreamOnlyModel extends Omeka_Record_AbstractRecord {

    /**
     * ID of the Item this Record belongs to.
     *
     * @var int
     */
    public $item_id;

    /**
     * directory where .mp3 files for this Item are stored.
     *
     * @var string
     */
    public $so_directory;

    /**
     * # users who may access this Item's files simultaneously.
     *
     * @var int
     */
    public $so_licenses;

    /**
     * # seconds after which .m3u files for this Item may be deleted.
     *
     * @var int
     */
    public $so_timeout;

    /**
     * StreamOnlyRecord constructor.
     * Called the end of parent::__construct()
     *
     * Initialize public properties
     */
    protected function construct() {

        $this->id = 0;
        $this->item_id = NULL;
        $this->so_directory = NULL;
        $this->so_licenses = NULL;
        $this->so_timeout = NULL;

        return ($this);
    }

//    /**
//     * @return string|void
//     */
//    public function getResourceId()
//    {
//        // TODO: Implement getResourceId() method.
//    }   return ('');

}