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
    // The same explanatory text is displayed in the Configuration Menu
    //   except there the descriptions of Item Element specific to
    //   Item Type StreamOnly can link to the user documentation.
    // Not sure why, but the HTML in SO_SEE_USER_DOCUMENTATION
    //   does not display correctly here
    //   (it is output by a JS routine),
    //   so instead we use SO_SEE_CONFIGURATION_PAGE
    //   which refers user to the Plugin Configuration page
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
            'name'=>ELEMENT_FOLDER,
            'description'=>ELEMENT_FOLDER_DESCRIPTION . SO_SEE_CONFIGURATION_PAGE,
            'order'=>5
        ),
        array(
            'name'=>ELEMENT_LICENSE_COUNT,
            'description'=>ELEMENT_LICENSE_COUNT_DESCRIPTION . SO_SEE_CONFIGURATION_PAGE,
            'order'=>6
        ),
        array(
            'name'=>ELEMENT_TIMEOUT,
            'description'=>ELEMENT_TIMEOUT_DESCRIPTION . SO_SEE_CONFIGURATION_PAGE,
            'order'=>7
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

    $itemList = $db->getTable('Item')->findBy(array('item_type_id'=>$id));

    $target = array ('which'=>'Omeka', 'dir'=>"");
    foreach ($itemList as $item) {
        $soRecord = $db->getTable('StreamOnly')->findBy(array('item_id' => $item->id));
        $source = array('which' => 'StreamOnly', 'dir' => $soRecord['so_directory']);
        $this->_moveFiles($item, $source, $target);
    }

    // Delete all the ItemTypesElements rows joined to this type
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
function isSOItem($item) {

    // The Item Type is currently StreamOnly,
    //   or in the process of being changed to StreamOnly
    if ((($itemType = $item->getItemType()) != NULL) && ($itemType->name == SO_ITEM_TYPE_NAME))
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


