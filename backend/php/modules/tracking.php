<?php
declare(strict_types=1);

use Hospital\Http\ApiException;
use Hospital\Http\Request;

function tracking_position(array $row):array
{
    return ['id'=>(string)$row['id'],'transfer_id'=>(string)$row['transfer_id'],'run_id'=>$row['run_id']===null?null:(string)$row['run_id'],'generation'=>(int)$row['generation'],'progress'=>(float)$row['progress'],'latitude'=>(float)$row['latitude'],'longitude'=>(float)$row['longitude'],'recorded_at'=>users_date($row['recorded_at']),'source'=>'simulated'];
}

function tracking_latest(PDO $db, string $id):?array
{
    $s=$db->prepare('SELECT p.* FROM transfer_latest_positions l JOIN transfer_positions p ON p.id=l.position_id WHERE l.transfer_id=?');$s->execute([$id]);$row=$s->fetch();return $row?tracking_position($row):null;
}

function tracking_sample(PDO $db, string $id,array $points,float $progress,?string $run,int $generation):void
{
    if (count($points)<2) { $s=$db->prepare('DELETE FROM transfer_latest_positions WHERE transfer_id=?'); $s->execute([$id]); return; }
    $progress=max(0,min(1,$progress));$lengths=[];$total=0;
    for($i=1;$i<count($points);$i++){$length=transfer_input_distance($points[$i-1],$points[$i]);$lengths[]=$length;$total+=$length;}
    $remaining=$total*$progress;$point=$points[count($points)-1];
    foreach($lengths as $i=>$length){if($remaining<=$length){$fraction=$length>0?$remaining/$length:0;$point=['latitude'=>$points[$i]['latitude']+($points[$i+1]['latitude']-$points[$i]['latitude'])*$fraction,'longitude'=>$points[$i]['longitude']+($points[$i+1]['longitude']-$points[$i]['longitude'])*$fraction];break;}$remaining-=$length;}
    $s=$db->prepare('INSERT INTO transfer_positions (transfer_id,run_id,generation,progress,latitude,longitude) VALUES (?,?,?,?,?,?)');$s->execute([$id,$run,$generation,$progress,$point['latitude'],$point['longitude']]);$position=$db->lastInsertId();
    $s=$db->prepare('INSERT INTO transfer_latest_positions (transfer_id,position_id) VALUES (?,?) ON DUPLICATE KEY UPDATE position_id=VALUES(position_id)');$s->execute([$id,$position]);
}

function tracking_status(PDO $db):array
{
    $row=$db->query('SELECT s.version AS state_version,r.* FROM simulation_state s LEFT JOIN simulation_runs r ON r.id=s.current_run_id WHERE s.id=1')->fetch();
    $heartbeat=$row['heartbeat_at']===null?null:strtotime($row['heartbeat_at'].' UTC');
    return ['version'=>(int)$row['state_version'],'run_id'=>$row['id']===null?null:(string)$row['id'],'status'=>$row['status']??'stopped','speed'=>$row['speed']===null?1:(float)$row['speed'],'generation'=>$row['generation']===null?1:(int)$row['generation'],'heartbeat_at'=>users_date($row['heartbeat_at']),'stale'=>$heartbeat===null||time()-$heartbeat>15,'source'=>'simulated'];
}

