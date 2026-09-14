<?php
declare(strict_types=1);
require_once __DIR__ . "/../../includes/seguridad.php";
exigirAdmin();
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$id||$id<=0){header('Location: temas.php');exit;}
header('Location: informacion_tema.php?id='.$id);
exit;
