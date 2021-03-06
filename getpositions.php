<?php
/* μlogger
 *
 * Copyright(C) 2017 Bartek Fabiszewski (www.fabiszewski.net)
 *
 * This is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Library General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU Library General Public
 * License along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 */
require_once("auth.php");

$userid = ((isset($_REQUEST["userid"]) && is_numeric($_REQUEST["userid"])) ? $_REQUEST["userid"] : 0);
$trackid = ((isset($_REQUEST["trackid"]) && is_numeric($_REQUEST["trackid"])) ? $_REQUEST["trackid"] : 0);

function haversine_distance($lat1, $lon1, $lat2, $lon2) {
  $lat1 = deg2rad($lat1);
  $lon1 = deg2rad($lon1);
  $lat2 = deg2rad($lat2);
  $lon2 = deg2rad($lon2);
  $latD = $lat2 - $lat1;
  $lonD = $lon2 - $lon1;
  $bearing = 2*asin(sqrt(pow(sin($latD/2),2)+cos($lat1)*cos($lat2)*pow(sin($lonD/2),2)));
  return $bearing * 6371000;
}

if ($userid) {
  if ($trackid) {
    // get all track data
    $query = $mysqli->prepare("SELECT p.id, p.latitude, p.longitude, p.altitude, p.speed, p.bearing, p.time, p.accuracy, p.comment, u.login, t.name, t.id 
                            FROM positions p
                            LEFT JOIN users u ON (p.user_id=u.id) 
                            LEFT JOIN tracks t ON (p.track_id=t.id) 
                            WHERE p.user_id=? AND p.track_id=? 
                            ORDER BY p.time");
    $query->bind_param('ii', $userid, $trackid);
  } else {
    // get data only for latest point
    $query = $mysqli->prepare("SELECT p.id, p.latitude, p.longitude, p.altitude, p.speed, p.bearing, p.time, p.accuracy, p.comment, u.login, t.name, t.id 
                                FROM positions p
                                LEFT JOIN users u ON (p.user_id=u.id) 
                                LEFT JOIN tracks t ON (p.track_id=t.id) 
                                WHERE p.user_id=?
                                ORDER BY p.time DESC LIMIT 1");
    $query->bind_param('i', $userid);
  }
  $query->execute();
  $query->bind_result($positionid,$latitude,$longitude,$altitude,$speed,$bearing,$dateoccured,$accuracy,$comments,$username,$trackname,$trackid);

  header("Content-type: text/xml");
  $xml = new XMLWriter();
  $xml->openURI("php://output");
  $xml->startDocument("1.0");
  $xml->setIndent(true);
  $xml->startElement('root');

  while ($query->fetch()) {
    $xml->startElement("position");
    $xml->writeAttribute("id", $positionid);
      $xml->writeElement("latitude", $latitude);
      $xml->writeElement("longitude", $longitude);
      $xml->writeElement("altitude", ($altitude)?round($altitude):$altitude);
      $xml->writeElement("speed", $speed);
      $xml->writeElement("bearing", $bearing);
      $xml->writeElement("dateoccured", $dateoccured);
      $xml->writeElement("accuracy", $accuracy);
      $xml->writeElement("comments", $comments);
      $xml->writeElement("username", $username);
      $xml->writeElement("trackid", $trackid);
      $xml->writeElement("trackname", $trackname);
      $distance = (isset($prev_latitude))?haversine_distance($prev_latitude,$prev_longitude,$latitude,$longitude):0;
      $prev_latitude = $latitude;
      $prev_longitude = $longitude;
      $xml->writeElement("distance", round($distance));
      $seconds = (isset($prev_dateoccured))?(strtotime($dateoccured)-strtotime($prev_dateoccured)):0;
      $prev_dateoccured = $dateoccured;
      $xml->writeElement("seconds", $seconds);
    $xml->endElement();
  }

  $xml->endElement();
  $xml->endDocument();
  $xml->flush();

  $query->free_result();
}

$mysqli->close();
?>
