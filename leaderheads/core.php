<?php
    // --------------------------------------------------------------------------------------------------------------------- //
    // This is the core file where all queries are executed
    // These are the only settings in the file. You need to set up the correct URL's for the linked files.
    
    define('config', 'config/config.php');
    if (!defined('spyc')) define("spyc", "libs/spyc.php");


    define('skull_url', 'https://minotar.net/avatar/{name}');
    // Do not edit anything under this comment if you don't know what you're doing
    // --------------------------------------------------------------------------------------------------------------------- //
    if(!include_once(constant('config'))) {
        echo "Could not find config file: " . constant('config') . "<br>";
        echo "Please check your settings";
        return;
    }
    ini_set('display_errors', 1);
    error_reporting(0);
    date_default_timezone_set(constant('timezone'));
    $timezone = new DateTimeZone(constant('timezone'));
    if(isset($_GET["query"])) {
        header('Content-Type: application/json');
        switch($_GET["query"]) {
            case "name": {
                if(isset($_GET["value"])) echo(json_encode(getNames($_GET["value"])));
                break;
            }
            case "playercount": {
                echo(json_encode(getPlayerCount()));
                break;
            }
            case "leaderboard": {
                echo(json_encode(getLeaderboard()));
                break;
            }
            case "playerstats": {
                echo(json_encode(getPlayerStats()));
                break;
            }
            case "info": {
                if(isset($_GET["name"])) echo(getInfo($_GET["name"]));
                break;
            }
        }
        exit();
    }
    if(!require_once(constant('spyc'))) {
        echo "Could not find the spyc file: " . constant('spyc') . "<br>";
        echo "Please check your settings";
        return;
    }
    ?>
    <script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/iframe-resizer/3.5.15/iframeResizer.contentWindow.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.devbridge-autocomplete/1.4.4/jquery.autocomplete.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <?php
    function timeToUTC($time) {
        $date = new DateTime($time, $GLOBALS['timezone']);
        $date->setTimezone(new DateTimeZone("UTC"));
        return $date->format("Y-m-d H:i:s");
    }
    function getSetting($setting, $search) {
        foreach($search as $section) 
            if(array_key_exists($setting, $section))
                return $section[$setting];
    }
    function filter($input) {
        return preg_replace("/[^a-zA-Z0-9_-]+/", "", $input);
    }
    function hasSetting($setting, $array) {
        return array_key_exists($setting, $array);
    }
    function getInfo($name) {
        $connection = getConnection();
        $query = "SELECT player_id, uuid, last_join FROM leaderheadsplayers WHERE name = '" . filter($name) . "' ORDER BY last_join LIMIT 1";
        $result_set = $connection->query($query);
        if($result_set) {
            if($result_set->num_rows != 0) {
                $result = $result_set->fetch_assoc();
                $result["last_join"] = timeToUTC($result["last_join"]);
            }
            $result_set->close();
        } else {
            $result = (object) ["error" => $connection->error]; 
        }
        $connection->close();
        return json_encode($result ? $result : new stdClass);
    }
    function getPlayerStats() { 
        if(!isset($_POST["player_id"])) return;
        $player_id = $_POST["player_id"];
        if(!ctype_digit($player_id)) return;
        $current_day = date("N");
        $previous_day = $current_day == 1 ? 7 : $current_day - 1;
        $current_year = date("Y");
        $current_week = date("W");
        $previous_week = date("W", strtotime(date("Y-m-d", strtotime("-1 week"))));
        $previous_week_year = date("Y", strtotime(date("Y-m-d", strtotime("-1 week"))));
       
        $current_month = date("n");
        $previous_month = $current_month == 1 ? 10 : $current_month - 1;
        $previous_month_year = $current_month == 1 ? $current_year - 1 : $current_year;
        $tables = json_decode($_POST["requests"]);
        $query = "SELECT ";
        for($x=0; $x < count($tables); $x++) {
            if($tables[$x]->time_type == "alltime") {
                $query .= ($x == 0 ? "" : ", ") . "{$x}a.stat_value";
            } else {
                $query .= ($x == 0 ? "" : ", ") . "({$x}n.stat_value - {$x}o.stat_value)";
            }
        }
        $query .= " FROM leaderheadsplayers p";
        for($x=0; $x < count($tables); $x++) {
            $table = getTable($tables[$x]->server, $tables[$x]->time_type);
            $type = filter($tables[$x]->type);
            switch($tables[$x]->time_type) {
                case "alltime": {
                    $query .= " LEFT JOIN $table {$x}a ON {$x}a.player_id = p.player_id AND {$x}a.stat_type = '$type'";
                    break;
                }
                case "daily": {
                    $query .= " LEFT JOIN $table {$x}n ON {$x}n.player_id = p.player_id AND {$x}n.stat_type = '$type' AND {$x}n.day = $current_day";
                    $query .= " LEFT JOIN $table {$x}o ON {$x}o.player_id = p.player_id AND {$x}o.stat_type = '$type' AND {$x}o.day = $previous_day";
                    break;
                }
                case "weekly": {
                    $query .= " LEFT JOIN $table {$x}n ON {$x}n.player_id = p.player_id AND {$x}n.stat_type = '$type' AND {$x}n.week = $current_week AND {$x}n.year = $current_year";
                    $query .= " LEFT JOIN $table {$x}o ON {$x}o.player_id = p.player_id AND {$x}o.stat_type = '$type' AND {$x}o.week = $previous_week AND {$x}o.year = $previous_week_year";
                    break;
                }
                case "monthly": {
                    $query .= " LEFT JOIN $table {$x}n ON {$x}n.player_id = p.player_id AND {$x}n.stat_type = '$type' AND {$x}n.month = $current_month AND {$x}n.year = $current_year";
                    $query .= " LEFT JOIN $table {$x}o ON {$x}o.player_id = p.player_id AND {$x}o.stat_type = '$type' AND {$x}o.month = $previous_month AND {$x}o.year = $previous_month_year";
                    break;
                }
            }
        }
        $query .= " WHERE p.player_id = $player_id";
        $results = array();
        $connection = getConnection();
        $result_set = $connection->query($query);
        if($result_set) {
            while($fetched_row = $result_set->fetch_array()) {
                $result = array();
                for($x = 0; $x < count($tables); $x++) {
                    $results[$x] = $fetched_row[$x] == NULL ? "0" : $fetched_row[$x];
                }
            }
            $result_set->close();
        } else {
            $results = (object) ["error" => $connection->error];
        }
        $connection->close();
        return $results;
    }
    function getTable($server, $timetype) {
        if($server == "default") {
            return "leaderheadsplayersdata_" . filter($timetype);
        } else {
            return filter($server) . "_leaderheadsplayersdata_" . filter($timetype);
        }
    }
    function getLeaderboard() {
        if(!isset($_POST["page"])) return;
        $page = $_POST["page"];
        if(!ctype_digit($page)) return;
        if(!isset($_POST["count"])) return;
        $count = $_POST["count"];
        if(!ctype_digit($count)) return;
        $lower_limit = rtrim(rtrim(sprintf('%.8F', ($page - 1) * $count), '0'), ".");
        $upper_limit = rtrim(rtrim(sprintf('%.8F', $count), '0'), ".");
        $current_day = date("N");
        $previous_day = $current_day == 1 ? 7 : $current_day - 1;
        $current_year = date("Y");
        $current_week = date("W");
        $previous_week = date("W", strtotime(date("Y-m-d", strtotime("-1 week"))));
        $previous_week_year = date("Y", strtotime(date("Y-m-d", strtotime("-1 week"))));
       
        $current_month = date("n");
        $previous_month = $current_month == 1 ? 10 : $current_month - 1;
        $previous_month_year = $current_month == 1 ? $current_year - 1 : $current_year;
        $tables = json_decode($_POST["requests"]);
        if(empty($tables)) {
            $query = "SELECT name FROM leaderheadsplayers ORDER BY name LIMIT $lower_limit, $upper_limit";
        } else {
            $query = "SELECT p.name";
            for ($x = 0; $x < count($tables); $x++) {
                if ($tables[$x]->time_type == "alltime") {
                    $query .= ", {$x}a.stat_value";
                } else {
                    $query .= ", ({$x}n.stat_value - {$x}o.stat_value)";
                }
                if (isset($tables[$x]->order) && $tables[$x]->order == true) {
                    $order_table = $tables[$x];
                    $order_index = $x;
                    $order_faction = $tables[$x]->faction;
                }
            }
            $query .= " FROM leaderheadsplayers p";
            for ($x = 0; $x < count($tables); $x++) {
                $table = getTable($tables[$x]->server, $tables[$x]->time_type);
                $type = filter($tables[$x]->type);
                switch ($tables[$x]->time_type) {
                    case "alltime": {
                        $query .= " LEFT JOIN $table {$x}a ON {$x}a.player_id = p.player_id AND {$x}a.stat_type = '$type'";
                        break;
                    }
                    case "daily": {
                        $query .= " LEFT JOIN $table {$x}n ON {$x}n.player_id = p.player_id AND {$x}n.stat_type = '$type' AND {$x}n.day = $current_day";
                        $query .= " LEFT JOIN $table {$x}o ON {$x}o.player_id = p.player_id AND {$x}o.stat_type = '$type' AND {$x}o.day = $previous_day";
                        break;
                    }
                    case "weekly": {
                        $query .= " LEFT JOIN $table {$x}n ON {$x}n.player_id = p.player_id AND {$x}n.stat_type = '$type' AND {$x}n.week = $current_week AND {$x}n.year = $current_year";
                        $query .= " LEFT JOIN $table {$x}o ON {$x}o.player_id = p.player_id AND {$x}o.stat_type = '$type' AND {$x}o.week = $previous_week AND {$x}o.year = $previous_week_year";
                        break;
                    }
                    case "monthly": {
                        $query .= " LEFT JOIN $table {$x}n ON {$x}n.player_id = p.player_id AND {$x}n.stat_type = '$type' AND {$x}n.month = $current_month AND {$x}n.year = $current_year";
                        $query .= " LEFT JOIN $table {$x}o ON {$x}o.player_id = p.player_id AND {$x}o.stat_type = '$type' AND {$x}o.month = $previous_month AND {$x}o.year = $previous_month_year";
                        break;
                    }
                    default: {
                        return (object)["error" => "Invalid time-type " . $tables[$x]->time_type];
                    }
                }
            }
            $query .= " WHERE p.player_id IN (SELECT player_id FROM (";
            $table = getTable($order_table->server, $order_table->time_type);
            $type = filter($order_table->type);
            switch ($order_table->time_type) {
                case "alltime": {
                    $query .= "SELECT 0a.player_id FROM $table 0a WHERE 0a.stat_type = '$type' ORDER BY 0a.stat_value DESC LIMIT $lower_limit, $upper_limit";
                    break;
                }
                case "daily": {
                    $query .= "SELECT 0n.player_id FROM $table 0n LEFT JOIN $table 0o ON 0o.player_id = 0n.player_id AND 0o.stat_type = '$type' AND 0o.day = $previous_day WHERE 0n.stat_type = '$type' AND 0n.day = $current_day ORDER BY (0n.stat_value - 0o.stat_value) DESC LIMIT $lower_limit, $upper_limit";
                    break;
                }
                case "weekly": {
                    $query .= "SELECT 0n.player_id FROM $table 0n LEFT JOIN $table 0o ON 0o.player_id = 0n.player_id AND 0o.stat_type = '$type' AND 0o.week = $previous_week AND 0o.year= $previous_week_year WHERE 0n.stat_type = '$type' AND 0n.week = $current_week AND 0n.year = $current_year ORDER BY (0n.stat_value - 0o.stat_value) DESC LIMIT $lower_limit, $upper_limit";

                    break;
                }
                case "monthly": {
                    $query .= "SELECT 0n.player_id FROM $table 0n LEFT JOIN $table 0o ON 0o.player_id = 0n.player_id AND 0o.stat_type = '$type' AND 0o.month = $previous_month AND 0o.year= $previous_month_year WHERE 0n.stat_type = '$type' AND 0n.month = $current_month AND 0n.year = $current_year ORDER BY (0n.stat_value - 0o.stat_value) DESC LIMIT $lower_limit, $upper_limit";
                    break;
                }
            }
            $query .= ") 1a)";
            if ($order_table->time_type == "alltime") {
                $query .= " ORDER BY {$order_index}a.stat_value DESC";
            } else {
                $query .= " ORDER BY ({$order_index}n.stat_value - {$order_index}o.stat_value) DESC";
            }
        }
        $results = array();
        $connection = getConnection();
        $result_set = $connection->query($query);
        if($result_set) {
            while($fetched_row = $result_set->fetch_array()) {
                $result = array();
                for($x = 0; $x <= count($tables); $x++) {
                    $result[$x] = $fetched_row[$x] == NULL ? "0" : $fetched_row[$x];
                }
                array_push($results, $result);
            }
            $result_set->close();
        } else {
            $results = (object) ["error" => $connection->error];
        }
        $connection->close();
        return $results;
    }
    function getPlayerCount() {
        $connection = getConnection();
        $query =  "SELECT COUNT(*) FROM leaderheadsplayers";
        $result_set = $connection->query($query);
        if($result_set) {
            $row = $result_set->fetch_array();
            $count = (object) ["result" => $row[0]];
            $result_set->close();
        } else {
            $count = (object) ["error" => $connection->error];
        }
        $connection->close();
        return $count;
    }
    function getNames($name) {
        $name = filter($name);
        if(empty($name)) return array();
        $connection = getConnection();
        $query = "SELECT name FROM leaderheadsplayers WHERE name LIKE '$name%'";
        $result_set = $connection->query($query);
        $names = array();
        if($result_set) {
            while($row = $result_set->fetch_array()) {
                $names[] = $row[0];
            }
        } else {
            $names = (object) ["error" => $connection->error];            
        }
        $connection->close();
        return (object) ["suggestions" => $names];
    }
    function getConnection() {
        $connection = new mysqli(constant('database_host'), constant('database_username'), constant('database_password'), constant('database_database'), constant('database_port'));
        if($connection->connect_error) {
           echo(json_encode((object) ["error" => $connection->connect_error]));
           exit();
        }
        return $connection;
    }
    function writeConsole($data) {
        if(constant('debug')) {
            if(is_array($data) || is_object($data)) {
		echo("<script>console.log('PHP: ".json_encode($data)."');</script>");
            } else {
		echo("<script>console.log('PHP: ".$data."');</script>");
            }
        }
    }
    function clean($type) {
        return str_replace('-', '_', $type);
    }
