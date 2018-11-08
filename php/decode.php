<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
                      "http://www.w3.org/TR/html4/strict.dtd">
<HTML>
<HEAD>
  <META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=us-ascii">
  <TITLE>GNU Backgammon ID Decoder</title>
  <META NAME="author" CONTENT="Petr Kadlec">
  <META NAME="description" CONTENT="Decoder of Position and Match IDs from GNU Backgammon">
  <META NAME="keywords" CONTENT="gnubg GNU Backgammon Position Match ID matchid positionid">
</HEAD>
<BODY LANG=en>
 <H1>GNU Backgammon ID Decoder</H1>
 <p>
<?php
/*
 * decode_core.php -- PHP script to decode position and match ID
 *                    from GNU Backgammon
 *
 * by Petr Kadlec <mormegil@centrum.cz>, 2003-2004.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of version 2 of the GNU General Public License as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

  function get_bit($bitarray, $curr_pos) {
    $byte_pos = $curr_pos >> 3;
    $bit_pos = $curr_pos & 7;
    return (ord($bitarray[$byte_pos]) >> $bit_pos) & 1;
  }

  function decode_matchid($id, &$cube_value, &$cube_owner, &$on_roll, &$is_crawford,
                          &$game_state, &$whose_turn, &$double_offered, &$resign_offered,
                          &$dice, &$match_len, &$score) {

    $decoded = base64_decode($id);
    if (strlen($decoded) <> 9) {
      return false;
    }

    $lg_cube_value = ord($decoded[0]) & 0xf;
    $cube_value = 1 << $lg_cube_value;
    $cube_owner = (ord($decoded[0]) >> 4) & 0x3;
    $on_roll = (ord($decoded[0]) >> 6) & 0x1;
    $is_crawford = (ord($decoded[0]) >> 7) & 0x1;
    $game_state = ord($decoded[1]) & 0x7;
    $whose_turn = (ord($decoded[1]) >> 3) & 0x1;
    $double_offered = (ord($decoded[1]) >> 4) & 0x1;
    $resign_offered = (ord($decoded[1]) >> 5) & 0x3;
    $dice[0] = ((ord($decoded[1]) >> 7) & 0x1) | ((ord($decoded[2]) << 1) & 0x6);
    $dice[1] = (ord($decoded[2]) >> 2) & 0x7;
    $match_len = ((ord($decoded[2]) >> 5) & 0x7) | (ord($decoded[3]) << 3) | ((ord($decoded[4]) & 0xf) << 11);
    $score[0] = ((ord($decoded[4]) >> 4) & 0x7) | (ord($decoded[5]) << 4) | ((ord($decoded[6]) & 0x7) << 12);
    $score[1] = ((ord($decoded[6]) >> 3) & 0x1f) | (ord($decoded[7]) << 5) | ((ord($decoded[8]) & 0x3) << 13);

    // basic validity tests -- not all, only those really necessary
    if ($cube_owner == 2 || $game_state > 4) return false;

    return true;
  }

  function decode_positionid($id, &$board, $on_roll) {
    $decoded = base64_decode($id . "=");
    if (strlen($decoded) <> 10) {
      return false;
    }
    $currpos = 0;

    $sum = 0;
    for ($i = 0; $i < 25; $i++) {
      $board[0][$i] = $board[1][$i] = 0;
      while (get_bit($decoded, $currpos++)) $board[$on_roll][$i]++;
      $sum += $board[$on_roll][$i];
    }
    if ($sum > 15) {
      return false;
    }
    $sum = 0;
    for ($i = 0; $i < 25; $i++) {
      while (get_bit($decoded, $currpos++)) $board[!$on_roll][$i]++;
      $sum += $board[!$on_roll][$i];
    }
    if ($sum > 15) {
      return false;
    }

    for ($i = 0; $i < 24; $i++) {
      if ($board[1][23 - $i] && $board[0][$i]) {
        return false;
      }
    }

    return true;
  }

  function count_pips($board) {
    $pips[0] = $pips[1] = 0;
    for ($i = 0; $i < 25; $i++) {
      $pips[0] += $board[0][$i] * ($i + 1);
      $pips[1] += $board[1][$i] * ($i + 1);
    }
    return $pips;
  }

  function print_image($image, $alt) {
    echo "<img src=\"img/$image.png\" class=block alt=\"$alt\">";
  }

  function print_point($point0, $point1, $color, $up) {
    if ($point0) {
      /* player 0 owns the point */

      $sz = sprintf("b-%s%s-x%d", $color ? 'g' : 'r', $up ? 'd' : 'u', $point0);
      $alt = sprintf("%1xX", $point0);
    } else if ($point1) {
      /* player 1 owns the point */

      $sz = sprintf("b-%s%s-o%d", $color ? 'g' : 'r', $up ? 'd' : 'u', $point1);
      $alt = sprintf("%1xO", $point1);
    } else {
      /* empty point */

      $sz = sprintf("b-%s%s", $color ? 'g' : 'r', $up ? 'd' : 'u');
      $alt = "&nbsp;";
    }

    print_image($sz, $alt);
  }

  function print_html($posid, $matchid, $board, $turn, $clockwise, $cube_owner, $cube_value, $whose_move, $dice, $doubled) {
    for ($i = 0; $i < 2; $i++) {
      $off[$i] = 15;
      for ($j = 0; $j < 25; $j++)
        $off[$i] -= $board[$i][$j];
    }

    if ($off[0] < 0 || $off[1] < 0) {
      echo " <p>The specified position ID is invalid (too many pieces).\n";
      return;
    }

    /* top line with board numbers */

    echo "<table cellpadding=\"0\" border=\"0\" cellspacing=\"0\" style=\"margin: 0; padding: 0; border: 0\">\n";
    echo "<tr>";
    echo "<td colspan=\"15\">";
    print_image($turn ? "b-hitop" : "b-lotop",
                $turn ? "+-13-14-15-16-17-18-+---+-19-20-21-22-23-24-+" : "+-12-11-10--9--8--7-+---+--6--5--4--3--2--1-+");
    echo "</td></tr>\n";

    /* display left bearoff tray */

    echo "<tr>";

    echo "<td rowspan=\"2\">";
    print_image($clockwise ? sprintf("b-loff-x%d", $off[1]) : "b-loff-x0", "|");
    echo "</td>";

    /* display player 0's outer quadrant */

    for($i = 0; $i < 6; $i++ ) {
      echo "<td rowspan=\"2\">";
      if ($clockwise)
        print_point($board[1][$i], $board[0][23-$i], !($i & 1), true);
      else
        print_point($board[1][11-$i], $board[0][12+$i], !($i & 1), true);
      echo "</td>";
    }

    /* display cube */

    echo "<td>";
    print_image(!$cube_owner ? sprintf("b-ct-%d", $cube_value) : "b-ct", "");
    echo "</td>";

    /* display player 0's home quadrant */

    for ($i = 0; $i < 6; $i++) {
      echo "<td rowspan=\"2\">";
      if ($clockwise)
        print_point($board[1][$i+6], $board[0][17-$i], !($i & 1), true);
      else
        print_point($board[1][5-$i], $board[0][18+$i], !($i & 1), true);
      echo "</td>";
    }

    /* right bearoff tray */

    echo "<td rowspan=\"2\">";
    print_image(!$clockwise ? sprintf("b-roff-x%d", $off[1]) : "b-roff-x0", "|");
    echo "</td>";

    echo "</tr>\n";

    /* display bar */

    echo "<tr>";
    echo "<td>";
    print_image(sprintf("b-bar-o%d", $board[1][24]),
                $board[1][24] ? sprintf("|%1X&nbsp;|", $board[1][24]) : "|&nbsp;&nbsp;&nbsp;|");
    echo "</td>";
    echo "</tr>\n";

    /* center of board */

    echo "<tr>";

    /* left part of bar */

    echo "<td>";
    if ($clockwise)
      print_image("b-midlb", "|");
    else
      print_image($turn ? "b-midlb-o" : "b-midlb-x", "|");
    echo "</td>";

    /* center of board */

    echo "<td colspan=\"6\">";

    if (!$whose_move && $dice[0] && $dice[1]) {
      /* player has rolled the dice */
      print_image(sprintf("b-midl-x%d%d", $dice[0], $dice[1]), sprintf("&nbsp;&nbsp;%d%d&nbsp;&nbsp;", $dice[0], $dice[1]));
    } elseif (!$whose_move && $doubled) {
      /* player 0 has doubled */
      print_image(sprintf("b-midl-c%d", 2*$cube_value), sprintf("&nbsp;[%d]&nbsp;&nbsp;", 2*$cube_value));
    } else {
      /* player 0 on roll */
      print_image("b-midl", "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;");
    }

    echo "</td>";

    /* centered cube */

    echo "<td>";
    print_image(($cube_owner < 0 && !$doubled) ? sprintf("b-midc-%d", $cube_value) : "b-midc", "|");
    echo "</td>";

    /* player 1 */

    echo "<td colspan=\"6\">";

    if ($whose_move && $dice[0] && $dice[1]) {
      /* player 1 has rolled the dice */
      print_image(sprintf("b-midr-o%d%d", $dice[0], $dice[1]), sprintf("&nbsp;&nbsp;%d%d&nbsp;&nbsp;", $dice[0], $dice[1]));
    } elseif ($whose_move && $doubled) {
      /* player 1 has doubled */
      print_image(sprintf("b-midr-c%d", 2*$cube_value), sprintf("&nbsp;[%d]&nbsp;&nbsp;", 2*$cube_value));
    } else {
      /* player 1 on roll */
      print_image("b-midr", "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;");
    }

    echo "</td>";

    /* right part of bar */

    echo "<td>";
    if (!$clockwise)
      print_image("b-midrb", "|");
    else
      print_image($turn ? "b-midrb-o" : "b-midrb-x", "|");
    echo "</td>";

    /* display left bearoff tray */

    echo "<tr>";

    echo "<td rowspan=\"2\">";
    print_image($clockwise ? sprintf("b-loff-o%d", $off[0]) : "b-loff-o0", "|");
    echo "</td>";

    /* display player 1's outer quadrant */

    for ($i = 0; $i < 6; $i++ ) {
      echo "<td rowspan=\"2\">";
      if ($clockwise)
        print_point($board[1][23-$i], $board[0][$i], $i & 1, false);
      else
        print_point($board[1][12+$i], $board[0][11-$i], $i & 1, false);
      echo "</td>";
    }

    /* display bar */

    echo "<td>";
    print_image(sprintf("b-bar-x%d", $board[0][24]),
                $board[0][24] ? sprintf("|%1X&nbsp;|", $board[0][24]) : "|&nbsp;&nbsp;&nbsp;|");
    echo "</td>";

    /* display player 1's outer quadrant */

    for ($i = 0; $i < 6; $i++) {
      echo "<td rowspan=\"2\">";
      if ($clockwise)
        print_point($board[1][17-$i], $board[0][$i+6], $i & 1, false);
      else
        print_point($board[1][18+$i], $board[0][5-$i], $i & 1, false);
      echo "</td>";
    }

    /* right bearoff tray */

    echo "<td rowspan=\"2\">";
    print_image(!$clockwise ? sprintf("b-roff-o%d", $off[0]) : "b-roff-o0", "|");
    echo "</td>";

    echo "</tr>\n";

    /* display cube */

    echo "<tr>";
    echo "<td>";
    print_image($cube_owner == 1 ? sprintf("b-cb-%d", $cube_value) : "b-cb", "");
    echo "</td>";
    echo "</tr>\n";

    /* bottom */

    echo "<tr>";
    echo "<td colspan=\"15\">";
    print_image($turn ? "b-lobot" : "b-hibot",
                $turn ? "+-12-11-10--9--8--7-+---+--6--5--4--3--2--1-+" : "+-13-14-15-16-17-18-+---+-19-20-21-22-23-24-+");
    echo "</td>";
    echo "</tr>";

    echo "</table>\n\n";

    /* pip counts */

    echo "<p>";

    $pips = count_pips($board);
    printf("Pip counts: Player on turn %d, the other player %d<br>\n", $pips[!$turn], $pips[$turn]);

    /* position ID */

    echo "<span class=positionid>";

    echo "Position ID: <tt>$posid</tt>";
    if ($matchid) echo " Match ID: <tt>$matchid</tt>";
    echo "<br>\n";

    echo "</span>";

    echo "</p>";
  }

  $_GET = $HTTP_GET_VARS;

  if (isset($_GET['lang'])) {
    $lang = $_GET['lang'];
  }
  //putenv("LANG=" . $lang);
  //putenv("LANG=cs");

  bindtextdomain("gnubg", "./locale");
  textdomain("gnubg");

  $have_posid = isset($_GET['pos']);
  $have_matchid = isset($_GET['match']);

  if ($have_posid) $pos = $_GET['pos'];
  if ($have_matchid) {
    $match = $_GET['match'];
    if (!strlen($match)) $have_matchid = false;
  }

  //$pos = "zu8OAABgRgXhOQ";//"YEYF4TnO7w4AAA";
  //$have_posid = true;
  //$match = "UQngAAAAAAAA";//"EQH2AAAAAAAA";
  //$have_matchid = true;

  if (!$have_posid && !$have_matchid) {

    echo " <form action=\"decode.php\"method=\"GET\">\n";
    echo "    <p><label accesskey=P>";
    echo gettext("Position ID: ");
    echo "<input type=\"text\" name=\"pos\"></label>\n";
    echo "       <label accesskey=M>";
    echo gettext("Match ID: ");
    echo "<input type=\"text\" name=\"match\"></label><br>\n";
    echo "       <label accesskey=R><input type=\"checkbox\" name=\"dir\" value=\"cw\">";
    echo gettext("Reverse direction of play");
    echo "</label><br>\n";
    echo "       <input type=\"submit\" value=\"";
    echo gettext("Show");
    echo "\">\n";
    echo " </form>\n";

  } else {
    if ($have_matchid) {
      if (!decode_matchid($match, $cube_value, $cube_owner, $on_roll, $is_crawford,
                          $game_state, $whose_turn, $double_offered, $resign_offered,
                          $dice, $match_len, $score)) {
        echo "<p>";
        printf(gettext("Illegal match ID '%s'\n"), $match);
        echo "</BODY></HTML>\n";
        return;
      }
    } else {
      // default values when Match ID not provided
      $cube_value = 1;
      $cube_owner = 0;
      $on_roll = 0;
      $is_crawford = false;
      $game_state = 0;
      $whose_turn = 0;
      $double_offered = 0;
      $resign_offered = 0;
      $dice[0] = $dice[1] = 0;
      $match_len = 0;
      $score[0] = $score[1] = 0;
    }

    if ($have_posid) {
      if (!decode_positionid($pos, $board, $on_roll)) {
        echo "<p><tt>$pos</tt>: ";
        echo gettext("Illegal position.");
        echo "</BODY></HTML>\n";
        return;
      }
    }

    if ($have_posid) {
      if (isset($_GET['dir'])) {
        $clockwise = !strcasecmp($_GET['dir'], 'cw');
      } else $clockwise = false;
      print_html($pos, $match, $board, $whose_turn, $clockwise, $cube_owner, $cube_value, $on_roll, $dice, $double_offered);
    } else {
      // if the position ID was not given, print the matchid (it is otherwise included next to the position ID)
      echo "<p>";
      echo "<span class=positionid>";
      echo gettext("Match ID: ");
      echo "<tt>$match</tt>";
      echo "<br>\n";
      echo "</span>";
      echo "</p>";
    }

    if ($have_matchid) {
      echo " <p>";
      if (!$have_posid) {
        switch ($cube_owner) {
        case 0:
        case 1:
          printf(gettext("The cube is at %d, and is owned by %s."), $cube_value, gettext("Player") . " " . $cube_owner);
          break;

        case 3:
          printf(gettext("The cube is at %d, and is centred."), $cube_value);
          break;
        }
        echo "<br>\n";

        echo gettext("Player on roll:");
        echo " $player_on_roll.<br>\n";
        if ($die1) {
          printf(gettext(" %s to play %d%d"), (string)$whose_turn, $die1, $die2);
        } else {
          printf(gettext("%s has not yet rolled the dice.\n"), (string)$whose_turn);
        }
        echo "<br>\n";
        if ($double_offered) echo " A double is being offered.<br>\n";

        if ($die1) echo " Dice throw: $die1$die2.<br>\n";
        else echo " Dice have not yet been thrown.<br>\n";

      }

      switch ($game_state) {
      case 0:
          echo "No game started.<br>\n";
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
      }

      if ($resign_offered) echo " A ${resign_offered}-point resignation is being offered.<br>\n";

      echo " Score: $score[0] &ndash; $score[1]";
      if ($match_len) {
        echo " (match to $match_len points";
        if ($is_crawford) echo ", Crawford game";
        echo ")<br>\n";
      } else echo " (money session)<br>\n";
    }
  }
?>

 <hr>
 <p><small>&copy; Petr Kadlec, 2004<br>
           Based on <a href="http://www.gnu.org/software/gnubg/">GNU Backgammon 0.14-dev</a>,
           (HTML Export version 1.152 by Joern Thyssen)
    </small>

</BODY>
</HTML>
