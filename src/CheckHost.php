<?php

namespace ILYAGVC\CheckHost;

use CurlHandle;

class CheckHost
{
    private array $nodes;
    private ?string $proxy;
    private string|array|null $selected_countries;
    private bool $except;
    private int $timeout;

    /**
     * @param array|string|null $selected_countries
     * @param bool $except
     * @param string|null $proxy
     * @param int $timeout
     */
    public function __construct(array|string|null $selected_countries = null, bool $except = false, string|null $proxy = null, int $timeout = 60) {
        $this->nodes = [];
        $this->setCountry($selected_countries, $except);
        $this->proxy = $proxy;
        $this->except = $except;
        $this->timeout = $timeout;
    }

    /**
     * @param CurlHandle|false $ch
     * @param bool $close
     * @return string|bool
     */
    protected function getResponse(CurlHandle|false $ch, bool $close = true): string|bool {

        if ($ch === false) {
            return false;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        if (!empty($this->proxy)) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $response = curl_exec($ch);
        if ($response === false && curl_error($ch)) {
            error_log(curl_error($ch));
        }
        if ($close) {
            curl_close($ch);
        }
        return $response;
    }

    /**
     * @param string $proxy
     * @return void
     */
    public function setProxy(string $proxy): void {
        $this->proxy = $proxy;
    }

    /**
     * @param int $timeout
     * @return void
     */
    public function setTimeout(int $timeout): void {
        $this->timeout = $timeout;
    }

    public function getNodes(): array {
        return $this->nodes;
    }

    public function updateNodes(): bool {
        return $this->setCountry($this->selected_countries, $this->except);
    }

    /**
     * @param array|string|null $countries
     * @param bool $except
     * @return bool
     */
    public function setCountry(array|string|null $countries = null, bool $except = false): bool {
        if (is_null($countries)) {
            $countries_list = [];
        }
        elseif (is_array($countries)) {
            $countries_list = array_map('strtolower', $countries);
        }
        else {
            $countries_list = [strtolower($countries)];
        }
        $this->selected_countries = $countries;

        $ch = curl_init('https://check-host.net/nodes/hosts');
        $response = json_decode($this->getResponse($ch), true);

        $nodes = $response['nodes'] ?? null;
        if (empty($nodes) || isset($nodes['error'])) {
            return false;
        }

        $this->nodes = [];
        foreach ($nodes as $node => $info) {
            $info_main = $info;

            $country_code = strtolower($info['location'][0]);
            $country_name = strtolower($info['location'][1]);

            if (is_null($countries)) {
                $match = true;
            }
            else {
                $match = (
                    in_array($country_code, $countries_list) ||
                    in_array($country_name, $countries_list) ||
                    in_array($node, $countries_list)
                );
            }

            if (($except && !$match) || (!$except && $match)) {
                $this->nodes[$node][] = [
                    'ip' => $info['ip'],
                    'domain' => $node ?? null,
                    'location' => [
                        'country_code' => strtoupper($info_main['location'][0]),
                        'country_name' => $info_main['location'][1],
                        'country_flag' => $this->getFlag($info_main['location'][0]),
                        'city' => $info_main['location'][2] ?? null,
                        'asn' => $info_main['asn']
                    ]
                ];
            }
        }

        return !empty($this->nodes);
    }

    /**
     * @param string $host_name
     * @param string $check_type
     * @param int|null $max_nodes
     * @return string|false
     */
    public function sendRequest(string $host_name, string $check_type, int|null $max_nodes = null): string|false {
        $check_type = strtolower($check_type);
        if (!in_array($check_type, ['ping', 'http', 'tcp', 'udp', 'dns', 'traceroute'])) {
            return false;
        }

        $query_array = [
            'host' => $host_name
        ];

        if (!is_null($max_nodes)) {
            $query_array['max_nodes'] = $max_nodes;
        }
        else {
            $query_array['node'] = array_keys($this->nodes);
        }

        $query = http_build_query($query_array);
        $query = preg_replace('/%5B[0-9]+%5D/', '', $query);

        $ch = curl_init("https://check-host.net/check-$check_type?$query");
        $result = json_decode($this->getResponse($ch), true);

        if (isset($result['error'])) {
            return false;
        }

        return $result['request_id'] ?? false;
    }

    /**
     * @param string $request_id
     * @return array|false
     */
    function getResults(string $request_id): array|false {
        if (empty($request_id)) {
            return false;
        }

        $ch = curl_init("https://check-host.net/check-result-extended/$request_id");

        $start_time = time();
        $result = null;

        while (time() - $start_time < $this->timeout) {
            $response = $this->getResponse($ch, false);

            if ($response === false) {
                continue;
            }

            $result = json_decode($response, true);

            if (isset($result['error']) || !is_array($result) || !is_array($result['results'])) {
                continue;
            }

            $allReady = true;
            foreach ($result['results'] as $value) {
                if ($value === null) {
                    $allReady = false;
                    break;
                }
            }

            if ($allReady) {
                break;
            }
        }

        if (empty($result['results'])) {
            return false;
        }

        $type = $result['command'];

        $final_result = [
            'host' => $result['host'] ?? null,
            'time' => time(),
            'create_time' => $result['created'] ?? null,
            'type' => $type ?? null,
        ];

        foreach ($result['results'] as $key => $value) {
            if ($type === 'ping') {
                $pings = [];

                $ok = 0;
                $not_ok = 0;
                $total = 0;
                $average = null;
                $min = null;
                $max = null;
                $jitter = null;

                if (isset($value[0]) && is_array($value[0])) {
                    foreach ($value[0] as $entry) {
                        if (is_array($entry) && $entry[0] === 'OK' && isset($entry[1])) {
                            $pings[] = round($entry[1] * 1000);
                            $ok++;
                        }
                        elseif (is_array($entry) && isset($entry[0]) && ($entry[0] !== 'OK' || !isset($entry[1]))) {
                            $pings[] = 1000;
                            $not_ok++;
                        }
                        $total++;
                    }
                }

                if ($total !== 0 && $not_ok !== $total) {

                    $average = round(count($pings) ? array_sum($pings) / count($pings) : -1);
                    $min = count($pings) ? min($pings) : -1;

                    if ($not_ok === 0) {
                        $max = count($pings) ? max($pings) : -1;
                    }
                    else {
                        $max = -1;
                    }

                    $jitters = [];
                    for ($i = 1; $i < count($pings); $i++) {
                        $jitters[] = abs($pings[$i] - $pings[$i - 1]);
                    }
                    $jitter = round(count($jitters) ? array_sum($jitters) / count($jitters) : -1);

                }
                elseif ($total !== 0) {
                    $average = -1;
                    $min = -1;
                    $max = -1;
                    $jitter = -1;
                }

                $final_result['results'][$this->nodes[$key][0]['location']['country_name']][] = [
                    'show' => !empty($value[0]),
                    'node_info' => $this->nodes[$key][0] ?? null,
                    'result_summary' => [
                        'ip' => $value[0][0][2] ?? null,
                        'ping' => "$ok/$total",
                        'average_ms' => $average ?? null,
                        'min_ms' => $min ?? null,
                        'max_ms' => $max ?? null,
                        'jitter_ms' => $jitter ?? null
                    ],
                    'result' => $value[0] ?? null
                ];

            }
            elseif ($type === 'http') {

                $final_result['results'][$this->nodes[$key][0]['location']['country_name']][] = [
                    'show' => !empty($value[0]),
                    'node_info' => $this->nodes[$key][0] ?? null,
                    'result_summary' => [
                        'ip' => $value[0][4] ?? null,
                        'ok' => (!empty($value[0])) ? (bool)$value[0][0] : null,
                        'time_s' => (!empty($value[0][4]) && !empty($value[0][3])) ? ($value[0][1] ? round($value[0][1], 2) : null) : -1,
                        'status_message' => $value[0][2] ?? null,
                        'status_code' => $value[0][3] ?? null,

                    ],
                    'result' => $value[0] ?? null
                ];

            }
            else {

                $final_result['results'][$this->nodes[$key][0]['location']['country_name']][] = [
                    'show' => !empty($value[0]),
                    'node_info' => $this->nodes[$key][0] ?? null,
                    'result' => $value[0] ?? null
                ];
            }

        }

        return $final_result;
    }

    /**
     * @param string $host_name
     * @param string $check_type
     * @param int|null $max_nodes
     * @return array|false
     */
    public function runCheck(string $host_name, string $check_type, int|null $max_nodes = null): array|false {
        return $this->getResults($this->sendRequest($host_name, $check_type, $max_nodes));
    }

    /**
     * @param string $host_name
     * @param int|null $max_nodes
     * @return array|false
     */
    public function fullCheck(string $host_name): array|false {
        $ping_id = $this->sendRequest($host_name, 'ping');
        $http_id = $this->sendRequest($host_name, 'http');
        $tcp_id = $this->sendRequest($host_name, 'tcp');
        $udp_id = $this->sendRequest($host_name, 'udp');
        $dns_id = $this->sendRequest($host_name, 'dns');

        $ping = $this->getResults($ping_id);
        $http = $this->getResults($http_id);
        $tcp = $this->getResults($tcp_id);
        $udp = $this->getResults($udp_id);
        $dns = $this->getResults($dns_id);

        if (empty($ping) && empty($http) && empty($tcp) && empty($udp) && empty($dns)) {
            return false;
        }

        $result = [];
        foreach ($this->nodes as $node_value) {
            foreach (['ping', 'http', 'tcp', 'udp', 'dns'] as $check_type) {
                $result[$node_value[0]['location']['country_name']][$check_type] =
                    ${$check_type}['results'][$node_value[0]['location']['country_name']] ?? null;
            }
        }

        return $result;
    }

    /**
     * @param string $country_code
     * @return string
     */
    private function getFlag(string $country_code): string {

        if (strlen($country_code) !== 2) return '';

        $country_code = strtoupper($country_code);
        $flag = mb_convert_encoding('&#' . (127397 + ord($country_code[0])) . ';', 'UTF-8', 'HTML-ENTITIES');
        $flag .= mb_convert_encoding('&#' . (127397 + ord($country_code[1])) . ';', 'UTF-8', 'HTML-ENTITIES');
        return $flag;
    }

    public function getNodesIp(): array|false {
        $ch = curl_init('https://check-host.net/nodes/ips');
        $response = $this->getResponse($ch);

        if ($response === false) {
            return false;
        }

        return json_decode($response, true)['nodes'] ?? false;

    }
}
