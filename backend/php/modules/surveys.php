<?php
declare(strict_types=1);

use Hospital\Http\ApiException;

const SURVEY_FIELDS = ['title','description','category','area','notes','form_title','form_description','color','config','items'];

const SURVEY_TYPES = ['short_text','long_text','single_choice','multiple_choice','linear_scale','rating','date','section','divider'];

function survey_input_fail(string $message): void
{ throw new ApiException(422,'VALIDATION_FAILED',$message);
}

function survey_input_text($value,int $max,bool $required=false): string
{
    if (!is_string($value)||!mb_check_encoding($value,'UTF-8')||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$value)) survey_input_fail(\Hospital\I18n::t("errors.texto_no_valido"));
    $value=trim($value);if (mb_strlen($value)>$max||($required&&$value==='')) survey_input_fail(\Hospital\I18n::t("errors.completa_los_campos_y_respeta_sus_limites"));return $value;
}

function survey_input_choice($value,array $choices): string
{ if (!is_string($value)||!in_array($value,$choices,true)) survey_input_fail(\Hospital\I18n::t("errors.opcion_no_valida"));return $value;
}

function survey_input_flag($value): bool
{ if (!is_bool($value)) survey_input_fail(\Hospital\I18n::t("errors.se_esperaba_un_valor_booleano"));return $value;
}

function survey_input_integer($value,int $min,int $max): int
{ if ((!is_int($value)&&!(is_string($value)&&preg_match('/^\d+$/',$value)))||$value<$min||$value>$max) survey_input_fail(\Hospital\I18n::t("errors.numero_fuera_de_rango"));return (int)$value;
}

