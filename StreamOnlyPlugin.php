<?php
/**
 * Stream Only Omeka Player Plugin
 * Hooks, Filters, Options
 *
 * @copyright Copyright 2018 Code for Burlington
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 *
 * Developer documentation available at
 * https://github.com/codeforbtv/StreamOnly/wiki/Developer-Documentation:-Introduction
 */

    require_once dirname(__FILE__) . '/helpers/StreamOnlyConstants.php';
    require_once dirname(__FILE__) . '/helpers/StreamOnlyFunctions.php';
    require_once dirname(__FILE__) . '/helpers/StreamOnlyItemType.php';
    require_once dirname(__FILE__) . '/helpers/StreamOnlyAccess.php';

/**
 * StreamOnly plugin.
 */
class StreamOnlyPlugin extends Omeka_Plugin_AbstractPlugin
{

    protected $_hooks = array(
        'install',
        'config',
        'config_form',
        'uninstall',
        'upgrade',

        'before_save_item',
        'after_save_item',
        'before_delete_item',
        'after_delete_item',

//        'before_save_file',
        'after_save_file',
        'before_delete_file',
//        'after_delete_file',

//        'before_save_element_text',
        'after_save_element_text',
//        'before_delete_element_text',
        'after_delete_element_text',

        'admin_footer');

    protected $_options = array();

    protected $_filters = array();

    protected static $_callbackOptions = array(
        'width' => '200',
        'height' => '20',
        'autoplay' => false,
        'controller' => true,
        'loop' => false
    );

    protected $_soState = array(
        'operation'           => 'unknown',
        'filelist'            => array(),
        'folder_elemtext_id'  => NULL,
        'license_elemtext_id' => NULL,
        'timeout_elemtext_id' => NULL,
        'folder_option'       => NULL,
        'license_option'      => NULL,
        'timeout_option'      => NULL
    );

    // TODO This should be a class method, and be registered as a hook
    // TODO Should include a warning that if there are large numbers of
    // TODO   Items of ItemType StreamOnly, this may take a while
    public $uninstall_message = "All protected files will be moved to the default upload directory. Visitors to the site will be able to download them. All Items of Item Type StreamOnly will be set to undefined.";

    /**
     * Checks that the file indicated by the record should be protected
     *
     * @param $file - record of type File
     * @return bool
     */
    private function _isProtectedFile($file) {
        return ($file->mime_type == "audio/mpeg");
    }

    /**
     * Builds the path name to a directory where one might find a Protected File
     * String ends in a DIRECTORY_SEPARATOR
     *
     * @param $which - 'Omeka' or 'StreamOnly'
     * @param $folder - the SO folder, if $which = 'StreamOnly'
     * @return string - the desired path
     */
    private function _buildPath($which, $folder) {
        switch ($which) {
            case 'Omeka':
                $path = FILES_DIR . DIRECTORY_SEPARATOR
                     . 'original' . DIRECTORY_SEPARATOR;
                break;
            case 'StreamOnly':
                $path = DIRECTORY_SEPARATOR . $folder
                    . DIRECTORY_SEPARATOR;
                break;
            default:
                // TODO Report internal error
                die('Building a nonsense path');
        }

        return $path;
    }

    /**
     * Check to see if the file is in the specified directory
     *
     * @param $file - record for a File
     * @param $which - 'Omeka' or 'StreamOnly'
     * @param $folder - the SO folder, if $which = 'StreamOnly'
     * @return bool
     */
    private function _isInDirectory($file, $which, $folder) {

        return (file_exists($this->_buildPath($which, $folder) . $file->filename));
    }

    /**
     * Moves the file from the source directory to the target directory
     * It has already been found to exist in the source directory
     * and not to exist in the target directory
     *
     * TODO Throw exception if failure
     *
     * @param $file - record from File Table
     * @param $sourceDir - directory where the file is now
     * @param $targetDir - directory where the file will be
     */
    private function _moveFile($file, $sourceDir, $targetDir) {

        $sourceFile = $sourceDir . $file->filename;
        $targetFile = $targetDir . $file->filename;

        if (!rename($sourceFile, $targetFile)) {
            die('Error renaming file');
        }
    }