?>
<style>
    .autocomplete-suggestions { border: 1px solid #999; background: #FFF; overflow: auto; }
    .autocomplete-suggestion { padding: 2px 5px; white-space: nowrap; overflow: hidden; }
    .autocomplete-selected { background: #F0F0F0; }
    .autocomplete-suggestions strong { font-weight: normal; color: #3399FF; }
    .autocomplete-group { padding: 2px 5px; }
    .autocomplete-group strong { display: block; border-bottom: 1px solid #000; }
    @font-face {
        font-family: 'Lato';
        font-style: normal;
        font-weight: 400;
        src: local('Lato Regular'), local('Lato-Regular'), url(https://fonts.gstatic.com/s/lato/v11/UyBMtLsHKBKXelqf4x7VRQ.woff2) format('woff2');
        unicode-range: "U+0100-024F, U+1E00-1EFF, U+20A0-20AB, U+20AD-20CF, U+2C60-2C7F, U+A720-A7FF";
    }
    @font-face {
      font-family: 'Lato';
      font-style: normal;
      font-weight: 400;
      src: local('Lato Regular'), local('Lato-Regular'), url(https://fonts.gstatic.com/s/lato/v11/1YwB1sO8YE1Lyjf12WNiUA.woff2) format('woff2');
      unicode-range: "U+0000-00FF, U+0131, U+0152-0153, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2212, U+2215, U+E0FF, U+EFFD, U+F000";
    }
    #errorBox {
        position: fixed;
        font-family: Arial, Helvetica, sans-serif;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        background: rgba(0, 0, 0, 0.8);
        opacity:0;
        -webkit-transition: opacity 400ms ease-in;
        -moz-transition: opacity 400ms ease-in;
        transition: opacity 400ms ease-in;
    }
    #errorBox:target {
        opacity:1;
        pointer-events: auto;
    }
    #errorBox > div {
        width: 400px;
        position: relative;
        margin: 10% auto;
        padding: 5px 20px 13px 20px;
        border-radius: 10px;
        background: #fff;
        background: -moz-linear-gradient(#fff, #999);
        background: -webkit-linear-gradient(#fff, #999);
        background: -o-linear-gradient(#fff, #999);
        -webkit-user-drag: element;
    }
    #closeErrorBox {
        background: #606061;
        color: #FFFFFF;
        line-height: 25px;
        position: absolute;
        right: -12px;
        text-align: center;
        top: -10px;
        width: 24px;
        text-decoration: none;
        font-weight: bold;
        -webkit-border-radius: 12px;
        -moz-border-radius: 12px;
        border-radius: 12px;
        -moz-box-shadow: 1px 1px 3px #000;
        -webkit-box-shadow: 1px 1px 3px #000;
        box-shadow: 1px 1px 3px #000;
    }
    #closeErrorBox:hover {
        background: #00d9ff;
    }
    .autocomplete-skull {
        height: 20px;
    }
</style>
<div id="errorBox">
    <div>
        <button title="Close" id="closeErrorBox">X
        </button>
        <h2>Errors:</h2>
    </div>
</div>
<script type="text/javascript">
    function replaceAll(str, find, replace) {
        return str.replace(new RegExp(find, "g"), replace);
    }
    var errors = [];
    $(function() {
        $("#closeErrorBox").on("click", function () {
            $("#errorBox").css('opacity', 0).css('z-index', 0);
        });
        $(".player-search-field").autocomplete({
            serviceUrl: window.location.href.split('?')[0].split('#')[0],
            paramName: "value",
            params: {
                "query": "name"
            },
            formatResult: function (suggestion, currentValue) {
                var skull = replaceAll("<?=constant("skull_url")?>", "{name}", suggestion.value);
                return "<img class='autocomplete-skull' src=" + skull + ">\n" + $.Autocomplete.defaults.formatResult(suggestion, currentValue);
            },
            onSelect: function (suggestion) {
                $(this).closest("form").submit();
            }
        });
        var anchor = window.location.hash;
        if(anchor.length > 0) {
            scrollTop: $(anchor).offset().top - ($(window).height() - $(anchor).outerHeight(true)) / 2;
        }
    });
    function checkError(result) {
        if(result.error) {
            var box = $("#errorBox > div");
            var matches = result.error.match("Table '\\w+\\.(\\w+)_\\w+_\\w+' doesn't exist");
            if(matches !== null) {
                result.error = "Server <b>" + matches[1] + "</b> does not exist.";
            }
            if(errors.indexOf(result.error) == -1) {
                box.append("<p style=font-size:16px;>" + result.error.replace(/["']/g, "") + "</p>");
                box.parent().css('opacity', 1).css('z-index', 100);
                errors.push(result.error);
            }
            return false;
        }
        return true;
    }
    function formatHighNumbers(input, formats) {
        if(input >= 1.0E24) return Math.round(input / 1.0E24) + formats["septillions_format"];
        if(input >= 1.0E21) return Math.round(input / 1.0E21) + formats["sextillions_format"];
        if(input >= 1.0E18) return Math.round(input / 1.0E18) + formats["quintillions_format"];
        if(input >= 1.0E15) return Math.round(input / 1.0E15) + formats["quadrillions_format"];
        if(input >= 1.0E12) return Math.round(input / 1.0E12) + formats["trillions_format"];
        if(input >= 1.0E9) return Math.round(input / 1.0E9) + formats["billions_format"];
        if(input >= 1.0E6) return Math.round(input / 1.0E6) + formats["millions_format"];
        if(input >= 1.0E3) return Math.round(input / 1.0E3) + formats["thousands_format"];
        return Math.round(input);
    }
    function formatTime(input, formats) {
        input = Math.round(Number(input));
        if(input < 60) return formats["time_format_minutes"]
                .replace("{minutes}", input)
                .replace("%i", input);
        if(input < 1440) return formats["time_format_hours"].replace("{minutes}", input % 60).replace("{hours}", Math.floor(input / 60));
        return formats["time_format_days"]
                .replace("{minutes}", input % 60)
                .replace("%i", input % 60)
                .replace("{hours}",  Math.floor((input % 1440) / 60))
                .replace("%h",  Math.floor((input % 1440) / 60))
                .replace("{days}", Math.floor(input / 1440))
                .replace("%a", Math.floor(input / 1440));
    }
    function replaceParameters(url, paramName, paramValue) {
        url = url.replace(window.location.hash , '');
        var pattern = new RegExp('\\b(' + paramName + '=).*?(&|$)');
        if(url.search(pattern) >= 0) {
            return url.replace(pattern,'$1' + paramValue + '$2') + window.location.hash;
        }
        return url + (url.indexOf('?') > 0 ? '&' : '?') + paramName + '=' + paramValue + window.location.hash;
    }
    function replaceAll(str, find, replace) {
        return str.replace(new RegExp(find, 'g'), replace);
    }
</script>