function survey_input_date($value): string
{
    $value=survey_input_text($value,16,true);$date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,new \DateTimeZone('America/Montevideo'));
    if (!$date||$date->format('Y-m-d\TH:i')!==$value||$date->format('Y')<'1900'||$date->format('Y')>'2200') survey_input_fail(\Hospital\I18n::t("errors.fecha_no_valida"));
    return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function survey_input_fields(array $input,bool $publish=false): array
{
    $config=$input['config']??null;$items=$input['items']??null;
    $keys=['audience','access','identity','limit_enabled','response_limit','opens_enabled','opens_at','closes_enabled','closes_at','completion_message'];
    if (!is_array($config)||array_diff(array_keys($config),$keys)||!is_array($items)||array_values($items)!==$items||count($items)>20) survey_input_fail(\Hospital\I18n::t("errors.configuracion_o_preguntas_no_validas"));
    $limit=survey_input_flag($config['limit_enabled']??false)?survey_input_integer($config['response_limit']??null,1,10000):null;
    $opens=survey_input_flag($config['opens_enabled']??false)?survey_input_date($config['opens_at']??null):null;
    $closes=survey_input_flag($config['closes_enabled']??false)?survey_input_date($config['closes_at']??null):null;
    if ($opens&&$closes&&$opens>=$closes) survey_input_fail(\Hospital\I18n::t("errors.el_cierre_debe_ser_posterior_a_la_apertura"));
    $row=[
        'title'=>survey_input_text($input['title']??null,120,true),'description'=>survey_input_text($input['description']??'',500),
        'category'=>survey_input_choice($input['category']??null,['patient_care','transfers','emergency','staff','other']),
        'area'=>survey_input_text($input['area']??'',80),'notes'=>survey_input_text($input['notes']??'',400),
        'form_title'=>survey_input_text($input['form_title']??'',150,$publish),'form_description'=>survey_input_text($input['form_description']??'',500),
        'color'=>survey_input_choice($input['color']??'blue',['blue','green','cyan','amber','rose','slate']),
        'audience'=>survey_input_choice($config['audience']??null,['patients','staff','everyone']),
        'access_mode'=>survey_input_choice($config['access']??null,['shared_link','individual_link']),
        'identity_mode'=>survey_input_choice($config['identity']??null,['anonymous','optional_name']),
        'response_limit'=>$limit,'opens_at'=>$opens,'closes_at'=>$closes,'completion_message'=>survey_input_text($config['completion_message']??'',500)
    ];
    $clean=[];$seen=[];$questions=0;
    foreach ($items as $item) {
        $allowed=['id','type','title','required','options','other','description','placeholder','scale_min','scale_max','scale_start','scale_end','rating_max'];
        if (!is_array($item)||array_diff(array_keys($item),$allowed)) survey_input_fail(\Hospital\I18n::t("errors.pregunta_no_valida"));
        $id=$item['id']??null;if (!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,48}$/',$id)||isset($seen[$id])) survey_input_fail(\Hospital\I18n::t("errors.identificador_de_pregunta_no_valido"));$seen[$id]=true;
        $type=survey_input_choice($item['type']??null,SURVEY_TYPES);$element=in_array($type,['section','divider'],true);$choice=in_array($type,['single_choice','multiple_choice'],true);
        $options=$item['options']??[];if (!is_array($options)||array_values($options)!==$options||($choice&&(count($options)<2||count($options)>10))) survey_input_fail(\Hospital\I18n::t("errors.usa_entre_2_y_10_opciones"));
        $options=$choice?array_map(static fn($v)=>survey_input_text($v,120,$publish),$options):[];
        if ($choice&&$publish&&($item['other']??false)&&in_array('otro',array_map(static fn($v)=>mb_strtolower($v),$options),true)) survey_input_fail(\Hospital\I18n::t("errors.usa_otra_etiqueta_para_distinguir_la_opcion_otro"));
        if ($choice&&$publish&&count(array_unique(array_map(static fn($v)=>mb_strtolower($v),$options)))!==count($options)) survey_input_fail(\Hospital\I18n::t("errors.las_opciones_deben_ser_diferentes"));
        $clean[]=['id'=>$id,'type'=>$type,'title'=>survey_input_text($item['title']??'',250,$publish&&$type!=='divider'),
            'required'=>$element?false:survey_input_flag($item['required']??false),'options'=>$options,'other'=>$choice?survey_input_flag($item['other']??false):false,
            'description'=>$type==='section'?survey_input_text($item['description']??'',500):'',
            'placeholder'=>in_array($type,['short_text','long_text'],true)?survey_input_text($item['placeholder']??'',120):'',
            'scale_min'=>$type==='linear_scale'?survey_input_integer($item['scale_min']??1,0,1):1,
            'scale_max'=>$type==='linear_scale'?survey_input_integer($item['scale_max']??5,2,10):5,
            'scale_start'=>$type==='linear_scale'?survey_input_text($item['scale_start']??'',40):'',
            'scale_end'=>$type==='linear_scale'?survey_input_text($item['scale_end']??'',40):'',
            'rating_max'=>$type==='rating'?survey_input_integer($item['rating_max']??5,3,10):5];
        if ($type==='rating'&&!in_array(end($clean)['rating_max'],[3,5,10],true)) survey_input_fail(\Hospital\I18n::t("errors.calificacion_no_valida"));
        if (!$element)$questions++;
    }
    if ($publish&&!$questions) survey_input_fail(\Hospital\I18n::t("errors.agrega_al_menos_una_pregunta"));
    return [$row,$clean];
}

