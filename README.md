# CheckHost PHP

**CheckHost PHP** is a lightweight PHP wrapper for interacting with the [check-host.net](https://check-host.net) API, allowing you to run ping, HTTP, TCP, UDP, DNS, and traceroute checks from a variety of global nodes. It includes flexible country/node filtering and comprehensive result parsing.

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

| Parameter            | Type                      | Description                                                                   |
| -------------------- | ------------------------- | ----------------------------------------------------------------------------- |
| `$selectedCountries` | `array`, `string`, `null` | List of countries (by name/code or node domain) to **include** or **exclude** |
| `$except`            | `bool`                    | If true, filters **exclude** the specified countries                          |
| `$proxy`             | `string`                  | Optional proxy for curl requests                                              |
| `$timeout`           | `int`                     | Request timeout (seconds) for waiting on test results                         |

---

## 🔧 Methods

### `setCountry(array|string|null $countries, bool $except = false): bool`

Filters available nodes by countries. Returns `true` if nodes were successfully loaded.

---

### `getNodes(): array`

Returns the currently filtered node list.

---

### `getNodesIp(): array|false`

Fetches raw IP node list from `check-host.net`.

---

### `updateNodes(): bool`

Refreshes node list using previous filters.

---

### `sendRequest(string $host, string $type, int|null $maxNodes = null): string|false`

Sends a check of a specified type (`ping`, `http`, etc.) for the given host.
Returns a `request_id` to be used with `getResults`.

---

### `getResults(string $requestId): array|false`

Fetches and parses the result for a previously submitted check.

---

### `runCheck(string $host, string $type, int|null $maxNodes = null): array|false`

Shortcut for sending and retrieving a single check result (ping, http, etc.).

---

### `fullCheck(string $host, int|null): array|false`

Runs all 5 core tests (`ping`, `http`, `tcp`, `udp`, `dns`) and returns a structured result for each node.

---

### `setProxy(string $proxy): void`

Sets or updates the proxy used for all curl requests.

---

### `setTimeout(int $seconds): void`

Sets or updates the timeout for result fetching.

---

## 📦 Example Usage

### Ping Check

```php
<?php
require 'vendor/autoload.php';

$checkHost = new \ILYAGVC\CheckHost\CheckHost();
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
                    [0] => Array
                        (
                            [ping] => 4/4
                            [average_ms] => 1
                            ...
                        )
                )
            [Iran] => ...
        )
)
```

---

### Full Check

```php
<?php
require 'vendor/autoload.php';

$checkHost = new \ILYAGVC\CheckHost\CheckHost();
$result = $checkHost->fullCheck('https://www.google.com');
print_r($result);
```

#### Sample Output (Simplified)

```php
Array
(
    [Austria] => Array
        (
            [ping] => [...]
            [http] => [...]
            [tcp] => [...]
            [udp] => [...]
            [dns] => [...]
        )
)
```

---

## 🌐 Node Filtering

Filter to only Germany and Austria nodes:

```php
$checkHost = new \ILYAGVC\CheckHost\CheckHost(['Germany', 'AT']);
```

Exclude France:

```php
$checkHost = new \ILYAGVC\CheckHost\CheckHost(['France'], true);
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
