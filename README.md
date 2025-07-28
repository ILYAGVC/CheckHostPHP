# CheckHost PHP

**CheckHost PHP** is a lightweight PHP wrapper for interacting with the [check-host.net](https://check-host.net) API, allowing you to run PING, HTTP, TCP, UDP, DNS, TRACEROUTE checks from a variety of global nodes. It includes flexible country/node filtering and comprehensive result parsing.

---

## ✅ Features

- Get real-time availability data from multiple global nodes
- Supports: `ping`, `http`, `tcp`, `udp`, `dns`, `traceroute`
- Filter nodes by country code, country name, or domain
- Collect results from specific or all available nodes
- Parse and structure response data (avg/min/max ping, jitter, status, etc.)

---

## 📦 Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require ilyagvc/checkhost
```
---

## 🧱 Constructor

```php
$checkHost = new \ILYAGVC\CheckHost\CheckHost(
    array|string|null $selectedCountries = null,
    bool $except = false,
    string|null $proxy = null,
    int $timeout = 60
);
```

### Parameters:

| Parameter            | Type                      | Description                                                                                               |
| -------------------- | ------------------------- | --------------------------------------------------------------------------------------------------------- |
| `$selectedCountries` | `array`, `string`, `null` | Country name(s), ISO country code(s), or node domain(s) to include/exclude (`null` = all available nodes) |
| `$except`            | `bool`                    | If `true`, excludes the specified countries instead of including them                                     |
| `$proxy`             | `string`                  | Optional proxy for curl requests                                                                          |
| `$timeout`           | `int`                     | Request timeout (seconds) for waiting on test results                                                     |

---

## 🔧 Methods

### `setCountry(array|string|null $countries, bool $except = false): bool`

**Filters nodes based on country names, codes, or node domains.**

| Parameter    | Type                      | Description                                                                                               |
| ------------ | ------------------------- | --------------------------------------------------------------------------------------------------------- |
| `$countries` | `array`, `string`, `null` | Country name(s), ISO country code(s), or node domain(s) to include/exclude (`null` = all available nodes) |
| `$except`    | `bool`                    | If `true`, excludes the specified countries instead of including them                                     |

---

### `getNodes(): array`

**Returns the currently selected and filtered node list.**
<br>
*No parameters.*

---

### `getNodesIp(): array|false`

**Fetches the raw IP list of all available nodes from check-host.net.**
<br>
*No parameters.*

---

### `updateNodes(): bool`

**Refreshes and re-applies node filters to fetch the latest node list.**
<br>
*No parameters.*

---

### `sendRequest(string $host, string $type, int|null $maxNodes = 0): string|false`

**Sends a check request of a given type to selected nodes.**

| Parameter   | Type     | Description                                                               |
| ----------- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `$host`     | `string` | The target domain or IP to check                                                                                                                                        |
| `$type`     | `string` | Type of check: one of `ping`, `http`, `tcp`, `udp`, `dns`, `traceroute`                                                                                                 |
| `$maxNodes` | `int`    | Maximum number of nodes to use. Any value other than `0` overrides the selected nodes and uses up to the specified number of available nodes (`0` = use selected nodes) |

---

### `getResults(string $requestId): array|false`

**Fetches the result of a previously sent check request.**

| Parameter    | Type     | Description                    |
| ------------ | -------- | ------------------------------ |
| `$requestId` | `string` | ID returned by `sendRequest()` |

---

### `runCheck(string $host, string $type, int|null $maxNodes = 0): array|false`

**Combines `sendRequest()` and `getResults()` into one call.**

| Parameter   | Type     | Description                                                                                                                                                             |
| ----------- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `$host`     | `string` | The target domain or IP to check                                                                                                                                        |
| `$type`     | `string` | Type of check: one of `ping`, `http`, `tcp`, `udp`, `dns`, `traceroute`                                                                                                 |
| `$maxNodes` | `int`    | Maximum number of nodes to use. Any value other than `0` overrides the selected nodes and uses up to the specified number of available nodes (`0` = use selected nodes) |

---

### `fullCheck(string $host, int|null): array|false`

**Performs all core tests (`ping`, `http`, `tcp`, `udp`, `dns`, `traceroute`) on the given host.**

| Parameter   | Type     | Description                                                                                                                                                             |
| ----------- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `$host`     | `string` | The target domain or IP to check                                                                                                                                        |
| `$maxNodes` | `int`    | Maximum number of nodes to use. Any value other than `0` overrides the selected nodes and uses up to the specified number of available nodes (`0` = use selected nodes) |

---

### `setProxy(string $proxy): void`

**Sets or updates a proxy to be used for all cURL HTTP requests.**

| Parameter | Type     | Description                                  |
| --------- | -------- | -------------------------------------------- |
| `$proxy`  | `string` | Proxy address, e.g., `http://127.0.0.1:8080` |

---

### `setTimeout(int $seconds): void`

**Sets the timeout for all result fetching requests.**

| Parameter  | Type  | Description                 |
| ---------- | ----- | --------------------------- |
| `$seconds` | `int` | Timeout duration in seconds |

---

## 📦 Example Usage

### Ping Check

```php
<?php
use ILYAGVC\CheckHost\CheckHost;
require 'vendor/autoload.php';

$checkHost = new CheckHost();
$result = $checkHost->runCheck('https://www.google.com', 'ping', 2);
print_r($result);
```

#### Sample Output (Simplified)

```php
Array
(
    [host] => www.google.com
    [type] => ping
    [results] => Array
        (
            [Germany] => Array
                (
                    [result_summary] => Array
                        (
                            [ping]       => 4/4
                            [average_ms] => 1
                            ...
                        )
                        ...
                )
            [Iran] => ...
        )
)
```

---

### Full Check

```php
<?php
use ILYAGVC\CheckHost\CheckHost;
require 'vendor/autoload.php';

$checkHost = new CheckHost();
$result = $checkHost->fullCheck('https://www.google.com');
print_r($result);
```

#### Sample Output (Simplified)

```php
Array
(
    [Austria] => Array
        (
            [ping]       => [...]
            [http]       => [...]
            [tcp]        => [...]
            [udp]        => [...]
            [dns]        => [...]
            [traceroute] => [...]
        )
        ...
)
```

---

## 🌐 Node Filtering

Filter to only Germany and Austria nodes:

```php
$checkHost = new CheckHost(['Germany', 'AT']);
```

Exclude France:

```php
$checkHost = new CheckHost(['France'], true);
```

---

## 🔄 Proxy & Timeout

```php
$checkHost->setProxy('http://127.0.0.1:8080');
$checkHost->setTimeout(10);
```

---

## 📄 License

[MIT](LICENSE)

---

## 📬 Contact

Developed by [ILYAGVC](https://github.com/ilyagvc)
Feel free to open issues or PRs!
