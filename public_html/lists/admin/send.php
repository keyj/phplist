<?php
require_once dirname(__FILE__).'/accesscheck.php';

$access = accessLevel("send");
switch ($access) {
  case "owner":
    $subselect = " where owner = ".$_SESSION["logindetails"]["id"];
    $ownership = ' and owner = '.$_SESSION["logindetails"]["id"];
    break;
  case "all":
    $subselect = "";
    $ownership = '';
    break;
  case "none":
  default:
    $subselect = " where id = 0";
    $ownership = " and id = 0";
    break;
}
$some = 0;

# handle commandline
if ($GLOBALS["commandline"]) {
#  error_reporting(63);
  $cline = parseCline();
  reset($cline);
  if (!$cline || !is_array($cline) || !$cline["s"] || !$cline["l"]) {
    clineUsage("-s subject -l list [-f from] < message");
    exit;
  }

  $listnames = explode(" ",$cline["l"]);
  $listids = array();
  foreach ($listnames as $listname) {
    if (!is_numeric($listname)) {
      $listid = Sql_Fetch_Array_Query(sprintf('select * from %s where name = "%s"',
        $tables["list"],$listname));
      if ($listid["id"]) {
        $listids[$listid["id"]] = $listname;
      }
     } else {
      $listid = Sql_Fetch_Array_Query(sprintf('select * from %s where id = %d',
        $tables["list"],$listname));
      if ($listid["id"]) {
        $listids[$listid["id"]] = $listid["name"];
      }
    }
  }

  $_POST["targetlist"] = array();
  foreach ($listids as $key => $val) {
    $_POST["targetlist"][$key] = "signup";
    $lists .= '"'.$val.'"' . " ";
  }

  if ($cline["f"]) {
    $_POST["from"] = $cline["f"];
  } else {
    $_POST["from"] = getConfig("message_from_name") . ' '.getConfig("message_from_address");
  }
  $_POST["subject"] = $cline["s"];
  $_POST["send"] = "1";
  $_POST["footer"] = getConfig("messagefooter");
  while (!feof (STDIN)) {
    $_POST["message"] .= fgets(STDIN, 4096);
  }

#  print clineSignature();
#  print "Sending message with subject ".$_POST["subject"]. " to ". $lists."\n";
}
ob_start();

### check for draft messages

if (!empty($_GET['delete'])) {
  if ($_GET['delete'] == 'alldraft') {
    $req = Sql_Query(sprintf('select id from %s where status = "draft" %s',$GLOBALS['tables']['message'],$ownership));
    while ($row = Sql_Fetch_Row($req)) {
      deleteMessage($row[0]);
    }
    print Info($GLOBALS['I18N']->get('campaigns deleted'));
  } else {
    deleteMessage(sprintf('%d',$_GET['delete']));
    print Info($GLOBALS['I18N']->get('campaign deleted'));
  }
}

$req = Sql_Query(sprintf('select id,entered,subject,unix_timestamp(current_timestamp) - unix_timestamp(entered) as age from %s where status = "draft" %s order by entered desc',$GLOBALS['tables']['message'],$ownership));
$numdraft = Sql_Num_Rows($req);
if ($numdraft > 0 && !isset($_GET['id']) && !isset($_GET['new'])) {
  print '<p class="button">'.PageLink2('send&amp;new=1',$I18N->get('start a new message')).'</p>';
  print '<p><h3>'.$I18N->get('Choose an existing draft message to work on').'</h3></p><br/>';
  $ls = new WebblerListing($I18N->get('Draft messages'));
  while ($row = Sql_Fetch_Array($req)) {
    $element = '<!--'.$row['id'].'-->'.$row['subject'];
    $ls->addElement($element);
    $ls->addColumn($element,$I18N->get('edit'),PageLink2('send&amp;id='.$row['id'],$I18N->get('edit')));
    $ls->addColumn($element,$I18N->get('entered'),$row['entered']);
    $ls->addColumn($element,$I18N->get('age'),secs2time($row['age']));
    $ls->addColumn($element,$I18N->get('del'),PageLink2('send&amp;delete='.$row['id'],$I18N->get('delete')));
  }
  $ls->addButton($I18N->get('delete all'),PageUrl2('send&amp;delete=alldraft'));
  print $ls->display();
  return;
}

include "send_core.php";

if ($done) {
  if ($GLOBALS["commandline"]) {
    ob_end_clean();
    print clineSignature();
    print "Message with subject ".$_POST["subject"]. " was sent to ". $lists."\n";
    exit;
  }
  return;
}

/*if (!$_GET["id"]) {
  Sql_Query(sprintf('insert into %s (subject,status,entered)
    values("(no subject)","draft",current_timestamp)',$GLOBALS["tables"]["message"]));
  $id = Sql_Insert_Id($GLOBALS['tables']['message'], 'id');
  Redirect("send&amp;id=$id");
}
*/
$list_content = '
<div id="listselection" class="accordion">
<h3><a name="lists">'.$GLOBALS['I18N']->get('selectlists').':</a></h3>
';


$list_content .= listSelectHTML($messagedata['targetlist'],'targetlist',$subselect);

if (USE_LIST_EXCLUDE) {
  $list_content .= '
    <h3><a name="excludelists">'.$GLOBALS['I18N']->get('selectexcludelist').'</a></h3>';

  if (!isset($messagedata['excludelist']) || !is_array($messagedata['excludelist'])) {
    $messagedata['excludelist'] = array();
  }
  $list_content .= listSelectHTML($messagedata['excludelist'],'excludelist',$subselect);
}

$list_content .= '</div>'; ## close accordion

if (isset($show_lists) && $show_lists) {
 # print htmlspecialchars($list_content);
  print $list_content;
} 

print '</form>';
