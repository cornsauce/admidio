<?php
/******************************************************************************
 * Funktionen des Benutzers speichern
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id: Funktionen der uebergebenen ID aendern
 * url:     URL auf die danach weitergeleitet wird
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/
require("../../../adm_config/config.php");
require("../../system/function.php");
require("../../system/date.php");
require("../../system/session_check_login.php");

// nur Webmaster & Moderatoren duerfen Rollen zuweisen
if(!isModerator() && !isGroupLeader() && !editUser())
 {
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
 }

if(isModerator())
{
   // Alle Rollen der Gruppierung auflisten
   $sql    = "SELECT ar_id FROM adm_rolle
               WHERE ar_ag_shortname = '$g_organization'
                 AND ar_valid        = 1
               ORDER BY ar_funktion";
}
elseif(isGroupLeader())
{
   // Alle Rollen auflisten, bei denen das Mitglied Leiter ist
   $sql    = "SELECT ar_id
                FROM adm_mitglieder, adm_rolle
               WHERE am_au_id  = $g_user_id
                 AND am_valid  = 1
                 AND am_leiter = 1
                 AND ar_id     = am_ar_id
                 AND ar_ag_shortname = '$g_organization'
                 AND ar_valid        = 1
                 AND ar_r_locked     = 0
               ORDER BY ar_funktion";
}
elseif(editUser())
{
   // Alle Rollen auflisten, die keinen Moderatorenstatus haben
   $sql    = "SELECT ar_id FROM adm_rolle
               WHERE ar_ag_shortname = '$g_organization'
                 AND ar_valid        = 1
                 AND ar_r_moderation = 0
                 AND ar_r_locked     = 0
               ORDER BY ar_funktion";
}
$result_rolle = mysql_query($sql, $g_adm_con);
db_error($result_rolle);

$count_assigned = 0;
$i     = 0;
$value = reset($_POST);
$key   = key($_POST);

while($row = mysql_fetch_object($result_rolle))
{
   if($key == "fkt-$i")
   {
      $function = 1;
      $value    = next($_POST);
      $key      = key($_POST);
   }
   else
      $function = 0;

   if($key == "leiter-$i")
   {
      $leiter   = 1;
      $value    = next($_POST);
      $key      = key($_POST);
   }
   else
      $leiter   = 0;

   $sql    = "SELECT * FROM adm_mitglieder, adm_rolle
               WHERE am_ar_id = $row->ar_id
                 AND am_au_id = {0}
                 AND am_ar_id = ar_id ";
   $sql    = prepareSQL($sql, array($_GET['user_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);
   
   $user_found = mysql_num_rows($result);

   if($user_found > 0)
   {
      // neue Mitgliederdaten zurueckschreiben
      if($function == 1)
      {
         $sql = "UPDATE adm_mitglieder SET am_valid  = 1
                                          , am_ende   = '0000-00-00'
                                          , am_leiter = $leiter
                  WHERE am_ar_id = $row->ar_id
                    AND am_au_id = {0}";
         $count_assigned++;
      }
      else
      {
         $sql = "UPDATE adm_mitglieder SET am_valid  = 0
                                          , am_ende   = NOW()
                                          , am_leiter = $leiter
                  WHERE am_ar_id = $row->ar_id
                    AND am_au_id = {0}";
      }
   }
   else
   {
      // neue Mitgliederdaten einfuegen, aber nur, wenn auch ein Haeckchen da ist
      if($function == 1)
      {
         $sql = "INSERT INTO adm_mitglieder (am_ar_id, am_au_id, am_start, am_valid, am_leiter)
                 VALUES ($row->ar_id, {0}, NOW(), 1, $leiter) ";
         $count_assigned++;
      }
   }
   $sql    = prepareSQL($sql, array($_GET['user_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   $i++;
}

if($_GET['new_user'] == 1 && $count_assigned == 0)
{
   // Neuem User wurden keine Rollen zugewiesen
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=norolle";
   header($location);
   exit();
}

if($_GET['popup'] == 1)
{
   echo "
   <?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?". ">
   <!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 TRANSITIONAL//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
   <html xmlns=\"http://www.w3.org/1999/xhtml\">
   <head>
      <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->
      <title>Funktionen zuordnen</title>
      <meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\" />
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\" />
      
      <!--[if gte IE 5.5000]>
      <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->
   </head>

   <body>
      <div align=\"center\"><br />
         <div class=\"groupBox\" align=\"left\" style=\"padding: 10px\">
            <p>Die &Auml;nderungen wurden erfolgreich gespeichert.</p>
            <p>Bitte denk daran, das Profil im Browser neu zu laden,
            damit die ge&auml;nderten Rollen angezeigt werden.</p>
         </div>
         <div style=\"padding-top: 10px;\" align=\"center\">
            <button name=\"schliessen\" type=\"button\" value=\"schliessen\" onclick=\"window.close()\">
            <img src=\"$g_root_path/adm_program/images/error.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\">
            &nbsp;Schlie&szlig;en</button>
         </div>
      </div>
   </body>
   </html>";
}
else
{
   // zur Ausgangsseite zurueck
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=save&url=". $_GET['url']. "&timer=2000";
   header($location);
   exit();
}