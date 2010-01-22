<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2009 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file:
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/// Tracking class
class Ticket extends CommonDBTM {


   // From CommonDBTM
   public $dohistory = true;

   // Specific ones
   /// Hardware datas used by getFromDBwithData
   var $hardwaredatas = NULL;
   /// Is a hardware found in getHardwareData / getFromDBwithData : hardware link to the job
   var $computerfound = 0;


   static function getTypeName() {
      global $LANG;

      return $LANG['job'][38];
   }

   function canCreate() {
      return haveRight('create_ticket', 1);
   }

   function canUpdate() {
      return haveRight('update_ticket', 1);
   }

   function canView() {
      return true;
   }

   /**
    * Is the current user have right to show the current ticket ?
    *
    * @return boolean
    */
   function canViewItem() {

      if (!haveAccessToEntity($this->getEntityID())) {
         return false;
      }
      return (haveRight("show_all_ticket","1")
              || (isset($_SESSION["glpiID"]) && $this->fields["users_id"]==$_SESSION["glpiID"])
              || (haveRight("show_group_ticket",'1')
                  && isset($_SESSION["glpigroups"])
                  && in_array($this->fields["groups_id"],$_SESSION["glpigroups"]))
              || (haveRight("show_assign_ticket",'1')
                  && ((isset($_SESSION["glpiID"])
                       && $this->fields["users_id_assign"]==$_SESSION["glpiID"]
                      )
                      || (isset($_SESSION["glpigroups"])
                          && in_array($this->fields["groups_id_assign"],$_SESSION["glpigroups"]))
                     )
                 )
             );
   }

   /**
    * Is the current user have right to create the current ticket ?
    *
    * @return boolean
    */
   function canCreateItem() {
      if (!haveAccessToEntity($this->getEntityID())) {
         return false;
      }
      return haveRight('create_ticket', '1');
   }

   /**
    * Is the current user have right to update the current ticket ?
    *
    * @return boolean
    */
   function canUpdateItem() {
      if (!haveAccessToEntity($this->getEntityID())) {
         return false;
      }
      return $this->canUpdate();
   }

   /**
    * Is the current user have right to delete the current ticket ?
    *
    * @return boolean
    */
   function canDeleteItem() {
      if (!haveAccessToEntity($this->getEntityID())) {
         return false;
      }
      return haveRight('delete_ticket', '1');
   }


   function defineTabs($ID,$withtemplate) {
      global $LANG,$CFG_GLPI;

      if ($ID>0) {
         if ($_SESSION["glpiactiveprofile"]["interface"]=="central") {
            if ($this->canAddFollowups()) {
               $ong[1] = $LANG['job'][9];
               $ong[2] = $LANG['job'][7];
            }
         } else if (haveRight("add_followups","1")) {
            if (!strstr($this->fields["status"],"closed") // TODO review this => to add "approbation"
                && $this->fields["users_id"]==$_SESSION["glpiID"]) {
            }
            $ong[1] = $LANG['Menu'][5];
            $ong[2] = $LANG['job'][7];
         }
         $ong[4] = $LANG['jobresolution'][1];
         $ong[3] = $LANG['job'][47];
         $ong[5] = $LANG['Menu'][27];
         $ong[6] = $LANG['title'][38];
         $ong['no_all_tab']=true;
      } else {
         $ong[1] = $LANG['job'][13];
      }

      return $ong;
   }


   /**
    * Retrieve an item from the database with datas associated (hardwares)
    *
    *@param $ID ID of the item to get
    *@param $purecontent boolean : true : nothing change / false : convert to HTML display
    *@return true if succeed else false
   **/
   function getFromDBwithData ($ID,$purecontent) {
      global $DB,$LANG;

      if ($this->getFromDB($ID)) {
         if (!$purecontent) {
            $this->fields["content"] = nl2br(preg_replace("/\r\n\r\n/","\r\n",
                                                          $this->fields["content"]));
         }
         $this->getHardwareData();
         return true;
      }
      return false;
   }


   /**
    * Retrieve data of the hardware linked to the ticket if exists
    *
    *@return nothing : set computerfound to 1 if founded
   **/
   function getHardwareData() {

      if ($this->fields["itemtype"] && class_exists($this->fields["itemtype"])) {
         $item = new $this->fields["itemtype"]();
         if ($item->getFromDB($this->fields["items_id"])) {
            $this->hardwaredatas=$item;
         }
      } else {
         $this->hardwaredatas=NULL;
      }
   }


   function cleanDBonPurge() {
      global $DB;

      $query = "SELECT `id`
                FROM `glpi_ticketfollowups`
                WHERE `tickets_id` = '".$this->fields['id']."'";
      $result=$DB->query($query);

      if ($DB->numrows($result)>0) {
         while ($data=$DB->fetch_array($result)) {
            $querydel = "DELETE
                         FROM `glpi_ticketplannings`
                         WHERE `ticketfollowups_id` = '".$data['id']."'";
            $DB->query($querydel);
         }
      }
      $query1 = "DELETE
                 FROM `glpi_ticketfollowups`
                 WHERE `tickets_id` = '".$this->fields['id']."'";
      $DB->query($query1);
   }


   function prepareInputForUpdate($input) {
      global $LANG,$CFG_GLPI;

      if (isset($input["date"]) && empty($input["date"])) {
         unset($input["date"]);
      }
      if (isset($input["closedate"]) && empty($input["closedate"])) {
         unset($input["closedate"]);
      }

      // Security checks
      if (!haveRight("assign_ticket","1")) {
         if (isset($input["users_id_assign"])) {
            $this->getFromDB($input['id']);
            // must own_ticket to grab a non assign ticket
            if ($this->fields['users_id_assign']==0) {
               if ((!haveRight("steal_ticket","1") && !haveRight("own_ticket","1"))
                   || ($input["users_id_assign"]!=$_SESSION["glpiID"])) {
                  unset($input["users_id_assign"]);
               }
            } else {
               // Can not steal or can steal and not assign to me
               if (!haveRight("steal_ticket","1")
                   || $input["users_id_assign"] != $_SESSION["glpiID"]) {
                  unset($input["users_id_assign"]);
               }
            }
         }
         if (isset($input["suppliers_id_assign"])) {
            unset($input["suppliers_id_assign"]);
         }
         if (isset($input["groups_id_assign"])) {
            unset($input["groups_id_assign"]);
         }
      }

      if (!haveRight("update_ticket","1")) {
         // Manage assign and steal right
         if (isset($input["users_id_assign"])) {
            $ret["users_id_assign"]=$input["users_id_assign"];
         }
         if (isset($input["suppliers_id_assign"])) {
            $ret["suppliers_id_assign"]=$input["suppliers_id_assign"];
         }
         if (isset($input["groups_id_assign"])) {
            $ret["groups_id_assign"]=$input["groups_id_assign"];
         }

         // Can only update content if no followups already added
         $ret["id"]=$input["id"];
         if (isset($input["content"])) {
            $ret["content"]=$input["content"];
         }
         if (isset($input["name"])) {
            $ret["name"]=$input["name"];
         }

         $input=$ret;
      }

      // Setting a solution type means the ticket is solved
      if (isset($input["ticketsolutiontypes_id"])
          && $input["ticketsolutiontypes_id"]>0
          && $this->fields['status']!='closed') {
         $input["status"] = 'solved';
      }

      if (isset($input["items_id"])
          && $input["items_id"]>=0
          && isset($input["itemtype"])) {

         if (isset($this->fields['groups_id']) && $this->fields['groups_id']) {
            if ($input["itemtype"] && class_exists($input["itemtype"])) {
               $item = new $input["itemtype"]();
               $item->getFromDB($input["items_id"]);
               if ($item->isField('groups_id')) {
                  $input["groups_id"] = $item->getField('groups_id');
               }
            }
         }
      } else if (isset($input["itemtype"]) && empty($input["itemtype"])) {
         $input["items_id"]=0;
      } else {
         unset($input["items_id"]);
         unset($input["itemtype"]);
      }

      // Add document if needed
      $this->getFromDB($input["id"]); // entities_id field required
      $docadded = $this->addFiles($input["id"]);
      /*
      if (count($docadded)>0) {
         $input["date_mod"]=$_SESSION["glpi_currenttime"];
         if ($CFG_GLPI["add_followup_on_update_ticket"]) {
            $input['_doc_added']=$docadded;
         }
      }
      */

      if (isset($input["document"]) && $input["document"]>0) {
         $doc=new Document();
         if ($doc->getFromDB($input["document"])) {
            $docitem=new Document_Item();
            if ($docitem->add(array('documents_id' => $input["document"],
                                    'itemtype' => $this->getType(),
                                    'items_id' => $input["id"]))) {
               // Force date_mod of tracking
               $input["date_mod"]=$_SESSION["glpi_currenttime"];
               $input['_doc_added'][]=$doc->fields["name"];
            }
         }
         unset($input["document"]);
      }

      /*
      // Old values for add followup in change
      if ($CFG_GLPI["add_followup_on_update_ticket"]) {
         $this->getFromDB($input["id"]);
         $input["_old_assign_name"] = Ticket::getAssignName($this->fields["users_id_assign"],'User');
         $input["_old_assign"]      = $this->fields["users_id_assign"];
         $input["_old_assign_supplier_name"]  = Ticket::getAssignName($this->fields["suppliers_id_assign"],
                                                             'Supplier');
         $input["_old_groups_id_assign_name"] = Ticket::getAssignName($this->fields["groups_id_assign"],
                                                              'Group');
         $input["_old_ticketcategories_id"]  = $this->fields["ticketcategories_id"];
         $input["_old_items_id"]       = $this->fields["items_id"];
         $input["_old_itemtype"]       = $this->fields["itemtype"];
         $input["_old_users_id"]       = $this->fields["users_id"];
         $input["_old_recipient"]      = $this->fields["users_id_recipient"];
         $input["_old_group"]          = $this->fields["groups_id"];
         $input["_old_priority"]       = $this->fields["priority"];
         $input["_old_status"]         = $this->fields["status"];
         $input["_old_requesttypes_id"]= $this->fields["requesttypes_id"];
         $input["_old_cost_time"]      = $this->fields["cost_time"];
         $input["_old_cost_fixed"]     = $this->fields["cost_fixed"];
         $input["_old_cost_material"]  = $this->fields["cost_material"];
         $input["_old_date"]           = $this->fields["date"];
         $input["_old_closedate"]      = $this->fields["closedate"];
         $input["_old_soltype"]        = $this->fields["ticketsolutiontypes_id"];
      }
      */
      return $input;
   }


   function pre_updateInDB() {
      global $LANG;

      if (((in_array("users_id_assign",$this->updates) && $this->input["users_id_assign"]>0)
           || (in_array("suppliers_id_assign",$this->updates) && $this->input["suppliers_id_assign"]>0)
           || (in_array("groups_id_assign",$this->updates) && $this->input["groups_id_assign"]>0)
          )
          && $this->fields["status"]=="new") {

         if (!in_array('status', $this->updates)) {
            $this->oldvalues['status'] = $this->fields['status'];
            $this->updates[] = 'status';
         }
         $this->fields['status'] = 'assign';
      }
      if (isset($this->input["status"])) {
         if (isset($this->input["suppliers_id_assign"])
             && $this->input["suppliers_id_assign"]==0
             && isset($this->input["groups_id_assign"])
             && $this->input["groups_id_assign"]==0
             && isset($this->input["users_id_assign"])
             && $this->input["users_id_assign"]==0
             && $this->input["status"]=="assign") {

            if (!in_array('status', $this->updates)) {
               $this->oldvalues['status'] = $this->fields['status'];
               $this->updates[] = 'status';
            }
            $this->fields['status'] = 'new';
         }

         if (in_array("status",$this->updates) && $this->input["status"]=="closed") {
            $this->updates[]="closedate";
            $this->oldvalues['closedate']=$this->fields["closedate"];
            $this->fields["closedate"]=$_SESSION["glpi_currenttime"];
            // If invalid date : set open date
            if ($this->fields["closedate"]<$this->fields["date"]){
               $this->fields["closedate"]=$this->fields["date"];
            }
         }
      }

      // Status close : check dates
      if ($this->fields["status"]=="closed"
          && (in_array("date",$this->updates) || in_array("closedate",$this->updates))) {

         // Invalid dates : no change
         if ($this->fields["closedate"]<$this->fields["date"]) {
            addMessageAfterRedirect($LANG['tracking'][3],false,ERROR);
            if (($key=array_search('date',$this->updates))!==false) {
               unset($this->updates[$key]);
            }
            if (($key=array_search('closedate',$this->updates))!==false) {
               unset($this->updates[$key]);
            }
         }
      }

      // Check dates change interval due to the fact that second are not displayed in form

      if (($key=array_search('date',$this->updates))!==false
          && (substr($this->fields["date"],0,16) == substr($this->oldvalues['date'],0,16))) {
         unset($this->updates[$key]);
      }
      if (($key=array_search('closedate',$this->updates))!==false
          && (substr($this->fields["closedate"],0,16) == substr($this->oldvalues['closedate'],0,16))) {
         unset($this->updates[$key]);
      }

      if (in_array("users_id",$this->updates)) {
         $user=new User;
         $user->getFromDB($this->input["users_id"]);
         if (!empty($user->fields["email"])) {
            $this->updates[]="user_email";
            $this->fields["user_email"]=$user->fields["email"];
         }
      }
      if (($key=array_search('status',$this->updates))!==false
          && $this->oldvalues['status'] == $this->fields['status']) {
         unset($this->updates[$key]);
         unset($this->oldvalues['status']);
      }

      // Do not take into account date_mod if no update is done
      if (count($this->updates)==1 && ($key=array_search('date_mod',$this->updates))!==false) {
         unset($this->updates[$key]);
      }
   }