function tracking_control(PDO $db, array $config, string $command,$version,$speed=1,?string $actor=null):array
{
    transfer_input_choice($command,['start','pause','resume','stop','reset']);
    if((!is_int($speed)&&!is_float($speed))||!is_finite((float)$speed)||$speed<0.1||$speed>100)throw new ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.velocidad_de_simulacion_no_valida"));
    transfer_serialized($db, $config, function() use ($command, $version, $speed, $actor, $db) {
        $db->beginTransaction();
        try{
            if($actor!==null){$user=users_by_id($db, $actor,true);if(!$user||$user['status']!=='active'||$user['staff_status']!=='active'||(int)$user['auth_version']!==(int)($_SESSION['auth_version']??0))throw new ApiException(401,'SESSION_EXPIRED',\Hospital\I18n::t("errors.la_sesion_vencio"));if(!in_array('admin',users_roles($db, $actor),true))throw new ApiException(403,'FORBIDDEN',\Hospital\I18n::t("errors.se_requiere_administracion"));}
            $state=$db->query('SELECT * FROM simulation_state WHERE id=1 FOR UPDATE')->fetch();
            if((int)$state['version']!==transfer_input_version($version))throw new ApiException(409,'VERSION_CONFLICT',\Hospital\I18n::t("errors.la_simulacion_cambio"));
            $run=null;if($state['current_run_id']!==null){$s=$db->prepare('SELECT * FROM simulation_runs WHERE id=? FOR UPDATE');$s->execute([$state['current_run_id']]);$run=$s->fetch();}
            if($command==='start'){
                if($run&&$run['status']!=='stopped')throw new ApiException(409,'SIMULATION_STATE',\Hospital\I18n::t("errors.la_simulacion_ya_esta_iniciada"));
                $s=$db->prepare("INSERT INTO simulation_runs (speed) VALUES (?)");$s->execute([$speed]);$id=$db->lastInsertId();$s=$db->prepare('UPDATE simulation_state SET current_run_id=?,version=version+1 WHERE id=1');$s->execute([$id]);
            }else{
                if(!$run||$run['status']==='stopped'||($command==='pause'&&$run['status']!=='running')||($command==='resume'&&$run['status']!=='paused'))throw new ApiException(409,'SIMULATION_STATE',\Hospital\I18n::t("errors.el_estado_de_la_simulacion_no_permite_esa_accion"));
                $status=['pause'=>'paused','resume'=>'running','stop'=>'stopped','reset'=>$run['status']][$command];
                $s=$db->prepare('UPDATE simulation_runs SET status=?,last_tick_at=NULL,version=version+1,generation=generation+? WHERE id=?');$s->execute([$status,$command==='reset'?1:0,$run['id']]);
                if($command==='reset'){
                    $rows=$db->query("SELECT id,geometry FROM transfers WHERE status='in_progress' ORDER BY id FOR UPDATE")->fetchAll();
                    foreach($rows as $row)tracking_sample($db, (string)$row['id'],json_decode($row['geometry'],true),0,(string)$run['id'],(int)$run['generation']+1);
                }
                $db->exec('UPDATE simulation_state SET version=version+1 WHERE id=1');
            }
            transfer_audit($db, $actor,'simulation.'.$command,null,'simulation');$db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    });return tracking_status($db);
}

function tracking_tick(PDO $db, array $config):int
{
    return transfer_serialized($db, $config, function() use ($db) {
        $db->beginTransaction();
        try{
            $s=$db->query('SELECT r.*,UTC_TIMESTAMP() AS now_utc FROM simulation_state s JOIN simulation_runs r ON r.id=s.current_run_id WHERE s.id=1 FOR UPDATE');$run=$s->fetch();
            if(!$run||$run['status']==='stopped'){$db->commit();return 0;}
            $seconds=$run['last_tick_at']===null?0:max(0,min(5,strtotime($run['now_utc'].' UTC')-strtotime($run['last_tick_at'].' UTC')));
            $s=$db->prepare('UPDATE simulation_runs SET heartbeat_at=UTC_TIMESTAMP(),last_tick_at=UTC_TIMESTAMP() WHERE id=?');$s->execute([$run['id']]);$count=0;
            if($run['status']==='running'){
                $rows=$db->query("SELECT t.*,p.progress FROM transfers t LEFT JOIN transfer_latest_positions l ON l.transfer_id=t.id LEFT JOIN transfer_positions p ON p.id=l.position_id WHERE t.status='in_progress' ORDER BY t.id FOR UPDATE")->fetchAll();
                foreach($rows as $row){
                    if ($row['duration_seconds']===null || (int)$row['duration_seconds']<=0) continue;
                    $progress=min(1,(float)($row['progress']??0)+$seconds*(float)$run['speed']/(int)$row['duration_seconds']);
                    tracking_sample($db, (string)$row['id'],json_decode($row['geometry'],true),$progress,(string)$run['id'],(int)$run['generation']);$count++;
                    if($progress>=1){$s=$db->prepare("UPDATE transfers SET status='arrived',arrived_at=UTC_TIMESTAMP(),version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?");$s->execute([$row['id']]);transfers_event($db, (string)$row['id'],null,'simulation_arrived','in_progress','arrived');transfer_audit($db, null,'transfer.simulation_arrived',(string)$row['id']);}
                }
            }
            $db->commit();return $count;
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    });
}

function tracking_snapshot(PDO $db, string $actor,array $query):array
{
    if(array_diff(array_keys($query),['after_id','transfer_id']))throw new ApiException(422,'UNEXPECTED_FIELDS',\Hospital\I18n::t("errors.filtro_no_permitido"));
    $args=[];$scope=transfer_scope($db, $actor,$args);$where=[$scope];
    if(isset($query['transfer_id'])){$where[]='t.id=?';$args[]=Request::id($query['transfer_id']);transfers_row($db, $actor,(string)$args[count($args)-1]);}
    else $where[]="t.status IN ('scheduled','in_progress','arrived')";
    if(isset($query['after_id'])){$where[]='t.id>?';$args[]=Request::id($query['after_id']);}
    $s=$db->prepare('SELECT t.* FROM transfers t WHERE '.implode(' AND ',$where).' ORDER BY t.id LIMIT 101');$s->execute($args);$rows=$s->fetchAll();$more=count($rows)>100;$rows=array_slice($rows,0,100);
    $data=[];foreach($rows as $row){$item=transfers_payload($db, $row);unset($item['crew']);$item['geometry']=json_decode($row['geometry'],true);$item['position']=tracking_latest($db, (string)$row['id']);$data[]=$item;}
    return ['transfers'=>$data,'next_cursor'=>$more?(string)$rows[count($rows)-1]['id']:null,'server_time'=>gmdate('Y-m-d\TH:i:s\Z'),'simulation'=>tracking_status($db)];
}

function tracking_history(PDO $db, string $actor,array $query):array
{
    if(array_diff(array_keys($query),['transfer_id','after_id','limit']))throw new ApiException(422,'UNEXPECTED_FIELDS',\Hospital\I18n::t("errors.filtro_no_permitido"));
    $id=Request::id($query['transfer_id']??null);transfers_row($db, $actor,$id);
    $after=isset($query['after_id'])?Request::id($query['after_id']):'0';$limit=$query['limit']??'100';
    if(!is_scalar($limit)||!preg_match('/^[1-9][0-9]{0,3}$/',(string)$limit)||(int)$limit>1000)throw new ApiException(422,'VALIDATION_FAILED',\Hospital\I18n::t("errors.limite_no_valido"));
    $s=$db->prepare('SELECT * FROM transfer_positions WHERE transfer_id=? AND id>? ORDER BY id LIMIT '.((int)$limit+1));$s->execute([$id,$after]);$rows=$s->fetchAll();$more=count($rows)>(int)$limit;$rows=array_slice($rows,0,(int)$limit);
    return ['positions'=>array_map(fn(array $row):array=>tracking_position($row),$rows),'next_cursor'=>$more?(string)$rows[count($rows)-1]['id']:null,'source'=>'simulated'];
}

function tracking_prune(PDO $db):int
{
    return $db->exec('DELETE FROM transfer_positions WHERE recorded_at<UTC_TIMESTAMP()-INTERVAL 7 DAY AND id NOT IN (SELECT position_id FROM transfer_latest_positions) LIMIT 1000');
}
