<?php
/* ----------------- API Details --------------------------- */
// Twilio Details From Your Dashboard
// https://twilio.com/console
$account_sid 	= 'AC3107cba67e400219e8e92561475294de';
$twilio_token 	= 'd13960dd0b613ecb46dc8cbd12eb498a';
$sms_number 	= '+12029331474'; // Include the plus

// Slack Bot Token and API Token.
// https://api.slack.com/
$channel_name 	= 'chitchat';
$channel_id 	= 'C016HPHHQMS';
$bot_token 		= 'xoxb-989268666327-1214317401589-fhmpOi8hwKp4W05LZ0vOUphC';
$oauthtoken 	= 'xoxp-989268666327-975937849667-1239663767440-c524ed588ec9ea41a700259c8d5f1ae3';
$app_username  	= 'ChitChat (SMS)';

/* ------------------- Main Code --------------------------- */
// Debugging Error Code
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// Required if your environment does not handle autoloading
require __DIR__ . '/vendor/autoload.php';

// Use the REST API Client to make requests to the Twilio REST API
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator; 

use JoliCode\Slack\Api\Model\ApiTestGetResponse200;
use JoliCode\Slack\Api\Model\ChannelsHistoryGetResponse200;
use JoliCode\Slack\Api\ChatPostMessagePostResponse200;
use JoliCode\Slack\Api\Model\ObjsFile;
use JoliCode\Slack\Api\Model\SearchMessagesGetResponsedefault;
use JoliCode\Slack\Api\Model\SearchMessagesGetResponse200;
use JoliCode\Slack\Api\Model\UsersListGetResponse200;
use JoliCode\Slack\ClientFactory;
use JoliCode\Slack\Exception\SlackErrorResponse;

$client = new Client($account_sid, $twilio_token);
$sclient = JoliCode\Slack\ClientFactory::create($bot_token);

if ($_REQUEST['q'] == "smstoslack") {

  $query = array(
      'token' 			=> $oauthtoken,
      'sort_dir' 		=> 'desc',
      'sort' 			=> 'timestamp',
      'count' 			=> 1,
      'query' 			=> 'in:#'.$channel_name.' {'.$_REQUEST['From'].'}'
  );

$result_ts = json_decode(file_get_contents("https://slack.com/api/search.messages?".http_build_query($query)), true);

    try {
        // This method requires your token to have the scope "chat:write"
        // Check if timestamp is valid for another thread and if that thread is still within 24 hours, otherwise create new thread.
        if (isset($result_ts["messages"]["matches"][0])) {
		$ts_reply = $result_ts["messages"]["matches"][0]["ts"];
		
        // Convert and compare microtime to 24 hour range.
        $withdot  = explode(".",$ts_reply);
        $nows     = explode(" ",microtime());


        $last     = DateTime::createFromFormat("m-d-y H:i:s", date("m-d-y H:i:s", $withdot[0]));
        $now      = date("m-d-y H:i:s", $nows[1]);
        $date_y   = date_sub(DateTime::createFromFormat("m-d-y H:i:s", $now),date_interval_create_from_date_string("1 day"));
		} 

        // Basically if the thread doesnt exist or the thread age + 24 hours is greater than the time now.
        if (!isset($ts_reply) || ($last < $date_y)) {
        $post_ts = $sclient->chatPostMessage([
            'username' 			=> $app_username,
            'channel' 			=> $channel_name,
            'reply_broadcast' 	=> true,
            'text' 				=> "Conversation With: {".$_REQUEST['From']."}\n\n".$_REQUEST['Body']
        ])->getTs();

        $result = $sclient->chatPostMessage([
            'username' 			=> $app_username,
            'channel' 			=> $channel_name,
            'text' 				=> "------Start Typing Below------",
            'thread_ts' 		=> $post_ts
        ]);
      }
      else {
        // respond to current thread
        $result = $sclient->chatPostMessage([
            'username' 			=> $app_username,
            'channel' 			=> $channel_name,
            'text' 				=> $_REQUEST['Body'],
            'thread_ts' 		=> $ts_reply
        ]);
      }
    } catch (SlackErrorResponse $e) {
        $result = $sclient->chatPostMessage([
            'username' 			=> $app_username,
            'channel' 			=> $channel_name,
            'text' 				=> "Failed to send the message"
        ]);
    }
}
else if ($_REQUEST['q'] == "slacktocell") {
  // Use the client to do fun stuff like send text messages!
  $data = json_decode(file_get_contents('php://input'), true);
  if (isset($data["challenge"])) {
      $message = [
          "challenge" => $data["challenge"]
      ];

      header('Content-Type: application/json');
      echo json_encode($message);
  }


  if ((isset($data["event"])) && ($data["event"]["channel"] == $channel_id) && (!empty($data["event"]["user"])) && (!empty($data["event"]["thread_ts"])) ) {
	   //Get the Phone Number of the Thread
	   $post_ts = $sclient->ConversationsHistory([
			'token' 				=> $oauthtoken,
			'channel' 				=> $channel_id,
			'oldest' 				=> floatval($data["event"]["thread_ts"]),
			'limit' 				=> 1,
			'inclusive' 			=> 1
		  ])->getMessages()[0]->getText();

		// Extract the {+phone number}
		  $regex = '/{\K[^}]*(?=})/m';
		  preg_match_all($regex, $post_ts, $results);
		  $phone_no = trim(implode("", $results[0]));
		
			
		$client->messages->create(
		  $phone_no,
		  array(
			  'from' 			=> $sms_number,
			  'body' 			=> $data["event"]["text"]
		  )
		);
	}
}
?>
