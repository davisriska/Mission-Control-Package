<?php

namespace Grafite\MissionControl;

use Exception;
use Grafite\MissionControl\Analyzers\TrafficAnalyzer;
use Grafite\MissionControl\BaseService;

class TrafficService extends BaseService
{
    public $token;

    public $curl;

    public function __construct($token = null)
    {
        parent::__construct();

        $this->token = $token;
        $this->service = new TrafficAnalyzer;
        $this->missionControlUrl = $this->missionControlDomain('traffic');
    }

    /**
     * Send the exception to Mission control.
     *
     * @param Exeption $exception
     *
     * @return bool
     */
    public function sendTraffic($log, $format)
    {
        $headers = [
            'token' => $this->token,
        ];

        if (is_null($this->token)) {
            throw new Exception("Missing token", 1);
        }

        $query = $this->getTraffic($log, $format);

        $response = $this->curl::post($this->missionControlUrl, $headers, $query);

        if ($response->code != 200) {
            $this->error('Unable to message Mission Control, please confirm your token');
        }

        return true;
    }

    /**
     * Collect data and set report details.
     *
     * @return array
     */
    public function getTraffic($log, $format)
    {
        return $this->service->analyze($log, $format);
    }
}