    /**
     * Only move those files that are protected files.
     * Don't move a file if it's already in the target directory
     * If a file is not in the source directory, report an error
     *
     * @param $item - record of the Item whose files are to be moved
     *                or NULL if the files to be moved are stored in $this->_soState['filelist']
     * @param $source['which']   - 'Omeka' or 'StreamOnly', to be provided as input to _buildPath
     *               ['srcDir']  - folder in which to search for files to be moved
     * @param $target['which']   - 'Omeka' or 'StreamOnly', to be provided as input to _buildPath
     *               ['destDir'] - folder in which to place the files to be moved
     */
    private function _moveFiles($item, $source, $target) {

        if ($item == NULL) {
            $filelist = $this->_soState['filelist'];
        } else {
            $filelist = get_db()->getTable('File')->findBy(array('item_id' => $item->id));
        }
        foreach ($filelist as $file) {

            if (!$this->_isProtectedFile($file)) continue;
            if ($this->_isInDirectory($file, $target['which'], $target['dir'])) continue;
            if (!$this->_isInDirectory($file, $source['which'], $source['dir']))
                die("Need to move file, but can't find it"); // TODO throw exception
            $sourceDir = $this->_buildPath($source['which'], $source['dir']);
            $targetDir = $this->_buildPath($target['which'], $target['dir']);
            $this->_moveFile($file, $sourceDir, $targetDir);
        }
    }
    /****** PLUGIN *********************************************************/

    /**
     * StreamOnly plugin constructor.
     *
     * Stores useful info in protected variable $this->_soState
     * Registers the callback function that outputs the HTML for audio/mpeg files.
     */
    public function __construct()
    {
        parent::__construct();

        // If the plugin is installed, store some useful info for optimization
        $plugin = get_record('Plugin', array("name"=>SO_PLUGIN_NAME));
        if ($plugin && ($plugin->active == 1)) {
            $option = get_record('Option',  array("name"=>OPTION_FOLDER));
            if ($option) {
                $this->_soState['folder_option'] = $option->value;
            }
            $element = get_record('Element', array("name"=>ELEMENT_FOLDER));
            if ($element) {
                $this->_soState['folder_elemtext_id'] = $element->id;
            }
            $option = get_record('Option',  array("name"=>OPTION_LICENSES));
            if ($option) {
                $this->_soState['license_option'] = $option->value;
            }

            $element = get_record('Element', array("name"=>ELEMENT_LICENSE_COUNT));
            if ($element) {
                $this->_soState['license_elemtext_id'] = $element->id;
            }
            $option = get_record('Option',  array("name"=>OPTION_TIMEOUT));
            if ($option) {
                $this->_soState['timeout_option'] = $option->value;
            }
            $element = get_record('Element', array("name"=>ELEMENT_TIMEOUT));
            if ($element) {
                $this->_soState['timeout_elemtext_id'] = $element->id;
            }
        }

        // Register callback for display of protected files
        add_file_display_callback(array('mimeTypes' => array('audio/mpeg')), 'soDisplayFile', self::$_callbackOptions);
    }

