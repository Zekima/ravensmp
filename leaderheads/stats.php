<?php
    // --------------------------------------------------------------------------------------------------------------------- //
    // These are the only settings in the file. You need to set up the correct URL's for the linked files.
    // These values will be correct by default.
	
    define('stats_config', 'config/stats_config.yml');
    define('core', 'core.php');
    define('stylesheet', 'stylesheets/stats_style.css');

    // Do not edit anything under this comment if you don't know what you're doing.
    // --------------------------------------------------------------------------------------------------------------------- //
?>
<?php
    if(!file_exists(constant('stats_config'))) {
        echo "Could not find config file: " . constant('stats_config') . "<br>";
        echo "Please check your settings";
        return;
    }
    if(!file_exists(constant('stylesheet'))) {
        echo "Could not find stylesheet file: " . constant('stylesheet') . "<br>";
        echo "Please check your settings";
        return;
    }
    if(!include_once(constant('core'))) {
        echo "Could not find the core file: " . constant('core') . "<br>";
        echo "Please check your settings";
        return;
    }
    $stats_config = Spyc::YAMLLoad(constant('stats_config'));
    $user = filter_input(INPUT_GET, "player");
    $user = preg_replace('/[^\w]/', '', $user);
    if(strlen($user) > 16) $user = substr($user, 0, 16);
    $description = $stats_config["description"];