   function post_updateItem($history=1) {
      global $CFG_GLPI,$LANG;

      if (count($this->updates)) {
         // New values for add followup in change
         $change_followup_content="";
         if (isset($this->input['_doc_added']) && count($this->input['_doc_added'])>0) {
            foreach ($this->input['_doc_added'] as $name) {
               $change_followup_content .= $LANG['mailing'][26]." $name\n";
            }
         }
         // Update Ticket Tco
         if (in_array("realtime",$this->updates)
             || in_array("cost_time",$this->updates)
             || in_array("cost_fixed",$this->updates)
             || in_array("cost_material",$this->updates)) {

            if ($this->fields["itemtype"] && class_exists($this->fields["itemtype"])) {
               $item=new $this->fields["itemtype"]();
               if ($item->getFromDB($this->fields["items_id"])) {
                  $newinput=array();
                  $newinput['id']=$this->fields["items_id"];
                  $newinput['ticket_tco'] = self::computeTco($item);
                  $item->update($newinput);
               }
            }
         }

/*
         $global_mail_change_count=0;
         if ($CFG_GLPI["add_followup_on_update_ticket"] && count($this->updates)) {
            foreach ($this->updates as $key) {
               switch ($key) {
                  case "name" :
                     $change_followup_content .= $LANG['mailing'][45]."\n";
                     $global_mail_change_count++;
                     break;

                  case "content" :
                     $change_followup_content .= $LANG['mailing'][46]."\n";
                     break;

                  case "ticketsolutiontypes_id" :
                     $change_followup_content .= $LANG['mailing'][53]." : " .
                                                 Dropdown::getDropdownName('glpi_ticketsolutiontypes',$this->input["_old_soltype"])." -> ".
                                                 Dropdown::getDropdownName('glpi_ticketsolutiontypes',$this->fields["ticketsolutiontypes_id"])."\n";
                     $global_mail_change_count++;
                     break;

                  case "solution" :
                     if (!in_array('ticketsolutiontypes_id', $this->updates)) {
                        $change_followup_content .= $LANG['mailing'][53];
                     }
                     break;

                  case "date" :
                     $change_followup_content .= $LANG['mailing'][48]."&nbsp;: ".
                                                 $this->input["_old_date"]." -> ".$this->fields["date"]."\n";
                     $global_mail_change_count++;
                     break;

                  case "closedate" :
                     // if update status from an not closed status : no mail for change closedate
                     if (!in_array("status",$this->updates) || !$this->input["status"]!="closed") {
                        $change_followup_content .= $LANG['mailing'][49]."&nbsp;: ".
                                                    $this->input["_old_closedate"]." -> ".
                                                    $this->fields["closedate"]."\n";
                        $global_mail_change_count++;
                     }
                     break;

                  case "status" :
                     $new_status=$this->fields["status"];
                     $change_followup_content .= $LANG['mailing'][27]."&nbsp;: ".
                                                 $this->getStatus($this->input["_old_status"])." -> ".
                                                 $this->getStatus($new_status)."\n";
                     if ($new_status=="closed") {
                        $newinput["add_close"]="add_close";
                     }
                     if (in_array("closedate",$this->updates)) {
                        $global_mail_change_count++; // Manage closedate
                     }
                     $global_mail_change_count++;
                     break;

                  case "users_id" :
                     $users_id=new User;
                     $users_id->getFromDB($this->input["_old_users_id"]);
                     $old_users_id_name = $users_id->getName();
                     $users_id->getFromDB($this->fields["users_id"]);
                     $new_users_id_name = $users_id->getName();
                     $change_followup_content .= $LANG['mailing'][18]."&nbsp;: $old_users_id_name -> ".
                                                 $new_users_id_name."\n";
                     $global_mail_change_count++;
                     break;

                  case "users_id_recipient" :
                     $recipient=new User;
                     $recipient->getFromDB($this->input["_old_recipient"]);
                     $old_recipient_name = $recipient->getName();
                     $recipient->getFromDB($this->fields["users_id_recipient"]);
                     $new_recipient_name = $recipient->getName();
                     $change_followup_content .= $LANG['mailing'][50]."&nbsp;: $old_recipient_name -> ".
                                                 $new_recipient_name."\n";
                     $global_mail_change_count++;
                     break;

                  case "groups_id" :
                     $new_group=$this->fields["groups_id"];
                     $old_group_name = str_replace("&nbsp;",$LANG['mailing'][109],
                                                   Dropdown::getDropdownName("glpi_groups",$this->input["_old_group"]));
                     $new_group_name = str_replace("&nbsp;",$LANG['mailing'][109],
                                                   Dropdown::getDropdownName("glpi_groups",$new_group));
                     $change_followup_content .= $LANG['mailing'][20].": ".$old_group_name." -> ".
                                                 $new_group_name."\n";
                     $global_mail_change_count++;
                     break;

                  case "priority" :
                     $new_priority = $this->fields["priority"];
                     $change_followup_content .= $LANG['mailing'][15]."&nbsp;: ".
                                                 Ticket::getPriorityName($this->input["_old_priority"])." -> ".
                                                 Ticket::getPriorityName($new_priority)."\n";
                     $global_mail_change_count++;
                     break;

                  case "ticketcategories_id" :
                     $new_ticketcategories_id = $this->fields["ticketcategories_id"];
                     $old_category_name = str_replace("&nbsp;",$LANG['mailing'][100],
                                                      Dropdown::getDropdownName("glpi_ticketcategories",
                                                                      $this->input["_old_ticketcategories_id"]));
                     $new_category_name = str_replace("&nbsp;",$LANG['mailing'][100],
                                                      Dropdown::getDropdownName("glpi_ticketcategories",
                                                                      $new_ticketcategories_id));
                     $change_followup_content .= $LANG['mailing'][14]."&nbsp;: ".
                                                 $old_category_name." -> ".$new_category_name."\n";
                     $global_mail_change_count++;
                     break;

                  case "requesttypes_id" :
                     $old_requesttype_name = Dropdown::getDropdownName('glpi_requesttypes',
                                                             $this->input["_old_requesttypes_id"]);
                     $new_requesttype_name = Dropdown::getDropdownName('glpi_requesttypes',
                                                             $this->fields["requesttypes_id"]);
                     $change_followup_content .= $LANG['mailing'][21]."&nbsp;: ".
                                                 $old_requesttype_name." -> ".
                                                 $new_requesttype_name."\n";
                     $global_mail_change_count++;
                     break;

                  case "items_id" :
                  case "itemtype" :
                     if (isset($already_done_computer_itemtype_update)) {
                        break;
                     } else {
                        $already_done_computer_itemtype_update=true;
                     }
                     $old_item_name = $LANG['mailing'][107];
                     if ($this->input["_old_itemtype"] && class_exists($this->input["_old_itemtype"])) {
                        $item=new $this->input["_old_itemtype"]();
                        if ($item->getFromDB($this->input["_old_items_id"])) {
                           $old_item_name = $item->getName();
                           if ($old_item_name==NOT_AVAILABLE || empty($old_item_name)) {
                              $old_item_name = $LANG['mailing'][107];
                           }
                        }
                     }
                     $new_item_name=$LANG['mailing'][107];
                     if ($this->fields["itemtype"] && class_exists($this->fields["itemtype"])) {
                        $item = new $this->fields["itemtype"]();
                        if ($item->getFromDB($this->fields["items_id"])) {
                           $new_item_name = $item->getName();
                           if ($new_item_name==NOT_AVAILABLE || empty($new_item_name)) {
                              $new_item_name=$LANG['mailing'][107];
                           }
                        }
                     }
                     $change_followup_content .= $LANG['mailing'][17]."&nbsp;:
                                                 $old_item_name -> ".$new_item_name."\n";
                     if (in_array("items_id",$this->updates)) {
                        $global_mail_change_count++;
                     }
                     if (in_array("itemtype",$this->updates)) {
                        $global_mail_change_count++;
                     }
                     break;

                  case "users_id_assign" :
                     $new_assign_name = Ticket::getAssignName($this->fields["users_id_assign"],'User');
                     if ($this->input["_old_assign"]==0) {
                        $this->input["_old_assign_name"]=$LANG['mailing'][105];
                     }
                     $change_followup_content .= $LANG['mailing'][12]."&nbsp;: ".
                                                 $this->input["_old_assign_name"]." -> ".
                                                 $new_assign_name."\n";
                     $global_mail_change_count++;
                     break;

                  case "suppliers_id_assign" :
                     $new_assign_supplier_name = Ticket::getAssignName($this->fields["suppliers_id_assign"],
                                                               'Supplier');
                     $change_followup_content .= $LANG['mailing'][12]."&nbsp;: ".
                                                 $this->input["_old_assign_supplier_name"]." -> ".
                                                 $new_assign_supplier_name."\n";
                     $global_mail_change_count++;
                     break;

                  case "groups_id_assign" :
                     $new_groups_id_assign_name = Ticket::getAssignName($this->fields["groups_id_assign"],
                                                                'Group');
                     $change_followup_content .= $LANG['mailing'][12]."&nbsp;: ".
                                                 $this->input["_old_groups_id_assign_name"]." -> ".
                                                 $new_groups_id_assign_name."\n";
                     $global_mail_change_count++;
                     break;

                  case "cost_time" :
                     $change_followup_content .= $LANG['mailing'][42]."&nbsp;: ".
                                                 formatNumber($this->input["_old_cost_time"])." -> ".
                                                 formatNumber($this->fields["cost_time"])."\n";
                     $global_mail_change_count++;
                     break;

                  case "cost_fixed" :
                     $change_followup_content .= $LANG['mailing'][43]."&nbsp;: ".
                                                 formatNumber($this->input["_old_cost_fixed"])." -> ".
                                                 formatNumber($this->fields["cost_fixed"])."\n";
                     $global_mail_change_count++;
                     break;

                  case "cost_material" :
                     $change_followup_content .= $LANG['mailing'][44]."&nbsp;: ".
                                                 formatNumber($this->input["_old_cost_material"])." -> ".
                                                 formatNumber($this->fields["cost_material"])."\n";
                     $global_mail_change_count++;
                     break;

                  case "use_email_notification" :
                     if ($this->fields["use_email_notification"]) {
                        $change_followup_content .= $LANG['mailing'][101]."\n";
                     } else {
                        $change_followup_content .= $LANG['mailing'][102]."\n";
                     }
                     $global_mail_change_count++;
                     break;
               }
            }
         }
         */
         if (!in_array("users_id_assign",$this->updates)) {
            unset($this->input["_old_assign"]);
         }
/*         $mail_send=false;

         if (!empty($change_followup_content)) { // Add followup if not empty
            $newinput=array();
            $newinput["content"]    = addslashes($change_followup_content);
            $newinput["users_id"]   = $_SESSION['glpiID'];
            $newinput["is_private"] = 0;
            $newinput["hour"]       = $newinput["minute"] = 0;
            $newinput["tickets_id"] = $this->fields["id"];
            $newinput["type"]       = "update";
            $newinput["_do_not_check_users_id"] = true;
            // pass _old_assign if assig changed
            if (isset($this->input["_old_assign"])) {
               $newinput["_old_assign"] = $this->input["_old_assign"];
            }

            if (isset($this->input["status"])
                && in_array("status",$this->updates)
                && $this->input["status"]=="solved") {

               $newinput["type"]="finish";
            }
            $fup=new TicketFollowup();
            $fup->add($newinput);
            $mail_send=true;
         }
*/
         // Clean content to mail
         $this->fields["content"] = stripslashes($this->fields["content"]);
/*
         if (!$mail_send
             && count($this->updates)>$global_mail_change_count
             && $CFG_GLPI["use_mailing"]) {
*/
         if (count($this->updates)>0 && $CFG_GLPI["use_mailing"]) {

            $user=new User;
            $user->getFromDB($_SESSION["glpiID"]);
            $mailtype = "update";

            if (isset($this->input["status"])
                && $this->input["status"]
                && in_array("status",$this->updates)
                && $this->input["status"]=="solved") {

               $mailtype="finish";
            }
            if (isset($this->input["_old_assign"])) {
               $this->fields["_old_assign"] = $this->input["_old_assign"];
            }
            $mail = new Mailing($mailtype,$this,$user);
            $mail->send();
         }
      }
   }


   function prepareInputForAdd($input) {
      global $CFG_GLPI,$LANG;

      // Check mandatory
      $mandatory_ok=true;

      // Do not check mandatory on auto import (mailgates)
      if (!isset($input['_auto_import'])) {
         $_SESSION["helpdeskSaved"]=$input;

         if (!isset($input["urgency"])) {
            addMessageAfterRedirect($LANG['tracking'][4],false,ERROR);
            $mandatory_ok=false;
         }
         if ($CFG_GLPI["is_ticket_content_mandatory"]
             && (!isset($input['content']) || empty($input['content']))) {

            addMessageAfterRedirect($LANG['tracking'][8],false,ERROR);
            $mandatory_ok=false;
         }
         if ($CFG_GLPI["is_ticket_title_mandatory"]
             && (!isset($input['name']) || empty($input['name']))) {

            addMessageAfterRedirect($LANG['help'][40],false,ERROR);
            $mandatory_ok=false;
         }
         if ($CFG_GLPI["is_ticket_category_mandatory"]
             && (!isset($input['ticketcategories_id']) || empty($input['ticketcategories_id']))) {

            addMessageAfterRedirect($LANG['help'][41],false,ERROR);
            $mandatory_ok=false;
         }
         if (isset($input['use_email_notification']) && $input['use_email_notification']
             && (!isset($input['user_email']) || empty($input['user_email']))) {

            addMessageAfterRedirect($LANG['help'][16],false,ERROR);
            $mandatory_ok=false;
         }

         if (!$mandatory_ok) {
            return false;
         }
      }
      if (!isset($input["urgency"])
          || !($CFG_GLPI['urgency_mask']&(1<<$input["urgency"]))) {
         $input["urgency"] = 3;
      }
      if (!isset($input["impact"])
          || !($CFG_GLPI['impact_mask']&(1<<$input["impact"]))) {
         $input["impact"] = 3;
      }
      if (!isset($input["priority"])) {
         $input["priority"] = $this->computePriority($input["urgency"], $input["impact"]);
      }

      unset($_SESSION["helpdeskSaved"]);

      // Manage helpdesk.html submission type
      unset($input["type"]);

      // No Auto set Import for external source
      if (!isset($input['_auto_import'])) {
         if (!isset($input["users_id"])) {
            if (isset($_SESSION["glpiID"]) && $_SESSION["glpiID"]>0) {
               $input["users_id"] = $_SESSION["glpiID"];
            }
         }
      }

      // No Auto set Import for external source
      if (isset($_SESSION["glpiID"]) && !isset($input['_auto_import'])) {
         $input["users_id_recipient"] = $_SESSION["glpiID"];
      } else if ($input["users_id"]) {
         $input["users_id_recipient"] = $input["users_id"];
      }
      if (!isset($input["requesttypes_id"])) {
         $input["requesttypes_id"]=RequestType::getDefault('helpdesk');
      }
      if (!isset($input["status"])) {
         $input["status"]="new";
      }
      if (!isset($input["date"]) || empty($input["date"])) {
         $input["date"] = $_SESSION["glpi_currenttime"];
      }

      // Set default dropdown
      $dropdown_fields = array('entities_id','groups_id','groups_id_assign', 'itemtype','items_id',
                               'users_id','users_id_assign', 'suppliers_id_assign',
                               'ticketcategories_id');
      foreach ($dropdown_fields as $field ) {
         if (!isset($input[$field])) {
            $input[$field]=0;
         }
      }

      $item=NULL;
      if ($input["items_id"]>0 && !empty($input["itemtype"])) {
         if (class_exists($input["itemtype"])) {
            $item= new $input["itemtype"]();
            if (!$item->getFromDB($input["items_id"])) {
               $item=NULL;
            }
         }
      }


      // Auto group define from item
      if ($item != NULL) {
         if ($item->isField('groups_id')) {
            $input["groups_id"] = $item->getField('groups_id');
         }
      }

      if ($CFG_GLPI["use_auto_assign_to_tech"]) {

         // Auto assign tech from item
         if ($input["users_id_assign"]==0 && $item != NULL) {

            if ($item->isField('users_id_tech')) {
               $input["users_id_assign"] = $item->getField('users_id_tech');
               if ($input["users_id_assign"]>0) {
                  $input["status"] = "assign";
               }
            }
         }

         // Auto assign tech/group from Category
         if ($input['ticketcategories_id']>0
             && (!$input['users_id_assign'] || !$input['groups_id_assign'])) {

            $cat = new TicketCategory();
            $cat->getFromDB($input['ticketcategories_id']);
            if (!$input['users_id_assign'] && $cat->isField('users_id')) {
               $input['users_id_assign'] = $cat->getField('users_id');
            }
            if (!$input['groups_id_assign'] && $cat->isField('groups_id')) {
               $input['groups_id_assign'] = $cat->getField('groups_id');
            }
         }
      }

      // Process Business Rules
      $rules=new RuleTicketCollection();

      // Set unset variables with are needed
      $user=new User();
      if ($user->getFromDB($input["users_id"])) {
         $input['users_locations']=$user->fields['locations_id'];
      }


      $input=$rules->processAllRules($input,$input);

      if (isset($input["use_email_notification"])
          && $input["use_email_notification"]
          && empty($input["user_email"])) {

         $user=new User();
         $user->getFromDB($input["users_id"]);
         $input["user_email"] = $user->fields["email"];
      }

      if (($input["users_id_assign"]>0
            || $input["groups_id_assign"]>0
            || $input["suppliers_id_assign"]>0)
          && $input["status"]=="new") {

         $input["status"] = "assign";
      }

      if (isset($input["hour"]) && isset($input["minute"])) {
         $input["realtime"] = $input["hour"]+$input["minute"]/60;
         $input["_hour"]    = $input["hour"];
         $input["_minute"]  = $input["minute"];
         unset($input["hour"]);
         unset($input["minute"]);
      }

      if (isset($input["status"]) && $input["status"]=="closed") {
         if (isset($input["date"])) {
            $input["closedate"] = $input["date"];
         } else {
            $input["closedate"] = $_SESSION["glpi_currenttime"];
         }
      }

      // No name set name
      if (empty($input["name"])) {
         $input["name"] = preg_replace('/\r\n/',' ',$input['content']);
         $input["name"] = preg_replace('/\n/',' ',$input['name']);
         $input["name"] = utf8_substr($input['name'],0,70);
      }
      return $input;
   }


   function post_addItem() {
      global $LANG,$CFG_GLPI;

      // Add document if needed
      $this->addFiles($this->fields['id']);

      // Log this event
      Event::log($this->fields['id'],"tracking",4,"tracking",
                  getUserName($this->input["users_id"])." ".$LANG['log'][20]);

      $already_mail=false;
      if (((isset($this->input["_followup"])
            && is_array($this->input["_followup"])
            && strlen($this->input["_followup"]['content']) > 0
           )
           || isset($this->input["plan"])
          )
          || (isset($this->input["_hour"])
              && isset($this->input["_minute"])
              && isset($this->input["realtime"])
              && $this->input["realtime"]>0)) {

         $fup=new TicketFollowup();
         $type="new";
         if (isset($this->fields["status"]) && $this->fields["status"]=="solved") {
            $type="finish";
         }
         $toadd = array("type"      => $type,
                        "tickets_id"=> $this->input['id']);
         if (isset($this->input["_hour"])) {
            $toadd["hour"] = $this->input["_hour"];
         }
         if (isset($this->input["_minute"])) {
            $toadd["minute"] = $this->input["_minute"];
         }
         if (isset($this->input["_followup"]['content']) && strlen($this->input["_followup"]['content']) > 0) {
            $toadd["content"] = $this->input["_followup"]['content'];
         }
         if (isset($this->input["_followup"]['is_private'])) {
            $toadd["is_private"] = $this->input["_followup"]['is_private'];
         }
         if (isset($this->input["plan"])) {
            $toadd["plan"] = $this->input["plan"];
         }
         $fup->add($toadd);
         $already_mail=true;
      }

      // Processing Email

      if ($CFG_GLPI["use_mailing"] && !$already_mail) {
         $user = new User();
         $user->getFromDB($this->input["users_id"]);
         // Clean reload of the ticket
         $this->getFromDB($this->fields['id']);

         $type = "new";
         if (isset($this->fields["status"]) && $this->fields["status"]=="solved") {
            $type = "finish";
         }
         //$mail = new Mailing($type,$this,$user);
         //$mail->send();
         NotificationEvent::raiseEvent($type,$this);
      }
   }


   // SPECIFIC FUNCTIONS
   /**
    * Number of followups of the ticket
    *
    *@param $with_private boolean : true : all ticket / false : only public ones
    *@return followup count
   **/
   function numberOfFollowups($with_private=1) {
      global $DB;

      $RESTRICT = "";
      if ($with_private!=1) {
         $RESTRICT = " AND `is_private` = '0'";
      }
      // Set number of followups
      $query = "SELECT count(*)
                FROM `glpi_ticketfollowups`
                WHERE `tickets_id` = '".$this->fields["id"]."'
                      $RESTRICT";
      $result = $DB->query($query);

      return $DB->result($result,0,0);
   }


   /**
    * Update realtime of the ticket based on realtime of the followups and tasks
    *
    *@param $ID ID of the ticket
    *@return boolean : success
   **/
   function updateRealTime($ID) {
      global $DB;

      $tot = 0;

      $query = "SELECT SUM(`realtime`)
                FROM `glpi_tickettasks`
                WHERE `tickets_id` = '$ID'";

      if ($result = $DB->query($query)) {
         $sum = $DB->result($result,0,0);
         if (!is_null($sum)) {
            $tot += $sum;
         }
      }
      $query2 = "UPDATE `".
                 $this->getTable()."`
                 SET `realtime` = '$tot'
                 WHERE `id` = '$ID'";

      return $DB->query($query2);
   }


   /**
    * Update date mod of the ticket
    *
    *@param $ID ID of the ticket
   **/
   function updateDateMod($ID) {
      global $DB;

      $query = "UPDATE `".
                $this->getTable()."`
                SET `date_mod` = '".$_SESSION["glpi_currenttime"]."'
                WHERE `id` = '$ID'";
      $DB->query($query);
   }