function survey_input_answers(array $items,$input,$name,string $identity): array
{
    if (!is_array($input)||array_values($input)!==$input||count($input)>20) survey_input_fail(\Hospital\I18n::t("errors.respuestas_no_validas"));
    $byKey=[];foreach ($items as $item) if (!in_array($item['type'],['section','divider'],true))$byKey[$item['id']]=$item;
    $answers=[];
    foreach ($input as $entry) {
        if (!is_array($entry)||array_diff(array_keys($entry),['question_id','value','other_text'])) survey_input_fail(\Hospital\I18n::t("errors.respuesta_no_valida"));
        $key=$entry['question_id']??null;if (!is_string($key)||!isset($byKey[$key])||isset($answers[$key])) survey_input_fail(\Hospital\I18n::t("errors.pregunta_desconocida_o_repetida"));
        $q=$byKey[$key];$value=$entry['value']??null;$other=$entry['other_text']??'';
        if (in_array($q['type'],['single_choice','multiple_choice'],true)) {
            $values=$q['type']==='single_choice'?[$value]:$value;
            if (!is_array($values)||array_values($values)!==$values||!count($values)||count($values)>11)survey_input_fail(\Hospital\I18n::t("errors.selecciona_opciones_validas"));
            $chosen=[];foreach ($values as $v) {
                if ($v==='other'&&$q['other'])$chosen[]='other';
                else $chosen[]=(string)survey_input_integer($v,0,count($q['options'])-1);
            }
            if (count(array_unique($chosen))!==count($chosen)) survey_input_fail(\Hospital\I18n::t("errors.opcion_repetida"));sort($chosen,SORT_STRING);
            $value=['choices'=>$chosen,'other_text'=>in_array('other',$chosen,true)?survey_input_text($other,120,true):''];
            if ($other!==''&&!in_array('other',$chosen,true))survey_input_fail(\Hospital\I18n::t("errors.la_respuesta_otro_no_corresponde"));
        } elseif (in_array($q['type'],['rating','linear_scale'],true)) $value=survey_input_integer($value,$q['type']==='rating'?1:$q['scale_min'],$q['type']==='rating'?$q['rating_max']:$q['scale_max']);
        else {
            $value=survey_input_text($value,$q['type']==='long_text'?1000:250,true);
            if ($q['type']==='date') { $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if (!$date||$date->format('Y-m-d')!==$value||$date->format('Y')<'1900'||$date->format('Y')>'2200')survey_input_fail(\Hospital\I18n::t("errors.fecha_no_valida")); }
        }
        if (!in_array($q['type'],['single_choice','multiple_choice'],true)&&$other!=='')survey_input_fail(\Hospital\I18n::t("errors.campo_otro_no_permitido"));
        $answers[$key]=$value;
    }
    foreach ($byKey as $key=>$q) if ($q['required']&&!array_key_exists($key,$answers))survey_input_fail(\Hospital\I18n::t("survey_patient.completa_las_preguntas_obligatorias"));
    $name=survey_input_text($name??'',100);if ($identity==='anonymous'&&$name!=='')survey_input_fail(\Hospital\I18n::t("errors.esta_encuesta_no_solicita_nombre"));
    ksort($answers);return [$answers,$name===''?null:$name];
}

function surveys_query(PDO $db, string $sql,array $params=[]): \PDOStatement
{ $s=$db->prepare($sql);$s->execute($params);return $s;
}

function surveys_admin(PDO $db, string $actor): bool
{ return in_array('admin',users_roles($db, $actor),true);
}

function surveys_actor(PDO $db, string $actor): void
{
    $u=users_by_id($db, $actor,true);
    if (!$u||$u['status']!=='active'||$u['staff_status']!=='active'||(int)$u['auth_version']!==(int)($_SESSION['auth_version']??0))throw new ApiException(401,'SESSION_EXPIRED',\Hospital\I18n::t("errors.la_sesion_vencio"));
}

function surveys_row(PDO $db, string $actor,string $id,bool $lock=false): array
{
    $r=surveys_query($db, 'SELECT s.*,COALESCE(NULLIF(st.full_name,\'\'),u.username) AS owner_name FROM surveys s JOIN users u ON u.id=s.owner_id LEFT JOIN staff st ON st.id=u.staff_id WHERE s.id=?'.($lock?' FOR UPDATE':''),[$id])->fetch();
    if (!$r||((string)$r['owner_id']!==$actor&&!surveys_admin($db, $actor)))throw new ApiException(404,'SURVEY_NOT_FOUND',\Hospital\I18n::t("errors.no_se_encontro_la_encuesta"));return $r;
}

function surveys_items(PDO $db, string $id): array
{
    return array_map(static function($r) {$settings=json_decode($r['settings'],true,32,JSON_THROW_ON_ERROR);return ['id'=>$r['item_key'],'type'=>$r['type'],'title'=>$r['title'],'required'=>(bool)$r['required']]+$settings;},surveys_query($db, 'SELECT * FROM survey_items WHERE survey_id=? ORDER BY position',[$id])->fetchAll());
}

function surveys_put_items(PDO $db, string $id,array $items): void
{
    surveys_query($db, 'DELETE FROM survey_items WHERE survey_id=?',[$id]);
    foreach ($items as $position=>$item) {
        $settings=array_diff_key($item,array_flip(['id','type','title','required']));
        surveys_query($db, 'INSERT INTO survey_items (survey_id,item_key,position,type,title,required,settings) VALUES (?,?,?,?,?,?,?)',[$id,$item['id'],$position,$item['type'],$item['title'],(int)$item['required'],json_encode($settings,JSON_THROW_ON_ERROR)]);
    }
}

function surveys_count(PDO $db, string $id): int
{ return (int)surveys_query($db, 'SELECT COUNT(*) FROM survey_responses WHERE survey_id=?',[$id])->fetchColumn();
}

