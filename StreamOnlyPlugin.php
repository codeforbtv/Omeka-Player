<?php
/**
 * Stream Only Omeka Player Plugin
 * Hooks, Filters, Options
 *
 * @copyright Copyright 2018 Code for Burlington
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
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
        'after_delete_file');

    protected $_options = array();

    protected $_filters = array();

    protected static $_callbackOptions = array(
        'width' => '200',
        'height' => '20',
        'autoplay' => false,
        'controller' => true,
        'loop' => false
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

        // Register callback
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
        return (preg_match("(audio/mpeg)i", $file->mimeType));
//        return (preg_match("(\.mp3$)", $file->filename));
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
     * @param $path - directory where the file might be found
     * @return bool
     */
    private function _isInPath($file, $path) {

        return (file_exists($path . $file->filename));
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
     * @param $source['which']  - 'Omeka' or 'StreamOnly', to be provided as input to _buildPath
     * @param $source['srcDir'] - folder in which to search for files to be moved
     * @param $target['which']    - 'Omeka' or 'StreamOnly', to be provided as input to _buildPath
     * @param $target['destDir']  - folder in which to place the files to be moved
     */
    private function _moveFiles($item, $source, $target) {

        $filelist = get_db()->getTable('File')->findBy(array('item_id' => $item->id));
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
            throw new Omeka_Plugin_Installer_Exception(__( // TODO Use substitution rules for building message
                '['. SO_PLUGIN_NAME . ']: The Item Type ' .
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
        if (!mkdir($m3uDir, 0777)) {
            die("Couldn't create m3u directory");  // TODO use Omeka error reporting
        }
        $handle = fopen($m3uDir . "/.htaccess", "w");
        fwrite($handle, SO_DENY_ACCESS);
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
        rmdir(m3uDir);

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
        echo('hookUpgrade');

    }

    /****** ITEMS **********************************************************/

    /**
     * Fires before the record is saved in the Item table
     * If this is an SO Item:
     *   Validate the fields
     *   Figure out where existing SO files (if any) are stored
     *   Figure out where SO files (if any) should be stored after the Item is saved
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

        // check that user-entered fields have valid entries
        $postData = $this->_getPostData();
        if (!$this->_soValidateItemFields($postData, $item)) return;

        // Just checking that we got here.
        echo(`hookBeforeSaveItem: $item->id \n`);

    }

    /**
     * Fires after the record is saved in the Item table
     *
     * Ensure that all the files are stored in the right place
     * If inserting a new Item, insert a record in the SO table
     * Else update the record in the SO table
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

        $insert = $args['insert'];
        $itemType = get_record('ItemType', array('name'=>SO_ITEM_TYPE_NAME));
        $postData = $this->_getPostData();

        // Get information where existing StreamOnly files for this Item (if any) are stored, etc.
        // If we're creating a new Item,
        // or if the Item was previously an Item Type other than StreamOnly
        //   the files are in the default upload directory
        $oldSORecord = get_record(SO_PLUGIN_MODEL, array('item_id'=>$item->id));
        if ($insert || ($oldSORecord == false)) {
            $oldSORecord                 = new StreamOnlyModel(get_db());
            $oldSORecord['id']           = NULL;
            $oldSORecord['item_id']      = $item->id;
            $oldSORecord['so_directory'] = NULL; // look for SO files in upload directory
            $oldSORecord['so_timeout']   = get_option(OPTION_TIMEOUT);
            $source = array('which'=>'Omeka', 'dir'=>"");
        } else {
            $source = array('which'=>'StreamOnly', 'dir'=>$oldSORecord['so_directory']);
        }

        // Get information about where StreamOnly files for this Item (if any) will be stored, etc.
        $newSORecord                 = new StreamOnlyModel(get_db());
        $newSORecord['id']           = $oldSORecord['id'];
        $newSORecord['item_id']      = $item->id;
        if (isset($postData[ELEMENT_FOLDER_FIELD]) && !empty($postData[ELEMENT_FOLDER_FIELD])) {
            $newSORecord['so_directory'] = $postData[ELEMENT_FOLDER_FIELD];
        } elseif ($oldSORecord['so_directory'] != NULL) {
            $newSORecord['so_directory'] = $oldSORecord['so_directory'];
        } else {
            $newSORecord['so_directory'] = get_option(OPTION_FOLDER);
        }
        $newSORecord['so_timeout']   = get_option(OPTION_TIMEOUT);
        // TODO Allow user to override timeout on an Item-by-Item basis

        if ($item->item_type_id == $itemType->id) {
            // The result is going to be an Item of Item Type StreamOnly
            //   so make sure the files go into a StreamOnly Folder
            //   and save/update the StreamOnly record
            $target = array('which'=>'StreamOnly', 'dir'=>$newSORecord['so_directory']);
            $newSORecord->save(true);
        } else {
            // The result is going to be an Item of some other Item Type
            //   so make sure the files go into the default upload directory
            //   and delete the StreamOnly record
            $target = array('which'=>'Omeka', 'dir'=>"");
            $oldSORecord->delete();
        }

        // Move the files, as needed
        $this->_moveFiles($item, $source, $target);

        echo(`hookAfterSaveItem: $item->id \n`);
    }

    /**
     * Fires before the record is deleted from the Item table
     *
     * @param $args->record  record of type Item
     */
    public function hookBeforeDeleteItem($args) {

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $args['record'];
        if (!isSOItem($item)) return;

        // Just checking that we got here.
        echo(`hookBeforeDeleteItem: $item->id \n`);

    }


    /**
     * Fires after the record is deleted from the Item table
     *
     * @param $args->record  record of type Item
     */
    public function hookAfterDeleteItem($args) {

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $args['record'];
        if (!isSOItem($item)) return;

        // Delete the record for this Item found in the StreamOnlyModel table
        get_record(SO_PLUGIN_MODEL, array('item_id'=>$item->id))->delete();

        // Just checking that we got here.
        echo(`hookAfterDeleteItem': $item->id \n`);

    }

    /*****  FILES  ******************************************************/

    /**
     * Fires before the record is saved in the Files table
     * If inserting, $args->record->id is not yet available
     * The file has not yet been uploaded
     *
     * @param $args
     *   ->record  record of type File
     *   ->post array or FALSE [DO NOT USE]
     *   ->insert bool (true if record is being inserted)
     */
    public function hookBeforeSaveFile($args)
    {
        // TODO probably won't need this hook

        $file = $args['record'];

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $file->getItem();
        if (!isSOItem($item)) return;

        // check that this file is one we need to protect against downloading
        if (!$this->_isProtectedFile($file)) return;

        // Just checking that we got here.
        echo(`hookBeforeSaveFile: $file->filename \n`);

    }


    /**
     * Fires after the record is saved in the Files table
     * $args->record->id is now available
     *
     * If adding a new file, it has been uploaded, and is in
     * the standard upload directory.
     * Needs to be renamed to the SO protected directory
     *   and a record in the StreamOnly Table needs to link to the
     *   new directory
     *
     * @param $args
     *   ->record - record of type File
     *   ->post - array (last POST command) or FALSE
     *   ->insert - bool (true if record is being inserted)
     */
    public function hookAfterSaveFile($args) {

        $file = $args['record'];

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $file->getItem();
        if (!isSOItem($item)) return;

        // check that this file is one we need to protect against downloading
        if (!$this->_isProtectedFile($file)) return;

        $itemType   = get_record('ItemType', array ('name'=>SO_ITEM_TYPE_NAME));
        $soRecord   = get_record(SO_PLUGIN_MODEL, array('item_id'=>$item->id)); // false if none
        $option     = get_record('Option', array('name'=>OPTION_FOLDER));
        $postData   = $this->_getPostData();

        // Case where Item Type is not StreamOnly
        // Must be changing Item Type from StreamOnly to some other Item Type
        // The soRecord must exist, or isSOItem() would have returned false
        // Move the file:
        //   from the StreamOnly directory specified in the soRecord
        //   to the Omeka default upload directory
        if ($item->item_type_id != $itemType->id) {
            $sourcePath = $this->_buildPath('StreamOnly', $soRecord['so_directory']);
            $targetPath = $this->_buildPath('Omeka', "");
        }

        // Case where Item Type is StreamOnly
        // Case where soRecord does not exist
        // Must be changing Item from other Item Type to Stream Only Item Type
        //   or inserting a new Item
        // Move file from Omeka default upload directory to one of the following:
        // 1. The directory entered by the user and found in $_POST (if not empty)
        // 2. The default directory stored in the Options table
        // 3. Because the soRecord does not exist, it cannot contain the destination
        elseif ($soRecord == false) {
            $sourcePath = $this->_buildPath('Omeka', "");
            if (isset($postData[ELEMENT_FOLDER_FIELD]) && (!empty($postData[ELEMENT_FOLDER_FIELD]))) {
                $targetPath = $this->_buildPath('StreamOnly', $postData[ELEMENT_FOLDER_FIELD]);
            } else {
                $targetPath = $this->_buildPath('StreamOnly', $option['value']);
            }
        }

        // Case where Item Type is StreamOnly
        // Case where soRecord does exist
        // Could be uploading new file
        // Could be changing Stream Only directory
        // Could be changing other fields not relevant to StreamOnly plugin
        // Could be making multiple changes
        // Could be making no changes at all
        // Set the target directory to one of the following:
        // 1. The directory entered by the user and found in $_POST (if not empty)
        // 2. The default directory stored in the soRecord
        // Set the source directory to whichever one has the file:
        // 1. The default upload directory
        // 2. The directory stored in the soRecord
        // 3. Because the soRecord exists, we are not inserting a new Item,
        //    so there is no need to refer to the entry in the Option table
        else {

            // Set target directory
            if (isset($postData[ELEMENT_FOLDER_FIELD]) && (!empty($postData[ELEMENT_FOLDER_FIELD]))) {
                $targetPath = $this->_buildPath('StreamOnly', $postData[ELEMENT_FOLDER_FIELD]);
            } else {
                $targetPath = $this->_buildPath('StreamOnly', $soRecord['so_directory']);
            }



            // Set source directory
            if ($this->_isInDirectory($file, 'Omeka', "")) {
                $sourcePath = $this->_buildPath('Omeka', "");
            } elseif ($this->_isInDirectory($file, 'StreamOnly', $soRecord['so_directory'])) {
                $sourcePath = $this->_buildPath('StreamOnly', $soRecord['so_directory']);
            } elseif ($this->_isInPath($file, $targetPath)) {  // already in the right place
                $sourcePath = $targetPath;
            } else {
                die("Should move file, but can't find it");
            }

        }

        // This routine might be called multiple times.
        // Only move the file if it has not already been moved
        if (!$this->_isInPath($file, $targetPath)) {
            $this->_moveFile($file, $sourcePath, $targetPath);
        }

        // Just checking that we got here.
        echo(`hookAfterSaveFile: $file->filename \n`);

    }

    /**
     * Fires before the record is deleted from the Files table
     * $args->record->id is set
     *
     * The file should exist, but maybe not where Omeka thinks it is
     * We can figure out where the file is from the Item it's associated with.
     *
     * We need to move the file from the protected SO directory
     * to the standard upload directory so Omeka can find it and delete it.
     *
     * @param $args->record  record of type File
     */
    public function hookBeforeDeleteFile($args)
    {

        $file = $args['record'];

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $file->getItem();
        if (!isSOItem($item)) return;

        // check that this file is one we need to protect against downloading
        if (!$this->_isProtectedFile($file)) return;


        $soRecord = get_record(SO_PLUGIN_MODEL, array('item_id'=>$item->id));

        // If the soRecord doesn't exist, the Item Type is being changed to StreamOnly
        // We don't need to move the file, because it's already in the default upload directory
        if (!$soRecord) return;

        $targetPath = $this->_buildPath('Omeka', '');
        $postData = $this->_getPostData();

        // If the soRecord exists, we need to move the file to the default upload directory
        // It is most likely in the directory specified by the StreamOnly record...
        if ($this->_isInDirectory($file, 'StreamOnly', $soRecord['so_directory'])) {
            $sourcePath = $this->_buildPath('StreamOnly', $soRecord['so_directory']);
        // ...but if the user changed the StreamOnly directory,...
        // ...we may have already moved it there during a call to hookAfterSaveFile()
        } elseif (isset($postData[ELEMENT_FOLDER_FIELD]) &&
                 !empty($postData[ELEMENT_FOLDER_FIELD]) &&
                 $this->_isInDirectory($file, 'StreamOnly', $postData[ELEMENT_FOLDER_FIELD])) {
            $sourcePath = $this->_buildPath('StreamOnly', $postData[ELEMENT_FOLDER_FIELD]);
        // ...or if the user switched the Item Type to something other than StreamOnly
        // ...we may have already moved it to the default upload folder
        } elseif ($this->_isInPath($file, $targetPath)) {
            $sourcePath = $targetPath;
        // We've looked everywhere, and didn't find it
        } else {
            die ("File not found where expected: $file->filename \n");
        }

        if ($targetPath != $sourcePath) {
            $this->_moveFile($file, $sourcePath, $targetPath);
        }


        echo("hookBeforeDeleteFile: $file->filename \n");
    }

    /**
     * Fires after the record is deleted from the Files table
     * $args->record->id is not valid
     * files no longer exist
     *
     * // TODO probably won't need this hook
     *
     * @param $args->record  record of type File
     */
    public function hookAfterDeleteFile($args) {

        $file = $args['record'];

        // check that this Item needs to be processed by the StreamOnly plugin
        $item = $file->getItem();
        if (!isSOItem($item)) return;

        // check that this file is one we need to protect against downloading
        if (!$this->_isProtectedFile($file)) return;

        // Just checking that we got here.
        echo(`hookAfterDeleteFile: $file->filename \n`);

    }

}
