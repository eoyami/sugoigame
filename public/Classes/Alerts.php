<?php

/**
 * Created by PhpStorm.
 * User: ivan.miranda
 * Date: 02/10/2017
 * Time: 08:21
 */
class Alerts
{

    /**
     * @var UserDetails
     */
    private $userDetails;

    /**
     * @var mywrap_con
     */
    private $connection;

    function __construct($userDetails, $connection)
    {
        $this->userDetails = $userDetails;
        $this->connection = $connection;
    }

    public function get_alert($class = "")
    {
        return "<span class=\"label label-danger label-alert $class\">!</span>";
    }

    public function has_alert_trip_sem_distribuir_atributo($pers)
    {
        return $pers["lvl"] > 2 && $pers["pts"];
    }

    public function has_alert_trip_sem_classe($pers)
    {
        return $pers["lvl"] > 3 && ! $pers["classe"];
    }

    public function has_alert_trip_sem_profissao($pers)
    {
        return $pers["lvl"] > 5 && ! $pers["profissao"];
    }

    public function has_alert_trip_sem_distribuir_haki($pers)
    {
        return $pers["haki_pts"];
    }

    public function has_alert_trip_sem_efeito_especial($pers)
    {
        return $pers["akuma"] && ! $this->connection->run(
            "SELECT count(*) AS total FROM tb_personagens_skil WHERE cod = ? AND tipo = ? AND special_effect IS NOT NULL ",
            "ii", array($pers["cod"], TIPO_SKILL_ATAQUE_AKUMA)
        )->fetch_array()["total"];
    }

    // TODO refactor
    public function has_alert_nova_habilidade_akuma($pers)
    {
        return $pers["akuma"] && $this->connection->run(
            "SELECT count(*) AS total FROM tb_personagens_skil WHERE cod_pers = ?",
            "i", array($pers["cod"]))->fetch_array()["total"] < $this->_habilidades_akuma_por_lvl($pers["lvl"]);
    }

    // TODO refactor
    public function has_alert_nova_habilidade_profissao($pers)
    {
        return ($pers["profissao"] == PROFISSAO_MUSICO || $pers["profissao"] == PROFISSAO_COMBATENTE)
            && $this->connection->run(
                "SELECT count(*) AS total FROM tb_personagens_skil WHERE cod_pers = ?",
                "i", array($pers["cod"]))->fetch_array()["total"] < $this->_habilidades_profissao_por_lvl($pers["profissao"], $pers["profissao_lvl"]);
    }

    public function has_alert_sem_equipamento($pers)
    {
        return $pers["lvl"] >= 50 && (! $pers["cod_acessorio"] || $this->connection->run(
            "SELECT count(*) AS total FROM tb_personagem_equipamentos
					 WHERE cod = ? AND (`1` IS NULL OR `2` IS NULL OR `3` IS NULL OR `4` IS NULL OR `5` IS NULL OR `6` IS NULL OR `7` IS NULL OR `8` IS NULL)",
            "i", array($pers["cod"]))->fetch_array()["total"]);
    }

    private function _habilidades_classe_por_lvl($lvl)
    {
        if ($lvl < 5) {
            return 1;
        } elseif ($lvl < 10) {
            return 2;
        } elseif ($lvl < 20) {
            return 3;
        } elseif ($lvl < 30) {
            return 4;
        } elseif ($lvl < 40) {
            return 5;
        } elseif ($lvl < 50) {
            return 6;
        } else {
            return 7;
        }
    }

    private function _habilidades_akuma_por_lvl($lvl)
    {
        return floor($lvl / 10);
    }

    private function _habilidades_profissao_por_lvl($profissao, $lvl)
    {
        return $profissao == PROFISSAO_MUSICO ? $lvl : $lvl;
    }

    function destroy()
    {
        $this->userDetails = null;
        $this->connection = null;
    }
}