   /**
    * Get text describing Followups
    *
    * @param $format text or html
    * @param $sendprivate true if both public and private followups have to be printed in the email
    */
   function textFollowups($format="text", $sendprivate=false) {
      global $DB,$LANG;

      // get the last followup for this job and give its content as
      if (isset($this->fields["id"])) {
         $query = "SELECT *
                   FROM `glpi_ticketfollowups`
                   WHERE `tickets_id` = '".$this->fields["id"]."' ".
                         ($sendprivate?"":" AND `is_private` = '0' ")."
                   ORDER by `date` DESC";
         $result=$DB->query($query);
         $nbfollow=$DB->numrows($result);

         if ($format=="html") {
            $message = "<div class='description b'>".$LANG['mailing'][4]."&nbsp;: $nbfollow<br></div>\n";

            if ($nbfollow>0) {
               $fup = new TicketFollowup();
               while ($data=$DB->fetch_array($result)) {
                  $fup->getFromDB($data['id']);
                  $message .= "<strong>[ ".convDateTime($fup->fields["date"])." ] ".
                               ($fup->fields["is_private"]?"<i>".$LANG['common'][77]."</i>":"").
                               "</strong>\n";
                  $message .= "<span style='color:#8B8C8F; font-weight:bold; ".
                               "text-decoration:underline; '>".$LANG['job'][4].":</span> ".
                               $fup->getAuthorName()."\n";
                  $message .= "<span style='color:#8B8C8F; font-weight:bold; ".
                               "text-decoration:underline; '>".$LANG['mailing'][3]."</span>:<br>".
                               str_replace("\n","<br>",$fup->fields["content"])."\n";
                  if ($fup->fields["realtime"]>0) {
                     $message .= "<span style='color:#8B8C8F; font-weight:bold; ".
                                  "text-decoration:underline; '>".$LANG['mailing'][104].":</span> ".
                                  Ticket::getRealtime($fup->fields["realtime"])."\n";
                  }
                  $message .= "<span style='color:#8B8C8F; font-weight:bold; ".
                               "text-decoration:underline; '>".$LANG['mailing'][25]."</span> ";

                  // Use tasks instead of followups
                  /*
                  $query2 = "SELECT *
                             FROM `glpi_ticketplannings`
                             WHERE `tickettasks_id` = '".$data['id']."'";
                  $result2=$DB->query($query2);

                  if ($DB->numrows($result2)==0) {
                     $message .= $LANG['job'][32]."\n";
                  } else {
                     $data2 = $DB->fetch_array($result2);
                     $message .= convDateTime($data2["begin"])." -> ".convDateTime($data2["end"])."\n";
                  }
                  */
                  $message .= $LANG['mailing'][0]."\n";
               }
            }
         } else { // text format
            $message = $LANG['mailing'][1]."\n".$LANG['mailing'][4]." : $nbfollow\n".
                       $LANG['mailing'][1]."\n";

            if ($nbfollow>0) {
               $fup=new TicketFollowup();
               while ($data=$DB->fetch_array($result)) {
                  $fup->getFromDB($data['id']);
                  $message .= "[ ".convDateTime($fup->fields["date"])." ]".
                               ($fup->fields["is_private"]?"\t".$LANG['common'][77] :"")."\n";
                  $message .= $LANG['job'][4]." : ".$fup->getAuthorName()."\n";
                  $message .= $LANG['mailing'][3]." :\n".$fup->fields["content"]."\n";
                  if ($fup->fields["realtime"]>0) {
                     $message .= $LANG['mailing'][104]." : ".
                                 Ticket::getRealtime($fup->fields["realtime"])."\n";
                  }
                  $message .= $LANG['mailing'][25]." ";

                  // Use tasks instead of followups
                  /*
                  $query2 = "SELECT *
                             FROM `glpi_ticketplannings`
                             WHERE `ticketfollowups_id` = '".$data['id']."'";
                  $result2=$DB->query($query2);

                  if ($DB->numrows($result2)==0) {
                     $message .= $LANG['job'][32]."\n";
                  } else {
                     $data2 = $DB->fetch_array($result2);
                     $message .= convDateTime($data2["begin"])." -> ".convDateTime($data2["end"])."\n";
                  }
                  */
                  $message .= $LANG['mailing'][0]."\n";
               }
            }
         }
         return $message;
      } else {
         return "";
      }
   }


   /**
    * Get text describing ticket
    *
    * @param $format text or html
    */
   function textDescription($format="text") {
      global $DB,$LANG;

      $name = $LANG['help'][30];
      $contact = '';
      $tech = '';

      if ($this->hardwaredatas!=NULL) {
         $name = $this->hardwaredatas->getTypeName()." ".$this->hardwaredatas->getName();

         if ($this->hardwaredatas->isField("serial")) {

            $name .= " - #".$this->hardwaredatas->getField("serial");
         }
         $modeltable = getSingular($this->hardwaredatas->getTable())."models";
         $modelfield = getForeignKeyFieldForTable($modeltable);
         if ($this->hardwaredatas->isField($modelfield)) {
            $name .= " - ".Dropdown::getDropdownName($modeltable,$this->hardwaredatas->getField($modelfield));
         }
         if ($this->hardwaredatas->isField("users_id_tech")) {
            $tech = getUserName($this->hardwaredatas->getField("users_id_tech"));
         }
         if ($this->hardwaredatas->isField("contact")) {
            $contact = $this->hardwaredatas->getField("contact");
         }
         if ($this->hardwaredatas->isField("users_id")) {
            $contact = getUserName($this->hardwaredatas->getField("users_id"));
         }
         if ($this->hardwaredatas->isField("groups_id")) {
            if (!empty($contact)) {
               $contact.=" / ";
            }
            $contact .= Dropdown::getDropdownName("glpi_groups",$this->hardwaredatas->getField("groups_id"));
         }
      }

      if ($format=="html") {
         $message  = "<html><head> <style type='text/css'>";
         $message .= ".description{ color: inherit; background: #ebebeb; border-style: solid; ".
                                    "border-color: #8d8d8d; border-width: 0px 1px 1px 0px; }";
         $message .= "</style></head><body>";

         $message .= "<div class='description b'>".$LANG['mailing'][5]."</div>\n";
         $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline; '>".
                      $LANG['common'][57]."&nbsp;:</span> ".$this->fields["name"]."\n";
         $users_id = $this->getAuthorName();
         if (empty($users_id)) {
            $users_id = $LANG['mailing'][108];
         }
         $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline;'>".
                      $LANG['job'][4]."&nbsp;:</span> ".$users_id."\n";
         $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline;'>".
                      $LANG['search'][8]."&nbsp;:</span> ".convDateTime($this->fields["date"])."\n";
         $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline;'>".
                      $LANG['job'][44]."&nbsp;:</span> ".
                      Dropdown::getDropdownName('glpi_requesttypes', $this->fields["requesttypes_id"])."\n";
         $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline;'>".
                      $LANG['mailing'][7]."&nbsp;:</span> ".$name."\n";
         if (!empty($tech)) {
            $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline;'>".
                         $LANG['common'][10]."&nbsp;:</span> ".$tech."\n";
         }
         $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline;'>".
                      $LANG['joblist'][0]."&nbsp;:</span> ".$this->getStatus($this->fields["status"])."\n";

         $assign = Ticket::getAssignName($this->fields["users_id_assign"],'User');
         $group_assign = "";
         if (isset($this->fields["groups_id_assign"])) {
            $group_assign = Ticket::getAssignName($this->fields["groups_id_assign"],'Group');
         }
         if ($assign=="[Nobody]") {
            if (!empty($group_assign)) {
               $assign = $group_assign;
            } else {
               $assign = $LANG['mailing'][105];
            }
         } else if (!empty($group_assign)) {
            $assign .= " / ".$group_assign;
         }
         $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline;'>".
                      $LANG['mailing'][8]."&nbsp;:</span> ".$assign."\n";
         $message .="<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline;'>".
                      $LANG['joblist'][2].":</span> ".Ticket::getPriorityName($this->fields["priority"])."\n";
         if ($this->fields["itemtype"] != 'Software' && !empty($contact)) {
            $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline;'>".
                         $LANG['common'][18]."&nbsp;:</span> ".$contact."\n";
         }
         if (isset($this->fields["use_email_notification"])
             && $this->fields["use_email_notification"]) {

            $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline;'>".
                         $LANG['mailing'][103]."&nbsp;:</span> ".$LANG['choice'][1]."\n";
         } else {
            $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline;'>".
                         $LANG['mailing'][103]."&nbsp;:</span> ".$LANG['choice'][0]."\n";
         }
         $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline;'>".
                      $LANG['common'][36]."&nbsp;:</span> ";

         if (isset($this->fields["ticketcategories_id"])
             && $this->fields["ticketcategories_id"]) {

            $message .= Dropdown::getDropdownName("glpi_ticketcategories",
                                        $this->fields["ticketcategories_id"]);
         } else {
            $message .= $LANG['mailing'][100];
         }
         $message .= "\n";
         $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline; '>".
                      $LANG['mailing'][3]."&nbsp;:</span><br>".
                      str_replace("\n","<br>",$this->fields["content"])."<br>\n";

         if (!empty($this->fields["solution"])) {
            $message .= "<span style='color:#8B8C8F; font-weight:bold; text-decoration:underline; '>";
            if ($this->fields['ticketsolutiontypes_id']>0) {
               $message .= Dropdown::getDropdownName('glpi_ticketsolutiontypes',
                                                     $this->fields['ticketsolutiontypes_id']);
            } else {
               $message .= $LANG['jobresolution'][1];
            }
            $message .= "&nbsp;:</span><br>".str_replace("\n","<br>",$this->fields["solution"])."<br>\n";
         }

      } else { //text format
         $message  = $LANG['mailing'][1]."\n*".$LANG['mailing'][5]."*\n".$LANG['mailing'][1]."\n";
         $message .= mailRow($LANG['common'][57],$this->fields["name"]);
         $users_id = $this->getAuthorName();
         if (empty($users_id)) {
            $users_id = $LANG['mailing'][108];
         }
         $message .= mailRow($LANG['job'][4],$users_id);
         $message .= mailRow($LANG['search'][8],convDateTime($this->fields["date"]));
         $message .= mailRow($LANG['job'][44],Dropdown::getDropdownName('glpi_requesttypes',
                                                              $this->fields["requesttypes_id"]));
         $message .= mailRow($LANG['mailing'][7],$name);
         if (!empty($tech)) {
            $message .= mailRow($LANG['common'][10],$tech);
         }
         $message .= mailRow($LANG['joblist'][0],$this->getStatus($this->fields["status"]));
         $assign = Ticket::getAssignName($this->fields["users_id_assign"],'User');
         $group_assign = "";
         if (isset($this->fields["groups_id_assign"])) {
            $group_assign = Ticket::getAssignName($this->fields["groups_id_assign"],'Group');
         }
         if ($assign=="[Nobody]") {
            if (!empty($groups_id_assign)) {
               $assign=$group_assign;
            } else {
               $assign=$LANG['mailing'][105];
            }
         } else if (!empty($groups_id_assign)) {
           $assign.=" / ".$group_assign;
         }

         $message .= mailRow($LANG['mailing'][8],$assign);
         $message .= mailRow($LANG['joblist'][2],Ticket::getPriorityName($this->fields["priority"]));
         if ($this->fields["itemtype"] != 'Software' && !empty($contact)) {
            $message .= mailRow($LANG['common'][18],$contact);
         }
         if (isset($this->fields["use_email_notification"])
             && $this->fields["use_email_notification"]) {
            $message .= mailRow($LANG['mailing'][103],$LANG['choice'][1]);
         } else {
            $message .= mailRow($LANG['mailing'][103],$LANG['choice'][0]);
         }

         if (isset($this->fields["ticketcategories_id"])
             && $this->fields["ticketcategories_id"]) {

            $message .= mailRow($LANG['common'][36],
                                Dropdown::getDropdownName("glpi_ticketcategories",
                                                $this->fields["ticketcategories_id"]));
         } else {
            $message .= mailRow($LANG['common'][36],$LANG['mailing'][100]);
         }
         $message .= "--\n";
         $message .= $LANG['mailing'][3]." : \n".$this->fields["content"]."\n";
         if (!empty($this->fields["solution"])) {
            $message .= "\n*";
            if ($this->fields['ticketsolutiontypes_id']>0) {
               $message .= Dropdown::getDropdownName('glpi_ticketsolutiontypes',$this->fields['ticketsolutiontypes_id']);
            } else {
               $message .= $LANG['jobresolution'][1];
            }
            $message .= "* : \n".$this->fields["solution"]."\n";
         }
         $message .= "\n\n";

      }
      return $message;
   }


   /**
    * Get users_id name
    *
    * @param $link boolean with link ?
    * @return string users_id name
    */
   function getAuthorName($link=0) {
      return getUserName($this->fields["users_id"],$link);
   }


   /**
    * Is the current user have right to add followups to the current ticket ?
    *
    * @return boolean
    */
   function canAddFollowups() {

      return ((haveRight("add_followups","1") && $this->fields["users_id"]==$_SESSION["glpiID"])
              || haveRight("global_add_followups","1")
              || (isset($_SESSION["glpiID"])
                  && $this->fields["users_id_assign"]==$_SESSION["glpiID"])
              || (isset($_SESSION["glpigroups"])
                  && in_array($this->fields["groups_id_assign"],$_SESSION['glpigroups'])));
   }


   /**
    * Is the current user have right to show the current ticket ?
    *
    * @return boolean
   function canUserView() {

      return (haveRight("show_all_ticket","1")
              || (isset($_SESSION["glpiID"]) && $this->fields["users_id"]==$_SESSION["glpiID"])
              || (haveRight("show_group_ticket",'1')
                  && isset($_SESSION["glpigroups"])
                  && in_array($this->fields["groups_id"],$_SESSION["glpigroups"]))
              || (haveRight("show_assign_ticket",'1')
                  && ((isset($_SESSION["glpiID"])
                       && $this->fields["users_id_assign"]==$_SESSION["glpiID"]
                      )
                      || (isset($_SESSION["glpigroups"])
                          && in_array($this->fields["groups_id_assign"],$_SESSION["glpigroups"]))
                     )
                 )
             );
   }
    */


   /**
    * add files (from $_FILES) to a ticket
    * create document if needed
    * create link from document to ticket
    *
    * @param $id of the ticket
    *
    * @return array of doc added name
    */
   function addFiles ($id) {
      global $LANG, $CFG_GLPI;

      if (!isset($_FILES)) {
         return array();
      }
      $docadded=array();
      $doc=new Document();
      $docitem=new Document_Item();

      // add Document if exists
      if (isset($_FILES['multiple']) ) {
         unset($_FILES['multiple']);
         $TMPFILE = $_FILES;
      } else {
         $TMPFILE = array( $_FILES );
      }
      foreach ($TMPFILE as $_FILES) {
         if (isset($_FILES['filename'])
             && count($_FILES['filename'])>0
             && $_FILES['filename']["size"]>0) {
            // Check for duplicate
            if ($doc->getFromDBbyContent($this->fields["entities_id"],
                                         $_FILES['filename']['tmp_name'])) {
               $docID = $doc->fields["id"];
            } else {
               $input2=array();
               $input2["name"]         = addslashes($LANG['tracking'][24]." $id");
               $input2["tickets_id"]   = $id;
               $input2["entities_id"]  = $this->fields["entities_id"];
               $input2["documentcategories_id"]   = $CFG_GLPI["documentcategories_id_forticket"];
               $input2["_only_if_upload_succeed"]  = 1;
               $input2["entities_id"]  = $this->fields["entities_id"];
               $docID = $doc->add($input2);
            }
            if ($docID>0) {
               if ($docitem->add(array('documents_id' => $docID,
                                       'itemtype' => $this->getType(),
                                       'items_id' => $id))) {
                  $docadded[]=stripslashes($doc->fields["name"] . " - " . $doc->fields["filename"]);
               }
            }

         } else if (!empty($_FILES['filename']['name'])
                    && isset($_FILES['filename']['error'])
                    && $_FILES['filename']['error']){
            addMessageAfterRedirect($LANG['document'][46],false,ERROR);
         }
      }
      unset ($_FILES);
      return $docadded;
   }

