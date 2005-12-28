<?php
/******************************************************************************
 * RSS - Feed fuer Termine
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 naechsten Termine
 *
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
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

require("../../system/common.php");
require("../../system/session_check.php");
require("../../system/bbcode.php");
require("../../system/rss_class.php");

// Nachschauen ob RSS ueberhaupt aktiviert ist...
if($g_current_organization->enable_rss != 1)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?url=home&err_code=rss_disabled";
   header($location);
   exit();
}

// Nachschauen ob BB-Code aktiviert ist...
if($g_current_organization->bbcode == 1)
{
   //BB-Parser initialisieren
   $bbcode = new ubbParser();
}

// alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS. "
         WHERE org_shortname = '$g_organization'
            OR org_org_id_parent    = '$g_organization' ";
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$organizations = "";
$i             = 0;

while($row = mysql_fetch_object($result))
   {
      if($i > 0) $organizations = $organizations. ", ";

      if($row->org_shortname == $g_organization)
         $organizations = $organizations. "'$row->org_org_id_parent'";
      else
         $organizations = $organizations. "'$row->org_shortname'";

      $i++;
   }




// aktuelle Termine aus DB holen die zur Orga passen
$sql = "SELECT * FROM ". TBL_DATES. "
                     WHERE (  dat_org_shortname = '$g_organization'
                        OR (   dat_global   = 1
                           AND dat_org_shortname IN ($organizations) ))
                       AND (  dat_begin >= sysdate()
                           OR dat_end >= sysdate() )
                     ORDER BY dat_begin ASC
                     LIMIT 10 ";

      $result = mysql_query($sql, $g_adm_con);
      db_error($result);



// ab hier wird der RSS-Feed zusammengestellt

// Ein RSSfeed-Objekt erstellen
$rss=new RSSfeed("http://$g_current_organization->homepage","$g_current_organization->longname - Termine","Die 10 naechsten Termine");

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while($row = mysql_fetch_object($result))
      {
        // Den Autor des Termins ermitteln
        $sql     = "SELECT * FROM ". TBL_USERS. " WHERE usr_id = $row->dat_usr_id";
        $result2 = mysql_query($sql, $g_adm_con);
        db_error($result2);
        $user = mysql_fetch_object($result2);

        // Die Attribute fuer das Item zusammenstellen
        $title			= mysqldatetime("d.m.y", $row->dat_begin). " ". $row->dat_headline;

        $link			= "$g_root_path/adm_program/modules/dates/dates.php?id=". $row->dat_id;

        $description 	= "<b>$row->dat_headline</b> <br />". mysqldatetime("d.m.y", $row->dat_begin);

        if (mysqldatetime("h:i", $row->dat_begin) != "00:00")
               {
                  $description =  $description. " um ".mysqldatetime("h:i", $row->dat_begin). " Uhr";
               }

        if($row->dat_begin != $row->dat_end)
               {
                  $description =  $description. "<br /> bis <br />";

                  if(mysqldatetime("d.m.y", $row->dat_begin) != mysqldatetime("d.m.y", $row->dat_end))
                  {
                     $description = $description. mysqldatetime("d.m.y", $row->dat_end);

                     if (mysqldatetime("h:i", $row->dat_end) != "00:00")
                        $description = $description. " um ";
                  }

                  if (mysqldatetime("h:i", $row->dat_end) != "00:00")
                     $description = $description. mysqldatetime("h:i", $row->dat_end). " Uhr";
               }

        if ($row->dat_location != "")
               {
                  $description = $description. "<br /><br />Treffpunkt:&nbsp;". strSpecialChars2Html($row->dat_location);
               }

        if($g_current_organization->bbcode == 1)
        {
        	  $description = $description. "<br /><br />". strSpecialChars2Html($bbcode->parse($row->dat_description));
        }
        else
        {
           $description = $description. "<br /><br />". nl2br(strSpecialChars2Html($row->dat_description));
        }

        $description = $description. "<br /><br /><a href=\"$link\">Link auf $g_current_organization->homepage</a>";
        $description = $description. "<br /><br /><i>Angelegt von ". strSpecialChars2Html($user->usr_first_name). " ". strSpecialChars2Html($user->usr_last_name);
        $description = $description. " am ". mysqldatetime("d.m.y h:i", $row->dat_timestamp). "</i>";




        $pubDate		= date('r',strtotime($row->dat_timestamp));



		// Item hinzufuegen
		$rss->add_Item($title, $description, $pubDate, $link);

      }


// jetzt nur noch den Feed generieren lassen
$rss->build_feed();

?>