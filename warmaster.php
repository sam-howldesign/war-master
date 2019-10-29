<?php
/**
 * 
 * LINE bot backend for War Master
 * 
 * LINE $event format:
 * 
 * (
 *   [type] => message
 *   [replyToken] => 8175023ee4fe41d5be101d68a1ddaa2b
 *   [source] => Array
 *       (
 *           [userId] => U6f73e309048616c04b001536c9e6aa05
 *           [type] => user
 *       )
 *   [timestamp] => 1531847852167
 *   [message] => Array
 *       (
 *           [type] => text
 *           [id] => 8279791191618
 *           [text] => test
 *       )
 * )
 * 
 * Database:
 * users
 * id, lineid, battlegroup, linename
 * 
 * champs
 * id, lineid, name, stars, rank, level, sig
 * 
 * 
 */
 

include('./champs.php');
include('./defense-order.php');
//error_log(print_r($champs, true));


require_once('./LINEBotTiny.php');
require_once('./channelinfo.php');
$channelAccessToken = $channelInfo['channelAccessToken'];
$channelSecret = $channelInfo['channelSecret'];
$client = new LINEBotTiny($channelAccessToken, $channelSecret);
foreach ($client->parseEvents() as $event) {
    switch ($event['type']) {
        case 'message':
            //error_log(print_r($event, true));
            $lineid = $event["source"]['userId'];
            
            //file_put_contents("./out.txt",print_r(json_decode($userinfo), true));

            $message = $event['message'];
            switch ($message['type']) {
                case 'text':

                    $replyText = processMessage($lineid, $message['text']);
                    $client->replyMessage(array(
                        'replyToken' => $event['replyToken'],
                        'messages' => array(
                            array(
                                'type' => 'text',
                                'text' => $replyText
                            )
                        )
                    ));
                    break;
                default:
                    error_log("Unsupported message type: " . $message['type']);
                    break;
            }
            break;
        default:
            error_log("Unsupporeted event type: " . $event['type']);
            break;
    }
};

