<?php

use DI\Container;
use Hive\PhpLib\Hive\Condenser as HiveCondenser;
use Hive\PhpLib\HiveEngine\Account as HeAccount;
use Hive\PhpLib\NetStat;
use PhpPkg\Config\ConfigBox;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/../vendor/autoload.php';

/* Define the config array in const */
const APICONFIG = [
    "debug" => false,
    "disableSsl" => false,
    "heNode" => "api.hive-engine.com/rpc",
    "hiveNode" => "anyx.io",
    "throwExceptions" => false
];

// Create Container
$container = new Container();
AppFactory::setContainer($container);

// If DB config file not exist, create it from sample file
$confDir =  __DIR__ . '/../config/';
if ((file_exists($confDir . 'db.sample.json')) && (!file_exists($confDir . 'db.json'))) {
    copy($confDir . 'db.sample.json', $confDir . 'db.json');
}

if ((file_exists($confDir . 'config.sample.json')) && (!file_exists($confDir . 'config.json'))) {
    copy($confDir . 'config.sample.json', $confDir . 'config.json');
}

// Set DB config in container
$container->set('db', function () {
    $dbConf = new ConfigBox();
    $dbConf = $dbConf->loadJsonFile(__DIR__ . '/../config/db.json');
    return $dbConf->getData();
});

// Set Hive & HiveEngine config in APICONFIG const
$container->set('apiConfig', function () {
    $hiveConf = new ConfigBox();
    $hiveConf = $hiveConf->loadJsonFile(__DIR__ . '/../config/config.json');
    return $hiveConf->getData();
});

// Set Twig template engine for view in Container
$container->set('view', function () {
    return Twig::create(
        __DIR__ . '/../resources/views',
        ['cache' => false]
    );
});

// Create App
$app = AppFactory::create();

// Add Twig-View Middleware
$app->add(TwigMiddleware::createFromContainer($app));

/**
 * Index: This route is to display the index page.
 */
$app->get('/', function ($request, $response) {
    $apiConfig = $this->get('apiConfig');
    $netstat = new NetStat($apiConfig);

    $heNode = $netstat->getEngineBestNode();
    if ($heNode['online'] === true) {
        $configFile = __DIR__ . '/../config/config.json';
        $config = file_get_contents($configFile);
        $array = json_decode($config, true);
        if ($heNode['url'] != $array['heNode']) {
            $array['heNode'] = $heNode['url'];
            $json = json_encode($array, JSON_PRETTY_PRINT);
            file_put_contents($configFile, $json);
        }
    }
    $hivesql = $netstat->getHiveSql();

    return $this->get('view')->render($response, 'index.html', [
        "heNode" => $heNode,
        "hivesql" => $hivesql
    ]);
})->setName('index');

/**
 * Account: This route get delegatees from the specified account.
 */
$app->get('/account/{name}', function ($request, $response, $args) {
    $account = $args['name'];

    /* Get Delegatees with HiveSQL query */
    $config = $this->get('db');
    $connectInfo = [
        "Database" => $config['dbName'],
        "UID" => $config['dbUser'],
        "PWD" => $config['dbPasswd'],
        "Encrypt" => true,
        "TrustServerCertificate" => true
    ];

    $conn = sqlsrv_connect($config['dbServer'], $connectInfo);

    if (!$conn) {
        $payload = "Connection error";
        die(print_r(sqlsrv_errors(), true));
    } else {
        $query = "SELECT delegator, vests FROM Delegations WHERE delegatee = ? ORDER BY vests DESC";
        $statement = sqlsrv_query($conn, $query, array($account));
    }

    if (!$statement) {
        $payload = "Query error";
        die(print_r(sqlsrv_errors(), true));
    } else {
        $arr = [];
        while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
            $arr[] = [
                "account" => $row['delegator'],
                "vests" => $row['vests']
            ];
        }
    }

    $api = new HiveCondenser($this->get('apiConfig'));
    $globalProps = json_encode($api->getDynamicGlobalProperties(), JSON_PRETTY_PRINT);

    /* Convert VESTS to HP for each account and create an array with data */
    $bcVars = json_decode($globalProps, true);
    $vests = [];
    $vests['tvfh'] = (float) $bcVars['total_vesting_fund_hive'];
    $vests['tvs'] = (float) $bcVars['total_vesting_shares'];
    $totalVests = $vests['tvfh'] / $vests['tvs'];
    $accData = [];
    foreach ($arr as $acc) {
        $hp = round((float) $acc['vests'] * $totalVests, 3);
        $accData[] = [
            "account" => $acc['account'],
            "hp" => $hp
        ];
    }

    /* Convert data array in JSON format and send to response */
    $payload = json_encode($accData, JSON_PRETTY_PRINT);
    $response->getBody()->write($payload);

    return $response->withHeader('Content-Type', 'application/json');
})->setName('account');

/**
 * Tokens: This route get all available Hive-Engine tokens from specified sender account.
 */
$app->get('/tokens/{name}', function ($request, $response, $args) {
    $account = $args['name'];

    function compareByName(array $a, array $b)
    {
        return strcmp($a["symbol"], $b["symbol"]);
    }

    $heApi = new HeAccount($this->get('apiConfig'));
    $tokens = $heApi->getAccountBalance($account);
    usort ($tokens, 'compareByName');
    $tokens = array_filter($tokens, function($value) {
        return $value['balance'] != "0";
    });
    $payload = json_encode($tokens, JSON_PRETTY_PRINT);

    $response->getBody()->write($payload);

    return $response->withHeader('Content-Type', 'application/json');
})->setName('tokens');

// Run app
$app->run();
