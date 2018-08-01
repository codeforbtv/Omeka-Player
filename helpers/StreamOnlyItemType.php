<?php
/**
 * Stream Only Omeka Player Plugin
 * ItemType Helper Functions
 *
 * @copyright Copyright 2018 Code for Burlington
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */



/**
 * Builds a list of Item Metadata Elements that can be passed to the global function insert_item_type()
 *
 * @param --
 * @uses $so_item_elements
 * @uses element_exists()
 * @return array of Elements that will be the Item Metadata for the StreamOnly Item Type
 *
 * Code based on:
 * https://omeka.org/forums-legacy/topic/plugin-to-add-new-item-type-and-elements/
 */

function so_elements(){

    // List of default 'Item Elements' for 'Item Type Metadata' of "StreamOnly"
    // Using the same list as for 'Item Type' of "Sound" for now
    // TODO Might want to store the item's license count as Item Type Metadata
    // TODO Might want to store the item's path name as Item Type Metadata
    $so_item_elements = array(
        array(
            'name'=>ELEMENT_TRANSCRIPTION,
            'description'=>ELEMENT_TRANSCRIPTION_DESCRIPTION,
            'order'=>1
        ),
        array(
            'name'=>ELEMENT_ORIGINAL_FORMAT,
            'description'=>ELEMENT_ORIGINAL_FORMAT_DESCRIPTION,
            'order'=>2
        ),
        array(
            'name'=>ELEMENT_DURATION,
            'description'=>ELEMENT_DURATION_DESCRIPTION,
            'order'=>3
        ),
        array(
            'name'=>ELEMENT_BIT_RATE,
            'description'=>ELEMENT_BIT_RATE_DESCRIPTION,
            'order'=>4
        ),
        array(
            'name'=>ELEMENT_LICENSE_COUNT,
            'description'=>ELEMENT_LICENSE_COUNT_DESCRIPTION,
            'order'=>5
        ),
        array(
            'name'=>ELEMENT_FOLDER,
            'description'=>ELEMENT_FOLDER_DESCRIPTION,
            'order'=>6
        )
    );
    $add_elements = array();

    // Sort out which elements already exist
    foreach($so_item_elements as $so_element){

        // Check for existence of Item Element
        if(!element_exists('Item Type Metadata',$so_element['name'])){


            // create and add a new Item Element
            $add_elements[] = $so_element;

        }else{

            // add an existing Item Element
            $elementObj=get_record('Element',array(
                'elementSet'=>'Item Type Metadata',
                'name'=>$so_element['name']));

            $add_elements[] = $elementObj;
        }

    }

    return $add_elements;
}

/**
 * Clean up the associated records for this Item Type.
 *
 * 1 Delete all the ItemTypesElements rows joined to the StreamOnly type
 *
 * 2 Set the Item_Type for Items designated as StreamOnly to null.
 *
 * 3 Delete the StreamOnly record from the Item Type table
 *
 * Based on _delete() in application/models/ItemType.php
 * Based on _dissociateItems() in application/models/ItemType.php
 *
 * @param $id - id of Item Type
 */
function so_delete($id)
{
    $db = get_db();

    $itemTable = $db->getTable('Item');  // TODO remove after testing
    $itemList = $db->getTable('Item')->findBy(array('item_type_id'=>$id));

    $target = array ('which'=>'Omeka', 'dir'=>"");
    foreach ($itemList as $item) {
        $soTable = $db->getTable('StreamOnly'); // TODO remove after testing
        // TODO might change this to look for source in Element Text table or Options table
        $soRecord = $db->getTable('StreamOnly')->findBy(array('item_id' => $item->id));
        $source = array('which' => 'StreamOnly', 'dir' => $soRecord['so_directory']);
        $this->_moveFiles($item, $source, $target);
    }

    // Delete all the ItemTypesElements rows joined to this type
    // TODO use get_records(); ???
    $ite_objs = $db->getTable('ItemTypesElements')->findBySql('item_type_id = ?', array( (int) $id));
    foreach ($ite_objs as $ite) {
        $ite->delete();
    }

    // For Items of this type set the Item Type to "undefined"
    $db->update($db->Item, array('item_type_id' => null),
        array('item_type_id = ?' => $id));

    // Delete the StreamOnly Item Type
    // TODO use get_record()->delete(); ???
    $it_objs = $db->getTable('ItemType')->findBySql('id = ?', array( (int) $id));
    $it_objs[0]->delete();

}

/**
 * Check that the Item is or was of Item Type StreamOnly
 * and needs to be processed by the StreamOnly plugin
 *
 * @param $item - an Item record
 * @return bool
 */
function isSOItem($item) { // TODO re-order tests

    // The Item Type is currently StreamOnly,
    //   or in the process of being changed to StreamOnly
    if ($item->getItemType()->name == SO_ITEM_TYPE_NAME)
        return true;

    // The user is creating an Item of a different Item Type
    if ($item->id == NULL) {
        return false;
    }

    // The Item Type was StreamOnly, but is being changed
    //    to a different Item Type
    if (get_record(SO_PLUGIN_MODEL, array('item_id'=>$item->id)))
        return true;

    // The Item Type was not and will not be StreamOnly
    return false;
}


// TODO this function not needed???
//function isInDirectory($file, $directory) {
//
//    switch ($directory) {
//
//        case 'source':
//            $path = $_SERVER[__FILE__] . DIRECTORY_SEPARATOR . $this->so_source;
//
//        case 'original':
//
//        case 'target':
//
//    }
//
//}

