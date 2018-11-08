<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0//EN"
                      "http://www.w3.org/TR/REC-html40/strict.dtd">

<HTML>
<HEAD>
  <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=us-ascii">
  <TITLE>GNU Backgammon Match ID Decoder</title>
  <META NAME="author" CONTENT="Petr Kadlec">
  <META NAME="description" CONTENT="Decoder of Match IDs from GNU Backgammon">
  <META NAME="keywords" CONTENT="gnubg GNU Backgammon Match ID matchid">
</HEAD>
<BODY LANG=en>
 <H1>GNU Backgammon Match ID Decoder</H1>
 <p>
<?php

 if (!isset($_GET['id'])) {

    echo " <form action=\"decode_matchid.php\"method=\"GET\">\n";
    echo "    <p>Match ID: <input type=\"text\" name=\"id\">\n";
    echo "       <input type=\"submit\" value=\"Decode!\">\n";
    echo " </form>\n";

 } else {

  $decoded = base64_decode($_GET['id']);

  /*  for ($i = 0; $i < 9; $i++)
    printf("%2x ", ord($decoded[$i]));
  echo "\n";*/
  
  $lg_cube_value = ord($decoded[0]) & 0xf;
  $cube_value = 1 << $lg_cube_value;
  $cube_owner = (ord($decoded[0]) >> 4) & 0x3;
  $player_on_roll = (ord($decoded[0]) >> 6) & 0x1;
  $is_crawford = (ord($decoded[0]) >> 7) & 0x1;
  $game_state = ord($decoded[1]) & 0x3;
  $whose_turn = (ord($decoded[1]) >> 3) & 0x1;
  $double_offered = (ord($decoded[1]) >> 4) & 0x1;
  $resign_offered = (ord($decoded[1]) >> 5) & 0x3;
  $die1 = ((ord($decoded[1]) >> 7) & 0x1) | ((ord($decoded[2]) << 1) & 0x6);
  $die2 = (ord($decoded[2]) >> 2) & 0x7;
  $match_len = ((ord($decoded[2]) >> 5) & 0x7) | (ord($decoded[3]) << 3) | ((ord($decoded[4]) & 0xf) << 11);
  $score0 = ((ord($decoded[4]) >> 4) & 0x7) | (ord($decoded[5]) << 4) | ((ord($decoded[6]) & 0x7) << 12);
  $score1 = ((ord($decoded[6]) >> 3) & 0x1f) | (ord($decoded[7]) << 5) | ((ord($decoded[8]) & 0x3) << 13);
  
  echo " Cube value: $cube_value<br>\n";
  echo " Cube state: ";
  switch ($cube_owner) {
  case 0:
      echo "Player 0 owns the cube.<br>\n";
      break;
      
  case 1:
      echo "Player 1 owns the cube.<br>\n";
      break;
      
  case 3:
      echo "Cube centered.<br>\n";
      break;
      
  default:
      echo "--invalid--<br>\n";
  }
  
  echo " Player $player_on_roll is on roll.<br>\n";
  
  if ($is_crawford) echo " This IS a Crawford game.<br>\n";
//  else echo " This is not a Crawford game.<br>\n";
 
  echo " Game state: ";
  switch ($game_state) {
  case 0:
      echo "No game started.<br>\n";
      break;
      
  case 1:
      echo "Playing a game.<br>\n";
      break;
      
  case 2:
      echo "Game is over.<br>\n";
      break;
      
  case 3:
      echo "Game was resigned.<br>\n";
      break;
      
  case 4:
      echo "Game was ended by dropping a cube.<br>\n";
      break;
  
  default:
      echo "--invalid--<br>\n";
  }

  echo " It is player $whose_turn's turn.<br>\n";

  if ($double_offered) echo " A double is being offered.<br>\n";
  if ($resign_offered) echo " A ${resign_offered}-point resignation is being offered.<br>\n";

  if ($die1) echo " Dice throw: $die1$die2.<br>\n";
  else echo " Dice have not yet been thrown.<br>\n";

  echo " Match length: ";
  if ($match_len) echo "$match_len.<br>\n";
  else echo "unlimited.<br>\n";
  echo " Current score: $score0 &ndash; $score1.<br>\n";

 }
?>

 <hr>
 <p><small>&copy; Petr Kadlec, 2004</small><br>

</BODY>
</HTML>
