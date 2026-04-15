<?php
require __DIR__ . '/lib.php';

$usersPath = __DIR__ . '/data/users.json';
$jobsPath  = __DIR__ . '/data/jobs.json';
$users = tm_json_load($usersPath);
$jobs  = tm_json_load($jobsPath);

$active=null;
foreach($jobs as $id=>$job){
  if(($job['status']??'')==='queued' || ($job['status']??'')==='sending'){ $active=$id; break; }
}
if(!$active){ echo "NO_JOBS\n"; exit; }

$job=$jobs[$active];
if(($job['status']??'')==='canceled' || ($job['status']??'')==='done'){ echo "DONE\n"; exit; }

$subs=array_values(array_filter($users, fn($u)=> !empty($u['subscribed']) && empty($u['blocked'])));
$total=count($subs);
$cursor=(int)($job['cursor']??0);
$batch=25;
$jobs[$active]['status']='sending';

$limit=min($cursor+$batch,$total);
for($i=$cursor;$i<$limit;$i++){
  $cid=$subs[$i]['chat_id'];
  $r=tm_api('sendMessage',['chat_id'=>$cid,'text'=>$job['text'],'disable_web_page_preview'=>true]);
  if(!empty($r['ok'])) $jobs[$active]['sent']++;
  else{
    $jobs[$active]['failed']++;
    $desc=json_encode($r);
    if(strpos($desc,'"error_code":403')!==false) $users[(string)$cid]['subscribed']=false;
  }
  usleep(70000);
}
$jobs[$active]['cursor']=$limit;
if($limit>=$total) $jobs[$active]['status']='done';

tm_json_save($usersPath,$users);
tm_json_save($jobsPath,$jobs);
echo "JOB $active ".$jobs[$active]['status']."\n";
