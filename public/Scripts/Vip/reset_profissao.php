<?php
require "../../Includes/conectdb.php";

$protector->need_tripulacao();

$tipo = $protector->get_enum_or_exit("tipo", array("gold"));
$protector->need_gold($tipo, PRECO_GOLD_RESET_PROFISSAO);

$pers = $protector->get_tripulante_or_exit("cod");

$connection->run("UPDATE tb_personagens
	SET profissao='0', profissao_lvl='0', profissao_xp='0', profissao_xp_max='0'
	WHERE cod=?", "i", array($pers["cod"]));

$userDetails->reduz_gold($tipo, PRECO_GOLD_RESET_PROFISSAO, "resetar_profissao");

echo("-Profissão resetada!");
