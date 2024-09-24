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

class GPA {
    private $db;

    private function createDatabase($db_path) {
        $db = new PDO('sqlite:' . $db_path);
        $db->query('CREATE TABLE stop_ids (stop integer, identifier varchar(30), date datetime)');
        $db->query('CREATE TABLE stops (stop integer, line integer, hour varchar(10), realtime boolean, date datetime)');
        return $db;
    }

    public function __construct() {
        $db_path = 'gtt.db';

        if (file_exists($db_path) == false) {
            $this->db = $this->createDatabase($db_path);
        }
        else {
            $this->db = new PDO('sqlite:' . $db_path);
        }
    }

    private function doCurl($body) {
        $url = 'https://plan.muoversiatorino.it/otp/routers/mato/index/graphql';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result);
    }

    private function actualStopID($stop) {
        $id = null;
        $now = Carbon::now();

        $query = sprintf("SELECT identifier FROM stop_ids WHERE stop = %d AND date > '%s'", $stop, $now->copy()->subMinutes(30)->format('Y-m-d H:i:s'));
        $res = $this->db->query($query);

        while ($row = $res->fetch()) {
            $id = $row['identifier'];
            break;
        }

        if (is_null($id)) {
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

            $result = $this->doCurl($request);
            $id = $result->data->stop->id;

            $this->db->query(sprintf("DELETE FROM stop_ids WHERE stop = %d", $stop));
            $query = sprintf("INSERT INTO stop_ids (stop, identifier, date) VALUES (%d, '%s', '%s')", $stop, $id, $now->format('Y-m-d H:i:s'));
            $this->db->query($query);
        }

        return $id;
    }

    private function probeStop($stop) {
        $id = $this->actualStopID($stop);

        /*
            Pesco i dati per la prossima ora
        */
        $offset = 60 * 60;

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

        $result = $this->doCurl($request);
        $ret = [];

        foreach($result->data->node as $prop => $data) {
            if (str_starts_with($prop, '_stoptimes')) {
                foreach($data as $row) {
                    $ret[] = (object) [
                        'line' => $row->trip->pattern->route->shortName,
                        /*
                            L'orario di arrivo è espresso in numero di secondi a
                            partire dall'inizio della giornata.
                            Una sorta di Unix time minimale...
                        */
                        'hour' => Carbon::today()->addSeconds($row->realtimeDeparture)->setTimezone('Europe/Rome')->format('H:i:s'),
                        'realtime' => ($row->realtimeState == 'UPDATED'),
                    ];
                }
            }
        }

        return $ret;
    }

    public function askStop($stop) {
        $fetch = $this->probeStop($stop);
        if ($fetch == null) {
            return [];
        }

        $this->db->query(sprintf("DELETE FROM stops WHERE stop = %d", $stop));
        foreach($fetch as $f) {
            $query = sprintf("INSERT INTO stops (stop, line, hour, realtime, date) VALUES (%d, %d, '%s', %d, datetime('now'))", $stop, $f->line, $f->hour, $f->realtime ? 1 : 0);
            $this->db->query($query);
        }

        $ret = $fetch;
        return $ret;
    }
}

$gpa = new GPA();
header('Content-Type: application/json');

$stop = $_GET['stop'];
if (is_numeric($stop)) {
    echo json_encode($gpa->askStop($stop));
}
else {
    echo "[]";
}
