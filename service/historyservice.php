<?php

/**
 * ownCloud - Dashboard
 *
 * @author Patrick Paysant <ppaysant@linagora.com>
 * @copyright 2014 CNRS DSI
 * @license This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Dashboard\Service;

class HistoryService {

    protected $historyMapper;

    public function __construct(\OCA\Dashboard\Db\HistoryMapper $historyMapper) {
        $this->historyMapper = $historyMapper;
    }

    /**
     * Returns datas from history
     * @param string $dataType The type of data you want, 'all' (default) if you want all datas.
     * @param integer $range Number of days from today you want to get the datas
     * @param integer $wanHumanReadable If you want to show humanreable values (1) or not (0)
     * @throws OCA\Dashboard\Service\HistoryStatsUnknownDatatypeException
     * @throws OCA\Dashboard\Service\HistoryStatsInvalidRangeException
     */
    public function getHistoryStats($dataType='all', $range=30, $wantHumanReadable) {
        $statName = array(
            'date',
            'defaultQuota',
            'totalUsedSpace',
            'nbUsers',
            'nbFolders',
            'nbFiles',
            'nbShares',
            'sizePerUser',
            'foldersPerUser',
            'filesPerUser',
            'sharesPerUser',
            'sizePerFolder',
            'filesPerFolder',
            'sizePerFile',
            'stdvFilesPerUser',
            'stdvFoldersPerUser',
            'stdvSharesPerUser',
        );
        $humanReadable = array(
            'totalUsedSpace',
            'sizePerUser',
            'sizePerFolder',
            'sizePerFile',
        );

        if ($dataType !== 'all') {
            if (!in_array($dataType, $statName)) {
                throw new HistoryStatsUnknownDatatypeException();

            }
            $statName = array('date', $dataType);
        }

        if (intval($range) <= 0) {
            throw new HistoryStatsInvalidRangeException();

        }

        $arrayDatas = array();
        foreach($statName as $name) {
            $arrayDatas[$name] = array();
        }

        $history = array();
        foreach($statName as $name) {
            $history[$name] = '';
        }

        // get $range last days stats
        $datetime = new \DateTime();
        $datetime->sub(new \dateInterval('P' . (int)$range . 'D'));
        $datetime->setTime(23, 59, 59);
        $datas = $this->historyMapper->findAllFrom($datetime, $dataType);

        // create a array struct
        foreach($datas as $data) {
            foreach($statName as $name) {
                // date need special processing as we only retain day number
                if ($name == "date") {
                    list($date, $time) = explode(' ', $data->getDate());
                    list($year, $month, $day) = explode('-', $date);
                    array_push($arrayDatas['date'], $day);
                }
                // elseif ($name == "totalUsedSpace") {
                //     $tempo = $totalUsedSpace = $data->getTotalUsedSpace();
                //     array_push($arrayDatas[$name], round($totalUsedSpace, 2));
                // }
                else {
                    $func = 'get' . ucfirst($name);
                    array_push($arrayDatas[$name], (float)$data->$func());
                }
            }
        }

        foreach($statName as $name) {
            if ($wantHumanReadable and  in_array($name, $humanReadable)) {
                if (isset($arrayDatas[$name])) {
                    $hr = $this->humanreadable($arrayDatas[$name]);
                    $history[$name] = $hr['datas'];
                    $history['unit'][$name] = $hr['unit'];
                }
            }
            else {
                $history[$name] = $arrayDatas[$name];
            }
        }

        return $history;
    }

    /**
     * Adapt datas in array to be "human readable". Ex: 2147483647 => 'data':2, 'unit':'GB'
     * @param array $datas Array containing the datas
     * @return array ['datas' => array, 'unit'=> string]
     */
    protected function humanreadable($datas) {
        // No need for keys here, but may seems more explicit
        $units = array(
            0 => '',
            1 => 'B',
            2 => 'KB',
            3 => 'MB',
            4 => 'GB',
            5 => 'TB',
            6 => 'PB',
        );

        $result = array();

        // init
        $unitUsed = array();
        $greater = 0; // greater nb of type of unit used
        $unitChoice = 0; // index of this unit (that has the greater nb of values)
        foreach($units as $k => $v) {
            $unitUsed[$k] = 0;
        }

        foreach ($datas as $data) {
            $i = 1;
            $tempo = $data;
            while ($tempo / 1024 >= 1) {
                $data = $data / 1024;
                $tempo = $tempo / 1024;
                $i++;
            }

            $unitUsed[$i]++;
            if ($unitUsed[$i] > $greater) {
                $greater = $unitUsed[$i];
                $unitChoice = $i;
            }

            array_push($result, round($data, 2));
        }

        return array(
            'datas' => $result,
            'unit' => $units[$unitChoice],
        );
    }
}