?>
<!DOCTYPE HTML>
<html>
    <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1.0" />
    <head>
        <meta http-equiv="Cache-control" content="public">
        <meta name="description" content=<?=$description?>>
        <meta charset="UTF-8">
        <title><?=str_replace("{name}", $user, $stats_config['page_title'])?></title>
        <style><?php include constant('stylesheet'); ?></style>
        <script type="text/javascript">data = {};</script>
    </head>
    <body>
        <?php
        $settings = array();
        $global_settings = $stats_config['settings'];
        $messages = $stats_config['messages'];
        $not_exist = $messages["not_exist"];
        $high_formats = $messages["high_formats"];
        $settings["last_seen_message"] = $messages["last_seen_message"];
        $settings["last_seen_messages"] = $messages['last_seen'];
        $settings["last_seen_interval"] = $global_settings["last_seen_interval"];
        $settings["player_picture"] = $global_settings["player_picture"];
        $settings["not_exist"] = $messages["not_exist"];
        if($global_settings['enable_page_header']) {?>
             <div class='row-fluid'>
                <div class="col-xs-10 col-xs-offset-1 col-md-10 col-md-offset-1">
                    <div class = "page-header">
                        <div class='row-fluid'>
                            <div class="col-xs-12 col-md-6">
                                <h1><?=$global_settings['page_header_text']?></h1> 
                            </div>
                            <?php 
                            if($global_settings['enable_global_search_bar']) { ?>
                                <div class="col-xs-12 col-md-6">
                                    <div class='header-search-bar form-inline pull-right'>
                                        <form class='form form-inline' onsubmit="window.location.href = replaceAll('<?=$global_settings['global_search_bar_url']?>', '{name}', document.getElementById('search-player-global').value); return false;">
                                            <div class='input-group'>
                                                <input required maxlength="16" class='search-field form-control input-sm player-search-field' id='search-player-global' placeholder='<?=$global_settings['global_search_bar_button_placeholder']?>' type='text'>
                                                <div class='input-group-btn'>
                                                   <button type='submit' class='search-button btn btn-sm btn-primary'><?=$global_settings['global_search_bar_button_text']?></button>
                                                </div>
                                            </div>
                                        </form>
                                     </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
        <div class="container page-content">
            <script type="text/javascript">
                settings = <?php echo json_encode($settings)?>
            </script>
            <div id="global-stats-row" class="row">
                <div class="col-xs-12 col-md-2">
                    <img id="player-picture" class="player-picture" src="<?=str_replace("{uuid}","8667ba71-b85a-4004-af54-457a9734eed7", $settings["player_picture"])?>">
                </div>
                <div class="col-xs-12 col-md-10">
                    <h1 id="player-name"><?=$user?></h1>
                    <div id="last-seen" class="last-seen"></div>
                    <div class='container-pack'>
                        <?php 
                            foreach($stats_config['tables'] as $table => $properties) {
                            ?>
                            <?php
                            $lowercase_table_name = preg_replace('/\s+/', '', strtolower($table));
                            $lowercase_table_name = preg_replace("/[^A-Za-z0-9 ]/", '', $lowercase_table_name);
                            $selected_type = NULL;
                            $selected_time = NULL;
                            $rows = $properties['rows'];
                            $settings = array_key_exists('settings', $properties) ? $properties['settings'] : array();
                            if(is_string($settings)) $settings = array();
                            $defaults = $stats_config['defaults'];
                            $table_width = array_key_exists('table_width', $settings) ? $settings['table_width'] : $defaults['table_width'];
                            $enable_header = array_key_exists('enable_header', $settings) ? $settings['enable_header'] : $defaults['enable_header'];
                            $enable_caption = array_key_exists('enable_caption', $settings) ? $settings['enable_caption'] : $defaults['enable_caption'];
                            $enable_caption_custom_text = array_key_exists('enable_caption_custom_text', $settings) ? $settings['enable_caption_custom_text'] : $defaults['enable_caption_custom_text'];
                            $caption_custom_text = array_key_exists('caption_custom_text', $settings) ? $settings['caption_custom_text'] : $defaults['caption_custom_text'];
                            $time_format_days = array_key_exists('time_format_days', $settings) ? $settings['time_format_days'] : $defaults["messages"]['time_format_days'];
                            $time_format_hours = array_key_exists('time_format_hours', $settings) ? $settings['time_format_hours'] : $defaults["messages"]['time_format_hours'];
                            $time_format_minutes = array_key_exists('time_format_minutes', $settings) ? $settings['time_format_minutes'] : $defaults["messages"]['time_format_minutes'];
                            $index_width = array_key_exists('index_width', $settings) ? $settings['index_width'] : $defaults['index_width'];
                            $row_data_collection = array();
                            foreach($rows as $title => $row) {
                                $row_data = array();
                                $sections = array($row, $properties["settings"], $defaults["rows"]);
                                $settings_query = array("type", "server", "time_type", "statistic_type", "decimals", "format_3_digits", "format_high_numbers", "format");
                                foreach($settings_query as $settings_query_single) {
                                    $row_data[$settings_query_single] = getSetting($settings_query_single, $sections);
                                }
                                $row_data["title"] = $title;
                                array_push($row_data_collection , $row_data);
                            }    
                            $server_settings = array();
                            $server_settings["high_formats"] = $messages["high_formats"];
                            $server_settings["time_formats"] = array_key_exists("time_formats", $messages) ? $messages["time_formats"] : $defaults["messages"];
                            ?>
                            <script>
                                data["<?=$lowercase_table_name?>"] = {};
                                data["<?=$lowercase_table_name?>"]["settings"] = <?php echo json_encode($server_settings)?>;
                                data["<?=$lowercase_table_name?>"]["rows"] = <?php echo json_encode($row_data_collection)?>;
                            </script>
                            <div id='<?=$lowercase_table_name?>' style='max-width: <?=$table_width?>;visibility:hidden;' class='<?=$lowercase_table_name?>-container server-container container'>
                            <?php
                            if($enable_header) {?>
                                <div class='row'>
                                    <div class="col-xs-12 col-md-12">
                                        <div class='page-header'>
                                            <h2><?=$table?></h2>
                                        </div>
                                    </div>
                                </div>
                                <?php } ?>
                                <div class='row'>
                                    <div class="col-xs-12 col-md-12">
                                        <div class="table-container">
                                            <table class='<?=$lowercase_table_name?>-table table-stats table table-bordered table-striped'>
                                                <tbody>
                                                <?php if($enable_caption) echo "<caption><h2>" . ($enable_caption_custom_text ? $caption_custom_text : $leaderboard) . "</h2></caption>";
                                                foreach($row_data_collection as $row) {
                                                    echo "<tr><td width=" . $index_width  . ">" . $row["title"] . "</td><td></td>";
                                                    echo "</tr>";
                                                 }?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                            
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </body>
    <script type="text/javascript">
        function updateTable(id) {
            var server = $("#" + id);
            server.css('visibility', 'visible');
            var properties = data[id]["rows"];
            var settings = data[id]["settings"];
            var tables = [];
            var tableElement = server.find("table.table-stats");
            var filterVal = "blur(2px)";
            tableElement.css('filter',filterVal).css('webkitFilter',filterVal).css('mozFilter',filterVal).css('oFilter',filterVal).css('msFilter',filterVal);
            for(var x in properties) {
                var property = properties[x];
                var table = {time_type:property["time_type"], server: property["server"] == null ? "default" : property["server"], type:property["type"]};
                tables.push(table);
            }
            $.post(window.location.href.split('?')[0].split('#')[0] + "?query=playerstats", {
                player_id: player_id,
                requests: JSON.stringify(tables),
            }, function(results) {
                if(checkError(results)) {
                    var body = server.find(".table-stats tbody");        
                    for(var r = 0; r < results.length; r++) {
                        var row = "";
                        var result_value = results[r];
                        var format = properties[r]["format"];
                        if(result_value === null) result_value = 0;
                        result_value = parseFloat(result_value);
                        switch(properties[r]["statistic_type"]) {
                            case "time": {
                                row += format.replace("{amount}", formatTime(result_value, settings["time_formats"]));
                                break;
                            }
                            default: {
                                result_value = parseFloat(result_value).toFixed(properties[r]["decimals"]);
                                if(properties[r]["format_3_digits"]) {
                                    result_value = result_value.toLocaleString();
                                } else {
                                    if(properties[r]["format_high_numbers"]) {
                                        result_value = formatHighNumbers(result_value, settings["high_formats"]);
                                    }
                                }
                                row += format.replace("{amount}", result_value);
                                break;
                            }
                        }
                        body.find("tr").eq(r).find("td").eq(1).html(row);
                    }
                }
            }).always(function() {
                var filterVal = "initial";
                tableElement.css('filter',filterVal).css('webkitFilter',filterVal).css('mozFilter',filterVal).css('oFilter',filterVal).css('msFilter',filterVal);
            });
        }
        function formatInterval(last_seen, messages, last_seen_interval) {
            var t = last_seen.split(/[- :]/);
            var d = new Date(Date.UTC(t[0], t[1]-1, t[2], t[3], t[4], t[5]));
            var d = Math.abs(d - new Date()) / 1000;
            var interval = {};
            var s = {
                year: 31536000,
                month: 2592000,
                day: 86400,
                hour: 3600,
                minute: 60,
                second: 1
            };
            Object.keys(s).forEach(function(key){
                interval[key] = Math.floor(d / s[key]);
                d -= interval[key] * s[key];
            });
            if(interval.minute <= last_seen_interval && interval.year === 0 && interval.month === 0 && interval.day === 0 && interval.hour === 0) return messages.just_now;
            if(interval.year === 1) return messages.year_ago.replace("%y", interval.year);
            if(interval.year) return messages.years_ago.replace("%y", interval.year);

            if(interval.month === 1) return messages.month_ago.replace("%m", interval.month);
            if(interval.month) return messages.months_ago.replace("%m", interval.month);

            if(interval.day === 1) return messages.day_ago.replace("%d", interval.day);
            if(interval.day) return messages.days_ago.replace("%d", interval.day);

            if(interval.hour === 1) return messages.hour_ago.replace("%h", interval.hour);
            if(interval.hour) return messages.hours_ago.replace("%h", interval.hour);
            
            if(interval.minute === 1) return messages.minute_ago.replace("%i", interval.minute);
            if(interval.minute) return messages.minutes_ago.replace("%i", interval.minute);
            return "";
        }  
        $(function() {
            $("form.search-form").on("submit", function(e) {
                e.preventDefault();
                window.location.href = $(this).attr("url").replace("{name}", $(this).find("input.search-field").val());
            });
            $("#player-picture").on('error', function() {
                var source = replaceAll(replaceAll(settings.player_picture, "{uuid}", "8667ba71-b85a-4004-af54-457a9734eed7"), "{name}", "<?=$user?>");
                if($(this).attr("src") != source) {
                    $("#player-picture").attr("src", source);
                }
            })
            function updatePage() {
				$.getJSON(window.location.href.split('?')[0].split('#')[0], {
				   query: 'info',
				   name: '<?=$user?>'
				}, function(results) {
					if(checkError(results)) {
						if(Object.keys(results).length === 0) {
							$("#last-seen").html(settings.last_seen_messages.never_joined);
							$("#player-name").html(settings.not_exist);
						} else {
							player_id = results.player_id;
							var uuid = results.uuid;
							$("#player-picture").attr("src", replaceAll(replaceAll(settings.player_picture, "{uuid}", uuid), "{name}", "<?=$user?>"));
							var last_seen = results.last_join;
							$("#last-seen").html(settings.last_seen_message.replace("{time}", formatInterval(last_seen, settings.last_seen_messages, settings.last_seen_interval)));
							$(".server-container").each(function() {
								updateTable($(this).attr('id'), false);
							});
						}
					}
				});
			}
			updatePage();
			setInterval(updatePage, 1000 * <?=array_key_exists("update_interval", $global_settings) ? $global_settings["update_interval"] : 10?>);
        });
    </script>    
</html>