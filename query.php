<?php

/*
  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
  
  Copyright 2017 Roberto Guido <bob@linux.it>
*/

function random_string($length) {
  $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
  $tot = strlen($characters);
  $ret = '';

  for ($i = 0; $i < $length; $i++)
    $ret .= $characters[rand(0, $tot - 1)];

  return $ret;
}

function initCurl($stop) {
  /*
    Non è chiaro se il primo parametro, la stringa randomica di 8 caratteri,
    serva a qualcosa, ma nel dubbio meglio metterla...
  */
  $url = sprintf('http://www.5t.torino.it/5t/trasporto/arrival-times-byline.jsp?%s&action=getTransitsByLine&shortName=%s&routeCallback=lineBranchCtrl.getLineBranch&oreMinuti=%s%%3A%s&gma=%s%%2F%s%%2F%s',
    random_string(8),
    $stop,
    date('H'),
    date('i'),
    date('d'),
    date('m'),
    date('y')
  );
  $ch = curl_init();

  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0');
  curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
  curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  return $ch;
}

function parsePage($page) {
  $dom = new DOMDocument('1.0', 'UTF-8');
  $dom->loadHTML($page, LIBXML_NOERROR);
  $xpath = new DOMXpath($dom);
  $rows = $xpath->query("//table/tr");
  $data = [];

  foreach ($rows as $row) {
    $cells = $row->getElementsByTagName('td');
    if ($cells->length == 0)
      continue;

    $line = trim($cells->item(0)->getElementsByTagName('a')->item(0)->nodeValue);

    for($i = 1; $i < $cells->length; $i++) {
      $r = (object)[
        'line' => $line,
        'hour' => trim($cells->item($i)->nodeValue),
        'realtime' => ($cells->item($i)->getElementsByTagName('i')->length != 0) ? 'true' : 'false',
        'direction' => '???'
      ];

      $data[] = $r;
    }
  }
  
  return $data;
}

function probeStop($stop) {
  $ch = initCurl($stop);
  $result = curl_exec($ch);
  if ($result === false)
    return null;

  curl_close($ch);
  return parsePage($result);
}

function createDatabase($db_path) {
  $db = new PDO('sqlite:' . $db_path);
  $db->query('CREATE TABLE stops (stop integer, line integer, hour varchar(10), realtime boolean, direction varchar(255), date datetime)');
  return $db;
}

function askStop($stop) {
  $db_path = 'gtt.db';
  
  if (file_exists($db_path) == false)
    $db = createDatabase($db_path);
  else
    $db = new PDO('sqlite:' . $db_path);

  /*
    Cerco i risultati degli ultimi 5 minuti
  */
  $query = sprintf("SELECT * FROM stops WHERE stop = %d AND strftime('%%s', 'now') - strftime('%%s', date) < 300", $stop);
  $data = $db->query($query);
  $ret = [];
  
  while($r = $data->fetchObject()) {
    $ret[] = (object) [
      'line' => $r->line,
      'hour' => $r->hour,
      'realtime' => $r->realtime ? 'true' : 'false',
      'direction' => $r->direction
    ];
  }
  
  if (empty($ret)) {
    $fetch = probeStop($stop);
    if ($fetch == null)
      return [];

    $db->query(sprintf("DELETE FROM stops WHERE stop = %d", $stop));
    foreach($fetch as $f) {
      $query = sprintf("INSERT INTO stops (stop, line, hour, realtime, direction, date) VALUES (%d, %d, '%s', %d, '%s', datetime('now'))", $stop, $f->line, $f->hour, $f->realtime ? 1 : 0, $f->direction);
      $db->query($query);
    }
    
    $ret = $fetch;
  }
  
  return $ret;
}

header('Content-Type: application/json');

$stop = $_GET['stop'];
if (is_numeric($stop))
  echo json_encode(askStop($stop));
else
  echo "[]";

