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
  
  Copyright 2016 Roberto Guido <bob@linux.it>
*/

function initCurl() {
  $url = 'http://gttweb.5t.torino.it/gtt/it/trasporto/arrivi-ricerca.jsp';
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
  $rows = $xpath->query("//table[@class='generic_table']/tr");
  $data = [];

  foreach ($rows as $row) {
    $cells = $row->getElementsByTagName('td');
    if ($cells->length == 0)
      continue;

    $r = (object)[
      'line' => trim($cells->item(0)->getElementsByTagName('a')->item(0)->nodeValue),
      'hour' => trim($cells->item(1)->nodeValue),
      'realtime' => $cells->item(2)->nodeValue == 'previsione in tempo reale' ? 'true' : 'false',
      'direction' => trim($cells->item(3)->nodeValue)
    ];
    
    $data[] = $r;
  }
  
  return $data;
}

function probeStop($stop) {
  /*
    La prima chiamata è per inizializzare/rinnovare il cookie, in funzione di
    quello viene aperta una nuova sessione e, quando la successiva POST produrrà
    un redirect, suddetto cookie sarà ispezionato per validare la richiesta
  */

  $ch = initCurl();
  curl_exec($ch);
  curl_close($ch);

  /*
    Giusto per sviare eventuali controlli sugli accessi...
  */
  sleep(2);

  /*
    Qui effettuo una POST come se stessi manualmente interrogando il form sul
    sito
  */
  $fields = array(
	  'shortName' => $stop,
	  'ore' => date('G'),
	  'minuti' => date('i'),
	  'giorno' => date('d'),
	  'mese' => date('m'),
	  'anno' => date('Y'),
	  urlencode('stoppingPointCtl:getTransits') => 'Invia'
  );

  $fields_string = '';
  foreach($fields as $key=>$value)
    $fields_string .= $key . '=' . $value . '&';
  rtrim($fields_string, '&');

  $ch = initCurl();
  curl_setopt($ch,CURLOPT_POST, count($fields));
  curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

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