    /**
     * Installs the StreamOnly Plugin
     *
     * Checks that the StreamOnly ItemType does not exist, then creates it
     * Creates a table in the database to store info about SO protected audio/mpeg files
     *   (table can be accessed with $db->getTable('StreamOnly'))
     * Initializes options with defaults for SO Items
     * Creates the files/m3u/ directory, creates .htaccess and reserved.txt files in it
     * Adds rule(s) to .htaccess file in Omeka root directory
     *
     * @param $pluginID (not used)
     * @throws Omeka_Plugin_Installer_Exception
     */
    public function hookInstall($pluginID) {

        $db = $this->_db;

        // Modify the .htaccess file so the script
        //   that downloads protected files can run
        if (!so_update_access('add')) {
            throw new Omeka_Plugin_Installer_Exception(__(
                'Cannot modify the .htaccess file'));
        }

        // Check for existing Item Type, to avoid "duplicate name" death
        $itemTypeList = $db->getTable('ItemType')->findBy(array('name'=>SO_ITEM_TYPE_NAME));
        if (!empty($itemTypeList)) {
            throw new Omeka_Plugin_Installer_Exception(__(
                'The Item Type %s already exists', SO_ITEM_TYPE_NAME));
        }

        // Create the StreamOnly Item Type
        $add_elements = so_elements();
        insert_item_type(
            array(
                'name'=> SO_ITEM_TYPE_NAME,
                'description' => SO_ITEM_TYPE_DESCRIPTION
            ),
            $add_elements
        );

        // Create a table for the items of type StreamOnly
        $sql = "
        CREATE TABLE IF NOT EXISTS `$db->StreamOnlyModel` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `item_id` int(10) unsigned NOT NULL,
          `so_directory` mediumtext COLLATE utf8_unicode_ci NOT NULL,
          `so_licenses` int(10) unsigned NOT NULL,
          `so_timeout` int(10) unsigned NOT NULL,
          PRIMARY KEY (`id`),
          KEY `item_id` (`item_id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

        $db->query($sql);

        // Default number of simultaneous streams (determined by licensing)
        set_option(OPTION_LICENSES, DEFAULT_LICENSES);

        // Default path to the directory where StreamOnly audio files are stored
        set_option(OPTION_FOLDER, DEFAULT_FOLDER);

        // Number of seconds after which temporary files granting access to protected audio files can be deleted
        set_option(OPTION_TIMEOUT, DEFAULT_TIMEOUT);

        // Create the m3u directory under the files directory, and create the .htaccess file there
        $m3uDir = FILES_DIR . DIRECTORY_SEPARATOR . SO_PLAYLIST;
        if (!mkdir($m3uDir, 0700)) {
            throw new Omeka_Plugin_Installer_Exception(__(
                'Could not create the playlist directory'));
        }

        // TODO Report and recover from errors
        // create the .htaccess file to protect the .m3u files
        file_put_contents($m3uDir . DIRECTORY_SEPARATOR . HTACCESS, SO_DENY_ACCESS);
        // Create an empty file for storing list of reserved files
        file_put_contents($m3uDir . DIRECTORY_SEPARATOR . SO_RESERVED_FILES, "");
        // create the file with the custom msg
        file_put_contents($m3uDir . DIRECTORY_SEPARATOR . SO_CUSTOM_MSG_FILE, SO_CUSTOM_MSG_TEXT);
    }


    /**
     * Uninstalls the plugin
     *
     * Removes the files/m3u/directory and all its files
     * Removes options created by hookInstall() for storing defaults
     * Removes the statements in the .htaccess file in the Omeka root directory
     *   that were added by hookInstall()
     * Removes the table in the database with info about SO protected audio/mpeg files
     * For each Item of Item Type StreamOnly
     *   Moves all files from the SO folder to the Omeka default uploads folder
     *   Resets the Item Type to NULL (undefined)
     * Removes the Item Type StreamOnly
     *
     * Leaves in place the Elements unique to SO Item Type
     * Leaves in place the Element Texts for Items that were of SO Item Type
     *
     * @param $args
     * @throws Omeka_Plugin_Installer_Exception
     */
    public function hookUninstall($args)
    {

        $db = get_db();

        // undo the changes hookInstall() made to the .htaccess file
        if (!so_update_access('remove')) {
            throw new Omeka_Plugin_Installer_Exception(__(
                'Cannot modify the .htaccess file'));
        }

        // Remove all files from the files/m3u/ directory, then delete the directory
        $m3uDir = FILES_DIR . DIRECTORY_SEPARATOR . SO_PLAYLIST. DIRECTORY_SEPARATOR;
        $dir = opendir($m3uDir);
        if (!$dir) {
            throw new Omeka_Plugin_Installer_Exception(__(
                'Cannot find playlist directory'));
        }
        while ($file = readdir($dir)) { // TODO Deal with errors
            unlink($m3uDir . $file);    // TODO Deal with errors
        }
        rmdir($m3uDir);                 // TODO Deal with errors

        // Remove the option values from the db table
        delete_option(OPTION_LICENSES);
        delete_option(OPTION_FOLDER);
        delete_option(OPTION_TIMEOUT);

        // Remove the references to the Item Type from all Items as needed,
        // and move protected files from the SO folder to the upload folder
        // Remove the StreamOnly Item Type, and associated Item Type Elements

        // Get the StreamOnly ItemType record

        $itemType = get_record('ItemType', array('name'=>SO_ITEM_TYPE_NAME));
        if ($itemType == NULL) {
            // Someone deleted the StreamOnly Item Type.
            throw new Omeka_Plugin_Installer_Exception(__(
                'Cannot complete uninstall; StreamOnly Item Type has been deleted'));
        }

        // TODO This could return a very large # of Items
        // TODO Consider iterating using get_records()
        // TODO Consider putting a warning in $uninstall_message()
        // TODO https://omeka.readthedocs.io/en/latest/Reference/libraries/globals/get_records.html
        // Get all the Items of ItemType StreamOnly
        $itemList = $db->getTable('Item')->findBy(array('item_type_id'=>$itemType->id));

        // For each Item, move the protected files from the StreamOnly folder to the default Omeka uploads folder
        $target = array ('which'=>'Omeka', 'dir'=>"");
        foreach ($itemList as $item) {
            $soRecord = get_record('StreamOnlyModel', array('item_id' => $item->id));
            $source = array('which' => 'StreamOnly', 'dir' => $soRecord['so_directory']);
            $this->_moveFiles($item, $source, $target);
        }

        // Delete all the ItemTypesElements rows joined to this type
        // findBy() does not seem to be implemented for this Table
        // TODO Consider using get_records()
        // TODO https://omeka.readthedocs.io/en/latest/Reference/libraries/globals/get_records.html
        $itemTypesElementsList = $db->getTable('ItemTypesElements')->findBySql('item_type_id = ?', array( (int) $itemType->id));
        foreach ($itemTypesElementsList as $itemTypeElement) {
            $itemTypeElement->delete();
        }

        // For Items of this type set the Item Type to "undefined"
        $db->update($db->Item, array('item_type_id' => null),
            array('item_type_id = ?' => $itemType->id));

        // Delete the StreamOnly Item Type
        $itemType->delete();

        // Delete the table used for the items of type StreamOnly
        $sql = "DROP TABLE IF EXISTS `$db->StreamOnlyModel`";
        $db->query($sql);

    }

    /**
     * Called to display the Configuration Form
     *
     * @params - none
     * @returns - none
     */
    public function hookConfigForm()
    {

        include 'config_form.php';
    }

    /**
     * Fires after the Configuration Form is filled out
     *
     * TODO How to report validation errors?
     *
     * @param - none
     * @global - uses the $_POST server global variable
     */
    public function hookConfig()
    {

        // should be an integer
        $defaultLicenses = $_POST[OPTION_LICENSES];

        // should be a valid directory with permissions 0700 or less restrictive
        // length of path + filename should be less than 255 chars
        $defaultFolder   = $_POST[OPTION_FOLDER];

        // should be an integer
        $timeout         = $_POST[OPTION_TIMEOUT];

        // not sure how to validate - maybe anything goes?
        $customMessage   = $_POST[OPTION_CUSTOM_MSG];

        set_option(OPTION_LICENSES, $defaultLicenses);
        set_option(OPTION_FOLDER, $defaultFolder);
        set_option(OPTION_TIMEOUT, $timeout);

        file_put_contents(FILES_DIR . DIRECTORY_SEPARATOR .
                          SO_PLAYLIST . DIRECTORY_SEPARATOR .
                          SO_CUSTOM_MSG_FILE,
                          $customMessage);
    }

    /**
     * Do any database conversions needed
     * Reserved for future use
     */
    public function hookUpgrade()
    {
        return;
    }

    /****** ITEMS **********************************************************/

    /**
     * Fires before the record is saved in the Item table
     *
     * TODO Some validation of the ItemTypeElements specific to
     * TODO   Items of ItemType StreamOnly would be helpful
     *
     * @param $args
     *   ['record']  record of type Item
     *   ['post'] array or FALSE [DO NOT USE]
     *   ['insert'] bool (true if record is being inserted)
     */
    public function hookBeforeSaveItem($args) {

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $args['record'];
        if (!isSOItem($item)) return;

        $itemType = get_record('ItemType', array('name'=>SO_ITEM_TYPE_NAME));
        $soRecord = ($args['insert'] ? false : get_record(SO_PLUGIN_MODEL, array("item_id"=>$item->id)));

        // Why are we saving this Item's record?
        if ($args['insert']) {
            $this->_soState['operation'] = "insert";
        } else if (($item->item_type_id == $itemType->id) && !$soRecord) {
            $this->_soState['operation'] = "+StreamOnly";
        } else if (($item->item_type_id != $itemType->id) && $soRecord) {
            $this->_soState['operation'] = "-StreamOnly";
        }  else {
            $this->_soState['operation'] = "update";
        }

        // Move files in filelist as appropriate for the scenario
        switch ($this->_soState['operation']) {

            // move protected files in filelist from Omeka to soRecord
            case 'update':
            case '-StreamOnly':
                // move protected files in filelist from Omeka to soRecord
                $source = array('which'=>'Omeka', 'dir' =>'');
                $target = array('which'=>'StreamOnly',
                                'dir'=>$soRecord->so_directory);
                $this->_moveFiles(NULL, $source, $target);
                break;

            // move protected files in filelist from Omeka to soOption
            case '+StreamOnly':
                // move protected files in filelist from Omeka to soOption
                $source = array('which'=>'Omeka', 'dir' =>'');
                $target = array('which'=>'StreamOnly',
                    'dir'=>$this->_soState['folder_option']);
                $this->_moveFiles(NULL, $source, $target);
                break;

            // no action required
            case 'insert': // $this->_soState['filelist'] is always empty
            case 'delete': // $this->_soState['filelist'] is always empty
                break;
        }

    }

    /**
     * Fires after the record is saved in the Item table
     *
     * @param $args
     *   ['record']  record of type Item
     *   ['post'] array or FALSE [DO NOT USE]
     *   ['insert'] bool (true if record is being inserted)
     * @throws Omeka_Record_Exception
     * @throws Omeka_Validate_Exception
     */
    public function hookAfterSaveItem($args) {

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $args['record'];
        if (!isSOItem($item)) return;


        // Finalize the state of the StreamOnlyModel record for this Item
        switch ($this->_soState['operation']) {

            // Create SORecord based on ElemText/SOOption
            case 'insert':
            case '+StreamOnly':
            $soRecord = new StreamOnlyModel(get_db());

            $soRecord->item_id = $item->id;

            // If the user specified a directory for this item, use it, otherwise use the default
            $soDirectory = get_record('ElementText',
                array("record_id"=>$item->id, "element_id"=>$this->_soState['folder_elemtext_id']));
            if ($soDirectory) {
                $soRecord->so_directory = $soDirectory->text;
            } else {
                $soRecord->so_directory = $this->_soState['folder_option'];
            }

            // If the user specified # licenses for this item, use it, otherwise use the default
            $soLicenses = get_record('ElementText',
                array("record_id"=>$item->id, "element_id"=>$this->_soState['license_elemtext_id']));
            if ($soLicenses) {
                $soRecord->so_licenses = $soLicenses->text;
            } else {
                $soRecord->so_licenses = $this->_soState['license_option'];
            }

            // If the user specified a timeout for this item, use it, otherwise use the default
            $soTimeout = get_record('ElementText',
                array("record_id"=>$item->id, "element_id"=>$this->_soState['timeout_elemtext_id']));
            if ($soTimeout) {
                $soRecord->so_timeout = $soTimeout->text;
            } else {
                $soRecord->so_timeout = $this->_soState['timeout_option'];
            }

            // TODO This might be where ElementText records get created so that next
            // TODO   time the user edits this record, values show up in the form fields

                $soRecord->save();
                break;

            //  move protected files from SORecord to Omeka and delete SORecord;
            case '-StreamOnly':
                $soRecord = get_record(SO_PLUGIN_MODEL, array("item_id"=>$item->id));
                $source = array('which'=>'StreamOnly',
                                'dir'=>$soRecord->so_directory);
                $target = array('which'=>'Omeka', 'dir' =>'');
                $this->_moveFiles($item, $source, $target);
                $soRecord->delete();
                break;

            // no action required
            case 'update':
            case 'delete': // This will never happen
                break;
        }

    }

    /**
     * Fires before the record is deleted from the Item table
     *
     * @param $args['record']  record of type Item
     */
    public function hookBeforeDeleteItem($args) {

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $args['record'];
        if (!isSOItem($item)) return;

        // We are deleting the Item's record
        $this->_soState['operation'] = "delete";

    }


    /**
     * Fires after the record is deleted from the Item table
     *
     * @param $args['record']  record of type Item
     */
    public function hookAfterDeleteItem($args) {

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $args['record'];
        if (!isSOItem($item)) return;

        // Delete the record for this Item found in the StreamOnlyModel table
        get_record(SO_PLUGIN_MODEL, array('item_id'=>$item->id))->delete();

    }

    /*****  FILES  ******************************************************/

    /**
     * Fires after the record is saved in the Files table
     *
     * @param $args
     *   ['record']  record of type File
     *   ['post'] array or FALSE [DO NOT USE]
     *   ['insert'] bool (true if record is being inserted)
     */
    public function hookAfterSaveFile($args) {

        $file = $args['record'];

        // hookBeforeSaveItem() has not yet been called, so it is not known
        //  if the file should be moved, or where it should be moved to
        //  Add it to the list, to be processed later.
        if (($this->_soState['operation'] == 'unknown') && $args['insert']) {
            $this->_soState['filelist'][] = $file;
            return;
        }

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $file->getItem();
        if (!isSOItem($item)) return;

        // check that this file is one we need to protect against downloading
        if (!$this->_isProtectedFile($file)) return;

        // Leave the file in the appropriate place
        switch ($this->_soState['operation']) {

            // move protected file from Omeka to SOOption
            case 'insert':
                if (!$args['insert']) break; // no action the first time we're called
            case '+StreamOnly':
                $sourceDir = $this->_buildPath('Omeka', '');
                $targetDir = $this->_buildPath('StreamOnly', $this->_soState['folder_option']);
                $this->_moveFile($file, $sourceDir, $targetDir);
                break;

            //if $args['insert'] move protected file from Omeka to SORecord/SOOption
            case 'update':
                if ($args['insert']) {
                    $sourceDir = $this->_buildPath('Omeka', '');
                    $soRecord = get_record(SO_PLUGIN_MODEL, array('item_id' => $item->id));
                    if ($soRecord) {
                        $targetDir = $this->_buildPath('StreamOnly', $soRecord->so_directory);
                    } else {
                        $targetDir = $this->_buildPath('StreamOnly', $this->_soState['folder_option']);
                    }
                    $this->_moveFile($file, $sourceDir, $targetDir);
                }
                break;

            // no action needed
            case '-StreamOnly':
            case 'delete': // never happens
                break;
            }

    }

    /**
     * Fires before the record is deleted from the Files table
     *
     * @param $args['record']  record of type File
     */
    public function hookBeforeDeleteFile($args)
    {

        $file = $args['record'];

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $file->getItem();
        if (!isSOItem($item)) return;

        // check that this file is one we need to protect against downloading
        if (!$this->_isProtectedFile($file)) return;

        // Leave the file in the right place
        switch ($this->_soState['operation']) {
            // move protected file from SORecord to Omeka
            case 'unknown':
            case 'update':
            case 'delete':
            case '-StreamOnly':
                $soRecord = get_record(SO_PLUGIN_MODEL, array('item_id'=>$item->id));
                $sourceDir = $this->_buildPath('StreamOnly', $soRecord->so_directory);
                $targetDir = $this->_buildPath('Omeka', '');
                $this->_moveFile($file, $sourceDir, $targetDir);
                break;

            // move protected file from SOOption to Omeka
            case '+StreamOnly':
                $sourceDir = $this->_buildPath('StreamOnly', $this->_soState['folder_option']);
                $targetDir = $this->_buildPath('Omeka', '');
                $this->_moveFile($file, $sourceDir, $targetDir);
                break;

            // no action required
            case 'insert': // never happens
                break;
        }

    }

    /****** ELEMENT TEXTS ********************************************/

    /**
     * Fires after the record is saved to the ItemTypesElement table
     *
     * @param $args
     *   ['record'] record of type ElementText
     *   ['post'] array or FALSE [DO NOT USE]
     *   ['insert'] bool (true if record is being inserted)
     */
    public function hookAfterSaveElementText($args) {

        // Get the ElementTexts record
        $elemText = $args['record'];

        // Check that this ElementText is associated with an Item
        if ($elemText->record_type != "Item") return;

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = get_record_by_id('Item', $elemText->record_id);
        if (!isSOItem($item)) return;

        // Get the Element Type
        $elemType = get_record_by_id("Element", $elemText->element_id)->name;

        switch ($elemType) {

            case ELEMENT_FOLDER:
                switch ($this->_soState['operation']) {

                    // if (SOElemText != SOOption) move protected files from SOOption to SOElemText
                    case 'insert':
                    case '+StreamOnly':
                        // If needed, move protected files from SOOption to SOElemText
                        if ($elemText->text != $this->_soState['folder_option']) {
                            $source = array("which"=>'StreamOnly', "dir"=>$this->_soState['folder_option']);
                            $target = array("which"=>'StreamOnly', "dir"=>$elemText->text);
                            $this->_moveFiles($item, $source, $target);
                        }
                        break;

                    // If needed move files from SORecord to SOElemtext and update SORecord
                    case 'update':
                        $soRecord = get_record(SO_PLUGIN_MODEL, array("item_id"=>$elemText->record_id));
                        if ($elemText->text != $soRecord->so_directory) {
                            $source = array("which"=>'StreamOnly', "dir"=>$soRecord->so_directory);
                            $target = array("which"=>'StreamOnly', "dir"=>$elemText->text);
                            $this->_moveFiles($item, $source, $target);
                            $soRecord->so_directory = $elemText->text;
                            $soRecord->save();
                        }
                        break;

                    // no action needed
                    case '-StreamOnly':
                    case 'delete':
                        break;
                }
                break;

            case ELEMENT_TIMEOUT:
                switch ($this->_soState['operation']) {
                    case 'update':
                        $soRecord = get_record(SO_PLUGIN_MODEL, array("item_id"=>$elemText->record_id));
                        $soRecord->so_timeout = $elemText->text;
                        $soRecord->save();
                        break;
                    case 'insert': // SORecord doesn't exist yet, will be created by hookAfterSaveItem()
                    case '+StreamOnly': // SORecord doesn't exist yet, will be created by hookAfterSaveItem()
                    case '-StreamOnly': // no action required
                    case 'delete': // never happens
                        break;
                }
                break;

            case ELEMENT_LICENSE_COUNT:
                switch ($this->_soState['operation']) {
                    case 'update':
                        $soRecord = get_record(SO_PLUGIN_MODEL, array("item_id"=>$elemText->record_id));
                        $soRecord->so_licenses = $elemText->text;
                        $soRecord->save();
                        break;
                    case 'insert': // SORecord doesn't exist yet, will be created by hookAfterSaveItem()
                    case '+StreamOnly': // SORecord doesn't exist yet, will be created by hookAfterSaveItem()
                    case '-StreamOnly': // no action required
                    case 'delete': // never happens
                        break;
                }
                break;

            // not a field that affects StreamOnly Items, no action required
            default:
                break;
        }

    }

    /**
     * Fires after the record is deleted from the ElementTexts table
     *
     * @param $args['record']  record from the ElementTexts table
     */
    public function hookAfterDeleteElementText($args) {

        // Get the ElementTexts record
        $elemText = $args['record'];

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = get_record_by_id('Item', $elemText->record_id);
        if (!isSOItem($item)) return;

        // Get the Element Type
        $elemType = get_record_by_id("Element", $elemText->element_id)->name;

        switch ($elemType) {

            case ELEMENT_FOLDER:
                switch ($this->_soState['operation']) {
                    // If needed, move protected files from SORecord to SOOption and update SORecord
                    case 'update':
                        $soRecord = get_record(SO_PLUGIN_MODEL, array("item_id"=>$elemText->record_id));
                        if ($soRecord->so_directory != $this->_soState['folder_option']) {
                            $source = array("which"=>'StreamOnly', "dir"=>$soRecord->so_directory);
                            $target = array("which"=>'StreamOnly', "dir"=>$this->_soState['folder_option']);
                            $this->_moveFiles($item, $source, $target);
                            $soRecord->so_directory = $this->_soState['folder_option'];
                            $soRecord->save();
                        }
                        break;

                    // no action needed
                    case 'insert': // never happens
                    case 'delete':
                    case '+StreamOnly':
                    case '-StreamOnly':
                        break;
                }
                break;

            case ELEMENT_TIMEOUT:
                switch ($this->_soState['operation']) {
                    case 'update':
                        // If needed update SORecord
                        $soRecord = get_record(SO_PLUGIN_MODEL, array("item_id"=>$elemText->record_id));
                        if ($soRecord->so_timeout != $this->_soState['timeout_option']) {
                            $soRecord->so_timeout = $this->_soState['timeout_option'];
                            $soRecord->save();
                        }
                        break;

                    // no action needed
                    case 'insert': // never happens
                    case 'delete': // no action needed
                    case '+StreamOnly': // no action needed
                    case '-StreamOnly': // no action needed
                        break;
                }
                break;

            case ELEMENT_LICENSE_COUNT:
                switch ($this->_soState['operation']) {
                    case 'update':
                        // If needed update SORecord
                        $soRecord = get_record(SO_PLUGIN_MODEL, array("item_id"=>$elemText->record_id));
                        if ($soRecord->so_licenses != $this->_soState['license_option']) {
                            $soRecord->so_licenses = $this->_soState['license_option'];
                            $soRecord->save();
                        }
                        break;
                    case 'insert': // never happens
                    case 'delete': // no action needed
                    case '+StreamOnly': // no action needed
                    case '-StreamOnly': // no action needed
                        break;
                }
                break;
            default:
                break;
        }

    }

    /****** FOOTER ****************************************************/

    /**
     * This function fires when the footer is being output
     * on an Admin page. It only needs to insert Javascript code
     * for certain menus. The JS code will remove the ability of
     * the Site Administrator to delete the StreamOnly Item Type,
     * or to delete the three Item Type Elements created by the
     * StreamOnly plugin. These will be deleted if/when the plugin
     * is uninstalled.
     *
     * Typical URL for View Item Type xx menu
     * http://domain/admin/item-types/show/xx
     * Typical URL for Edit Item Type XX menu
     * http://domain/admin/item-types/edit/xx
     *   where "xx" is the id of the record
     *   for the StreamOnly Item Type.
     *
     */
    public function hookAdminFooter() {

        // TODO Script to update # seconds until audio file available?
        // TODO You never know, it might be needed in Admin Footer

        // Add JS for Show Item Type and Edit Item Type menus, if needed
        if (soItemTypeNoDelete()) return;

        // Add JS for Settings:Item Type Elements menu, if needed
        if (soSettingsITE()) return;

    }
}