function processMessage($lineid, $input){
    $retText = '';
    //if it starts with "defense"
    //defense, War Machine, 4*, 4, 40, 20
    //split on comma and make sure there are at least 5 fields
    $commandArray = explode(",", $input);
    global $champs;
    if (preg_match('/defense/i',$commandArray[0]) ){
        if (count($commandArray) >= 5 ){
            $notEnoughInformation = false;
            //set or update defense for this user
            //$retText = "Command Received.  You sent: $input ";
            //verify data and add to champ db
            $name = $commandArray[1];
            //strip down the name, verify it is legit
            $name = preg_replace("/[^a-z0-9]/", '', strtolower($name));
            if (array_key_exists($name, $champs)){
                //$retText .= "You are adding {$champs[$name]}\n";
            }else {
                $retText .= "I'm sorry I don't know who $name {$commandArray[1]} is.\n";
                $notEnoughInformation = true;
            }
            $stars = intval($commandArray[2]);
            if ($stars > 0){
                //$retText .= "$stars star\n";
            }else {
                $retText .= "Please include a number of stars.\n";
                $notEnoughInformation = true;
            }
            $rank = intval($commandArray[3]);
            if ($rank > 0){
                //$retText .= "Rank $rank\n";
            }else {
                $retText .= "Please include a rank.\n";
                $notEnoughInformation = true;
            }
            $level = intval($commandArray[4]);
            if ($level > 0){
                //$retText .= "Level $level\n";
            }else {
                $retText .= "Please include a level.\n";
                $notEnoughInformation = true;
            }
            $sig = intval($commandArray[5]);
            if ($sig < 0){
                $sig = 0;
            }
            
            if (!$notEnoughInformation){
                //connect to database and save or update this defender
                $mysqli = new mysqli("localhost", "warmaster", "warmaster", "warmaster");
                if ($mysqli->connect_errno){
                    $retText .= "Problems saving, please let an officer know.";
                }else{
                    $replace_sql = "
                        replace into champs 
                            (lineid, name, stars, rank, level, sig) 
                            values 
                            ('$lineid', '{$champs[$name]}', '$stars', '$rank','$level','$sig') 
                    ";
                    $mysqli->query($replace_sql);
                    $retText .= "Your $stars* {$champs[$name]} $rank/$level sig $sig, has been added";
                }
            }

        }else{
            $retText = "You didn't give enough information about your defender.  You sent: $input ";
        }
    }elseif (preg_match('/remove/i',$commandArray[0]) ){
        $name = $commandArray[1];
        //strip down the name, verify it is legit
        $name = preg_replace("/[^a-z0-9]/", '', strtolower($name));
        if (array_key_exists($name, $champs)){
            //$retText .= "You are adding {$champs[$name]}\n";
        }else {
            $retText .= "I'm sorry I don't know who $name {$commandArray[1]} is.\n";
            $notEnoughInformation = true;
        }
        if (!$notEnoughInformation){
            //connect to database and save or update this defender
            $mysqli = new mysqli("localhost", "warmaster", "warmaster", "warmaster");
            if ($mysqli->connect_errno){
                $retText .= "Problems saving, please let an officer know.";
            }else{
                $sql = "
                    delete from champs
                    where lineid = '$lineid' and name = '{$champs[$name]}';
                ";
                $mysqli->query($sql);
                $retText .= "Your {$champs[$name]} has been removed from your defense options";
            }
        }

    }elseif ( preg_match('/\?/i',$commandArray[0]) || 
                preg_match('/help/i',$commandArray[0]) ||
                preg_match('/commands/i',$commandArray[0])
            ){
        //show help text
        $retText = " I am a bot created to help with Alliance stuff.  You can type messages to me and I can help with things like defender choices (for diversity).\n ";
        $retText .= " Commands: \n\n ";
        $retText .= " help or ? or commands \n";
        $retText .= " - Show this message \n\n";
        $retText .= " defense,Kamala Khan,4*,4,40,62\n";
        $retText .= " - Adds or changes your defense to include a 4* 440 KK sig 62 \n\n";
        $retText .= " show \n";
        $retText .= " - Shows all defenders you have uploaded \n\n";
        $retText .= " remove,blade \n";
        $retText .= " - Removes blade from your defense \n\n";
        $retText .= " bg1 \n";
        $retText .= " - Sets you as being in battlegroup 1 \n\n";
        $retText .= " defenders \n";
        $retText .= " - Shows all best picked defenders in your BG \n\n";
        $retText .= " diversity \n";
        $retText .= " - Shows all diversity pick defenders in your BG \n\n";

        
    }elseif ( preg_match('/show/i',$commandArray[0]) ){
        $mysqli = new mysqli("localhost", "warmaster", "warmaster", "warmaster");
        if ($mysqli->connect_errno){
            $retText .= "Problems hitting the DB, please let an officer know.";
        }else{
            $sql = "
                select * from champs where lineid = '$lineid'
            ";
            $res = $mysqli->query($sql);
            while ($row = $res->fetch_assoc()) {
                $retText .= "{$row['stars']}* {$row['name']} {$row['rank']}/{$row['level']} sig {$row['sig']}\n";
            }
        }
    }elseif ( preg_match('/^bg\d$/i',$commandArray[0]) ){
        $mysqli = new mysqli("localhost", "warmaster", "warmaster", "warmaster");
        if ($mysqli->connect_errno){
            $retText .= "Problems hitting the DB, please let an officer know.";
        }else{
            global $client;
            $userinfo = json_decode($client->getUsername($lineid));
            $bg = strtolower($commandArray[0]);
            $replace_sql = "
                    replace into users 
                        (lineid, battlegroup, linename) 
                        values 
                        ('$lineid', '$bg', '{$userinfo->displayName}');
                ";
            $mysqli->query($replace_sql);
            
            $retText .= "{$userinfo->displayName} you are currently in $bg ";
            
        }
    }elseif ( preg_match('/defenders/i',$commandArray[0]) ){
        $retText .= calculateDefenders($lineid, false);
    }elseif ( preg_match('/diversity/i',$commandArray[0]) ){
        $retText .= calculateDefenders($lineid, true);
    }else{
        $retText = "Sorry I don't know what you want try typing a question mark (?)  ";
        error_log("didn't understand: {$commandArray[0]}\n");
    }
    

    return $retText;
}