   function getSearchOptions() {
      global $LANG;

      $tab = array();
      $tab['common'] = $LANG['common'][32];

      $tab[1]['table']         = 'glpi_tickets';
      $tab[1]['field']         = 'name';
      $tab[1]['linkfield']     = 'name';
      $tab[1]['name']          = $LANG['common'][16];
      $tab[1]['datatype']      = 'itemlink';
      $tab[1]['itemlink_type'] = 'Ticket';
      $tab[1]['searchtype']    = 'contains';

      $tab[21]['table']         = 'glpi_tickets';
      $tab[21]['field']         = 'content';
      $tab[21]['linkfield']     = '';
      $tab[21]['name']          = $LANG['joblist'][6];

      $tab[2]['table']     = 'glpi_tickets';
      $tab[2]['field']     = 'id';
      $tab[2]['linkfield'] = '';
      $tab[2]['name']      = $LANG['common'][2];

      $tab[12]['table']     = 'glpi_tickets';
      $tab[12]['field']     = 'status';
      $tab[12]['linkfield'] = 'status';
      $tab[12]['name']      = $LANG['joblist'][0];
      $tab[12]['searchtype']= 'equals';

      $tab[10]['table']     = 'glpi_tickets';
      $tab[10]['field']     = 'urgency';
      $tab[10]['linkfield'] = 'urgency';
      $tab[10]['name']      = $LANG['joblist'][29];
      $tab[10]['searchtype']= 'equals';

      $tab[11]['table']     = 'glpi_tickets';
      $tab[11]['field']     = 'impact';
      $tab[11]['linkfield'] = 'impact';
      $tab[11]['name']      = $LANG['joblist'][30];
      $tab[11]['searchtype']= 'equals';

      $tab[3]['table']     = 'glpi_tickets';
      $tab[3]['field']     = 'priority';
      $tab[3]['linkfield'] = 'priority';
      $tab[3]['name']      = $LANG['joblist'][2];
      $tab[3]['searchtype']= 'equals';

      $tab[15]['table']     = 'glpi_tickets';
      $tab[15]['field']     = 'date';
      $tab[15]['linkfield'] = '';
      $tab[15]['name']      = $LANG['reports'][60];
      $tab[15]['datatype'] = 'datetime';

      $tab[16]['table']     = 'glpi_tickets';
      $tab[16]['field']     = 'closedate';
      $tab[16]['linkfield'] = '';
      $tab[16]['name']      = $LANG['reports'][61];
      $tab[16]['datatype'] = 'datetime';

      $tab[19]['table']     = 'glpi_tickets';
      $tab[19]['field']     = 'date_mod';
      $tab[19]['linkfield'] = '';
      $tab[19]['name']      = $LANG['common'][26];
      $tab[19]['datatype'] = 'datetime';

      $tab[7]['table']     = 'glpi_ticketcategories';
      $tab[7]['field']     = 'name';
      $tab[7]['linkfield'] = 'ticketcategories_id';
      $tab[7]['name']      = $LANG['common'][36];

      $tab[9]['table']     = 'glpi_requesttypes';
      $tab[9]['field']     = 'name';
      $tab[9]['linkfield'] = 'requesttypes_id';
      $tab[9]['name']      = $LANG['job'][44];

      if (haveRight("show_all_ticket","1") || haveRight("show_assign_ticket",'1')) {

         $tab[80]['table']     = 'glpi_entities';
         $tab[80]['field']     = 'completename';
         $tab[80]['linkfield'] = 'entities_id';
         $tab[80]['name']      = $LANG['entity'][0];

         $tab['requester'] = $LANG['job'][4];

         $tab[4]['table']     = 'glpi_users';
         $tab[4]['field']     = 'name';
         $tab[4]['linkfield'] = 'users_id';
         $tab[4]['name']      = $LANG['job'][4];

         $tab[71]['table']     = 'glpi_groups';
         $tab[71]['field']     = 'name';
         $tab[71]['linkfield'] = 'groups_id';
         $tab[71]['name']      = $LANG['common'][35];

         $tab['assign'] = $LANG['job'][5];

         $tab[5]['table']     = 'glpi_users';
         $tab[5]['field']     = 'name';
         $tab[5]['linkfield'] = 'users_id_assign';
         $tab[5]['name']      = $LANG['job'][5]." - ".$LANG['job'][6];

         $tab[6]['table']     = 'glpi_suppliers';
         $tab[6]['field']     = 'name';
         $tab[6]['linkfield'] = 'suppliers_id_assign';
         $tab[6]['name']      = $LANG['job'][5]." - ".$LANG['financial'][26];

         $tab[8]['table']     = 'glpi_groups';
         $tab[8]['field']     = 'name';
         $tab[8]['linkfield'] = 'groups_id_assign';
         $tab[8]['name']      = $LANG['job'][5]." - ".$LANG['common'][35];

         $tab[22]['table']     = 'glpi_users';
         $tab[22]['field']     = 'name';
         $tab[22]['linkfield'] = 'users_id_recipient';
         $tab[22]['name']      = $LANG['job'][3];

         $tab['followup'] = $LANG['job'][9];

         $tab[25]['table']     = 'glpi_ticketfollowups';
         $tab[25]['field']     = 'content';
         $tab[25]['linkfield'] = '';
         $tab[25]['name']      = $LANG['job'][9];
         $tab[25]['forcegroupby'] = true;

         $tab[26]['table']     = 'glpi_tickettasks';
         $tab[26]['field']     = 'content';
         $tab[26]['linkfield'] = '';
         $tab[26]['name']      = $LANG['job'][7];
         $tab[26]['forcegroupby'] = true;

         $tab['solution'] = $LANG['jobresolution'][1];

         $tab[23]['table']     = 'glpi_ticketsolutiontypes';
         $tab[23]['field']     = 'name';
         $tab[23]['linkfield'] = 'ticketsolutiontypes_id';
         $tab[23]['name']      = $LANG['job'][48];

         $tab[24]['table']     = 'glpi_tickets';
         $tab[24]['field']     = 'solution';
         $tab[24]['linkfield'] = '';
         $tab[24]['name']      = $LANG['jobresolution'][1];

      }

      return $tab;
   }

   /**
    * Compute Priority
    *
    * @param $urgency integer from 1 to 5
    * @param $impact integer from 1 to 5
    *
    * @return integer from 1 to 5 (priority)
    */
   static function computePriority ($urgency, $impact) {
      global $CFG_GLPI;

      if (isset($CFG_GLPI['priority_matrix'][$urgency][$impact])) {
         return $CFG_GLPI['priority_matrix'][$urgency][$impact];
      }
      // Failback to trivial
      return round(($urgency+$impact)/2);
   }

   /**
   * Dropdown of ticket priority
   *
   * @param $name select name
   * @param $value default value
   * @param $complete see also at least selection (major included)
   * @param $major display major priority
   *
   * @return string id of the select
   */
   static function dropdownPriority($name,$value=0,$complete=false,$major=false) {
      global $LANG;

      $id = "select_$name".mt_rand();
      echo "<select id='$id' name='$name'>";
      if ($complete) {
         echo "<option value='0' ".($value==0?" selected ":"").">".$LANG['common'][66]."</option>";
         echo "<option value='-5' ".($value==-5?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][3]."</option>";
         echo "<option value='-4' ".($value==-4?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][4]."</option>";
         echo "<option value='-3' ".($value==-3?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][5]."</option>";
         echo "<option value='-2' ".($value==-2?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][6]."</option>";
         echo "<option value='-1' ".($value==-1?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][7]."</option>";
      }
      if ($complete || $major) {
         echo "<option value='6' ".($value==6?" selected ":"").">".$LANG['help'][2]."</option>";
      }
      echo "<option value='5' ".($value==5?" selected ":"").">".$LANG['help'][3]."</option>";
      echo "<option value='4' ".($value==4?" selected ":"").">".$LANG['help'][4]."</option>";
      echo "<option value='3' ".($value==3?" selected ":"").">".$LANG['help'][5]."</option>";
      echo "<option value='2' ".($value==2?" selected ":"").">".$LANG['help'][6]."</option>";
      echo "<option value='1' ".($value==1?" selected ":"").">".$LANG['help'][7]."</option>";

      echo "</select>";

      return $id;
   }

   /**
    * Get ticket priority Name
    *
    * @param $value status ID
    */
   static function getPriorityName($value) {
      global $LANG;

      switch ($value) {
         case 6 :
            return $LANG['help'][2];

         case 5 :
            return $LANG['help'][3];

         case 4 :
            return $LANG['help'][4];

         case 3 :
            return $LANG['help'][5];

         case 2 :
            return $LANG['help'][6];

         case 1 :
            return $LANG['help'][7];
      }
   }

   /**
    * Dropdown of ticket Urgency
    *
    * @param $name select name
    * @param $value default value
    * @param $complete see also at least selection
    *
    * @return string id of the select
    */
   static function dropdownUrgency($name, $value=0, $complete=false) {
      global $LANG, $CFG_GLPI;

      $id = "select_$name".mt_rand();
      echo "<select id='$id' name='$name'>";

      if ($complete) {
         echo "<option value='0' ".($value==0?" selected ":"").">".$LANG['common'][66]."</option>";
         echo "<option value='-5' ".($value==-5?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][42]."</option>";
         echo "<option value='-4' ".($value==-4?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][43]."</option>";
         echo "<option value='-3' ".($value==-3?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][44]."</option>";
         echo "<option value='-2' ".($value==-2?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][45]."</option>";
         echo "<option value='-1' ".($value==-1?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][46]."</option>";
      }


      if ($complete || ($CFG_GLPI['urgency_mask'] & (1<<5))) {
         echo "<option value='5' ".($value==5?" selected ":"").">".$LANG['help'][42]."</option>";
      }
      if ($complete || ($CFG_GLPI['urgency_mask'] & (1<<4))) {
         echo "<option value='4' ".($value==4?" selected ":"").">".$LANG['help'][43]."</option>";
      }
      echo "<option value='3' ".($value==3?" selected ":"").">".$LANG['help'][44]."</option>";
      if ($complete || ($CFG_GLPI['urgency_mask'] & (1<<2))) {
         echo "<option value='2' ".($value==2?" selected ":"").">".$LANG['help'][45]."</option>";
      }
      if ($complete || ($CFG_GLPI['urgency_mask'] & (1<<1))) {
         echo "<option value='1' ".($value==1?" selected ":"").">".$LANG['help'][46]."</option>";
      }
      echo "</select>";

      return $id;
   }

   /**
    * Get ticket Urgence Name
    *
    * @param $value status ID
    */
   static function getUrgencyName($value) {
      global $LANG;

      switch ($value) {
         case 5 :
            return $LANG['help'][42];

         case 4 :
            return $LANG['help'][43];

         case 3 :
            return $LANG['help'][44];

         case 2 :
            return $LANG['help'][45];

         case 1 :
            return $LANG['help'][46];
      }
   }

   /**
   * Dropdown of ticket Impact
   *
   * @param $name select name
   * @param $value default value
   * @param $complete see also at least selection (major included)
   *
   * @return string id of the select
   */
   static function dropdownImpact($name, $value=0, $complete=false) {
      global $LANG, $CFG_GLPI;

      $id = "select_$name".mt_rand();
      echo "<select id='$id' name='$name'>";


      if ($complete) {
         echo "<option value='0' ".($value==0?" selected ":"").">".$LANG['common'][66]."</option>";
         echo "<option value='-5' ".($value==-5?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][47]."</option>";
         echo "<option value='-4' ".($value==-4?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][48]."</option>";
         echo "<option value='-3' ".($value==-3?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][49]."</option>";
         echo "<option value='-2' ".($value==-2?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][50]."</option>";
         echo "<option value='-1' ".($value==-1?" selected ":"").">".$LANG['search'][16]." ".
                $LANG['help'][51]."</option>";
      }


      if ($complete || ($CFG_GLPI['impact_mask'] & (1<<5))) {
         echo "<option value='5' ".($value==5?" selected ":"").">".$LANG['help'][47]."</option>";
      }
      if ($complete || ($CFG_GLPI['impact_mask'] & (1<<4))) {
         echo "<option value='4' ".($value==4?" selected ":"").">".$LANG['help'][48]."</option>";
      }
      echo "<option value='3' ".($value==3?" selected ":"").">".$LANG['help'][49]."</option>";
      if ($complete || ($CFG_GLPI['impact_mask'] & (1<<2))) {
         echo "<option value='2' ".($value==2?" selected ":"").">".$LANG['help'][50]."</option>";
      }
      if ($complete || ($CFG_GLPI['impact_mask'] & (1<<1))) {
         echo "<option value='1' ".($value==1?" selected ":"").">".$LANG['help'][51]."</option>";
      }
      echo "</select>";

      return $id;
   }

   /**
    * Get ticket Impact Name
    *
    * @param $value status ID
    */
   static function getImpactName($value) {
      global $LANG;

      switch ($value) {
         case 5 :
            return $LANG['help'][47];

         case 4 :
            return $LANG['help'][48];

         case 3 :
            return $LANG['help'][49];

         case 2 :
            return $LANG['help'][50];

         case 1 :
            return $LANG['help'][51];
      }
   }

   /**
    * get the Ticket status list
    *
    * @param $withmetaforsearch boolean
    * @return an array
    */
   static function getAllStatusArray($withmetaforsearch=false) {
      global $LANG;

      $tab = array(
         'new'          => $LANG['joblist'][9],
         'assign'       => $LANG['joblist'][18],
         'plan'         => $LANG['joblist'][19],
         'waiting'      => $LANG['joblist'][26],
         'solved'       => $LANG['joblist'][32],
         'closed'       => $LANG['joblist'][33]);

      if ($withmetaforsearch) {
         $tab['notold']  = $LANG['joblist'][24];
         $tab['process'] = $LANG['joblist'][21];
         $tab['old']     = $LANG['joblist'][25];
         $tab['all']     = $LANG['common'][66];
      }
      return $tab;
   }

   /**
    * get the Ticket status allowed for a current status
    *
    * @param $current status
    * @return an array
    */
   static function getAllowedStatusArray($current) {
      global $LANG;

      $tab = self::getAllStatusArray();
      if (!isset($current)) {
         $current = 'new';
      }
      foreach ($tab as $status => $label) {
         if ($status != $current
             && isset($_SESSION['glpiactiveprofile']['helpdesk_status'][$current][$status])
             && !$_SESSION['glpiactiveprofile']['helpdesk_status'][$current][$status]) {
            unset($tab[$status]);
         }
      }
      return $tab;
   }


   /**
    * Dropdown of ticket status
    *
    * @param $name select name
    * @param $value default value
    * @param $option list proposed 0:normal, 1:search, 2:allowed
    *
    * @return nothing (display)
    */
   static function dropdownStatus($name, $value='new', $option=0) {

      if ($option == 2) {
         $tab = self::getAllowedStatusArray($value);
      } else if ($option == 1) {
         $tab = self::getAllStatusArray(true);
      } else {
         $tab = self::getAllStatusArray(false);
      }
      echo "<select name='$name'>";
      foreach ($tab as $key => $val) {
         echo "<option value='$key' ".($value==$key?" selected ":"").">$val</option>";
      }
      echo "</select>";
   }

   /**
    * Get ticket status Name
    *
    * @param $value status ID
    */
   static function getStatus($value) {
      global $LANG;

      $tab = self::getAllStatusArray(true);
      return (isset($tab[$value]) ? $tab[$value] : '');
   }

   /**
    * Show the current ticket sumnary --- NOT USED
   function showSummary() {
      global $DB, $LANG, $CFG_GLPI;

      if (!haveRight("observe_ticket", "1") && !haveRight("show_full_ticket", "1")) {
         return false;
      }

      $tID = $this->getField('id');

      // Display existing Followups
      $showprivate = haveRight("show_full_ticket", "1");
      $caneditall = haveRight("update_followups", "1");

      $RESTRICT = "";
      if (!$showprivate) {
         $RESTRICT = " AND (`is_private` = '0'
                                     OR `users_id` ='" . $_SESSION["glpiID"] . "') ";
      }

      // TODO keep this for a union with followup + task + histo + ...
      $query = "(SELECT 'TicketFollowup' as itemtype, `id`, `date`
                 FROM `glpi_ticketfollowups`
                 WHERE `tickets_id` = '$tID'
                        $RESTRICT)
                UNION
                (SELECT 'TicketTask' as itemtype, `id`, `date`
                 FROM `glpi_tickettasks`
                 WHERE `tickets_id` = '$tID'
                        $RESTRICT)
                ORDER BY `date` DESC";
      $result = $DB->query($query);

      $rand = mt_rand();

      echo "<div id='viewfollowup" . $tID . "$rand'></div>\n";

      echo "<div class='center'>";

      if ($DB->numrows($result) == 0) {
         echo "<table class='tab_cadre_fixe'>";
         echo "<tr><th>".$LANG['event'][20]."</th></tr>";
         echo "</table>";
         echo "<br>";
       } else {
         echo "<table class='tab_cadre_fixe'>";
         echo "<tr><th colspan ='7'>".$LANG['title'][38]."</th></tr>";
         echo "<tr class='tab_bg_2 center'><td colspan ='7'>";
         printPagerForm();
         echo "</td></tr></table>";
         echo "<table class='tab_cadre_fixe'>";
         echo "<tr><th>".$LANG['common'][17]."</th><th>" . $LANG['common'][27] . "</th>";
         echo "<th>" . $LANG['joblist'][6] . "</th><th>" . $LANG['job'][31] . "</th>";
         echo "<th>" . $LANG['common'][37] . "</th>";
         if ($showprivate) {
            echo "<th>" . $LANG['common'][77] . "</th>";
         }
         echo "<th>" . $LANG['job'][35] . "</th></tr>\n";

         while ($data = $DB->fetch_array($result)) {
            if (class_exists($data['itemtype'])) {
               $item = new $data['itemtype'];
               if ($item->getFromDB($data['id'])) {
                  $item->showInTicketSumnary($this, $rand, $showprivate, $caneditall);
               }
            }
         }
         echo "</table>";
      }
      echo "</div>";
   }
    */