function surveys_local_date(?string $date): string
{ return $date?(new \DateTimeImmutable($date,new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('America/Montevideo'))->format('Y-m-d\TH:i'):'';
}

function surveys_payload(PDO $db, array $r,bool $results=false): array
{
    $id=(string)$r['id'];$count=surveys_count($db, $id);$status=$r['status'];
    if ($status==='published'&&(($r['closes_at']&&$r['closes_at']<=gmdate('Y-m-d H:i:s'))||($r['response_limit']!==null&&$count>=(int)$r['response_limit'])))$status='closed';
    $record=['id'=>$id,'version'=>(int)$r['version'],'owner_id'=>(string)$r['owner_id'],'owner_name'=>$r['owner_name'],'title'=>$r['title'],'description'=>$r['description'],'category'=>$r['category'],'area'=>$r['area'],'notes'=>$r['notes'],
        'form_title'=>$r['form_title'],'form_description'=>$r['form_description'],'color'=>$r['color'],'status'=>$r['archived_at']?'archived':$status,'lifecycle'=>$r['status'],'published_at'=>users_date($r['published_at']),
        'created_at'=>users_date($r['created_at']),'updated_at'=>users_date($r['updated_at']),'archived_at'=>users_date($r['archived_at']),
        'audience'=>$r['audience'],'accepting_responses'=>!$r['archived_at']&&$status==='published'&&(!$r['opens_at']||$r['opens_at']<=gmdate('Y-m-d H:i:s')),'response_count'=>$count,'questions'=>surveys_items($db, $id),
        'config'=>['audience'=>$r['audience'],'access'=>$r['access_mode'],'identity'=>$r['identity_mode'],'limit_enabled'=>$r['response_limit']!==null,'response_limit'=>$r['response_limit']===null?100:(int)$r['response_limit'],
            'opens_enabled'=>$r['opens_at']!==null,'opens_at'=>surveys_local_date($r['opens_at']),'closes_enabled'=>$r['closes_at']!==null,'closes_at'=>surveys_local_date($r['closes_at']),'completion_message'=>$r['completion_message']]];
    if ($results) {
        $record['results']=surveys_results($db, $id,$record['questions'],$count);
        $record['activity']=surveys_query($db, "SELECT DATE(DATE_SUB(submitted_at,INTERVAL 3 HOUR)) AS date,COUNT(*) AS count FROM survey_responses WHERE survey_id=? GROUP BY date ORDER BY date DESC LIMIT 7",[$id])->fetchAll();
        $record['activity']=array_reverse(array_map(static fn($r)=>['date'=>$r['date'],'count'=>(int)$r['count']],$record['activity']));
        $ratings=[];foreach ($record['questions'] as $q) if ($q['type']==='rating')foreach ($record['results'][$q['id']]['distribution'] as $bin)if ($bin['count'])$ratings[]=[$bin['value']/$q['rating_max']*5,$bin['count']];
        $n=array_sum(array_column($ratings,1));$record['average_rating']=$n?array_sum(array_map(static fn($v)=>$v[0]*$v[1],$ratings))/$n:null;$record['rating_scale']=5;$record['rating_count']=$n;
    }
    return $record;
}

function surveys_get(PDO $db, string $actor,string $id): array
{ return surveys_payload($db, surveys_row($db, $actor,$id),true);
}

function surveys_listing(PDO $db, string $actor,int $page,int $size,bool $archived): array
{
    $where='s.archived_at IS '.($archived?'NOT NULL':'NULL');$params=[];if (!surveys_admin($db, $actor)){$where.=' AND s.owner_id=?';$params[]=$actor;}
    $total=(int)surveys_query($db, 'SELECT COUNT(*) FROM surveys s WHERE '.$where,$params)->fetchColumn();$offset=($page-1)*$size;
    $rows=surveys_query($db, 'SELECT s.*,COALESCE(NULLIF(st.full_name,\'\'),u.username) AS owner_name FROM surveys s JOIN users u ON u.id=s.owner_id LEFT JOIN staff st ON st.id=u.staff_id WHERE '.$where.' ORDER BY s.updated_at DESC,s.id DESC LIMIT '.$size.' OFFSET '.$offset,$params)->fetchAll();
    return [array_map(function($r) use ($db) {$record=surveys_payload($db, $r,true);unset($record['results'],$record['activity']);return $record;},$rows),$total];
}

function surveys_save(PDO $db, string $actor,array $input,?string $id=null): array
{
    [$fields,$items]=survey_input_fields($input);$db->beginTransaction();
    try {
        surveys_actor($db, $actor);
        if ($id===null) {
            $names=array_keys($fields);surveys_query($db, 'INSERT INTO surveys (owner_id,'.implode(',',$names).') VALUES (?'.str_repeat(',?',count($names)).')',array_merge([$actor],array_values($fields)));$id=$db->lastInsertId();surveys_put_items($db, $id,$items);
        } else {
            $r=surveys_row($db, $actor,$id,true);surveys_revision($r,$input['version']??null);if ($r['archived_at'])throw new ApiException(409,'SURVEY_ARCHIVED',\Hospital\I18n::t("errors.restaura_la_encuesta_antes_de_editarla"));
            if ($r['published_at']) {
                foreach (['form_title','form_description','color','audience','access_mode','identity_mode'] as $key)if ($fields[$key]!==$r[$key])throw new ApiException(409,'FORM_PUBLISHED',\Hospital\I18n::t("errors.duplica_la_encuesta_para_cambiar_el_formulario_publicado"));
                if ($items!==surveys_items($db, $id))throw new ApiException(409,'FORM_PUBLISHED',\Hospital\I18n::t("errors.las_preguntas_publicadas_no_se_pueden_cambiar"));
            } else surveys_put_items($db, $id,$items);
            if ($fields['response_limit']!==null&&$fields['response_limit']<surveys_count($db, $id))survey_input_fail(\Hospital\I18n::t("errors.el_cupo_no_puede_ser_menor_a_las_respuestas_recibidas"));
            surveys_query($db, 'UPDATE surveys SET '.implode(',',array_map(static fn($key)=>$key.'=?',array_keys($fields))).',version=version+1 WHERE id=?',array_merge(array_values($fields),[$id]));
        }
        users_audit($db, $actor,'survey.saved',$id,$GLOBALS['request_id']);$db->commit();return surveys_get($db, $actor,$id);
    } catch (\Throwable $e) { if ($db->inTransaction())$db->rollBack();throw $e; }
}

function surveys_revision(array $r,$given): void
{ if (survey_input_integer($given,1,2147483647)!==(int)$r['version'])throw new ApiException(409,'VERSION_CONFLICT',\Hospital\I18n::t("errors.la_encuesta_cambio_volve_a_abrirla"));
}

function surveys_transition(PDO $db, array $config, string $actor,string $id,$version,string $action): array
{
    $db->beginTransaction();try {
        surveys_actor($db, $actor);$r=surveys_row($db, $actor,$id,true);surveys_revision($r,$version);
        if ($action==='restore') { if (!$r['archived_at'])throw new ApiException(409,'INVALID_STATE',\Hospital\I18n::t("errors.la_encuesta_no_esta_archivada"));surveys_query($db, 'UPDATE surveys SET archived_at=NULL,version=version+1 WHERE id=?',[$id]); }
        elseif ($action==='archive')surveys_query($db, 'UPDATE surveys SET archived_at=UTC_TIMESTAMP(),version=version+1 WHERE id=?',[$id]);
        else {
            if ($r['archived_at'])throw new ApiException(409,'SURVEY_ARCHIVED',\Hospital\I18n::t("errors.la_encuesta_esta_archivada"));
            if ($action==='publish') {
                if ($r['status']!=='draft')throw new ApiException(409,'INVALID_STATE',\Hospital\I18n::t("errors.la_encuesta_ya_fue_publicada"));
                $record=surveys_payload($db, $r);survey_input_fields(['title'=>$r['title'],'description'=>$r['description'],'category'=>$r['category'],'area'=>$r['area'],'notes'=>$r['notes'],'form_title'=>$r['form_title'],'form_description'=>$r['form_description'],'color'=>$r['color'],'config'=>$record['config'],'items'=>$record['questions']],true);
                if ($r['closes_at']&&$r['closes_at']<=gmdate('Y-m-d H:i:s'))survey_input_fail(\Hospital\I18n::t("errors.la_fecha_de_cierre_ya_paso"));
                surveys_query($db, "UPDATE surveys SET status='published',published_at=UTC_TIMESTAMP(),version=version+1 WHERE id=?",[$id]);
                if ($r['access_mode']==='shared_link')surveys_new_link($db, $config, $id,false);
            } elseif ($action==='close'&&$r['status']==='published')surveys_query($db, "UPDATE surveys SET status='closed',version=version+1 WHERE id=?",[$id]);
            elseif ($action==='reopen'&&$r['status']==='closed') {
                if (($r['closes_at']&&$r['closes_at']<=gmdate('Y-m-d H:i:s'))||($r['response_limit']!==null&&surveys_count($db, $id)>=(int)$r['response_limit']))survey_input_fail(\Hospital\I18n::t("errors.amplia_el_plazo_o_cupo_antes_de_reabrir"));
                surveys_query($db, "UPDATE surveys SET status='published',version=version+1 WHERE id=?",[$id]);
            } else throw new ApiException(409,'INVALID_STATE',\Hospital\I18n::t("errors.cambio_de_estado_no_valido"));
        }
        users_audit($db, $actor,'survey.'.$action,$id,$GLOBALS['request_id']);$db->commit();return surveys_get($db, $actor,$id);
    } catch (\Throwable $e) { if ($db->inTransaction())$db->rollBack();throw $e; }
}

function surveys_raw_token(array $config, array $link): string
{ return hash_hmac('sha256','survey-link:'.$link['id'].':'.$link['nonce'],$config['app_key']);
}

function surveys_new_link(PDO $db, array $config, string $id,bool $single): void
{
    surveys_query($db, 'INSERT INTO survey_links (survey_id,nonce,single_use) VALUES (?,?,?)',[$id,bin2hex(random_bytes(16)),(int)$single]);$lid=$db->lastInsertId();$row=surveys_query($db, 'SELECT * FROM survey_links WHERE id=?',[$lid])->fetch();surveys_query($db, 'UPDATE survey_links SET token_hash=? WHERE id=?',[hash('sha256',surveys_raw_token($config, $row)),$lid]);
}

function surveys_links(PDO $db, array $config, string $actor,string $id): array
{
    $r=surveys_row($db, $actor,$id);$links=surveys_query($db, 'SELECT * FROM survey_links WHERE survey_id=? ORDER BY id DESC',[$id])->fetchAll();
    return array_map(fn($l)=>['id'=>(string)$l['id'],'single_use'=>(bool)$l['single_use'],'used_at'=>users_date($l['used_at']),'revoked_at'=>users_date($l['revoked_at']),'created_at'=>users_date($l['created_at']),'token'=>$l['revoked_at']?null:surveys_raw_token($config, $l)],$links);
}

function surveys_change_link(PDO $db, array $config, string $actor,string $id,$version,?string $linkId): array
{
    $db->beginTransaction();try {
        surveys_actor($db, $actor);$r=surveys_row($db, $actor,$id,true);surveys_revision($r,$version);
        if (!$r['published_at']||$r['archived_at'])throw new ApiException(409,'INVALID_STATE',\Hospital\I18n::t("errors.publica_la_encuesta_antes_de_generar_enlaces"));
        if ($linkId!==null) { $l=surveys_query($db, 'SELECT * FROM survey_links WHERE id=? AND survey_id=? FOR UPDATE',[$linkId,$id])->fetch();if (!$l)throw new ApiException(404,'LINK_NOT_FOUND',\Hospital\I18n::t("errors.no_se_encontro_la_invitacion"));surveys_query($db, 'UPDATE survey_links SET revoked_at=UTC_TIMESTAMP() WHERE id=?',[$linkId]);if (!$l['single_use'])surveys_new_link($db, $config, $id,false); }
        elseif ($r['access_mode']==='individual_link')surveys_new_link($db, $config, $id,true);
        else throw new ApiException(409,'SHARED_LINK_EXISTS',\Hospital\I18n::t("errors.el_enlace_compartido_ya_existe"));
        surveys_query($db, 'UPDATE surveys SET version=version+1 WHERE id=?',[$id]);users_audit($db, $actor,$linkId?'survey.link_revoked':'survey.link_created',$id,$GLOBALS['request_id']);$db->commit();return ['survey'=>surveys_get($db, $actor,$id),'links'=>surveys_links($db, $config, $actor,$id)];
    } catch (\Throwable $e) { if ($db->inTransaction())$db->rollBack();throw $e; }
}

function surveys_link(PDO $db, string $token): array
{
    if (!preg_match('/^[a-f0-9]{64}$/',$token))throw new ApiException(404,'SURVEY_NOT_FOUND',\Hospital\I18n::t("errors.no_se_encontro_la_encuesta"));
    $l=surveys_query($db, 'SELECT * FROM survey_links WHERE token_hash=?',[hash('sha256',$token)])->fetch();if (!$l)throw new ApiException(404,'SURVEY_NOT_FOUND',\Hospital\I18n::t("errors.no_se_encontro_la_encuesta"));return $l;
}

function surveys_available(PDO $db, array $s,array $l): void
{
    if ($s['archived_at']||$l['revoked_at'])throw new ApiException(404,'SURVEY_NOT_FOUND',\Hospital\I18n::t("errors.no_se_encontro_la_encuesta"));
    if ($s['status']!=='published'||($s['opens_at']&&$s['opens_at']>gmdate('Y-m-d H:i:s'))||($s['closes_at']&&$s['closes_at']<=gmdate('Y-m-d H:i:s'))||($s['response_limit']!==null&&surveys_count($db, (string)$s['id'])>=(int)$s['response_limit']))throw new ApiException(409,'SURVEY_UNAVAILABLE',\Hospital\I18n::t("errors.la_encuesta_no_esta_recibiendo_respuestas"));
    if ($l['single_use']&&$l['used_at'])throw new ApiException(409,'INVITATION_USED',\Hospital\I18n::t("errors.esta_invitacion_ya_fue_respondida"));
}

function surveys_public_get(PDO $db, array $config, string $token): array
{
    $l=surveys_link($db, $token);$s=surveys_query($db, 'SELECT * FROM surveys WHERE id=?',[$l['survey_id']])->fetch();surveys_available($db, $s,$l);
    $nonce=bin2hex(random_bytes(24));$submit=$nonce.'.'.hash_hmac('sha256',$l['token_hash'].':'.$s['version'].':'.$nonce,$config['app_key']);
    return ['title'=>$s['form_title'],'description'=>$s['form_description'],'color'=>$s['color'],'identity'=>$s['identity_mode'],'audience'=>$s['audience'],'questions'=>surveys_items($db, (string)$s['id']),'version'=>(int)$s['version'],'submit_token'=>$submit,'csrf_token'=>\Hospital\Security\Csrf::token()];
}

function surveys_audience(PDO $db, string $token): string
{ $l=surveys_link($db, $token);return (string)surveys_query($db, 'SELECT audience FROM surveys WHERE id=?',[$l['survey_id']])->fetchColumn();
}

function surveys_submit(PDO $db, array $config, string $token,array $input): array
{
    $l=surveys_link($db, $token);$db->beginTransaction();try {
        $s=surveys_query($db, 'SELECT * FROM surveys WHERE id=? FOR UPDATE',[$l['survey_id']])->fetch();$l=surveys_query($db, 'SELECT * FROM survey_links WHERE id=? FOR UPDATE',[$l['id']])->fetch();
        $version=survey_input_integer($input['version']??null,1,2147483647);$submit=$input['submit_token']??'';
        if (!is_string($submit)||!preg_match('/^([a-f0-9]{48})\.([a-f0-9]{64})$/',$submit,$match)||!hash_equals(hash_hmac('sha256',$l['token_hash'].':'.$version.':'.$match[1],$config['app_key']),$match[2]))throw new ApiException(403,'INVALID_SUBMISSION',\Hospital\I18n::t("errors.volve_a_abrir_la_encuesta"));
        [$answers,$name]=survey_input_answers(surveys_items($db, (string)$s['id']),$input['answers']??null,$input['participant_name']??'',$s['identity_mode']);$payload=hash('sha256',json_encode([$answers,$name],JSON_THROW_ON_ERROR));$hash=hash('sha256',$submit);
        $prior=surveys_query($db, 'SELECT * FROM survey_responses WHERE submit_hash=?',[$hash])->fetch();
        if ($prior) { if ($prior['payload_hash']!==$payload)throw new ApiException(409,'SUBMISSION_CHANGED',\Hospital\I18n::t("errors.estas_respuestas_ya_fueron_enviadas"));$db->commit();return ['accepted'=>true,'completion_message'=>$s['completion_message'],'replayed'=>true]; }
        // Published participant fields/items are frozen. The signed version records
        // the issued snapshot; invitation/metadata edits must not invalidate it.
        surveys_available($db, $s,$l);
        surveys_query($db, 'INSERT INTO survey_responses (survey_id,link_id,submit_hash,payload_hash,survey_version,participant_name) VALUES (?,?,?,?,?,?)',[$s['id'],$l['id'],$hash,$payload,$version,$name]);$rid=$db->lastInsertId();
        foreach ($answers as $key=>$answer)surveys_query($db, 'INSERT INTO survey_answers (response_id,item_id,answer) SELECT ?,id,? FROM survey_items WHERE survey_id=? AND item_key=?',[$rid,json_encode($answer,JSON_THROW_ON_ERROR),$s['id'],$key]);
        if ($l['single_use'])surveys_query($db, 'UPDATE survey_links SET used_at=UTC_TIMESTAMP() WHERE id=?',[$l['id']]);
        surveys_query($db, 'UPDATE surveys SET updated_at=UTC_TIMESTAMP() WHERE id=?',[$s['id']]);users_audit($db, null,'survey.responded',(string)$s['id'],$GLOBALS['request_id']);$db->commit();return ['accepted'=>true,'completion_message'=>$s['completion_message'],'replayed'=>false];
    } catch (\Throwable $e) { if ($db->inTransaction())$db->rollBack();throw $e; }
}

function surveys_results(PDO $db, string $id,array $questions,int $responses): array
{
    $result=[];foreach ($questions as $q) {
        if (in_array($q['type'],['section','divider'],true))continue;
        $rows=surveys_query($db, 'SELECT a.answer,COUNT(*) AS n FROM survey_answers a JOIN survey_items i ON i.id=a.item_id WHERE i.survey_id=? AND i.item_key=? GROUP BY a.answer',[$id,$q['id']])->fetchAll();$n=0;$bins=[];
        if (in_array($q['type'],['single_choice','multiple_choice'],true))foreach ($q['options'] as $v)$bins[$v]=0;
        if ($q['other'])$bins['Otro']=0;
        if (in_array($q['type'],['rating','linear_scale'],true))for ($v=$q['type']==='rating'?1:$q['scale_min'];$v<=($q['type']==='rating'?$q['rating_max']:$q['scale_max']);$v++)$bins[$v]=0;
        foreach ($rows as $row) { $value=json_decode($row['answer'],true,32,JSON_THROW_ON_ERROR);$count=(int)$row['n'];$n+=$count;
            if (is_array($value))foreach ($value['choices'] as $v){$label=$v==='other'?'Otro':$q['options'][(int)$v];$bins[$label]=($bins[$label]??0)+$count;}
            elseif (in_array($q['type'],['rating','linear_scale'],true))$bins[$value]=($bins[$value]??0)+$count;
        }
        $distribution=[];foreach ($bins as $value=>$count)$distribution[]=['value'=>$value,'count'=>$count];$result[$q['id']]=['answer_count'=>$n,'skipped_count'=>$responses-$n,'distribution'=>$distribution];
    }return $result;
}

function surveys_entries(PDO $db, string $actor,string $id,string $key,string $search,int $page,int $size): array
{
    surveys_row($db, $actor,$id);$q=surveys_query($db, 'SELECT * FROM survey_items WHERE survey_id=? AND item_key=?',[$id,$key])->fetch();if (!$q)throw new ApiException(404,'QUESTION_NOT_FOUND',\Hospital\I18n::t("errors.no_se_encontro_la_pregunta"));
    $choice=in_array($q['type'],['single_choice','multiple_choice'],true);$expression=$choice?"JSON_UNQUOTE(JSON_EXTRACT(a.answer,'$.other_text'))":"JSON_UNQUOTE(a.answer)";
    if (!in_array($q['type'],['short_text','long_text','date','single_choice','multiple_choice'],true))survey_input_fail(\Hospital\I18n::t("errors.esta_pregunta_no_tiene_respuestas_escritas"));
    $where='a.item_id=?';$params=[$q['id']];if ($choice)$where.=' AND '.$expression."<>''";
    if ($search!==''){$where.=' AND '.$expression." LIKE ? ESCAPE '!'";$params[]='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}
    $total=(int)surveys_query($db, 'SELECT COUNT(*) FROM survey_answers a WHERE '.$where,$params)->fetchColumn();
    $rows=surveys_query($db, 'SELECT '.$expression.' AS value,r.submitted_at,r.participant_name FROM survey_answers a JOIN survey_responses r ON r.id=a.response_id WHERE '.$where.' ORDER BY r.id DESC LIMIT '.$size.' OFFSET '.(($page-1)*$size),$params)->fetchAll();
    foreach ($rows as &$row)$row['submitted_at']=users_date($row['submitted_at']);unset($row);return [$rows,$total];
}