function calculateDefenders($lineid, $DIVERSITY){
    $retText = "";
    //for this users bg, show what everyone has entered
    $mysqli = new mysqli("localhost", "warmaster", "warmaster", "warmaster");
    if ($mysqli->connect_errno){
        $retText .= "Problems hitting the DB, please let an officer know.";
    }else{
        $sql = "
            select 
              u.linename, c.* 
            from 
              champs c
              join  
              users u on c.lineid = u.lineid and u.battlegroup in (select battlegroup from users where lineid = '$lineid');
        ";
        $res = $mysqli->query($sql);
        $diverse_defense = array();
        while ($row = $res->fetch_assoc()) {
            $rowDefender = new stdClass();
            $rowDefender->user = $row['linename'];
            $rowDefender->stars = $row['stars'];
            $rowDefender->name = $row['name'];
            $rowDefender->rank = $row['rank'];
            $rowDefender->level = $row['level'];
            $rowDefender->sig = $row['sig'];
            $rowDefender->challengerRating = calculateChallengerRating($row['stars'],$row['rank']);
            array_push($diverse_defense, $rowDefender);
        }

        usort($diverse_defense, "diversitySort");
        //this is every entered defender for a specific battlegroup ordered by toughness
        $defendersByUser = array();
        $diversityUsed = array();
        foreach ($diverse_defense as $defender){
            if ($DIVERSITY && count($defendersByUser[$defender->user]) < 5 ){
                if (empty($diversityUsed) || !array_key_exists($defender->name, $diversityUsed)){
                    $diversityUsed[$defender->name] = 1;
                    $defender->duplicate = false;
                }else {
                    $defender->duplicate = true;
                    continue;
                }
                $defendersByUser[$defender->user][] = $defender;
            }else {
                $defendersByUser[$defender->user][] = $defender;
            }
            
            //$retText .= " {$defender->user} {$defender->stars}* {$defender->name} {$defender->rank}/{$defender->level} sig {$defender->sig} \n"; 
        }
        //$retText .= print_r($defendersByUser, true);
        foreach($defendersByUser as $user => $defenderArray){
            $retText .= "\n$user\n";
            $count = 0;
            foreach($defenderArray as $defender){
                if ($count == 5 ){
                    break;
                }
                if ($DIVERSITY){
                    if ($defender->duplicate){
                        $retText .= "!!! {$defender->stars}* {$defender->name} {$defender->rank}/{$defender->level} sig {$defender->sig} \n"; 
                        //$count++;
                    }else{
                        $retText .= " {$defender->stars}* {$defender->name} {$defender->rank}/{$defender->level} sig {$defender->sig} \n"; 
                        $count++;
                    }
                }else{
                    $retText .= " {$defender->stars}* {$defender->name} {$defender->rank}/{$defender->level} sig {$defender->sig} \n"; 
                    $count++;
                }
                
            }
        }
    }
    
    //next show arse for diversity
    return $retText;
}

function calculateChallengerRating($stars, $rank){
    $challengerRating = 0;
        //6* 2/35  
    //5* 5/65
    //6* 1/25
    //5* 4/55
    //5* 3/45
    //4* 5/50
    //5* 2/35
    //4* 4/40
    //5* 1/35
    //4* 3/30
    //3* 4/40
    //4* 2/20
    //3* 3/30
    //4* 1/10
    //3* 2/20
    if ($stars == 6 && $rank == 5){
        $challengerRating = 150;
    }elseif($stars == 6 && $rank == 4){
        $challengerRating = 140;
    }elseif($stars == 6 && $rank == 3){
        $challengerRating = 130;
    }elseif( 
            ($stars == 6 && $rank == 2) ||
            ($stars == 5 && $rank == 5)
        ){
        $challengerRating = 120;
    }elseif( 
            ($stars == 6 && $rank == 1) ||
            ($stars == 5 && $rank == 4)
        ){
        $challengerRating = 110;
    }elseif( 
            ($stars == 5 && $rank == 3) ||
            ($stars == 4 && $rank == 5)
        ){
        $challengerRating = 100;
    }elseif( 
            ($stars == 5 && $rank == 2) ||
            ($stars == 4 && $rank == 4)
        ){
        $challengerRating = 90;
    }elseif( 
            ($stars == 5 && $rank == 1) ||
            ($stars == 4 && $rank == 3) ||
            ($stars == 3 && $rank == 4)
        ){
        $challengerRating = 80;
    }elseif( 
            ($stars == 4 && $rank == 2) ||
            ($stars == 3 && $rank == 3)
        ){
        $challengerRating = 70;
    }elseif( 
            ($stars == 4 && $rank == 1) ||
            ($stars == 3 && $rank == 2)
        ){
        $challengerRating = 60;
    }

    return $challengerRating;
}

function diversitySort($a, $b) {
    global $best_defenders;

    if ($a->challengerRating > $b->challengerRating){
        return -1;
    } elseif ($a->challengerRating < $b->challengerRating) {
        //B is larger
        return 1;
    } else {
        //same challenger rating - yield to higher star rating
        if ($a->stars > $b->stars) {
            return -1;
        } elseif ($a->stars < $b->stars){
            return 1;
        }else {
            //same challenger rating and star level, yield to level
            if ($a->level > $b->level){
                return -1;
            } elseif ($a->level < $b->level){
                return 1;
            }else {
                if ($best_defenders[$a->name] > $best_defenders[$b->name]){
                    return -1;
                }elseif ($best_defenders[$a->name] < $best_defenders[$b->name]){
                    return 1;
                }else {
                    //sig as last resort
                    return strcmp($b->sig, $a->sig);
                }
                
            }
        }
    }
}
?>