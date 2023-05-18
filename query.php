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

    Copyright 2023 Roberto Guido <bob@linux.it>
*/

require 'vendor/autoload.php';
use Carbon\Carbon;

function doCurl($body) {
    $url = 'https://plan.muoversiatorino.it/otp/routers/mato/index/graphql';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $result = curl_exec($ch);
    return json_decode($result);
}

function probeStop($stop) {
    /*
        Per interrogare l'endpoint effettivo con i passaggi, ho bisogno dell'ID
        che identifica la fermata. Codificato con un hash di natura non meglio
        identificata, e probabilmente mutevole nel tempo, dunque lo pesco in
        modo esplicito con una chiamata ad-hoc
    */
    $request = '{
    	"id": "q01",
    	"query": "query StopRoutes($id_0:String!,$startTime_1:Long!,$timeRange_2:Int!,$numberOfDepartures_3:Int!) {stop(id:$id_0) {id,...F2}} fragment F0 on Alert {id,alertDescriptionText,alertHash,alertHeaderText,alertSeverityLevel,alertUrl,effectiveEndDate,effectiveStartDate,alertDescriptionTextTranslations {language,text},alertHeaderTextTranslations {language,text},alertUrlTranslations {language,text}} fragment F1 on Route {alerts {trip {pattern {code,id},id},id,...F0},id} fragment F2 on Stop {_stoptimesWithoutPatterns4nTcNn:stoptimesWithoutPatterns(startTime:$startTime_1,timeRange:$timeRange_2,numberOfDepartures:$numberOfDepartures_3,omitCanceled:false) {realtimeState,trip {pattern {code,id},route {gtfsId,shortName,longName,mode,color,id,...F1},id}},id}",
    	"variables": {
    		"id_0": "gtt:' . $stop . '",
    		"startTime_1": ' . time() . ',
    		"timeRange_2": 900,
    		"numberOfDepartures_3": 100
    	}
    }';

    $result = doCurl($request);
    $id = $result->data->stop->id;

    /*
        Pesco i dati per le prossime 2 ore
    */
    $offset = 60 * 60 * 2;

    /*
        Qui interrogo l'endpoint reale, passando l'ID della fermata
        precedentemente individuato
    */
    $request = '{
    	"id": "q02",
    	"query": "query StopPageContentContainer_StopRelayQL($id_0:ID!,$startTime_1:Long!,$timeRange_2:Int!,$numberOfDepartures_3:Int!) {node(id:$id_0) {...F2}} fragment F0 on Route {alerts {alertSeverityLevel,effectiveEndDate,effectiveStartDate,trip {pattern {code,id},id},id},id} fragment F1 on Stoptime {realtimeState,realtimeDeparture,scheduledDeparture,realtimeArrival,scheduledArrival,realtime,trip {pattern {route {shortName,id,...F0},id},id}} fragment F2 on Stop {_stoptimesWithoutPatterns1WnWVl:stoptimesWithoutPatterns(startTime:$startTime_1,timeRange:$timeRange_2,numberOfDepartures:$numberOfDepartures_3,omitCanceled:false) {...F1},id}",
    	"variables": {
    		"id_0": "' . $id . '",
    		"startTime_1": "' . time() . '",
    		"timeRange_2": ' . $offset . ',
    		"numberOfDepartures_3": 100
    	}
    }';

    $result = doCurl($request);
    $ret = [];

    foreach($result->data->node as $prop => $data) {
    	if (str_starts_with($prop, '_stoptimes')) {
    		foreach($data as $row) {
                $ret[] = (object) [
                    'line' => $row->trip->pattern->route->shortName,
                    'hour' => Carbon::today()->addSeconds($row->realtimeDeparture)->setTimezone('Europe/Rome')->format('H:i:s'),
                    'realtime' => ($row->realtimeState == 'UPDATED'),
                ];
    		}
    	}
    }

    return $ret;
}

function createDatabase($db_path) {
    $db = new PDO('sqlite:' . $db_path);
    $db->query('CREATE TABLE stops (stop integer, line integer, hour varchar(10), realtime boolean, date datetime)');
    return $db;
}

function askStop($stop) {
    $db_path = 'gtt.db';

    if (file_exists($db_path) == false) {
        $db = createDatabase($db_path);
    }
    else {
        $db = new PDO('sqlite:' . $db_path);
    }

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
        ];
    }

    if (empty($ret)) {
        $fetch = probeStop($stop);
        if ($fetch == null) {
            return [];
        }

        $db->query(sprintf("DELETE FROM stops WHERE stop = %d", $stop));
        foreach($fetch as $f) {
            $query = sprintf("INSERT INTO stops (stop, line, hour, realtime, date) VALUES (%d, %d, '%s', %d, datetime('now'))", $stop, $f->line, $f->hour, $f->realtime ? 1 : 0);
            $db->query($query);
        }

        $ret = $fetch;
    }

    return $ret;
}

header('Content-Type: application/json');

$stop = $_GET['stop'];
if (is_numeric($stop)) {
    echo json_encode(askStop($stop));
}
else {
    echo "[]";
}
