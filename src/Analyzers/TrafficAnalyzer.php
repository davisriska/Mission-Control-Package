<?php

namespace Grafite\MissionControl\Analyzers;

use Exception;
use Carbon\Carbon;
use Kassner\LogParser\LogParser;

class TrafficAnalyzer
{
    public $parser;

    public $fileName;

    public function analyze($log, $format)
    {
        if (is_null($format)) {
            $format = '%a %l %u %t "%m %U %H" %>s %O "%{Referer}i" \"%{User-Agent}i"';
        }

        $this->parser = new LogParser($format);
        $this->fileName = $log;

        $now = Carbon::now()->timestamp;
        $then = Carbon::now()->subSeconds(300)->timestamp;

        $logCollection = $this->getLogCollection($now, $then);

        $stats = $this->processLogStats($logCollection);

        return $stats;
    }

    /**
     * Get the log collection based on time
     *
     * @param  int $now
     * @param  int $then
     *
     * @return Illuminate\Support\Collection
     */
    public function getLogCollection($now, $then)
    {
        if (!file_exists($this->fileName)) {
            throw new Exception("Access log file is not present - no traffic to report.", 1);
        }

        $collection = [];
        $lines = file($this->fileName, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $collection[] = $this->parser->parse($line);
        }

        $logs = collect($collection);

        return $logs->where('stamp', '<=', $now)
            ->where('stamp', '>=', $then);
    }

    /**
     * Process the log stats
     *
     * @param  Collection $collection
     *
     * @return array
     */
    public function processLogStats($collection)
    {
        $validLogs = $collection->filter(function ($line) {
            return $this->validateLine($line);
        });

        $sentBytes = $validLogs->pluck('sentBytes');

        $stats = [
            'hits' => $validLogs->count(),
            'total_data_sent' => $sentBytes->sum(),
            'most_common_method' => $this->sortByField('requestMethod', $validLogs),
            'most_common_url' => $this->sortByField('URL', $validLogs),
            'most_common_user_agent' => $this->sortByField('HeaderUserAgent', $validLogs),
        ];

        return $stats;
    }

    /**
     * Sort data by field
     *
     * @param  string $field
     * @param  Collection $collection
     *
     * @return Collection
     */
    public function sortByField($field, $collection)
    {
        if ($collection->isEmpty()) {
            return 'N/A';
        }

        return $collection->groupBy($field)->sortByDesc(function ($logs) {
            return count($logs);
        })->first()->pluck($field)->first();
    }

    /**
     * Line object from logs
     *
     * @param  object $line
     *
     * @return bool
     */
    public function validateLine($line)
    {
        $invalidExtensions = [
            '.jpg',
            '.js',
            '.css',
            '.sass',
            '.scss',
            '.png',
            '.svg',
            '.ico',
            '.jpeg',
            '.gif',
            '.mp4',
        ];

        foreach ($invalidExtensions as $needle) {
            if (stristr($line->URL, $needle)) {
                return false;
            }
        }

        if ($line->HeaderUserAgent === 'MissionControlAgent') {
            return false;
        }

        return true;
    }
}
