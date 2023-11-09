<?php

# CONFIGURATION

# For Privacy, all config details such as API keys are stored in the .env file
# We need to read this in and populate the $config array
$config = [];
if(file_exists(".env")) {
  $env = file(".env");
  foreach ($env as $p) {
    $explode_p = explode("=", $p);
    $config[$explode_p[0]] = $explode_p[1];
  }
}

# If config variables are set from the .env file then allocate them to variables used in the functions
# and html code below, otherwise fall back to defaults
$api_key = isset($config['API_KEY']) ? $config['API_KEY'] : 'YOUTUBE API KEY';
$parent_domain = isset($config['PARENT_DOMAIN']) ? $config['PARENT_DOMAIN'] : "DOMAIN THIS SCRIPT IS SERVED FROM";
# Airliners Live Channel ID
$channel_id = isset($config['CHANNEL_ID']) ? $config['CHANNEL_ID'] : "YOUTUBE CHANNEL ID";
$twitch_channel = isset($config['TWITCH_CHANNEL']) ? $config['TWITCH_CHANNEL'] : "TWITCH CHANNEL NAME";
$title = isset($config['TITLE']) ? $config['TITLE'] : "WEBPAGE TITLE";
$img_url = isset($config['IMG_URL']) ? $config['IMG_URL'] : "LOGO URL";

# LOGGING

$file_name = "logs/".date("Y-m-d").".log";
if(!file_exists($file_name)) {
  # If log file does not exist, create one
  $log = fopen($file_name, "w");
} else {
  # Log file does exist so will append to it
  $log = fopen($file_name, "a");
}

# Functions

# Get will fetch remote data and decode the JSON
function get($url, $log) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_URL, $url);
  $data = curl_exec($ch);
  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if($httpcode==200) {
    fwrite($log, date("Y-m-d h:i:s").": "."Fetching JSON from $url");
    fwrite($log, date("Y-m-d h:i:s").": ".$data);

    return json_decode($data);
  } else {
    fwrite($log, date("Y-m-d h:i:s").": "."Error fetching JSON from $url");
    fwrite($log, date("Y-m-d h:i:s").": ".$data);
  }
  curl_close($ch);

}

# This function will parse the decoded JSON file and try to find the 
# id of the most recent/current stream
function get_next_live_stream_id($channel_id, $api_key, $log) {
   # Get next stream details
   # GET request trumps everything
   if( !empty($_GET['stream_id'])) {
    return $_GET['stream_id'];
   }

   # Live stream url
   $live_url = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=$channel_id&type=video&eventType=live&key=$api_key";
   fwrite($log, date("Y-m-d h:i:s").": "."$live_url\n");

   $scheduled_url = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=$channel_id&type=video&eventType=upcoming&key=$api_key";
   fwrite($log, date("Y-m-d h:i:s").": "."$scheduled_url\n");

   #Try live stream search first
   $next_stream = get($live_url, $log);

   if(isset($next_stream->items) && count($next_stream->items) > 0) {
    fwrite($log, date("Y-m-d h:i:s").": "."Live stream\n");
    # We have a live stream";
    $info = $next_stream->items[0];

    # Return video id
    return $info->id->videoId;

  } else {
    fwrite($log,date("Y-m-d h:i:s").": "."NO Live stream\n");
    # No live stream, find next scheduled
    $next_stream = get($scheduled_url, $log);
    #var_dump($next_stream);
    if(isset($next_stream->items) && count($next_stream->items) > 0) {
      # We have a scheduled stream";
      $info = $next_stream->items[0];
  
      # Return video id
      return $info->id->videoId;
      
    } else {
      # No luck with either
      fwrite($log,date("Y-m-d h:i:s").": "."No Luck\n");
      return "";
    }
  }

}


# If variables are sent in the URL, then replace local variables with these
# The stream_id can be passed in this way too, but appears later so 
# that it will always trump anything else
$parent_domain = !empty($_GET['domain'])? $_GET['domain'] : $parent_domain;
$channel_id = !empty($_GET['channel_id']) ? $_GET['channel_id'] : $channel_id;
$q = !empty($_GET['q']) ? $_GET['q'] : "";
$stream_id = get_next_live_stream_id($channel_id, $api_key, $log);
fwrite($log,date("Y-m-d h:i:s").": "."stream id is $stream_id");


if(!empty($q)) {
  fwrite($log, "GET query: $q\n");
  $query_url = "https://www.googleapis.com/youtube/v3/search?part=snippet&type=channel&field=items&snippet=channelId&q=$q&key=$api_key";
  fwrite($log, date("Y-m-d h:i:s").": "."$query_url\n");

  #Try live stream search first
  $channel_id = get($live_url, $log);

  if(count($channel_id->items) > 0) {
    $channel_id = $channel_id->items[0]->snippet->channelId;
    fwrite($log,date("Y-m-d h:i:s").": ". "Channel ID is: $channel_id\n");
    $stream_id = get_next_live_stream_id($channel_id, $api_key, $log);
  } else {
    fwrite($log, date("Y-m-d h:i:s").": "."No items from search query\n");
  }

}

fclose($log);


?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $title ?></title>
  <link rel="stylesheet" href="./styles.css">
</head>

  <body class="container">
    <header class="header"><img src="<?php echo $img_url; ?>" /><?php echo $title ?></header>
    
    <div class="twitch">
        <iframe id="twitch-chat-embed"
            src="<?php echo "https://www.twitch.tv/embed/$twitch_channel/chat?parent=$parent_domain"; ?>">
        </iframe>
    </div>
    
    <div class="yt">
        <?php if(empty($stream_id)) {
          echo "<p>Unable to load YT chat";
        } else {
          echo "<iframe
            src=\"https://youtube.com/live_chat?v=$stream_id&embed_domain=$parent_domain\"
            frameborder=\"0\"
            allowfullscreen=\"allowfullscreen\"
            >
          </iframe>";
        }
        ?>

    </div>
  </body>
</html>



