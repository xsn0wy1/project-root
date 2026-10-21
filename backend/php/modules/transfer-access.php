<?php
declare(strict_types=1);

use Hospital\Http\ApiException;

function transfer_dispatch(PDO $db, string $actor): bool
{ return in_array('dispatcher', users_roles($db, $actor), true);
}

function transfer_require_dispatch(PDO $db, string $actor): void
{ if (!transfer_dispatch($db, $actor)) throw new ApiException(403,'FORBIDDEN',\Hospital\I18n::t("errors.se_requiere_permiso_de_coordinacion_de_traslados"));
}

function transfer_scope(PDO $db, string $actor, array &$args): string
{
    if (transfer_dispatch($db, $actor)) return '1=1';
    $args[]=$actor;
    return 'EXISTS (SELECT 1 FROM transfer_crew tc JOIN users au ON au.staff_id=tc.staff_id WHERE tc.transfer_id=t.id AND au.id=?)';
}

function transfer_actor(PDO $db, string $actor): void
{
    $user=users_by_id($db, $actor,true);
    if (!$user || $user['status']!=='active' || $user['staff_status']!=='active' || (int)$user['auth_version']!==(int)($_SESSION['auth_version']??0)) throw new ApiException(401,'SESSION_EXPIRED',\Hospital\I18n::t("errors.la_sesion_ya_no_tiene_acceso"));
    transfer_require_dispatch($db, $actor);
}

function transfer_lock_name(array $config, string $name='write'): string
{ return 'ht:'.substr(hash('sha256',$config['db']['name'].':'.$name),0,60);
}

function transfer_serialized(PDO $db, array $config, callable $work, string $name='write')
{
    $key=transfer_lock_name($config, $name); $stmt=$db->prepare('SELECT GET_LOCK(?,5)'); $stmt->execute([$key]);
    if ((int)$stmt->fetchColumn()!==1) throw new ApiException(409,'RESOURCE_BUSY',\Hospital\I18n::t("errors.hay_otra_actualizacion_en_curso_reintenta"));
    try { return $work(); }
    finally { $stmt=$db->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$key]); }
}

function transfer_audit(PDO $db, ?string $actor,string $action,?string $id,string $type='transfer'): void
{
    $s=$db->prepare('INSERT INTO audit_events (actor_user_id,action,resource_type,resource_id,request_id) VALUES (?,?,?,?,?)');
    $s->execute([$actor,$action,$type,$id,$GLOBALS['request_id']??'cli-'.bin2hex(random_bytes(12))]);
}
