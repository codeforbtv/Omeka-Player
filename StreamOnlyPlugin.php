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
//    require_once dirname(__FILE__) . '/helpers/StreamOnlyAccess.php'; TODO restore when testing rule

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

        'before_save_file',
        'after_save_file',
        'before_delete_file',
        'after_delete_file',

        'before_save_element_text',
        'after_save_element_text',
        'before_delete_element_text',
        'after_delete_element_text');

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

    // TODO Check that Omeka translates this message, and that plugin doesn't have to do it
    public $uninstall_message = "All protected files will be moved to the default upload directory. Visitors to the site will be able to download them. All Items of Item Type StreamOnly will be set to undefined.";

    /**
     * StreamOnly plugin constructor.
     *
     * Registers the callback function used by Omeka's Global Theming Function "file_markup()"
     *   to output the HTML for audio/mpeg files.
     * TODO third param - $options - to be passed to callback as second param - $props?
     * TODO If so, should we allow admin or theme to customize?
     *
     * @return StreamOnlyPlugin
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
     * Get the fields from the $_POST variable that are useful for the StreamOnly plugin
     *
     * // TODO Is there ever a time when the Element Type for an Item Type is not found in $_POST?
     * // TODO Should we be checking for empty()?
     * // TODO Should we be filling in default values? leaving array elements unset?
     *
     * @uses $_POST
     * @return array - the keys are the field names of the forms, the values are the user entries
     *                 for the form fields (in Item Type Metadata) that affect SO Item behavior
     */
    private function _getPostData(){

        $licenseElementId = get_record('Element', array('name'=>ELEMENT_LICENSE_COUNT))->id;
        if (isset($_POST['Elements'][$licenseElementId])) {
            $data[ELEMENT_LICENSE_COUNT_FIELD]=$_POST['Elements'][$licenseElementId][0]['text'];
        } else {
            $data[ELEMENT_LICENSE_COUNT_FIELD] = DEFAULT_LICENSES;
        }

        $folderElementId  = get_record('Element', array('name'=>ELEMENT_FOLDER))->id;
        if (isset($_POST['Elements'][$folderElementId])) {
            $data[ELEMENT_FOLDER_FIELD]=$_POST['Elements'][$folderElementId][0]['text'];
        } else {
            $data[ELEMENT_FOLDER_FIELD] = "";
        }

        // TODO Get value of timeout from user
        $data[ELEMENT_TIMEOUT_FIELD] = DEFAULT_TIMEOUT;

        return $data;

    }

    /**
     * Check that the user input is valid when creating/editing an Item
     *
     * TODO output error messages as needed
     * TODO check that Omeka sanitizes POST variables
     *
     * @postData - the output from _getPostData()
     * @return bool (true if no errors;
     *               false if errors, messages stored in $record)
     */
    private function _soValidateItemFields($postData, $item) {

        $no_errors = true;

        // 1. $postData[ELEMENT_LICENSE_COUNT_FIELD] is an integer > 0
        // 2. $postData[ELEMENT_FOLDER_FIELD] is a valid path
        // 3. The length of the path + file name < 255 chars
        // 4.

        // Use defined constants for the field names
        // $item->addError('field_name', __('Error message'));
        // $item->addError(ELEMENT_LICENSE_COUNT_FIELD, __('Error message'));
        // $item->addError(ELEMENT_FOLDER_FIELD,        __('Error message'));
        // $item->addError(ELEMENT_TIMEOUT_FIELD,       __('Error message'));

        return $no_errors;

    }

    /**
     * Check that the user input is valid
     *
     * TODO output error messages as needed
     * TODO check that Omeka sanitizes POST variables
     *
     * TODO how should error messages be returned? throw an error?
     *
     * @postData - the output from $this->_getPostData()
     * @return bool (true if no errors;
     *               false if errors, messages stored in $record)
     */
    private function _soValidateConfigFields($postData) {

        $no_errors = true;

        // 1. $postData[OPTION_LICENSE_COUNT_FIELD] is an integer
        // 2. $postData[OPTION_FOLDER_FIELD] is a valid directory
        // 3  $postData[OPTION_FOLDER_FIELD] has correct permissions
        // 4. The length of the path + file name < 255 chars
        // 5. $postData[OPTION_TIMEOUT_FIELD] is an integer

        // Use defined constants for the field names
        // $record->addError('field_name', __('Error message')); ?? where is the record ??
        // $record->addError(OPTION_LICENSE_COUNT_FIELD, __('Error message'));
        // $record->addError(OPTION_FOLDER_FIELD,        __('Error message'));
        // $record->addError(OPTION_TIMEOUT_FIELD,       __('Error message'));

        return $no_errors;

    }

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
     * TODO Use Omeka's constants for building path names
     *
     * @param $which - 'Omeka' or 'StreamOnly'
     * @param $folder - the SO folder, if $which = 'StreamOnly'
     * @return string - the desired path
     */
    private function _buildPath($which, $folder) {
        switch ($which) {
            case 'Omeka':
                $path = _remove_nodes(dirname(__FILE__), 2)
                    . DIRECTORY_SEPARATOR . 'files'
                    . DIRECTORY_SEPARATOR . 'original'
                    . DIRECTORY_SEPARATOR;
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
     * TODO Throw execption if failure
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
     * @param $source['which']  - 'Omeka' or 'StreamOnly', to be provided as input to _buildPath
     * @param $source['srcDir'] - folder in which to search for files to be moved
     * @param $target['which']    - 'Omeka' or 'StreamOnly', to be provided as input to _buildPath
     * @param $target['destDir']  - folder in which to place the files to be moved
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
     * Installs the StreamOnly Plugin
     *
     * checks that the StreamOnly ItemType does not exist, then creates it
     * (to ensure the integrity of the plugin, we want it to set up the db properly)
     * creates a table in the database to store info about SO protected audio/mpeg files
     * (table can be accessed with $db->getTable('StreamOnly'))
     * initializes options with defaults for SO Items
     *
     * @param $pluginID (not used)
     * @throws Omeka_Plugin_Installer_Exception
     */
    public function hookInstall($pluginID) {

        $db = $this->_db;

        // Check for existing Item Type, to avoid "duplicate name" death
        $itemTypeTable = $db->getTable('ItemType');
        $itemTypeList = $itemTypeTable->findBy(array('name'=>SO_ITEM_TYPE_NAME));
        if (!empty($itemTypeList)) {
            throw new Omeka_Plugin_Installer_Exception(__(
                '['. SO_PLUGIN_NAME . ' Plugin]: The Item Type ' .
                SO_ITEM_TYPE_NAME . ' already exists.'));
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
        //
        // Taken from Omeka install hook documentation
        // *** http://omeka.org/forums-legacy/topic/plugin-modify-item-table/
        // "create your own Model and table with the values, mapped to the item"
        // id | item_id | value1 | value2 | etc


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
        $m3uDir = _remove_nodes(dirname(__FILE__), 2) . '/files/m3u';
        if (!mkdir($m3uDir, 0777)) {               // TODO determine appropriate permissions
            die("Couldn't create m3u directory");  // TODO use Omeka error reporting
        }
        // create the .htaccess file to protect the .m3u files
        $handle = fopen($m3uDir . "/.htaccess", "w");
        fwrite($handle, SO_DENY_ACCESS);
        fclose($handle);
        // Create an empty file for storing list of reserved files
        $handle = fopen($m3uDir . "/reserved.txt", "w");
        fclose($handle);

//        // Add new Rewrite rule to .htaccess file TODO restore when testing rule
//        so_update_access('add');

    }


    /**
     * For each Item of Item Type StreamOnly
     *   Moves all files from the SO folder to the Omeka default uploads folder
     *   Resets the Item Type to NULL (undefined)
     * Removes the Item Type StreamOnly
     *
     * removes options created by hookInstall() for storing defaults
     * removes the table in the database with info about SO protected audio/mpeg files
     * removes the Rewrite rule in the .htaccess file added by hookInstall()
     *
     * Leaves in place the Elements unique to SO Item Type
     * Leaves in place the Element Texts for Items that were of SO Item Type
     *
     * * @param $args
     * * @error - if StreamOnly Item Type is missing, returns with an error message
     */
    public function hookUninstall($args)
    {

        $db = get_db();

        // Remove all the files from the m3u directory under the files directory,
        // then delete the directory
        $m3uDir = _remove_nodes(dirname(__FILE__), 2) . '/files/m3u/';
        $dir = opendir($m3uDir);
        if (!$dir) die("Can't find playlist directory"); // TODO Report Omeka Error
        while ($file = readdir($dir)) {
            unlink($m3uDir . $file);
        }
        rmdir($m3uDir);

        // Remove the option values from the db table
        delete_option(OPTION_LICENSES);
        delete_option(OPTION_FOLDER);
        delete_option(OPTION_TIMEOUT);


//        // remove the Rewrite rule we added to the .htaccess file during install
//        so_update_access('remove'); TODO restore when testing rule

        // Remove the references to the Item Type from all Items as needed,
        // and move protected files from the SO folder to the upload folder
        // Remove the StreamOnly Item Type, and associated Item Type Elements

        // Get the StreamOnly Item Type record

        $itemType = get_record('ItemType', array('name'=>SO_ITEM_TYPE_NAME));
        if ($itemType == NULL) {
            // Someone deleted the StreamOnly Item Type.
            // TODO display an Omeka error message
            return;
        }

        // Get all the Items of Item Type StreamOnly
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
     * The needed Options will be solicited from the system administrator
     * Appropriate instructions and warnings will be provided
     *
     * @params - none
     * @returns - none
     */
    public function hookConfigForm()
    {

        $defaultLicenses = get_option(OPTION_LICENSES);
        $defaultFolder   = get_option(OPTION_FOLDER);
        $timeout         = get_option(OPTION_TIMEOUT);

        include 'config_form.php';
    }

    /**
     * Called after the Configuration Form is filled out
     *
     * TODO How to report validation errors?
     *
     * @param - none
     * @return - none
     * @global - uses the $_POST server global variable
     */
    public function hookConfig()
    {
        $postData = $this->_getPostData();

        // TODO Need to sanitize the $_POST variables???
        if (!$this->_soValidateConfigFields($postData)) return;

        $defaultLicenses = $_POST[OPTION_LICENSES];
        $defaultFolder   = $_POST[OPTION_FOLDER];
        $timeout         = $_POST[OPTION_TIMEOUT];

        set_option(OPTION_LICENSES, $defaultLicenses);
        set_option(OPTION_FOLDER, $defaultFolder);
        set_option(OPTION_TIMEOUT, $timeout);

    }

    /**
     * Do any database conversions needed
     * Reserved for future use
     */
    public function hookUpgrade()
    {

        // Just checking that we got here.
        echo("hookUpgrade");

    }

    /****** ITEMS **********************************************************/

    /**
     * Fires before the record is saved in the Item table
     * If this is an SO Item:
     *   Determine why this Item's record is being updated
     *   $this->$_soState['filelist'] contains files which were added to the Item
     *   Move files in filelist based on the scenario
     *
     * switch ($this->$_soState['operation'])
     *   case update:
     *   case -StreamOnly:
     *     move protected files in filelist from Omeka to soRecord
     *   case +StreamOnly:
     *     move protected files in filelist from Omeka to soOption
     *   case insert:
     *   case delete:
     *
     *   TODO Might be the place to validate the fields
     *
     * @param $args
     *   ->record  record of type Item
     *   ->post array or FALSE [DO NOT USE]
     *   ->insert bool (true if record is being inserted)
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
            case 'update':
            case '-StreamOnly':
                // move protected files in filelist from Omeka to soRecord
                $source = array('which'=>'Omeka', 'dir' =>'');
                $target = array('which'=>'StreamOnly',
                                'dir'=>$soRecord->so_directory);
                $this->_moveFiles(NULL, $source, $target);
                break;
            case '+StreamOnly':
                // move protected files in filelist from Omeka to soOption
                $source = array('which'=>'Omeka', 'dir' =>'');
                $target = array('which'=>'StreamOnly',
                    'dir'=>$this->_soState['folder_option']);
                $this->_moveFiles(NULL, $source, $target);
                break;
            case 'insert': // $this->_soState['filelist'] is always empty
            case 'delete': // $this->_soState['filelist'] is always empty
                break;
        }

        // Just checking that we got here.
        echo("hookBeforeSaveItem: $item->id \n");

    }

    /**
     * Fires after the record is saved in the Item table
     *
     * if this is an SO Item:
     *   switch ($this->_soState['operation']) {
     *     case 'insert':
     *     case '+StreamOnly':
     *       create SORecord based on ElemText/SOOption
     *       break;
     *     case '-StreamOnly':
     *       move protected files from SORecord to Omeka
     *       delete SORecord;
     *       break;
     *     case 'update':
     *     case 'delete': // This will never happen
     *       break;
     *   }
     *
     * @param $args
     *   ->record  record of type Item
     *   ->post array or FALSE [DO NOT USE]
     *   ->insert bool (true if record is being inserted)
     * @throws Omeka_Record_Exception
     * @throws Omeka_Validate_Exception
     */
    public function hookAfterSaveItem($args) {

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $args['record'];
        if (!isSOItem($item)) return;


        // Finalize the state of the StreamOnlyModel record for this Item
        switch ($this->_soState['operation']) {
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
            $soDirectory = get_record('ElementText',
                array("record_id"=>$item->id, "element_id"=>$this->_soState['license_elemtext_id']));
            if ($soDirectory) {
                $soRecord->so_licenses = $soDirectory->text;
            } else {
                $soRecord->so_licenses = $this->_soState['license_option'];
            }


            // If the user specified a timeout for this item, use it, otherwise use the default
            $soDirectory = get_record('ElementText',
                array("record_id"=>$item->id, "element_id"=>$this->_soState['timeout_elemtext_id']));
            if ($soDirectory) {
                $soRecord->so_directory = $soDirectory->text;
            } else {
                $soRecord->so_timeout = $this->_soState['timeout_option'];
            }

            // TODO This might be where ElementText records get created so that next
            // TODO time the user edits this record, values show up in the form fields

                $soRecord->save();
                break;
            case '-StreamOnly':
                // Move protected files from SORecord to Omeka
                $soRecord = get_record(SO_PLUGIN_MODEL, array("item_id"=>$item->id));
                $source = array('which'=>'StreamOnly',
                                'dir'=>$soRecord->so_directory);
                $target = array('which'=>'Omeka', 'dir' =>'');
                $this->_moveFiles($item, $source, $target);
                $soRecord->delete();
                break;
            case 'update':
            case 'delete': // This will never happen
                break;
        }

         echo("hookAfterSaveItem: $item->id \n");
    }

    /**
     * Fires before the record is deleted from the Item table
     *
     * If this is an SO Item:
     *   Record why this Item's record is being updated
     *
     * @param $args['record']  record of type Item
     */
    public function hookBeforeDeleteItem($args) {

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $args['record'];
        if (!isSOItem($item)) return;

        // We are deleting the Item's record
        $this->_soState['operation'] = "delete";

        // Just checking that we got here.
        echo("hookBeforeDeleteItem: $item->id \n");

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

        // Just checking that we got here.
        echo("hookAfterDeleteItem': $item->id \n");

    }

    /*****  FILES  ******************************************************/

    /**
     * Fires before the record is saved in the Files table
     * If inserting:
     *   $args['record']->id is not yet available
     *   the file has not yet been uploaded
     *
     * TODO probably don't need this hook
     *
     * @param $args
     *   ->record  record of type File
     *   ->post array or FALSE [DO NOT USE]
     *   ->insert bool (true if record is being inserted)
     */
    public function hookBeforeSaveFile($args)
    {

        $file = $args['record'];

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $file->getItem();
        if (!isSOItem($item)) return;

        // check that this file is one we need to protect against downloading
        if (!$this->_isProtectedFile($file)) return;

        // Just checking that we got here.
        echo("hookBeforeSaveFile: $file->filename \n");

    }


    /**
     * Fires after the record is saved in the Files table
     *
     * $args['record']->id is available and
     *   the file has been uploaded. Where it is depends
     *   on the scenario.
     *
     * If a File is being inserted, and $this->_soState['operation'] == 'unknown',
     *   hookBeforeSaveItem() has not yet been called, and
     *   there is not enough information to know if the file needs to be moved
     *   or where it should be moved to.
     *   Append the record to $this->_soState['filelist'] and return.
     *   Don't worry if the Item is StreamOnly or if this is a protected file.
     *   That will be sorted out by other hooks and helper functions.
     *
     * logic for moving files, otherwise
     *
     * switch $this->_soState['operation']
     *   case 'insert':
     *   case '+StreamOnly':
     *     move protected file from Omeka to SOOption
     *   case 'update':
     *     if $args['insert']
     *       move protected file from Omeka to SORecord/SOOption
     *   case '-StreamOnly':
     *   case 'delete':  // Never happens
     *
     * @param $args
     *   ->record - record of type File
     *   ->post - array (last POST command) or FALSE
     *   ->insert - bool (true if record is being inserted)
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
            case 'insert':
                if (!$args['insert']) break; // no action the first time we're called
            case '+StreamOnly':
                // move protected file from Omeka to SOOption
                $sourceDir = $this->_buildPath('Omeka', '');
                $targetDir = $this->_buildPath('StreamOnly', $this->_soState['folder_option']);
                $this->_moveFile($file, $sourceDir, $targetDir);
                break;
            case 'update':
                if ($args['insert']) {
                    // Move protected files from Omeka to SORecord/SOOption
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
            case '-StreamOnly':
            case 'delete': // never happens
                break;
            }

        // Just checking that we got here.
        echo("hookAfterSaveFile: $file->filename \n");

    }

    /**
     * Fires before the record is deleted from the Files table
     *
     * switch $this->_soState['operation']
     *   case 'update':
     *   case 'delete':
     *   case '+StreamOnly':
     *     move protected file from SORecord to Omeka
     *   case '-StreamOnly':
     *     move protected file from SOOption to Omeka
     *   case 'insert': // never happens
     *     break;
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
            case 'update':
            case 'delete':
            case '-StreamOnly':
                // move protected file from SORecord to Omeka
                $soRecord = get_record(SO_PLUGIN_MODEL, array('item_id'=>$item->id));
                $sourceDir = $this->_buildPath('StreamOnly', $soRecord->so_directory);
                $targetDir = $this->_buildPath('Omeka', '');
                $this->_moveFile($file, $sourceDir, $targetDir);
                break;
            case '+StreamOnly':
                // move protected file from SOOption to Omeka
                $sourceDir = $this->_buildPath('StreamOnly', $this->_soState['folder_option']);
                $targetDir = $this->_buildPath('Omeka', '');
                $this->_moveFile($file, $sourceDir, $targetDir);
                break;
            case 'insert': // never happens
                break;
        }

        echo("hookBeforeDeleteFile: $file->filename \n");
    }

    /**
     * Fires after the record is deleted from the Files table
     * The files have also been deleted
     *
     * // TODO probably won't need this hook
     *
     * @param $args['record']  record of type File
     */
    public function hookAfterDeleteFile($args) {

        $file = $args['record'];

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $file->getItem();
        if (!isSOItem($item)) return;

        // check that this file is one we need to protect against downloading
        if (!$this->_isProtectedFile($file)) return;

        // Just checking that we got here.
        echo("hookAfterDeleteFile: $file->filename \n");

    }

    /****** ELEMENT TEXTS ********************************************/

    /**
     * Fires before the record is saved to the ItemTypesElement table
     * If the record is new, the id will be NULL
     * We only need to process the record if it is associated with
     *   an Item of ItemType StreamOnly and
     *   it is one of the fields stored in the StreamOnlyModel table.
     *
     * TODO This hook is probably not needed
     *
     * @param $args->record  record from the ElementTexts table
     * @param $args->post    false or contains data from POST
     * @param $args->insert  true if record is being created
     *                       false if record is being updated
     */
    public function hookBeforeSaveElementText($args) {

        // Get the ElementTexts record
        $elemText = $args['record'];

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = get_record_by_id('Item', $elemText->record_id);
        if (!isSOItem($item)) return;

        // Get the Element Type
        $elemType = get_record_by_id("Element", $elemText->element_id)->name;

        echo("hookBeforeSaveElementText: $elemType - $elemText->text\n");

    }

    /**
     * Fires after the record is saved to the ItemTypesElement table
     * We only need to process the record if it is associated with
     *   an Item of ItemType StreamOnly and
     *   it is one of the fields stored in the StreamOnlyModel table.
     *
     * Logic for updating SORecord, but only if SORecord exists
     * In some cases SORecord will be created later by hookAfterSaveItem:
     *
     * if (field in SORecord != SOElemText)
     *   field in SORecord = SOElemText
     *   update SORecord
     *
     * Logic for moving files:
     *
     * switch ($_soState['operation'])
     *   case 'insert':
     *   case '+StreamOnly':
     *     if (SOElemText != SOOption)
     *       move protected files from SOOption to SOElemText
     *   case 'update':
     *    if (SOMRecord != SODElemText)
     *      move files from SOMRecord to SODElemText
     *      update SOMRecord
     *   case '-StreamOnly':
     *   case 'delete':
     *
     * @param $args->record  record from the ElementTexts table
     * @param $args->post    false or contains data from POST
     * @param $args->insert  true if record is being created
     *                       false if record is being updated
     */
    public function hookAfterSaveElementText($args) {

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

                    case 'insert':
                    case '+StreamOnly':
                        // If needed, move protected files from SOOption to SOElemText
                        if ($elemText->text != $this->_soState['folder_option']) {
                            $source = array("which"=>'StreamOnly', "dir"=>$this->_soState['folder_option']);
                            $target = array("which"=>'StreamOnly', "dir"=>$elemText->text);
                            $this->_moveFiles($item, $source, $target);
                        }
                        // SORecord doesn't exist yet, will be created by hookAfterSaveItem()
                        break;

                    case 'update':
                        // If needed move files from SORecord to SOElemtext and update SORecord
                        $soRecord = get_record(SO_PLUGIN_MODEL, array("item_id"=>$elemText->record_id));
                        if ($elemText->text != $soRecord->so_directory) {
                            $source = array("which"=>'StreamOnly', "dir"=>$soRecord->so_directory);
                            $target = array("which"=>'StreamOnly', "dir"=>$elemText->text);
                            $this->_moveFiles($item, $source, $target);
                            $soRecord->so_directory = $elemText->text;
                            $soRecord->save();
                        }
                        break;
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

            default:
                break;
        }

        echo("hookAfterSaveElementText: $elemType - $elemText->text\n");
    }

    /**
     * Fires before the record is deleted from the ElementTexts table
     *
     * TODO hook probably not needed
     *
     * @param $args['record']  record from the ElementTexts table
     */
    public function hookBeforeDeleteElementText($args) {

        // Get the ElementTexts record
        $elemText = $args['record'];

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = get_record_by_id('Item', $elemText->record_id);
        if (!isSOItem($item)) return;

        // Get the Element Type
        $elemType = get_record_by_id("Element", $elemText->element_id)->name;

        echo("hookBeforeDeleteElementText: $elemType - $elemText->text\n");
    }

    /**
     * Fires after the record is deleted from the ElementTexts table
     * We only need to process the record if it is associated with
     *   an Item of ItemType StreamOnly and
     *   it is one of the fields stored in the StreamOnlyModel table.
     *
     * Logic for updating SORecord
     *   but only if SORecord will not be updated or deleted later by hookAfterSaveItem()
     *
     * if (field in SORecord ~= $this->_soState['Option'])
     *   field in SORecord = $this->_soState['Option']
     *   update SORecord
     *
     * Logic for moving files:
     *
     * switch ($this->_soState['operation'])
     *   case 'update':
     *     if (SORecord != SOOption)
     *       move protected files from SORecord to SOOption
     *       update SORecord to SOOption
     *   case 'insert': // never happens
     *   case 'delete': // no action needed
     *   case '+StreamOnly': // no action needed
     *   case '-StreamOnly': // no action needed
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
                    case 'update':
                        // If needed, move protected files from SORecord to SOOption and update SORecord
                        $soRecord = get_record(SO_PLUGIN_MODEL, array("item_id"=>$elemText->record_id));
                        if ($soRecord->so_directory != $this->_soState['folder_option']) {
                            $source = array("which"=>'StreamOnly', "dir"=>$soRecord->so_directory);
                            $target = array("which"=>'StreamOnly', "dir"=>$this->_soState['folder_option']);
                            $this->_moveFiles($item, $source, $target);
                            $soRecord->so_directory = $this->_soState['folder_option'];
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

        echo("hookAfterDeleteElementText: $elemType - $elemText->text\n");
    }
}