   /**
   * Form to add a solution to a ticket
   *
   */
   function showSolutionForm() {
      global $DB,$LANG,$CFG_GLPI;

      $this->check($this->getField('id'), 'r');
      $canedit = $this->can($this->getField('id'), 'w');

      $this->showFormHeader($this->getFormURL(), $this->getField('id'), '', 2);

      echo "<tr class='tab_bg_2'>";
      echo "<td>".$LANG['job'][48]."</td><td colspan='3'>";

      $current = $this->fields['status'];
      if (!$canedit
          || ($current!='solved'
              && isset($_SESSION['glpiactiveprofile']['helpdesk_status'][$current]['solved'])
              && !$_SESSION['glpiactiveprofile']['helpdesk_status'][$current]['solved'])) {
         // Settings a solution will set status to solved
         echo Dropdown::getDropdownName('glpi_ticketsolutiontypes',
                                        $this->getField('ticketsolutiontypes_id'));
      } else {
         Dropdown::show('TicketSolutionType',
                        array('value' => $this->getField('ticketsolutiontypes_id')));
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".$LANG['joblist'][6]."</td><td colspan='3'>";
      if ($canedit) {
         echo "<textarea name='solution' rows='12' cols='100'>";
         echo $this->getField('solution') . "</textarea>";
      } else {
         echo nl2br($this->getField('solution'));
      }
      echo "</td></tr>";

      $this->showFormButtons($this->getField('id'), '', 2, false);
   }


   /**
   * Make a select box for Ticket my devices
   *
   *
   * @param $userID User ID for my device section
   * @param $entity_restrict restrict to a specific entity
   * @param $itemtype of selected item
   * @param $items_id of selected item
   *
   * @return nothing (print out an HTML select box)
   */
   static function dropdownMyDevices($userID=0, $entity_restrict=-1, $itemtype=0, $items_id=0) {
      global $DB,$LANG,$CFG_GLPI;

      if ($userID==0) {
         $userID=$_SESSION["glpiID"];
      }

      $rand=mt_rand();
      $already_add=array();

      if ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"]&pow(2,HELPDESK_MY_HARDWARE)) {
         $my_devices="";

         $my_item= $itemtype.'_'.$items_id;

         // My items
         foreach ($CFG_GLPI["linkuser_types"] as $itemtype) {
            if (class_exists($itemtype) && self::isPossibleToAssignType($itemtype)) {
               $itemtable=getTableForItemType($itemtype);
               $item = new $itemtype();
               $query="SELECT *
                     FROM `$itemtable`
                     WHERE `users_id`='".$userID."'";
               if ($item->maybeDeleted()) {
                  $query.=" AND `is_deleted`='0' ";
               }
               if ($item->maybeTemplate()) {
                  $query.=" AND `is_template`='0' ";
               }
               if (in_array($itemtype,$CFG_GLPI["helpdesk_visible_types"])){
                  $query.=" AND `is_helpdesk_visible`='1' ";
               }

               $query.=getEntitiesRestrictRequest("AND",$itemtable,"",$entity_restrict,
                                                $item->maybeRecursive());
               $query.=" ORDER BY `name` ";

               $result=$DB->query($query);
               if ($DB->numrows($result)>0) {
                  $type_name=$item->getTypeName();

                  while ($data=$DB->fetch_array($result)) {
                     $output=$data["name"];
                     if (empty($output) || $_SESSION["glpiis_ids_visible"]) {
                        $output.=" (".$data['id'].")";
                     }
                     $output = $type_name . " - " . $output;
                     if ($itemtype != 'Software') {
                        if (!empty($data['serial'])) {
                           $output.=" - ".$data['serial'];
                        }
                        if (!empty($data['otherserial'])) {
                           $output.=" - ".$data['otherserial'];
                        }
                     }
                     $my_devices.="<option title=\"$output\" value='".$itemtype."_".$data["id"]."' ";
                     $my_devices.=($my_item==$itemtype."_".$data["id"]?"selected":"").">";
                     $my_devices.=utf8_substr($output,0,$_SESSION["glpidropdown_chars_limit"])."</option>";

                     $already_add[$itemtype][]=$data["id"];
                  }
               }
            }
         }
         if (!empty($my_devices)) {
            $my_devices="<optgroup label=\"".$LANG['tracking'][1]."\">".$my_devices."</optgroup>";
         }

         // My group items
         if (haveRight("show_group_hardware","1")) {
            $group_where="";
            $groups=array();
            $query="SELECT `glpi_groups_users`.`groups_id`, `glpi_groups`.`name`
                  FROM `glpi_groups_users`
                  LEFT JOIN `glpi_groups` ON (`glpi_groups`.`id` = `glpi_groups_users`.`groups_id`)
                  WHERE `glpi_groups_users`.`users_id`='".$userID."' ".
                        getEntitiesRestrictRequest("AND","glpi_groups","",$entity_restrict);
            $result=$DB->query($query);
            $first=true;
            if ($DB->numrows($result)>0) {
               while ($data=$DB->fetch_array($result)) {
                  if ($first) {
                     $first=false;
                  } else {
                     $group_where.=" OR ";
                  }
                  $group_where.=" `groups_id` = '".$data["groups_id"]."' ";
               }

               $tmp_device="";
               foreach ($CFG_GLPI["linkgroup_types"] as $itemtype) {
                  if (class_exists($itemtype) && self::isPossibleToAssignType($itemtype)) {
                     $itemtable=getTableForItemType($itemtype);
                     $item = new $itemtype();
                     $query="SELECT *
                           FROM `$itemtable`
                           WHERE ($group_where) ".
                                 getEntitiesRestrictRequest("AND",$itemtable,"",
                                    $entity_restrict,$item->maybeRecursive());

                     if ($item->maybeDeleted()) {
                        $query.=" AND `is_deleted`='0' ";
                     }
                     if ($item->maybeTemplate()) {
                        $query.=" AND `is_template`='0' ";
                     }

                     $result=$DB->query($query);
                     if ($DB->numrows($result)>0) {
                        $type_name=$item->getTypeName();
                        if (!isset($already_add[$itemtype])) {
                           $already_add[$itemtype]=array();
                        }
                        while ($data=$DB->fetch_array($result)) {
                           if (!in_array($data["id"],$already_add[$itemtype])) {
                              $output='';
                              if (isset($data["name"])) {
                                 $output = $data["name"];
                              }
                              if (empty($output) || $_SESSION["glpiis_ids_visible"]) {
                                 $output .= " (".$data['id'].")";
                              }
                              $output = $type_name . " - " . $output;
                              if (isset($data['serial'])) {
                                 $output .= " - ".$data['serial'];
                              }
                              if (isset($data['otherserial'])) {
                                 $output .= " - ".$data['otherserial'];
                              }
                              $tmp_device.="<option title=\"$output\" value='".$itemtype."_".$data["id"];
                              $tmp_device.="' ".($my_item==$itemtype."_".$data["id"]?"selected":"").">";
                              $tmp_device.=utf8_substr($output,0,$_SESSION["glpidropdown_chars_limit"]);
                              $tmp_device.="</option>";

                              $already_add[$itemtype][]=$data["id"];
                           }
                        }
                     }
                  }
               }
               if (!empty($tmp_device)) {
                  $my_devices.="<optgroup label=\"".$LANG['tracking'][1]." - ".$LANG['common'][35]."\">";
                  $my_devices.=$tmp_device."</optgroup>";
               }
            }
         }
         // Get linked items to computers
         if (isset($already_add['Computer']) && count($already_add['Computer'])) {
            $search_computer=" XXXX IN (".implode(',',$already_add['Computer']).') ';
            $tmp_device="";

            // Direct Connection
            $types=array('Peripheral', 'Monitor', 'Printer', 'Phone');
            foreach ($types as $itemtype) {
               if (in_array($itemtype,$_SESSION["glpiactiveprofile"]["helpdesk_item_type"])
                  && class_exists($itemtype)) {
                  $itemtable=getTableForItemType($itemtype);
                  $item = new $itemtype();
                  if (!isset($already_add[$itemtype])) {
                     $already_add[$itemtype]=array();
                  }
                  $query="SELECT DISTINCT `$itemtable`.*
                        FROM `glpi_computers_items`
                        LEFT JOIN `$itemtable`
                              ON (`glpi_computers_items`.`items_id`=`$itemtable`.`id`)
                        WHERE `glpi_computers_items`.`itemtype`='$itemtype'
                              AND  ".str_replace("XXXX","`glpi_computers_items`.`computers_id`",
                                                   $search_computer);
                  if ($item->maybeDeleted()) {
                     $query.=" AND `is_deleted`='0' ";
                  }
                  if ($item->maybeTemplate()) {
                     $query.=" AND `is_template`='0' ";
                  }
                  $query.=getEntitiesRestrictRequest("AND",$itemtable,"",$entity_restrict)
                        ." ORDER BY `$itemtable`.`name`";

                  $result=$DB->query($query);
                  if ($DB->numrows($result)>0) {
                     $type_name=$item->getTypeName();
                     while ($data=$DB->fetch_array($result)) {
                        if (!in_array($data["id"],$already_add[$itemtype])) {
                           $output=$data["name"];
                           if (empty($output) || $_SESSION["glpiis_ids_visible"]) {
                              $output.=" (".$data['id'].")";
                           }
                           $output = $type_name . " - " . $output;
                           if ($itemtype != 'Software') {
                              $output.=" - ".$data['serial']." - ".$data['otherserial'];
                           }
                           $tmp_device.="<option title=\"$output\" value='".$itemtype."_".$data["id"];
                           $tmp_device.="' ".($my_item==$itemtype."_".$data["id"]?"selected":"").">";
                           $tmp_device.=utf8_substr($output,0,$_SESSION["glpidropdown_chars_limit"]);
                           $tmp_device.="</option>";

                           $already_add[$itemtype][]=$data["id"];
                        }
                     }
                  }
               }
            }
            if (!empty($tmp_device)) {
               $my_devices.="<optgroup label=\"".$LANG['reports'][36]."\">".$tmp_device."</optgroup>";
            }

            // Software
            if (in_array('Software',$_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
               $query = "SELECT DISTINCT `glpi_softwareversions`.`name` AS version,
                                       `glpi_softwares`.`name` AS name, `glpi_softwares`.`id`
                        FROM `glpi_computers_softwareversions`, `glpi_softwares`,
                              `glpi_softwareversions`
                        WHERE `glpi_computers_softwareversions`.`softwareversions_id`=
                                 `glpi_softwareversions`.`id`
                              AND `glpi_softwareversions`.`softwares_id` = `glpi_softwares`.`id`
                              AND ".str_replace("XXXX","`glpi_computers_softwareversions`.`computers_id`",
                                                $search_computer)."
                              AND `glpi_softwares`.`is_helpdesk_visible`='1' ".
                              getEntitiesRestrictRequest("AND","glpi_softwares","",$entity_restrict)."
                        ORDER BY `glpi_softwares`.`name`";

               $result=$DB->query($query);
               if ($DB->numrows($result)>0) {
                  $tmp_device="";
                  $item = new Software();
                  $type_name=$item->getTypeName();
                  if (!isset($already_add['Software'])) {
                     $already_add['Software'] = array();
                  }
                  while ($data=$DB->fetch_array($result)) {
                     if (!in_array($data["id"],$already_add['Software'])) {
                        $output  = "$type_name - ".$data["name"]." (v. ".$data["version"].")";
                        $output .= ($_SESSION["glpiis_ids_visible"]?" (".$data["id"].")":"");

                        $tmp_device.="<option title=\"$output\" value='Software_".$data["id"]."' ";
                        $tmp_device.=($my_item == 'Software'."_".$data["id"]?"selected":"").">";
                        $tmp_device.=utf8_substr($output,0,$_SESSION["glpidropdown_chars_limit"]);
                        $tmp_device.="</option>";

                        $already_add['Software'][]=$data["id"];
                     }
                  }
                  if (!empty($tmp_device)) {
                     $my_devices.="<optgroup label=\"".ucfirst($LANG['software'][17])."\">";
                     $my_devices.=$tmp_device."</optgroup>";
                  }
               }
            }
         }
         echo "<div id='tracking_my_devices'>";
         echo $LANG['tracking'][1].":&nbsp;<select id='my_items' name='_my_items'><option value=''>--- ";
         echo $LANG['help'][30]." ---</option>$my_devices</select></div>";
      }
   }
   /**
   * Make a select box for Tracking All Devices
   *
   * @param $myname select name
   * @param $itemtype preselected value.for item type
   * @param $items_id preselected value for item ID
   * @param $admin is an admin access ?
   * @param $entity_restrict Restrict to a defined entity
   * @return nothing (print out an HTML select box)
   */
   static function dropdownAllDevices($myname,$itemtype,$items_id=0,$admin=0,$entity_restrict=-1) {
      global $LANG,$CFG_GLPI,$DB;

      $rand=mt_rand();

      if ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"]==0) {
         echo "<input type='hidden' name='$myname' value='0'>";
         echo "<input type='hidden' name='items_id' value='0'>";
      } else {
         echo "<div id='tracking_all_devices'>";
         if ($_SESSION["glpiactiveprofile"]["helpdesk_hardware"]&pow(2,HELPDESK_ALL_HARDWARE)) {
            // Display a message if view my hardware
            if (!$admin
               && $_SESSION["glpiactiveprofile"]["helpdesk_hardware"]&pow(2,HELPDESK_MY_HARDWARE)) {
               echo $LANG['tracking'][2]."&nbsp;: ";
            }

            $types = self::getAllTypesForHelpdesk();
            echo "<select id='search_$myname$rand' name='$myname'>\n";
            echo "<option value='-1' >-----</option>\n";
            echo "<option value='' ".(empty($itemtype)?" selected":"").">".$LANG['help'][30]."</option>";
            foreach ($types as $type => $label) {
               echo "<option value='".$type."' ".(($type==$itemtype)?" selected":"").">".$label;
               echo "</option>\n";
            }
            echo "</select>";

            $params=array('itemtype'=>'__VALUE__',
                        'entity_restrict'=>$entity_restrict,
                        'admin'=>$admin,
                        'myname'=>"items_id",);

            ajaxUpdateItemOnSelectEvent("search_$myname$rand","results_$myname$rand",$CFG_GLPI["root_doc"].
                                       "/ajax/dropdownTrackingDeviceType.php",$params);

            echo "<span id='results_$myname$rand'>\n";

            if ($itemtype && class_exists($itemtype) && $items_id) {
               $item = new $itemtype();
               if ($item->getFromDB($items_id)) {
                  echo "<select name='items_id'>\n";
                  echo "<option value='$items_id'>".$item->getName();
                  echo "</option></select>";
               }
            }
            echo "</span>\n";
         }
         echo "</div>";
      }
      return $rand;
   }

   function showCost($target) {
      global $DB,$LANG;

      $this->check($this->getField('id'), 'r');
      $canedit = $this->can($this->getField('id'), 'w');

      $this->showFormHeader($this->getFormURL(), $this->getField('id'), '', 1);

      echo "<tr><th colspan='2'>".$LANG['job'][47]."</th></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='left' width='50%'>".$LANG['job'][20]."&nbsp;: </td>";

      echo "<td class='b'>".Ticket::getRealtime($this->fields["realtime"])."</td>";
      echo "</tr>";

      if ($canedit) {  // admin = oui on affiche les couts liés à l'interventions
         echo "<tr class='tab_bg_1'>";
         echo "<td class='left'>".$LANG['job'][40]."&nbsp;: </td>";

         echo "<td><input type='text' maxlength='100' size='15' name='cost_time' value='".
                    formatNumber($this->fields["cost_time"],true)."'></td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td class='left'>".$LANG['job'][41]."&nbsp;: </td>";

         echo "<td><input type='text' maxlength='100' size='15' name='cost_fixed' value='".
                    formatNumber($this->fields["cost_fixed"],true)."'></td>";
         echo "</tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td class='left'>".$LANG['job'][42]."&nbsp;: </td>";

         echo "<td><input type='text' maxlength='100' size='15' name='cost_material' value='".
                    formatNumber($this->fields["cost_material"],true)."'></td>";
         echo "</tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td class='left'>".$LANG['job'][43]."&nbsp;: </td>";

         echo "<td class='b'>";
         echo self::trackingTotalCost($this->fields["realtime"], $this->fields["cost_time"],
                                      $this->fields["cost_fixed"],$this->fields["cost_material"]);
         echo "</td>";
         echo "</tr>\n";
      }
      $this->showFormButtons($this->getField('id'), '', 1, false);
   }

   /**
    * Calculate Ticket TCO for an item
    *
    *@param $item CommonDBTM object of the item
    *
    *@return float
    *
    **/
   static function computeTco(CommonDBTM $item) {
      global $DB;

      $totalcost=0;

      $query = "SELECT `realtime`, `cost_time`, `cost_fixed`, `cost_material`
                FROM `glpi_tickets`
                WHERE `itemtype` = '".get_class($item)."'
                      AND `items_id` = '".$item->getField('id')."'
                      AND (`cost_time` > '0'
                           OR `cost_fixed` > '0'
                           OR `cost_material` > '0')";
      $result = $DB->query($query);

      $i = 0;
      if ($DB->numrows($result)) {
         while ($data=$DB->fetch_array($result)) {
            $totalcost += self::trackingTotalCost($data["realtime"],$data["cost_time"],$data["cost_fixed"],
                                            $data["cost_material"]);
         }
      }
      return $totalcost;
   }

   /**
    * Computer total cost of a ticket
    *
    * @param $realtime float : ticket realtime
    * @param $cost_time float : ticket time cost
    * @param $cost_fixed float : ticket fixed cost
    * @param $cost_material float : ticket material cost
    * @return total cost formatted string
    */
   static function trackingTotalCost($realtime,$cost_time,$cost_fixed,$cost_material) {
      return formatNumber(($realtime*$cost_time)+$cost_fixed+$cost_material,true);
   }

   function showForm($target, $ID,$array=array()) {
      global $DB,$CFG_GLPI,$LANG;

      $canupdate = haveRight('update_ticket','1');
      $canpriority = haveRight('update_priority','1');
      $showuserlink=0;
      if (haveRight('user','r')) {
         $showuserlink=1;
      }

      if ($ID > 0) {
         $this->check($ID,'r');
      } else {
         // Create item
         $this->check(-1,'w',$array);
      }

      $this->showTabs($ID);

      $canupdate_descr = $canupdate || ($this->numberOfFollowups()==0
                                        && $this->fields['users_id']==$_SESSION['glpiID']);


      echo "<form method='post' name='form_ticket' action='$target' enctype='multipart/form-data'>";
      echo '<div class="center" id="tabsbody">';
      echo "<table class='tab_cadre_fixe'>";

      // Optional line
      if (isMultiEntitiesMode()) {
         echo '<tr>';
         echo '<th colspan="4">';
         if ($ID) {
            echo Dropdown::getDropdownName('glpi_entities',$this->fields['entities_id']);
         } else {
            echo $LANG['job'][46]."&nbsp;:&nbsp;".Dropdown::getDropdownName("glpi_entities",
                                                                  $this->fields['entities_id']);
         }
         echo '</th>';
         echo '</tr>';
      }

      echo "<tr>";
      echo "<th class='left' colspan='2' width='50%'>";

      echo "<table>";
      echo "<tr>";
      echo "<td><span class='tracking_small'>".$LANG['joblist'][11]."&nbsp;: </span></td>";
      echo "<td>";
      if ($ID) {
         showDateTimeFormItem("date",$this->fields["date"],1,false,$canupdate);
      } else {
         showDateTimeFormItem("date",date("Y-m-d H:i:s"),1);
      }
      echo "</td>";
      if ($ID) {
         echo "<td><span class='tracking_small'>&nbsp;&nbsp; ".$LANG['job'][2]." &nbsp;: </span>";
         if ($canupdate) {
            User::dropdown(array('name'   => 'users_id_recipient',
                                 'value'  => $this->fields["users_id_recipient"],
                                 'entity' => $this->fields["entities_id"],
                                 'right'  => 'all'));
         } else {
            echo getUserName($this->fields["users_id_recipient"],$showuserlink);
         }
         echo "</td>";
         if (strstr($this->fields["status"],"old_")) {
            echo "<td>";
            echo "</tr><tr>";
            echo "<td><span class='tracking_small'>".$LANG['joblist'][12]."&nbsp;: </td>";
            echo "<td>";
            showDateTimeFormItem("closedate",$this->fields["closedate"],1,false,$canupdate);
            echo "</span>";
            echo "</td>";
         }
         echo "</tr>";
      }
      echo "</table>";
      echo "</th>";

      echo "<th colspan='2' width='50%'>";
      if ($ID) {
         echo "<span class='tracking_small'>".$LANG['common'][26]."&nbsp;:<br>";
         echo convDateTime($this->fields["date_mod"])."\n";
         echo "</span>";
      }
      echo "</th>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='left' width='60'>".$LANG['joblist'][0]."&nbsp;: </td>";
      echo "<td>";
      if ($canupdate) {
         Ticket::dropdownStatus("status",$this->fields["status"],2); // Allowed status
      } else {
         echo Ticket::getStatus($this->fields["status"]);
      }
      echo "</td>";
      echo "<th class='center b' colspan='2'>".$LANG['job'][4]."&nbsp;: </th>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='left'>".$LANG['joblist'][29]."&nbsp;: </td>";
      echo "<td>";
      if ($canupdate && ($canpriority || !$ID || $this->fields["users_id_recipient"]==$_SESSION["glpiID"])) {
         // Only change during creation OR when allowed to change priority OR when user is the creator
         $idurgency = Ticket::dropdownUrgency("urgency",$this->fields["urgency"]);
      } else {
         $idurgency = "value_urgency".mt_rand();
         echo "<input id='$idurgency' type='hidden' name='urgency' value='".$this->fields["urgency"]."'>";
         echo Ticket::getUrgencyName($this->fields["urgency"]);
      }
      echo "</td>";
      echo "<td class='left'>";
      if (!$ID && haveRight("update_ticket","1")) {
         echo $LANG['job'][4]."&nbsp;: </td>";
         echo "<td>";

         //List all users in the active entity (and all it's sub-entities if needed)
         User::dropdown(array('value'        => $array["users_id"],
                              'entity'       => $this->fields["entities_id"],
                              'entity_sons'  => haveAccessToEntity($this->fields["entities_id"],true),
                              'right'        => 'all',
                              'helpdesk_ajax'=> 1));

         //Get all the user's entities
         $all_entities = Profile_User::getUserEntities($array["users_id"], true);
         $values = array();

         //For each user's entity, check if the technician which creates the ticket have access to it
         foreach ($all_entities as $tmp => $ID_entity) {
            if (haveAccessToEntity($ID_entity)) {
               $values[] = $ID_entity;
            }
         }

         $count = count($values);

         if ($count>0 && !in_array($this->fields["entities_id"],$values)) {
            // If entity is not in the list of user's entities,
            // then use as default value the first value of the user's entites list
            $this->fields["entities_id"] = $values[0];
         }

         //If user have access to more than one entity, then display a combobox
         if ($count > 1) {
            $rand = Dropdown::show('Entity',
                           array('value'        => $this->fields["entities_id"],
                                 'entity'       => $values,
                                 'auto_submit'  => 1));

         } else {
            echo "<input type='hidden' name='entities_id' value='".$this->fields["entities_id"]."'>";
         }
      } else if ($canupdate){
         echo $LANG['common'][34]."&nbsp;: </td>";
         echo "<td>";
         User::dropdown(array('value'  => $this->fields["users_id"],
                              'entity' => $this->fields["entities_id"],
                              'right'  => 'all'));
      } else {
         echo $LANG['common'][34]."&nbsp;: </td>";
         echo "<td>";
         echo getUserName($this->fields["users_id"],$showuserlink);
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='left'>".$LANG['joblist'][30]."&nbsp;: </td>";
      echo "<td>";
      if ($canupdate) {
         $idimpact = Ticket::dropdownImpact("impact",$this->fields["impact"]);
      } else {
         echo Ticket::getImpactName($this->fields["impact"]);
      }
      echo "</td>";
      echo "<td class='left'>".$LANG['common'][35]."&nbsp;: </td>";
      echo "<td>";
      if ($canupdate) {
         Dropdown::show('Group',
                  array('value'  => $this->fields["groups_id"],
                        'entity' => $this->fields["entities_id"]));
      } else {
         echo Dropdown::getDropdownName("glpi_groups",$this->fields["groups_id"]);
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='left'>".$LANG['joblist'][2]."&nbsp;: </td>";
      echo "<td>";
      if ($canupdate && $canpriority) {
         $idpriority = Ticket::dropdownPriority("priority",$this->fields["priority"],false,true);
         $idajax = 'change_priority_' . mt_rand();
         echo "&nbsp;<span id='$idajax' style='display:none'></span>";
      } else {
         $idajax = 'change_priority_' . mt_rand();
         $idpriority = 0;
         echo "<span id='$idajax'>".Ticket::getPriorityName($this->fields["priority"])."</span>";
      }
      if ($canupdate) {
         $params=array('urgency'  => '__VALUE0__',
                       'impact'   => '__VALUE1__',
                       'priority' => $idpriority);
         ajaxUpdateItemOnSelectEvent(array($idurgency, $idimpact), $idajax,
                                     $CFG_GLPI["root_doc"]."/ajax/priority.php", $params);
      }
      echo "</td>";
      echo "<th class='center b' colspan='2'>".$LANG['job'][5]."&nbsp;: </th>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='left'>".$LANG['common'][36]."&nbsp;: </td>";
      echo "<td >";
      if ($canupdate) {
         Dropdown::show('TicketCategory',
                  array('value'  => $this->fields["ticketcategories_id"],
                        'entity' => $this->fields["entities_id"]));
      } else {
         echo Dropdown::getDropdownName("glpi_ticketcategories",$this->fields["ticketcategories_id"]);
      }
      echo "</td>";
      if (haveRight("assign_ticket","1")) {
         echo "<td class='left'>".$LANG['job'][6]."&nbsp;: </td>";
         echo "<td>";
         User::dropdown(array('name'   => 'users_id_assign',
                              'value'  => $this->fields["users_id_assign"],
                              'right'  => 'own_ticket',
                              'entity' => $this->fields["entities_id"]));
         echo "</td>";
      } else if (haveRight("steal_ticket","1")) {
         echo "<td class='right'>".$LANG['job'][6]."&nbsp;: </td>";
         echo "<td>";
         User::dropdown(array('name'   => 'users_id_assign',
                              'value'  => $this->fields["users_id_assign"],
                              'entity' => $this->fields["entities_id"]));
         echo "</td>";
      } else if (haveRight("own_ticket","1") && $this->fields["users_id_assign"]==0) {
         echo "<td class='right'>".$LANG['job'][6]."&nbsp;: </td>";
         echo "<td>";
         User::dropdown(array('name'   => 'users_id_assign',
                              'value'  => $this->fields["users_id_assign"],
                              'entity' => $this->fields["entities_id"]));
         echo "</td>";
      } else {
         echo "<td class='left'>".$LANG['job'][6]."&nbsp;: </td>";
         echo "<td>";
         echo getUserName($this->fields["users_id_assign"],$showuserlink);
         echo "</td>";
      }
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='left'>".$LANG['job'][44]."&nbsp;: </td>";
      echo "<td>";
      if ($canupdate) {
         Dropdown::show('RequestType',array('value' => $this->fields["requesttypes_id"]));
      } else {
         echo Dropdown::getDropdownName('glpi_requesttypes', $this->fields["requesttypes_id"]);
      }
      echo "</td>";
      if (haveRight("assign_ticket","1")) {
         echo "<td class='left'>".$LANG['common'][35]."&nbsp;: </td>";
         echo "<td>";
         Dropdown::show('Group',
                  array('name'   => 'groups_id_assign',
                        'value'  => $this->fields["groups_id_assign"],
                        'entity' => $this->fields["entities_id"]));
         echo "</td>";
      } else {
         echo "<td class='left'>".$LANG['common'][35]."&nbsp;: </td>";
         echo "<td>";
         echo Dropdown::getDropdownName("glpi_groups",$this->fields["groups_id_assign"]);
         echo "</td>";
      }
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='left' rowspan='2'>".$LANG['common'][1]."&nbsp;: </td>";
      echo "<td rowspan='2'>";
      if ($canupdate) {
         if ($ID && $this->fields['itemtype'] && class_exists($this->fields['itemtype'])) {
            $item = new $this->fields['itemtype']();
            if ($item->can($this->fields["items_id"],'r')) {
               echo $item->getTypeName()." - ".$item->getLink(true);
            } else {
               echo $item->getTypeName()." ".$item->getNameID();
            }
         } else {
            Ticket::dropdownMyDevices($this->fields["users_id"],$this->fields["entities_id"],
                              $this->fields["itemtype"], $this->fields["items_id"]);
         }
         Ticket::dropdownAllDevices("itemtype", $this->fields["itemtype"], $this->fields["items_id"],
                                    1, $this->fields["entities_id"]);
      } else {
         if ($ID && class_exists($this->fields['itemtype'])) {
            $item = new $this->fields['itemtype']();
            echo $item->getTypeName()." ".$item->getNameID();
         } else {
            echo $LANG['help'][30];
         }
      }
      echo "</td>";

      if (haveRight("assign_ticket","1")) {
         echo "<td class='left'>".$LANG['financial'][26]."&nbsp;: </td>";
         echo "<td>";
         Dropdown::show('Supplier',
                  array('name'   => 'suppliers_id_assign',
                        'value'  => $this->fields["suppliers_id_assign"],
                        'entity' => $this->fields["entities_id"]));

         echo "</td>";
      } else {
         echo "<td colspan='2'>&nbsp;</td>";
      }
      echo "</tr>\n";

      echo "<tr class='tab_bg_1'>";
      // Need comment right to add a followup with the realtime
      if (haveRight("global_add_followups","1") && !$ID) {
         echo "<td class='left'>".$LANG['job'][20]."&nbsp;: </td>";
         echo "<td class='center' colspan='3'>";
         Dropdown::showInteger('hour',$array['hour'],0,100);
         echo "&nbsp;".$LANG['job'][21]."&nbsp;&nbsp;";
         Dropdown::showInteger('minute',$array['minute'],0,59);
         echo "&nbsp;".$LANG['job'][22]."&nbsp;&nbsp;";
      } else {
         echo "<td colspan='2'>&nbsp;";
      }
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<th>".$LANG['common'][57]."&nbsp;: </th>";
      echo "<th>";
      if ($canupdate_descr) {
         $rand = mt_rand();
         echo "<script type='text/javascript' >\n";
         echo "function showName$rand(){\n";
         echo "Ext.get('name$rand').setDisplayed('none');";
         $params = array('maxlength' => 250,
                         'size'      => 50,
                         'name'      => 'name',
                         'data'      => rawurlencode($this->fields["name"]));
         ajaxUpdateItemJsCode("viewname$rand",$CFG_GLPI["root_doc"]."/ajax/inputtext.php",$params,
                              false);
         echo "}";
         echo "</script>\n";
         echo "<div id='name$rand' class='tracking' onClick='showName$rand()'>\n";
         if (empty($this->fields["name"])) {
            echo $LANG['reminder'][15];
         } else {
            echo $this->fields["name"];
         }
         echo "</div>\n";

         echo "<div id='viewname$rand'>\n";
         echo "</div>\n";
         if (!$ID) {
            echo "<script type='text/javascript' >\n
            showName$rand();
            </script>";
         }
      } else {
         if (empty($this->fields["name"])) {
            echo $LANG['reminder'][15];
         } else {
            echo $this->fields["name"];
         }
      }
      echo "</th>";
      echo "<th colspan='2'>";
      if ($CFG_GLPI["use_mailing"]==1) {
         echo $LANG['title'][10];
      }
      echo "</th></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td rowspan='4'>".$LANG['joblist'][6]."</td>";
      echo "<td class='left' rowspan='4'>";
      if ($canupdate_descr) { // Admin =oui on autorise la modification de la description
         $rand = mt_rand();
         echo "<script type='text/javascript' >\n";
         echo "function showDesc$rand(){\n";
         echo "Ext.get('desc$rand').setDisplayed('none');";
         $params = array('rows'  => 6,
                         'cols'  => 50,
                         'name'  => 'content',
                         'data'  => rawurlencode($this->fields["content"]));
         ajaxUpdateItemJsCode("viewdesc$rand",$CFG_GLPI["root_doc"]."/ajax/textarea.php",$params,
                              false);
         echo "}";
         echo "</script>\n";
         echo "<div id='desc$rand' class='tracking' onClick='showDesc$rand()'>\n";
         if (!empty($this->fields["content"])) {
            echo nl2br($this->fields["content"]);
         } else {
            echo $LANG['job'][33];
         }
         echo "</div>\n";

         echo "<div id='viewdesc$rand'></div>\n";
         if (!$ID) {
            echo "<script type='text/javascript' >\n
            showDesc$rand();
            </script>";
         }
      } else {
         echo nl2br($this->fields["content"]);
      }
      echo "</td>";
      // Mailing ? Y or no ?
      if ($CFG_GLPI["use_mailing"]==1) {
         echo "<td class='left'>".$LANG['job'][19]."&nbsp;: </td>";
         echo "<td>";
         if (!$ID) {
            $query = "SELECT `email`
                      FROM `glpi_users`
                      WHERE `id` ='".$this->fields["users_id"]."'";
            $result=$DB->query($query);

            $email = "";
            if ($result && $DB->numrows($result)) {
               $email=$DB->result($result,0,"email");
            }
            Dropdown::showYesNo('use_email_notification',!empty($email));
         } else {
            if ($canupdate){
               Dropdown::showYesNo('use_email_notification',$this->fields["use_email_notification"]);
            } else {
               if ($this->fields["use_email_notification"]) {
                  echo $LANG['choice'][1];
               } else {
                  echo $LANG['choice'][0];
               }
            }
         }
      } else {
         echo "<td colspan='2'>&nbsp;";
      }
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      // Mailing ? Y or no ?
      if ($CFG_GLPI["use_mailing"] == 1) {
         echo "<td class='left'>".$LANG['joblist'][27]."&nbsp;: </td>";
         echo "<td>";
         if (!$ID) {
            echo "<input type='text' size='30' name='user_email' value='$email'>";
         } else {
            if ($canupdate) {
               autocompletionTextField($this,"user_email");
               if (!empty($this->fields["user_email"])) {
                  echo "<a href='mailto:".$this->fields["user_email"]."'>";
                  echo "<img src='".$CFG_GLPI["root_doc"]."/pics/edit.png' alt='Mail'></a>";
               }
            } else if (!empty($this->fields["user_email"])) {
               echo "<a href='mailto:".$this->fields["user_email"]."'>".$this->fields["user_email"]."</a>";
            } else {
               echo "&nbsp;";
            }
         }
         echo "</td>";
      } else {
          echo "<td colspan='2'>&nbsp;";
      }
      echo "</td></tr>";
   /*
      echo "<tr class='tab_bg_1'>";
      echo "<th colspan='2'>".$LANG['document'][21]."</th>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td class='top' colspan='2'>";

      // File associated ?
      $query2 = "SELECT *
                 FROM `glpi_documents_items`
                 WHERE `glpi_documents_items`.`items_id` = '".$this->fields["id"]."'
                       AND `glpi_documents_items`.`itemtype` = 'Ticket' ";
      $result2 = $DB->query($query2);
      $numfiles=$DB->numrows($result2);

      echo "<table width='100%'>";

      if ($numfiles>0) {
         $doc=new Document;
         while ($data=$DB->fetch_array($result2)) {
            $doc->getFromDB($data["documents_id"]);
            echo "<tr><td>";
            if (empty($doc->fields["filename"])) {
               if (haveRight("document","r")) {
                  echo "<a href='".$CFG_GLPI["root_doc"]."/front/document.form.php?id=".
                        $data["documents_id"]."'>".$doc->fields["name"]."</a>";
               } else {
                  echo $LANG['document'][37];
               }
            } else {
               echo $doc->getDownloadLink("&tickets_id=$ID");
            }
            if (haveRight("document","w")) {
               echo "<a href='".$CFG_GLPI["root_doc"]."/front/document.form.php?deletedocumentitem=".
                     "1&amp;id=".$data["id"]."&amp;documents_id=".$data["documents_id"]."'>";
               echo "<img src='".$CFG_GLPI["root_doc"]."/pics/delete.png' alt='".$LANG['buttons'][6]."'>";
               echo "</a>";
            }
            echo "</td></tr>";
         }
      }

      if ($canupdate || haveRight("global_add_followups","1")
          || (haveRight("add_followups","1") && !strstr($this->fields["status"],'old_'))) {
         echo "<tr>";
         echo "<td colspan='2'>";
         echo "<input type='file' name='filename' size='20'>";
         if ($canupdate && haveRight("document","r")) {
            echo "<br>";
            Document::dropdown(array('name' => "document", 'entity' => $this->fields["entities_id"]));
         }
         echo "</td></tr>";
      }
      echo "</table>";
     echo "</td></tr>";
   */

      if ($canupdate
          || $canupdate_descr
          || haveRight("global_add_followups","1")
          ||(haveRight("add_followups","1") && !strstr($this->fields["status"],'old_'))
          || haveRight("assign_ticket","1")
          || haveRight("steal_ticket","1")) {

         echo "<tr class='tab_bg_1'>";
         if ($ID) {
            echo "<td colspan='3' class='center'>";
            echo "<input type='submit' class='submit' name='update' value='".$LANG['buttons'][7]."'>";
         } else {
            echo "<td colspan='1' class='center'>";
            echo "<a href='$target'>";
            echo "<input type='button' value='".$LANG['buttons'][16]."' class='submit'></a></td>";
            echo "<td colspan='2' class='center'>";
            echo "<input type='submit' name='add' value='".$LANG['buttons'][2]."' class='submit'>";
         }
         echo "</td></tr>";
      }

      echo "</table>";
      echo "<input type='hidden' name='id' value='$ID'>";
      echo "</div>";

      if (!$ID) {
   /* TODO to be fixed => task + solution
         $commentall = haveRight("update_followups","1");
         $prefix = "";
         $postfix = "";
         $randfollow = mt_rand();
         echo "<script type='text/javascript' >\n";
         echo "function showFollow$randfollow(){\n";
         echo "document.getElementById('follow$randfollow').style.display='block';\n";
         echo "document.getElementById('followLink$randfollow').style.display='none';\n";
         echo "}";
         echo "</script>\n";

         // Follow for add ticket
         echo "<br/>";
         echo "<div class='center'>";

         echo "<div id='followLink$randfollow'>";
         echo "<a href='javascript:onClick=showFollow$randfollow();'>".$LANG['job'][29]."</a></div>";

         echo "<div id='follow$randfollow' style='display:none'>";
         showAddFollowupForm(-1,false);
         echo "</div>";
   */
      }
      echo "<input type='hidden' name='id' value='$ID'>";
      echo "</form>";

      echo "<div id='tabcontent'></div>";
      echo "<script type='text/javascript'>loadDefaultTab();</script>";

      return true;
   }


   static function showCentralList($target,$start,$status="process",$showgrouptickets=true) {
      global $DB,$CFG_GLPI, $LANG;

      if (!haveRight("show_all_ticket","1")
         && !haveRight("show_assign_ticket","1")
         && !haveRight("create_ticket","1")) {
         return false;
      }

      $search_users_id = " (`glpi_tickets`.`users_id` = '".$_SESSION["glpiID"]."'
                           AND (`status` = 'new'
                                 OR `status` = 'plan'
                                 OR `status` = 'assign'
                                 OR `status` = 'waiting'))
                        OR ";
      $search_assign = " `users_id_assign` = '".$_SESSION["glpiID"]."' ";
      if ($showgrouptickets) {
         $search_users_id = "";
         $search_assign = " 0 = 1 ";
         if (count($_SESSION['glpigroups'])) {
            $groups = implode("','",$_SESSION['glpigroups']);
            $search_assign = " `groups_id_assign` IN ('$groups') ";
            if (haveRight("show_group_ticket",1)) {
               $search_users_id = " (`groups_id` IN ('$groups')
                                    AND (`status` = 'new'
                                          OR `status` = 'plan'
                                          OR `status` = 'assign'
                                          OR `status` = 'waiting'))
                                 OR ";
            }
         }
      }

      $query = "SELECT `id`
               FROM `glpi_tickets`";

      if ($status=="waiting") { // on affiche les tickets en attente
         $query .= "WHERE ($search_assign)
                        AND `status` ='waiting' ".
                        getEntitiesRestrictRequest("AND","glpi_tickets");

      } else { // on affiche les tickets planifiés ou assignés à glpiID
         $query .= "WHERE ($search_users_id (( $search_assign )
                                             AND (`status` ='plan'
                                                OR `status` = 'assign'))) ".
                        getEntitiesRestrictRequest("AND","glpi_tickets");
      }
      $query .= "ORDER BY `date_mod` DESC";

      $result = $DB->query($query);
      $numrows = $DB->numrows($result);

      $query .= " LIMIT ".intval($start).",".intval($_SESSION['glpilist_limit']);

      $result = $DB->query($query);
      $i = 0;
      $number = $DB->numrows($result);
      //echo $query;
      if ($number > 0) {
         echo "<table class='tab_cadrehov' style='width:420px'>";

         echo "<tr><th colspan='5'>";
         if ($status=="waiting") {
            if ($showgrouptickets) {
               $options['reset']  = 'reset';
               $options['field'][0]      = 12; // status
               $options['searchtype'][0] = 'equals';
               $options['contains'][0]   = 'waiting';
               $options['link'][0]        = 'AND';

               $num=1;
               foreach ($_SESSION['glpigroups'] as $gID) {
                  $options['field'][$num]      = 8; // status
                  $options['searchtype'][$num] = 'equals';
                  $options['contains'][$num]   = $gID;
                  $options['link'][$num]        = ($num==1?'AND':'OR');
                  $num++;

               }

               echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options).
                     "\">".$LANG['central'][16]."</a>";
            } else {
               $options['reset']  = 'reset';
               $options['field'][0]      = 12; // status
               $options['searchtype'][0] = 'equals';
               $options['contains'][0]   = 'waiting';
               $options['link'][0]        = 'AND';

               echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options).
                     "\">".$LANG['central'][11]."</a>";
            }
         } else {
            echo $LANG['central'][17]."&nbsp;: ";
            if ($showgrouptickets) {
      // Not used ??
/*               if (haveRight("show_group_ticket",1)) {
                  echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.phpq?group=-1&amp;users_id=".
                        $_SESSION["glpiID"]."&amp;reset=reset\">".$LANG['joblist'][5]."</a> / ";
               }*/
               $options['reset']  = 'reset';
               $options['field'][0]      = 12; // status
               $options['searchtype'][0] = 'equals';
               $options['contains'][0]   = 'process';
               $options['link'][0]        = 'AND';

               $num=1;
               foreach ($_SESSION['glpigroups'] as $gID) {
                  $options['field'][$num]      = 8; // status
                  $options['searchtype'][$num] = 'equals';
                  $options['contains'][$num]   = $gID;
                  $options['link'][$num]        = ($num==1?'AND':'OR');
                  $num++;

               }

               echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options).
                     "\">".$LANG['joblist'][21]."</a>";
            } else {
               $options['reset']  = 'reset';
               $options['field'][0]      = 4; // users_id
               $options['searchtype'][0] = 'equals';
               $options['contains'][0]   = $_SESSION["glpiID"];
               $options['link'][0]        = 'AND';

               echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options).
                        "\">".$LANG['joblist'][5]."</a> / ";

               $options['field'][0]      = 5; // users_id_assign

               echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options).
                        "\">".$LANG['joblist'][21]."</a>";
            }
         }
         echo "</th></tr>";
         echo "<tr><th></th>";
         echo "<th>".$LANG['job'][4]."</th>";
         echo "<th>".$LANG['common'][1]."</th>";
         echo "<th>".$LANG['joblist'][6]."</th></tr>";
         while ($i < $number) {
            $ID = $DB->result($result, $i, "id");
            Ticket::showVeryShort($ID);
            $i++;
         }
         echo "</table>";
      } else {
         echo "<table class='tab_cadrehov'>";
         echo "<tr><th>";
         if ($status=="waiting") {
            echo $LANG['central'][11];
         } else {
            echo $LANG['central'][9];
         }
         echo "</th></tr>";
         echo "</table>";
      }
   }

   static function showCentralCount() {
      global $DB,$CFG_GLPI, $LANG;

      // show a tab with count of jobs in the central and give link
      if (!haveRight("show_all_ticket","1")) {
         return false;
      }

      $query = "SELECT `status`, count(*) AS COUNT
               FROM `glpi_tickets` ".
               getEntitiesRestrictRequest("WHERE","glpi_tickets")."
               GROUP BY `status`";
      $result = $DB->query($query);

      $status = array('new'      => 0,
                     'assign'   => 0,
                     'plan'     => 0,
                     'waiting'  => 0);

      if ($DB->numrows($result)>0) {
         while ($data=$DB->fetch_assoc($result)) {
            $status[$data["status"]] = $data["COUNT"];
         }
      }

      $options['field'][0]      = 12;
      $options['searchtype'][0] = 'equals';
      $options['contains'][0]   = 'all';
      $options['link'][0]        = 'AND';
      $options['reset']='reset';

      echo "<table class='tab_cadrehov' >";
      echo "<tr><th colspan='2'>";

      $options['contains'][0]   = 'process';
      echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options)."\">".
            $LANG['title'][10]."</a></th></tr>";
      echo "<tr><th>".$LANG['title'][28]."</th><th>".$LANG['tracking'][29]."</th></tr>";
      echo "<tr class='tab_bg_2'>";
      echo "<td>";
      $options['contains'][0]   = 'new';
      echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options)."\">".
            $LANG['tracking'][30]."</a> </td>";
      echo "<td>".$status["new"]."</td></tr>";
      echo "<tr class='tab_bg_2'>";
      echo "<td>";
      $options['contains'][0]   = 'assign';
      echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options)."\">".
            $LANG['tracking'][31]."</a></td>";
      echo "<td>".$status["assign"]."</td></tr>";
      echo "<tr class='tab_bg_2'>";
      echo "<td>";

      $options['contains'][0]   = 'plan';
      echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options)."\">".
            $LANG['tracking'][32]."</a></td>";
      echo "<td>".$status["plan"]."</td></tr>";
      echo "<tr class='tab_bg_2'>";
      echo "<td>";

      $options['contains'][0]   = 'waiting';
      echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options)."\">".
            $LANG['joblist'][26]."</a></td>";
      echo "<td>".$status["waiting"]."</td></tr>";
      echo "</table><br>";
   }

   static function showCentralNewList() {
      global $DB,$CFG_GLPI, $LANG;

      if (!haveRight("show_all_ticket","1")) {
         return false;
      }

      $query = "SELECT ".Ticket::getCommonSelect()."
               FROM `glpi_tickets` ".Ticket::getCommonLeftJoin()."
               WHERE `status` = 'new' ".
                     getEntitiesRestrictRequest("AND","glpi_tickets")."
               ORDER BY `glpi_tickets`.`date_mod` DESC
               LIMIT ".intval($_SESSION['glpilist_limit']);
      $result = $DB->query($query);
      $number = $DB->numrows($result);

      if ($number > 0) {
         initNavigateListItems('Ticket');

         $options['field'][0]      = 12;
         $options['searchtype'][0] = 'equals';
         $options['contains'][0]   = 'new';
         $options['link'][0]        = 'AND';
         $options['reset']='reset';

         echo "<div class='center'><table class='tab_cadre_fixe'>";
         echo "<tr><th colspan='10'>".$LANG['central'][10]." ($number)&nbsp;: &nbsp;";
         echo "<a href='".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options).
               "'>".$LANG['buttons'][40]."</a>";
         echo "</th></tr>";

         Ticket::commonListHeader(HTML_OUTPUT);

         while ($data=$DB->fetch_assoc($result)) {
            addToNavigateListItems('Ticket',$data["id"]);
            Ticket::showShort($data, 0);
         }
         echo "</table></div>";
      } else {
         echo "<div class='center'>";
         echo "<table class='tab_cadre_fixe'>";
         echo "<tr><th>".$LANG['joblist'][8]."</th></tr>";
         echo "</table>";
         echo "</div><br>";
      }
   }

   static function commonListHeader($output_type=HTML_OUTPUT) {
      global $LANG,$CFG_GLPI;

      // New Line for Header Items Line
      echo displaySearchNewLine($output_type);
      // $show_sort if
      $header_num=1;

      $items=array();

      $items[$LANG['joblist'][0]] = "glpi_tickets.status";
      $items[$LANG['common'][27]] = "glpi_tickets.date";
      $items[$LANG['common'][26]] = "glpi_tickets.date_mod";
      if (count($_SESSION["glpiactiveentities"])>1) {
         $items[$LANG['Menu'][37]] = "glpi_entities.completename";
      }
      $items[$LANG['joblist'][2]]   = "glpi_tickets.priority";
      $items[$LANG['job'][4]]       = "glpi_tickets.users_id";
      $items[$LANG['joblist'][4]]   = "glpi_tickets.users_id_assign";
      $items[$LANG['common'][1]]    = "glpi_tickets.itemtype,glpi_tickets.items_id";
      $items[$LANG['common'][36]]   = "glpi_ticketcategories.completename";
      $items[$LANG['common'][57]]   = "glpi_tickets.name";

      foreach ($items as $key => $val) {
         $issort = 0;
         $link = "";
         echo displaySearchHeaderItem($output_type,$key,$header_num,$link);
      }

      // End Line for column headers
      echo displaySearchEndLine($output_type);
   }

   /**
   * Display tickets for an item
   *
   * Will also display tickets of linked items
   *
   * @param $itemtype
   * @param $items_id
   *
   * @return nothing (display a table)
   */
   static function showListForItem($itemtype,$items_id) {
      global $DB,$CFG_GLPI, $LANG;

      if (!haveRight("show_all_ticket","1")) {
         return false;
      }
      if (!class_exists($itemtype)) {
         return false;
      }
      $item=new $itemtype();
      if (!$item->getFromDB($items_id)) {
         return false;
      }

      $query = "SELECT ".Ticket::getCommonSelect()."
               FROM `glpi_tickets` ".Ticket::getCommonLeftJoin()."
               WHERE (`items_id` = '$items_id'
                     AND `itemtype` = '$itemtype') ".
                     getEntitiesRestrictRequest("AND","glpi_tickets")."
               ORDER BY `glpi_tickets`.`date_mod` DESC
               LIMIT ".intval($_SESSION['glpilist_limit']);
      $result = $DB->query($query);
      $number = $DB->numrows($result);

      // Ticket for the item
      echo "<div class='center'><table class='tab_cadre_fixe'>";
      if ($number > 0) {
         initNavigateListItems('Ticket',$item->getTypeName()." = ".$item->getName());

         $options['field'][0]      = 12;
         $options['searchtype'][0] = 'equals';
         $options['contains'][0]   = 'new';
         $options['link'][0]        = 'AND';

         $options['itemtype2'][0]  = $itemtype;
         $options['field2'][0]      = 2;
         $options['searchtype2'][0] = 'equals';
         $options['contains2'][0]   = $items_id;
         $options['link2'][0]        = 'AND';

         $options['reset']='reset';


         echo "<tr><th colspan='10'>".$number." ".$LANG['job'][8]."&nbsp;: &nbsp;";
         echo "<a href='".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options)."'>".
                     $LANG['buttons'][40]."</a>";
         echo "</th></tr>";
      } else {
         echo "<tr><th>".$LANG['joblist'][8]."</th></tr>";
      }

      // Link to open a new ticcket
      if ($items_id) {
         echo "<tr><td class='tab_bg_2 center' colspan='10'>";
         echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.form.php?items_id=$items_id&amp;itemtype=".
               "$itemtype\"><strong>".$LANG['joblist'][7]."</strong></a>";
         echo "</td></tr>";
      }

      // Ticket list
      if ($number > 0) {
         Ticket::commonListHeader(HTML_OUTPUT);

         while ($data=$DB->fetch_assoc($result)) {
            addToNavigateListItems('Ticket',$data["id"]);
            Ticket::showShort($data, 0);
         }
      }
      echo "</table></div><br>";

      // Tickets for linked items
      if ($subquery = $item->getSelectLinkedItem()) {
         $query = "SELECT ".Ticket::getCommonSelect()."
                  FROM `glpi_tickets` ".Ticket::getCommonLeftJoin()."
                  WHERE (`itemtype`,`items_id`) IN (" . $subquery . ")".
                        getEntitiesRestrictRequest(' AND ', 'glpi_tickets') . "
                  ORDER BY `glpi_tickets`.`date_mod` DESC
                  LIMIT ".intval($_SESSION['glpilist_limit']);
         $result = $DB->query($query);
         $number = $DB->numrows($result);

         echo "<div class='center'><table class='tab_cadre_fixe'>";
         echo "<tr><th colspan='10'>".$LANG['joblist'][28]."</th></tr>";
         if ($number > 0) {
            Ticket::commonListHeader(HTML_OUTPUT);

            while ($data=$DB->fetch_assoc($result)) {
               // addToNavigateListItems(TRACKING_TYPE,$data["id"]);
               Ticket::showShort($data, 0);
            }
         } else {
            echo "<tr><th>".$LANG['joblist'][8]."</th></tr>";
         }
         echo "</table></div><br>";

      } // Subquery for linked item
   }

   static function showListForSupplier($entID) {
      global $DB,$CFG_GLPI, $LANG;

      if (!haveRight("show_all_ticket","1")) {
         return false;
      }

      $query = "SELECT ".Ticket::getCommonSelect()."
               FROM `glpi_tickets` ".Ticket::getCommonLeftJoin()."
               WHERE (`suppliers_id_assign` = '$entID') ".
                     getEntitiesRestrictRequest("AND","glpi_tickets")."
               ORDER BY `glpi_tickets`.`date_mod` DESC
               LIMIT ".intval($_SESSION['glpilist_limit']);
      $result = $DB->query($query);
      $number = $DB->numrows($result);

      if ($number > 0) {
         $ent=new Supplier();
         $ent->getFromDB($entID);
         initNavigateListItems('Ticket',$LANG['financial'][26]." = ".$ent->fields['name']);


         $options['field'][0]      = 6;
         $options['searchtype'][0] = 'equals';
         $options['contains'][0]   = $entID;
         $options['link'][0]        = 'AND';

         $options['reset']='reset';

         echo "<div class='center'><table class='tab_cadre_fixe'>";
         echo "<tr><th colspan='10'>".$number." ".$LANG['job'][8]."&nbsp;:&nbsp;";
         echo "<a href='".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options)."'>".
                           $LANG['buttons'][40]."</a>";
         echo "</th></tr>";

         Ticket::commonListHeader(HTML_OUTPUT);

         while ($data=$DB->fetch_assoc($result)) {
            addToNavigateListItems('Ticket',$data["id"]);
            Ticket::showShort($data, 0);
         }
         echo "</table></div>";
      } else {
         echo "<div class='center'>";
         echo "<table class='tab_cadre_fixe'>";
         echo "<tr><th>".$LANG['joblist'][8]."</th></tr>";
         echo "</table>";
         echo "</div><br>";
      }
   }

   static function showListForUser($userID) {
      global $DB,$CFG_GLPI, $LANG;

      if (!haveRight("show_all_ticket","1")) {
         return false;
      }

      $query = "SELECT ".Ticket::getCommonSelect()."
               FROM `glpi_tickets` ".Ticket::getCommonLeftJoin()."
               WHERE (`glpi_tickets`.`users_id` = '$userID') ".
                     getEntitiesRestrictRequest("AND","glpi_tickets")."
               ORDER BY `glpi_tickets`.`date_mod` DESC
               LIMIT ".intval($_SESSION['glpilist_limit']);
      $result = $DB->query($query);
      $number = $DB->numrows($result);

      if ($number > 0) {
         $user=new User();
         $user->getFromDB($userID);
         initNavigateListItems('Ticket',$LANG['common'][34]." = ".$user->getName());

         echo "<div class='center'><table class='tab_cadre_fixe'>";
         echo "<tr><th colspan='10'>".$number." ".$LANG['job'][8]."&nbsp;: &nbsp;";

         $options['reset']  = 'reset';
         $options['field'][0]      = 4; // status
         $options['searchtype'][0] = 'equals';
         $options['contains'][0]   = $userID;
         $options['link'][0]        = 'AND';

         echo "<a href='".$CFG_GLPI["root_doc"]."/front/ticket.php?".append_params($options)."'>".$LANG['buttons'][40]."</a>";
         echo "</th></tr>";

         Ticket::commonListHeader(HTML_OUTPUT);

         while ($data=$DB->fetch_assoc($result)) {
            addToNavigateListItems('Ticket',$data["id"]);
            Ticket::showShort($data, 0);
         }
         echo "</table></div>";
      } else {
         echo "<div class='center'>";
         echo "<table class='tab_cadre_fixe'>";
         echo "<tr><th>".$LANG['joblist'][8]."</th></tr>";
         echo "</table>";
         echo "</div><br>";
      }
   }


   static function showShort($data, $followups,$output_type=HTML_OUTPUT,$row_num=0) {
      global $CFG_GLPI, $LANG;

      // Prints a job in short form
      // Should be called in a <table>-segment
      // Print links or not in case of user view
      // Make new job object and fill it from database, if success, print it
      $job=new Ticket();
      $job->fields = $data;
      $candelete = haveRight("delete_ticket","1");
      $canupdate = haveRight("update_ticket","1");
      $align = "class='center";
      $align_desc = "class='left";
      if ($followups) {
         $align .= " top'";
         $align_desc .= " top'";
      } else {
         $align .= "'";
         $align_desc .= "'";
      }
      if ($data["id"]) {
         $item_num=1;
         $bgcolor=$_SESSION["glpipriority_".$data["priority"]];

         echo displaySearchNewLine($output_type,$row_num%2);

         // First column
         $first_col = "ID : ".$data["id"];
         if ($output_type==HTML_OUTPUT) {
            $first_col .= "<br><img src=\"".$CFG_GLPI["root_doc"]."/pics/".$data["status"].".png\"
                           alt='".Ticket::getStatus($data["status"])."' title='".
                           Ticket::getStatus($data["status"])."'>";
         } else {
            $first_col .= " - ".Ticket::getStatus($data["status"]);
         }
         if (($candelete || $canupdate)
            && $output_type==HTML_OUTPUT) {

            $sel = "";
            if (isset($_GET["select"]) && $_GET["select"]=="all") {
               $sel = "checked";
            }
            if (isset($_SESSION['glpimassiveactionselected'][$data["id"]])) {
               $sel = "checked";
            }
            $first_col .= "&nbsp;<input type='checkbox' name='item[".$data["id"]."]' value='1' $sel>";
         }

         echo displaySearchItem($output_type,$first_col,$item_num,$row_num,$align);

         // Second column
         $second_col = "";
         if (!strstr($data["status"],"old_")) {
            $second_col .= "<span class='tracking_open'>".$LANG['joblist'][11]."&nbsp;:";
            if ($output_type==HTML_OUTPUT) {
               $second_col .= "<br>";
            }
            $second_col .= "&nbsp;".convDateTime($data["date"])."</span>";
         } else {
            $second_col .= "<div class='tracking_hour'>";
            $second_col .= "".$LANG['joblist'][11]."&nbsp;:";
            if ($output_type==HTML_OUTPUT) {
               $second_col .= "<br>";
            }
            $second_col .= "&nbsp;<span class='tracking_bold'>".convDateTime($data["date"])."</span><br>";
            $second_col .= "".$LANG['joblist'][12]."&nbsp;:";
            if ($output_type==HTML_OUTPUT) {
               $second_col .= "<br>";
            }
            $second_col .= "&nbsp;<span class='tracking_bold'>".convDateTime($data["closedate"]).
                           "</span><br>";
            if ($data["realtime"]>0) {
               $second_col .= $LANG['job'][20]."&nbsp;: ";
            }
            if ($output_type==HTML_OUTPUT) {
               $second_col .= "<br>";
            }
            $second_col .= "&nbsp;".Ticket::getRealtime($data["realtime"]);
            $second_col .= "</div>";
         }

         echo displaySearchItem($output_type,$second_col,$item_num,$row_num,$align." width=130");

         // Second BIS column
         $second_col = convDateTime($data["date_mod"]);
         echo displaySearchItem($output_type,$second_col,$item_num,$row_num,$align." width=90");

         // Second TER column
         if (count($_SESSION["glpiactiveentities"])>1) {
            if ($data['entityID']==0) {
               $second_col = $LANG['entity'][2];
            } else {
               $second_col = $data['entityname'];
            }
            echo displaySearchItem($output_type,$second_col,$item_num,$row_num,$align." width=100");
         }

         // Third Column
         echo displaySearchItem($output_type,"<strong>".Ticket::getPriorityName($data["priority"])."</strong>",
                              $item_num,$row_num,"$align bgcolor='$bgcolor'");

         // Fourth Column
         $fourth_col = "";
         if ($data['users_id']) {
            $userdata = getUserName($data['users_id'],2);
            $comment_display = "";
            if ($output_type==HTML_OUTPUT) {
               $comment_display  = "<a href='".$userdata["link"]."'>";
               $comment_display .= "<img alt='' src='".$CFG_GLPI["root_doc"]."/pics/aide.png' ".
                                    "onmouseout=\"cleanhide('comment_trackusers_id".$data['id']."')\" ".
                                    "onmouseover=\"cleandisplay('comment_trackusers_id".$data['id']."')\">";
               $comment_display .= "</a>";
               $comment_display .= "<span class='over_link' id='comment_trackusers_id".$data['id']."'>".
                                    $userdata["comment"]."</span>";
            }
            $fourth_col .= "<strong>".$userdata['name']."&nbsp;".$comment_display."</strong>";
         }

         if ($data["groups_id"]) {
            $fourth_col .= "<br>".$data["groupname"];
         }
         echo displaySearchItem($output_type,$fourth_col,$item_num,$row_num,$align);

         // Fifth column
         $fifth_col = "";
         if ($data["users_id_assign"]>0) {
            $userdata = getUserName($data['users_id_assign'],2);
            $comment_display = "";
            if ($output_type==HTML_OUTPUT) {
               $comment_display  = "<a href='".$userdata["link"]."'>";
               $comment_display .= "<img alt='' src='".$CFG_GLPI["root_doc"]."/pics/aide.png' ".
                                    "onmouseout=\"cleanhide('comment_trackassign".$data['id']."')\" ".
                                    "onmouseover=\"cleandisplay('comment_trackassign".$data['id']."')\">";
               $comment_display .= "</a>";
               $comment_display .= "<span class='over_link' id='comment_trackassign".$data['id']."'>".
                                    $userdata["comment"]."</span>";
            }
            $fifth_col = "<strong>".$userdata['name']."&nbsp;".$comment_display."</strong>";
         }

         if ($data["groups_id_assign"]>0) {
            if (!empty($fifth_col)) {
               $fifth_col .= "<br>";
            }
            $fifth_col .= Ticket::getAssignName($data["groups_id_assign"],'Group',1);
         }

         if ($data["suppliers_id_assign"]>0) {
            if (!empty($fifth_col)) {
               $fifth_col .= "<br>";
            }
            $fifth_col .= Ticket::getAssignName($data["suppliers_id_assign"],'Supplier',1);
         }
         echo displaySearchItem($output_type,$fifth_col,$item_num,$row_num,$align);

         // Sixth Colum
         $sixth_col = "";
         $is_deleted = false;
         if (!empty($data["itemtype"]) && $data["items_id"]>0) {
            if (class_exists($data["itemtype"])) {
               $item=new $data["itemtype"]();
               if ($item->getFromDB($data["items_id"])) {
                  $is_deleted=$item->isDeleted();

                  $sixth_col .= $item->getTypeName();

                  $sixth_col .= "<br><strong>";
                  if ($item->canView()) {
                     $sixth_col .= $item->getLink($output_type==HTML_OUTPUT);
                  } else {
                     $sixth_col .= $item->getNameID();
                  }
                  $sixth_col .= "</strong>";
               }
            }
         } else if (empty($data["itemtype"])) {
            $sixth_col=$LANG['help'][30];
         }

         echo displaySearchItem($output_type,$sixth_col,$item_num,$row_num,
                              ($is_deleted?" class='center deleted' ":$align));

         // Seventh column
         echo displaySearchItem($output_type,"<strong>".$data["catname"]."</strong>",$item_num,
                              $row_num,$align);

         // Eigth column
         $eigth_column = "<strong>".$data["name"]."</strong>&nbsp;";
         if ($output_type==HTML_OUTPUT) {
            $eigth_column .= "<img alt='' src='".$CFG_GLPI["root_doc"]."/pics/aide.png' ".
                              "onmouseout=\"cleanhide('comment_tracking".$data["id"]."')\" ".
                              "onmouseover=\"cleandisplay('comment_tracking".$data["id"]."')\" >";
            $eigth_column .= "<span class='over_link' id='comment_tracking".$data["id"]."'>".
                              nl2br($data['content'])."</span>";
         }

         // Add link
         if ($_SESSION["glpiactiveprofile"]["interface"]=="central") {
            if ($job->canViewItem()) {
               $eigth_column = "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.form.php?id=".
                                 $data["id"]."\">$eigth_column</a>";

               if ($followups && $output_type==HTML_OUTPUT) {
                  $eigth_column .= TicketFollowup::showShortForTicket($data["id"]);
               } else {
                  $eigth_column .= "&nbsp;(".$job->numberOfFollowups(haveRight("show_full_ticket",
                                                                              "1")).")";
               }
            }
         } else {
            $eigth_column = "<a href=\"".$CFG_GLPI["root_doc"]."/front/helpdesk.public.php?show=".
                              "user&amp;id=".$data["id"]."\">$eigth_column</a>";
            if ($followups && $output_type==HTML_OUTPUT) {
               $eigth_column .= TicketFollowup::showShortForTicket($data["id"]);
            } else {
               $eigth_column .= "&nbsp;(".$job->numberOfFollowups(haveRight("show_full_ticket","1")).")";
            }
         }
         echo displaySearchItem($output_type,$eigth_column,$item_num,$row_num,$align_desc."width='300'");

         // Finish Line
         echo displaySearchEndLine($output_type);

      } else {
         echo "<tr class='tab_bg_2'><td colspan='6' ><i>".$LANG['joblist'][16]."</i></td></tr>";
      }
   }

   static function showVeryShort($ID) {
      global $CFG_GLPI, $LANG;

      // Prints a job in short form
      // Should be called in a <table>-segment
      // Print links or not in case of user view
      // Make new job object and fill it from database, if success, print it
      $viewusers = haveRight("user","r");
      $job=new Ticket;
      if ($job->getFromDBwithData($ID,0)) {
         $bgcolor = $_SESSION["glpipriority_".$job->fields["priority"]];
         $rand = mt_rand();
         echo "<tr class='tab_bg_2'>";
         echo "<td class='center' bgcolor='$bgcolor' >ID : ".$job->fields["id"]."</td>";
         echo "<td class='center'>";

         if ($viewusers) {
            $userdata = getUserName($job->fields['users_id'],2);
            $comment_display  = "<a href='".$userdata["link"]."'>";
            $comment_display .= "<img alt='' src='".$CFG_GLPI["root_doc"]."/pics/aide.png' ".
                                 "onmouseout=\"cleanhide('comment_trackusers_id".$rand.$ID."')\" ".
                                 "onmouseover=\"cleandisplay('comment_trackusers_id".$rand.$ID."')\">";
            $comment_display .= "</a>";
            $comment_display .= "<span class='over_link' id='comment_trackusers_id".$rand.$ID."'>".
                                 $userdata["comment"]."</span>";

            echo "<strong>".$userdata['name']."&nbsp;".$comment_display."</strong>";
         } else {
            echo "<strong>".$job->getAuthorName()."</strong>";
         }

         if ($job->fields["groups_id"]) {
            echo "<br>".Dropdown::getDropdownName("glpi_groups",$job->fields["groups_id"]);
         }
         echo "</td>";

         if ($job->hardwaredatas && $job->hardwaredatas->canView()) {
            echo "<td class='center";
            if ($job->hardwaredatas->isDeleted()) {
               echo " tab_bg_1_2";
            }
            echo "'>";
            echo $job->hardwaredatas->getTypeName()."<br>";
            echo "<strong>".$job->hardwaredatas->getLink()."</strong>";
            echo "</td>";
         } else if ($job->hardwaredatas) {
            echo "<td class='center' >".$job->hardwaredatas->getTypeName()."<br><strong>".
                  $job->hardwaredatas->getNameID()."</strong></td>";
         } else {
            echo "<td class='center' >".$LANG['help'][30]."</td>";
         }
         echo "<td>";

         if ($_SESSION["glpiactiveprofile"]["interface"]=="central") {
            echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/ticket.form.php?id=".
                  $job->fields["id"]."\">";
         } else {
            echo "<a href=\"".$CFG_GLPI["root_doc"]."/front/helpdesk.public.php?show=user&amp;id=".
                  $job->fields["id"]."\">";
         }
         echo "<strong>".$job->fields["name"]."</strong>&nbsp;";
         echo "<img alt='".$LANG['joblist'][6]."' src='".$CFG_GLPI["root_doc"]."/pics/aide.png' ".
               "onmouseout=\"cleanhide('comment_tracking".$rand.$job->fields["id"]."')\" ".
               "onmouseover=\"cleandisplay('comment_tracking".$rand.$job->fields["id"]."')\" >";
         echo "<span class='over_link' id='comment_tracking".$rand.$job->fields["id"]."'>".
               nl2br($job->fields['content'])."</span>";
         echo "</a>&nbsp;(".$job->numberOfFollowups().")&nbsp;";
         echo "</td>";

         // Finish Line
         echo "</tr>";
      } else {
         echo "<tr class='tab_bg_2'><td colspan='6' ><i>".$LANG['joblist'][16]."</i></td></tr>";
      }
   }

   static function getRealtime($realtime) {
      global $LANG;

      $output = "";
      $hour = floor($realtime);
      if ($hour>0) {
         $output .= $hour." ".$LANG['job'][21]." ";
      }
      $output .= round((($realtime-floor($realtime))*60))." ".$LANG['job'][22];
      return $output;
   }

   static function getCommonSelect() {

      $SELECT = "";
      if (count($_SESSION["glpiactiveentities"])>1) {
         $SELECT .= ", `glpi_entities`.`completename` AS entityname,
                     `glpi_tickets`.`entities_id` AS entityID ";
      }

      return " DISTINCT `glpi_tickets`.*,
                        `glpi_ticketcategories`.`completename` AS catname,
                        `glpi_groups`.`name` AS groupname
                        $SELECT";
   }


   static function getCommonLeftJoin() {

      $FROM = "";
      if (count($_SESSION["glpiactiveentities"])>1) {
         $FROM .= " LEFT JOIN `glpi_entities` ON (`glpi_entities`.`id` = `glpi_tickets`.`entities_id`) ";
      }

      return " LEFT JOIN `glpi_groups` ON (`glpi_tickets`.`groups_id` = `glpi_groups`.`id`)
               LEFT JOIN `glpi_ticketcategories`
                  ON (`glpi_tickets`.`ticketcategories_id` = `glpi_ticketcategories`.`id`)
               $FROM";
   }

   static function showPreviewAssignAction($output) {
      global $LANG,$CFG_GLPI;

      //If ticket is assign to an object, display this information first
      if (isset($output["entities_id"]) && isset($output["items_id"]) && isset($output["itemtype"])) {

         if (class_exists($output["itemtype"])) {
            $item = new $output["itemtype"]();
            if ($item->getFromDB($output["items_id"])) {
               echo "<tr class='tab_bg_2'>";
               echo "<td>".$LANG['rulesengine'][48]."</td>";

               echo "<td>";
               echo $item->getLink(true);
               echo "</td>";
               echo "</tr>";
            }
         }

            //Clean output of unnecessary fields (already processed)
            unset($output["items_id"]);
            unset($output["itemtype"]);
      }
      unset($output["entities_id"]);
      return $output;
   }


   /**
   * Get all available types to which a ticket can be assigned
   *
   */
   static function getAllTypesForHelpdesk() {
      global $LANG, $PLUGIN_HOOKS, $CFG_GLPI;

      $types = array();

      //Types of the plugins (keep the plugin hook for right check)
      if (isset($PLUGIN_HOOKS['assign_to_ticket'])) {
         foreach ($PLUGIN_HOOKS['assign_to_ticket'] as $plugin => $value) {
            $types = doOneHook($plugin,'AssignToTicket',$types);
         }
      }

      //Types of the core (after the plugin for robustness)
      foreach($CFG_GLPI["helpdesk_types"] as $itemtype) {
         if (class_exists($itemtype)) {
            if (!isPluginItemType($itemtype) // No plugin here
               && in_array($itemtype,$_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
               $item = new $itemtype();
               $types[$itemtype] = $item->getTypeName();
            }
         }
      }
      ksort($types); // core type first... asort could be better ?

      return $types;
   }


   /**
   * Check if it's possible to assign ticket to a type (core or plugin)
   * @param $itemtype the object's type
   * @return true if ticket can be assign to this type, false if not
   */
   static function isPossibleToAssignType($itemtype) {
      global $PLUGIN_HOOKS;


      // Plugin case
      if (isPluginItemType($itemtype)){
         /// TODO maybe only check plugin of itemtype ?
         //If it's not a core's type, then check plugins
         $types = array();
         if (isset($PLUGIN_HOOKS['assign_to_ticket'])) {
            foreach ($PLUGIN_HOOKS['assign_to_ticket'] as $plugin => $value) {
               $types = doOneHook($plugin,'AssignToTicket',$types);
            }
            if (array_key_exists($itemtype,$types)) {
               return true;
            }
         }
      } else { // standard case
         if (in_array($itemtype,$_SESSION["glpiactiveprofile"]["helpdesk_item_type"])) {
            return true;
         }
      }

      return false;
   }

   static function getAssignName($ID,$itemtype,$link=0) {
      global $CFG_GLPI;

      switch ($itemtype) {
         case 'User' :
            if ($ID==0) {
               return "";
            }
            return getUserName($ID,$link);
            break;

         case 'Supplier' :
         case 'Group' :
            $item=new $itemtype();
            if ($item->getFromDB($ID)) {
               $before = "";
               $after = "";
               if ($link) {
                  return $item->getLink(1);
               }
               return $item->getNameID();
            }
            return "";
      }
   }

}
?>
